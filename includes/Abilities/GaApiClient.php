<?php

declare(strict_types=1);
/**
 * Google Analytics 4 traffic analysis abilities for the AI agent.
 *
 * Connects to the Google Analytics Data API v1 using a service account JSON
 * key stored in WordPress options. Provides three abilities:
 *
 *   - sd-ai-agent/ga-traffic-summary  — sessions, pageviews, bounce rate,
 *                                           avg session duration for a date range
 *   - sd-ai-agent/ga-top-pages        — top N pages by pageviews
 *   - sd-ai-agent/ga-realtime         — active users right now
 *
 * Authentication: Google service account JSON key (downloaded from Google Cloud
 * Console). The key is stored in a dedicated WordPress option and never exposed
 * through the general GET /settings endpoint.
 *
 * @package SdAiAgent
 * @since 1.0.0
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Analytics abilities facade.
 *
 * Registers three GA4 Data API abilities and provides static proxy methods
 * for backwards-compatible test access.
 */
trait GaApiClient {

	/**
	 * Obtain a short-lived OAuth 2.0 access token from a service account JSON key.
	 *
	 * The token is cached in a transient for 55 minutes (tokens expire at 60 min).
	 *
	 * @param array<string,mixed> $sa Service account JSON decoded as array.
	 * @return string|WP_Error Access token string or WP_Error on failure.
	 */
	private function get_access_token( array $sa ) {
		// @phpstan-ignore-next-line
		$cache_key = 'sd_ga_token_' . substr( md5( $sa['client_email'] ?? '' ), 0, 8 );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		// Build JWT.
		$now    = time();
		$header = $this->base64url_encode(
			(string) wp_json_encode(
				[
					'alg' => 'RS256',
					'typ' => 'JWT',
				]
			)
		);
		$claim  = $this->base64url_encode(
			(string) wp_json_encode(
				[
					'iss'   => $sa['client_email'] ?? '',
					'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'exp'   => $now + 3600,
					'iat'   => $now,
				]
			)
		);

		$signing_input = $header . '.' . $claim;
		$private_key   = $sa['private_key'] ?? '';

		if ( empty( $private_key ) ) {
			return new WP_Error( 'ga_no_private_key', __( 'Service account JSON is missing private_key.', 'superdav-ai-agent' ) );
		}

		// @phpstan-ignore-next-line

		// @phpstan-ignore-next-line
		if ( ! function_exists( 'openssl_sign' ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'ga_no_openssl', __( 'OpenSSL extension is required for Google Analytics authentication.', 'superdav-ai-agent' ) );
			// @phpstan-ignore-next-line
		}

		// @phpstan-ignore-next-line

		// @phpstan-ignore-next-line
		$pkey = openssl_pkey_get_private( $private_key );
		if ( false === $pkey ) {
			return new WP_Error( 'ga_invalid_key', __( 'Could not load service account private key. Verify the JSON is correct.', 'superdav-ai-agent' ) );
		}

		$signature = '';
		$signed    = openssl_sign( $signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256 );
		if ( ! $signed ) {
			return new WP_Error( 'ga_sign_failed', __( 'Failed to sign JWT for Google Analytics authentication.', 'superdav-ai-agent' ) );
		}

		$jwt = $signing_input . '.' . $this->base64url_encode( $signature );

		// Exchange JWT for access token.
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			[
				'timeout' => 15,
				'body'    => [
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				],
			]
		);

		// @phpstan-ignore-next-line

		if ( is_wp_error( $response ) ) {
			// @phpstan-ignore-next-line
			// @phpstan-ignore-next-line
			return new WP_Error( 'ga_token_request_failed', $response->get_error_message() );
		}

		// @phpstan-ignore-next-line

		// @phpstan-ignore-next-line

		// @phpstan-ignore-next-line

		// @phpstan-ignore-next-line
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		// @phpstan-ignore-next-line
		// @phpstan-ignore-next-line
		// @phpstan-ignore-next-line
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			// @phpstan-ignore-next-line
			// @phpstan-ignore-next-line
			$err     = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
			$err_str = is_scalar( $err ) ? (string) $err : 'Unknown error';
			// translators: %s: OAuth error message returned by Google.
			$error_message = sprintf( __( 'Google OAuth error: %s', 'superdav-ai-agent' ), $err_str );
			return new WP_Error( 'ga_token_error', $error_message );
		}

		// @phpstan-ignore-next-line
		$token = (string) $body['access_token'];
		set_transient( $cache_key, $token, 55 * MINUTE_IN_SECONDS );
		return $token;
	}

	/**
	 * Make an authenticated POST request to the GA Data API v1.
	 *
	 * @param string              $endpoint Full API URL.
	 * @param array<string,mixed> $body     Request body.
	 * @param string              $token    Bearer token.
	 * @return array<string,mixed>|WP_Error Decoded response body or WP_Error.
	 */
	private function ga_api_post( string $endpoint, array $body, string $token ) {
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( $body ),
				// @phpstan-ignore-next-line
			]
		);

		// @phpstan-ignore-next-line

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ga_api_request_failed', $response->get_error_message() );
			// @phpstan-ignore-next-line
		}

		// @phpstan-ignore-next-line

		$code = wp_remote_retrieve_response_code( $response );
		// @phpstan-ignore-next-line
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// @phpstan-ignore-next-line
		if ( ! is_array( $data ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'ga_api_invalid_response', __( 'Google Analytics API returned an invalid response.', 'superdav-ai-agent' ) );
			// @phpstan-ignore-next-line
			// @phpstan-ignore-next-line
		}

		// @phpstan-ignore-next-line
		// @phpstan-ignore-next-line
		if ( $code >= 400 ) {
			// @phpstan-ignore-next-line
			$msg      = $data['error']['message'] ?? __( 'Unknown API error.', 'superdav-ai-agent' );
			$code_int = is_numeric( $code ) ? (int) $code : 0;
			$msg_str  = is_scalar( $msg ) ? (string) $msg : 'Unknown API error.';
			// translators: %1$d: HTTP status code, %2$s: error message from Google Analytics API.
			$error_message = sprintf( __( 'Google Analytics API error (%1$d): %2$s', 'superdav-ai-agent' ), $code_int, $msg_str );
			return new WP_Error( 'ga_api_error', $error_message );
		}

		// @phpstan-ignore-next-line
		return $data;
	}

	/**
	 * Make an authenticated GET request to the GA Data API v1.
	 *
	 * @param string $endpoint Full API URL.
	 * @param string $token    Bearer token.
	 * @return array<string,mixed>|WP_Error Decoded response body or WP_Error.
	 */
	private function ga_api_get( string $endpoint, string $token ) {
		$response = wp_remote_get(
			// @phpstan-ignore-next-line
			$endpoint,
			[
				// @phpstan-ignore-next-line
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					// @phpstan-ignore-next-line
					'Content-Type'  => 'application/json',
				],
			]
		);

		// @phpstan-ignore-next-line
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ga_api_request_failed', $response->get_error_message() );
			// @phpstan-ignore-next-line
		}

		// @phpstan-ignore-next-line
		$code = wp_remote_retrieve_response_code( $response );
		// @phpstan-ignore-next-line
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// @phpstan-ignore-next-line

		if ( ! is_array( $data ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'ga_api_invalid_response', __( 'Google Analytics API returned an invalid response.', 'superdav-ai-agent' ) );
			// @phpstan-ignore-next-line
		}

		// @phpstan-ignore-next-line

		// @phpstan-ignore-next-line
		if ( $code >= 400 ) {
			// @phpstan-ignore-next-line
			// @phpstan-ignore-next-line
			$msg      = $data['error']['message'] ?? __( 'Unknown API error.', 'superdav-ai-agent' );
			$code_int = is_numeric( $code ) ? (int) $code : 0;
			$msg_str  = is_scalar( $msg ) ? (string) $msg : 'Unknown API error.';
			// translators: %1$d: HTTP status code, %2$s: error message from Google Analytics API.
			$error_message = sprintf( __( 'Google Analytics API error (%1$d): %2$s', 'superdav-ai-agent' ), $code_int, $msg_str );
			return new WP_Error( 'ga_api_error', $error_message );
		}

		// @phpstan-ignore-next-line
		return $data;
	}

	/**
	 * Load and validate GA credentials, returning token + property_id.
	 *
	 * @return array{token: string, property_id: string}|WP_Error
	 */
	private function load_credentials() {
		$creds = GoogleAnalyticsAbilities::get_credentials();

		if ( empty( $creds['property_id'] ) ) {
			return new WP_Error(
				'ga_no_property_id',
				__( 'Google Analytics property ID is not configured. Go to Settings > Superdav AI Agent Settings > Integrations to add your GA4 property ID and service account key.', 'superdav-ai-agent' )
			);
		}

		if ( empty( $creds['service_account_json'] ) ) {
			return new WP_Error(
				'ga_no_credentials',
				__( 'Google Analytics service account JSON is not configured. Go to Settings > Superdav AI Agent Settings > Integrations to add your service account key.', 'superdav-ai-agent' )
			);
		}

		$sa = json_decode( $creds['service_account_json'], true );
		if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
			return new WP_Error(
				'ga_invalid_credentials',
				__( 'Google Analytics service account JSON is invalid. It must contain client_email and private_key fields.', 'superdav-ai-agent' )
			);
		}

		$token = $this->get_access_token( $sa );
		// @phpstan-ignore-next-line
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return [
			'token'       => $token,
			'property_id' => $creds['property_id'],
		];
	}

	/**
	 * URL-safe base64 encode (no padding).
	 *
	 * @param string $data Raw bytes.
	 * @return string Base64url-encoded string.
	 */
	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for JWT signing per RFC 7515; not used for obfuscation.
	}

	/**
	 * Extract a metric value from a GA API row.
	 *
	 * @param array<string,mixed> $row         Row from GA API response.
	 * @param int                 $metric_index Zero-based index into metricValues.
	 * @return string Raw value string.
	 */
	private function extract_metric( array $row, int $metric_index ): string {
		// @phpstan-ignore-next-line
		return (string) ( $row['metricValues'][ $metric_index ]['value'] ?? '0' );
	}

	/**
	 * Extract a dimension value from a GA API row.
	 *
	 * @param array<string,mixed> $row            Row from GA API response.
	 * @param int                 $dimension_index Zero-based index into dimensionValues.
	// @phpstan-ignore-next-line
	 * @return string Raw value string.
	// @phpstan-ignore-next-line
	 */
	// @phpstan-ignore-next-line
	private function extract_dimension( array $row, int $dimension_index ): string {
		// @phpstan-ignore-next-line
		// @phpstan-ignore-next-line
		return (string) ( $row['dimensionValues'][ $dimension_index ]['value'] ?? '' );
	}
}
