<?php

declare(strict_types=1);
/**
 * Feedback report HTTP sender.
 *
 * Forwards the sanitized payload to the configured feedback endpoint using
 * wp_remote_post(). Errors are returned as WP_Error objects; callers should
 * not crash on send failures (a broken feedback channel must never interrupt
 * normal plugin operation).
 *
 * @package GratisAiAgent\Feedback
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Feedback;

use GratisAiAgent\Core\Settings;
use WP_Error;

class ReportSender {

	/**
	 * Send a sanitized report payload to the configured feedback endpoint.
	 *
	 * @param array<string, mixed> $payload    Sanitized payload from ReportSanitizer::sanitize().
	 * @param bool                 $force_send Bypass the enabled check. Set to true for manual user submissions
	 *                                     from the feedback form; false for automatic background reporting.
	 * @return true|WP_Error True on success (2xx response), WP_Error on failure.
	 */
	public static function send( array $payload, bool $force_send = false ): true|WP_Error {
		$endpoint_url = (string) ( Settings::instance()->get( 'feedback_endpoint_url' ) ?? '' );

		// Skip enabled check when $force_send is true (manual form submissions).
		// The setting only controls automatic/batch feedback reporting.
		if ( ! $force_send ) {
			$enabled = (bool) ( Settings::instance()->get( 'feedback_enabled' ) ?? false );

			if ( ! $enabled ) {
				return new WP_Error( 'feedback_disabled', 'Feedback reporting is not enabled in Settings.' );
			}
		}

		if ( '' === $endpoint_url ) {
			return new WP_Error( 'feedback_no_endpoint', 'No feedback endpoint URL configured.' );
		}

		if ( ! filter_var( $endpoint_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'feedback_invalid_url', 'Configured feedback endpoint URL is not valid.' );
		}

		$api_key = Settings::instance()->get_feedback_api_key();

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( '' !== $api_key ) {
			$headers['X-Feedback-Api-Key'] = $api_key;
		}

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			return new WP_Error( 'feedback_encode_error', 'Failed to JSON-encode the report payload.' );
		}

		$response = wp_remote_post(
			$endpoint_url,
			array(
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$body    = wp_remote_retrieve_body( $response );
			$message = sprintf(
				'Feedback endpoint returned HTTP %d: %s',
				$status_code,
				wp_strip_all_tags( $body )
			);
			return new WP_Error( 'feedback_http_error', $message, array( 'status' => $status_code ) );
		}

		return true;
	}
}
