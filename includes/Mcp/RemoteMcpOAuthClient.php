<?php

declare(strict_types=1);
/**
 * Bounded MCP OAuth authorization-code/PKCE client using WordPress HTTP APIs.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use SdAiAgent\Core\Net\SsrfGuard;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpOAuthClient {

	public function __construct( private readonly RemoteMcpConnectionRepository $connections ) {}

	public static function callback_url(): string {
		return add_query_arg( 'action', 'sd_ai_agent_mcp_oauth_callback', admin_url( 'admin-post.php' ) );
	}

	public static function client_metadata_url(): string {
		return add_query_arg( 'action', 'sd_ai_agent_mcp_client_metadata', admin_url( 'admin-post.php' ) );
	}

	/** @return array<string,mixed> Public identity document, not connection configuration. */
	public static function client_metadata(): array {
		return array(
			'client_id'                  => self::client_metadata_url(),
			'client_name'                => 'SD AI Agent',
			'redirect_uris'              => array( self::callback_url() ),
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => 'none',
		);
	}

	/** @return array<string,string>|WP_Error */
	public function begin( string $id ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'forbidden', __( 'Only an administrator can connect this server.', 'superdav-ai-agent' ) );
		}
		$connection = $this->connections->get_for_execution( $id );
		if ( ! is_array( $connection ) || 'oauth' !== ( $connection['auth_type'] ?? '' ) ) {
			return $this->error( 'not_found', __( 'Save this server with Sign in authentication first.', 'superdav-ai-agent' ) );
		}
		$callback = self::callback_url();
		$host     = wp_parse_url( $callback, PHP_URL_HOST );
		if ( 'https' !== wp_parse_url( $callback, PHP_URL_SCHEME ) && ! in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true ) ) {
			return $this->error( 'https_required', __( 'OAuth sign-in requires HTTPS on this WordPress site, or a localhost development callback.', 'superdav-ai-agent' ) );
		}
		$oauth = $this->discover( (string) $connection['endpoint'] );
		if ( is_wp_error( $oauth ) ) {
			return $oauth;
		}
		$credentials = $this->connections->get_secret( $id );
		$client_id   = (string) ( $credentials['client_id'] ?? '' );
		if ( '' !== $client_id ) {
			$bound_issuer = (string) ( $credentials['oauth']['issuer'] ?? '' );
			if ( '' !== $bound_issuer && $bound_issuer !== $oauth['issuer'] ) {
				return $this->error( 'issuer_changed', __( 'The authorization server changed. Review and replace the registered client details before signing in again.', 'superdav-ai-agent' ) );
			}
			$oauth['client_id']     = $client_id;
			$oauth['client_secret'] = (string) ( $credentials['client_secret'] ?? '' );
		} elseif ( ! empty( $oauth['client_id_metadata_document_supported'] ) && 'https' === wp_parse_url( self::client_metadata_url(), PHP_URL_SCHEME ) ) {
			$oauth['client_id']     = self::client_metadata_url();
			$oauth['client_secret'] = '';
		} else {
			return $this->error( 'client_registration_required', __( 'This server requires a registered client ID. Add it under Advanced, then sign in again. Automatic client metadata requires a publicly reachable HTTPS site.', 'superdav-ai-agent' ) );
		}
		$methods = $oauth['token_endpoint_auth_methods_supported'] ?? array( 'client_secret_basic' );
		$method  = '' === $oauth['client_secret'] ? 'none' : ( in_array( 'client_secret_basic', $methods, true ) ? 'client_secret_basic' : 'client_secret_post' );
		if ( ! in_array( $method, $methods, true ) ) {
			return $this->error( 'unsupported_auth', __( 'This server’s token authentication method is not supported.', 'superdav-ai-agent' ) );
		}
		$oauth['token_auth_method'] = $method;
		$state_oauth                = $oauth;
		unset( $state_oauth['client_secret'] );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7636 S256 verifier encoding, not executable code.
		$verifier = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$state    = ( new RemoteMcpOAuthStateRepository() )->create(
			array(
				'connection_id' => $id,
				'user_id'       => get_current_user_id(),
				'blog_id'       => get_current_blog_id(),
				'revision'      => (int) ( $connection['revision'] ?? 0 ),
				'generation'    => $connection['generation'] ?? '',
				'endpoint'      => $connection['endpoint'],
				'callback'      => $callback,
				'verifier'      => $verifier,
				'oauth'         => $state_oauth,
			)
		);
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7636 S256 challenge encoding.
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$params    = array(
			'response_type'         => 'code',
			'client_id'             => $oauth['client_id'],
			'redirect_uri'          => $callback,
			'code_challenge'        => $challenge,
			'code_challenge_method' => 'S256',
			'state'                 => $state,
			'resource'              => $oauth['resource'],
		);
		if ( '' !== $oauth['scope'] ) {
			$params['scope'] = $oauth['scope'];
		}
		return array( 'authorization_url' => add_query_arg( $params, (string) $oauth['authorization_endpoint'] ) );
	}

	/**
	 * @param array<string,string> $query Callback parameters; issuer must remain byte-identical.
	 * @return string|WP_Error Connected server ID, or a safe error.
	 */
	public function complete( array $query ): string|WP_Error {
		$state = ( new RemoteMcpOAuthStateRepository() )->consume( $query['state'] ?? '' );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		$oauth = $state['oauth'];
		if ( ! is_array( $oauth ) ) {
			return $this->error( 'state_invalid', __( 'This sign-in request is invalid. Sign in again.', 'superdav-ai-agent' ) );
		}
		$oauth      = RemoteMcpPolicy::record( $oauth );
		$id         = (string) $state['connection_id'];
		$connection = $this->connections->get_for_execution( $id );
		if ( ! is_array( $connection ) || 'oauth' !== ( $connection['auth_type'] ?? '' ) || $connection['endpoint'] !== $state['endpoint'] || (int) ( $connection['revision'] ?? 0 ) !== $state['revision'] || self::callback_url() !== $state['callback'] ) {
			return $this->error( 'state_invalid', __( 'This server changed while signing in. Review it and sign in again.', 'superdav-ai-agent' ) );
		}
		$issuer = $query['iss'] ?? '';
		if ( ( '' !== $issuer && $issuer !== $oauth['issuer'] ) || ( ! empty( $oauth['authorization_response_iss_parameter_supported'] ) && '' === $issuer ) ) {
			return $this->error( 'issuer_mismatch', __( 'The sign-in response came from an unexpected authorization server.', 'superdav-ai-agent' ) );
		}
		if ( ! empty( $query['error'] ) ) {
			return $this->error( 'cancelled', __( 'Sign-in was not completed. You can try again.', 'superdav-ai-agent' ) );
		}
		$code = $query['code'] ?? '';
		if ( '' === $code || strlen( $code ) > 4096 ) {
			return $this->error( 'invalid_code', __( 'The authorization server did not return a valid code.', 'superdav-ai-agent' ) );
		}
		$lock = RemoteMcpLock::acquire( 'oauth-' . $id, 240 );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$credentials            = $this->connections->get_secret( $id );
			$oauth['client_secret'] = (string) ( $credentials['client_secret'] ?? '' );
			$record                 = $this->exchange(
				$oauth,
				array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'code_verifier' => $state['verifier'],
					'redirect_uri'  => $state['callback'],
				)
			);
			if ( is_wp_error( $record ) ) {
				return $record;
			}
			$fence  = array(
				'key'   => 'oauth-' . $id,
				'owner' => $lock,
			);
			$stored = $this->connections->store_secret( $id, $record, (int) $state['revision'], (string) $state['generation'], $fence );
			if ( is_wp_error( $stored ) ) {
				return $stored;
			}
			$client = new RemoteMcpClient( $this->connections, new RemoteMcpHttpTransport( $this->connections ) );
			$result = $client->discover( $id );
			if ( is_wp_error( $result ) || ! $this->connections->set_enabled( $id, true, (int) $state['revision'], (string) $state['generation'], $fence ) ) {
				return is_wp_error( $result ) ? $result : $this->error( 'connection_changed', __( 'Sign-in succeeded but the connection changed. Connect it again.', 'superdav-ai-agent' ) );
			}
			return $id;
		} finally {
			RemoteMcpLock::release( 'oauth-' . $id, $lock );
		}
	}

	/** Retrieve/refresh without background jobs; callers must not replay ambiguous tool calls. */
	public function access_token( string $id, bool $force_refresh = false ): string|WP_Error {
		$record = $this->connections->get_secret( $id );
		if ( ! $force_refresh && ! empty( $record['value'] ) && ( empty( $record['expires_at'] ) || (int) $record['expires_at'] > time() + 30 ) ) {
			return $this->bound_token( $id, $record );
		}
		$lock = RemoteMcpLock::acquire( 'oauth-' . $id, 60 );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$fresh = $this->connections->get_secret( $id );
			if ( ! empty( $fresh['value'] ) && ( empty( $fresh['expires_at'] ) || (int) $fresh['expires_at'] > time() + 30 ) && ( ! $force_refresh || $fresh['value'] !== ( $record['value'] ?? '' ) ) ) {
				return $this->bound_token( $id, $fresh );
			}
			if ( empty( $fresh['refresh_token'] ) || ! is_array( $fresh['oauth'] ?? null ) ) {
				return $this->error( 'authentication_required', __( 'Sign in again to use this MCP server.', 'superdav-ai-agent' ) );
			}
			$connection = $this->connections->get_for_execution( $id );
			if ( is_wp_error( $this->bound_token( $id, $fresh, false ) ) || ! is_array( $connection ) ) {
				return $this->error( 'authentication_required', __( 'This server changed. Sign in again.', 'superdav-ai-agent' ) );
			}
			$fence = array(
				'key'   => 'oauth-' . $id,
				'owner' => $lock,
			);
			$next  = $this->exchange(
				$fresh['oauth'],
				array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $fresh['refresh_token'],
				),
				$fresh
				);
			if ( is_wp_error( $next ) ) {
				if ( 'sd_ai_agent_remote_mcp_oauth_invalid_grant' === $next->get_error_code() ) {
					unset( $fresh['value'], $fresh['refresh_token'], $fresh['expires_at'] );
					$cleared = $this->connections->store_secret( $id, $fresh, (int) ( $connection['revision'] ?? 0 ), (string) ( $connection['generation'] ?? '' ), $fence );
					if ( ! is_wp_error( $cleared ) ) {
						$this->connections->mark_failed( $id, 'sd_ai_agent_remote_mcp_authentication_required' );
					}
				}
				return $next;
			}
			$saved = $this->connections->store_secret( $id, $next, (int) ( $connection['revision'] ?? 0 ), (string) ( $connection['generation'] ?? '' ), $fence );
			return is_wp_error( $saved ) ? $saved : $this->bound_token( $id, $next );
		} finally {
			RemoteMcpLock::release( 'oauth-' . $id, $lock );
		}
	}

	public function disconnect( string $id ): true|WP_Error {
		$lock = RemoteMcpLock::acquire( 'oauth-' . $id, 60 );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$record     = $this->connections->get_secret( $id );
			$connection = $this->connections->get_for_execution( $id );
			$fence      = array(
				'key'   => 'oauth-' . $id,
				'owner' => $lock,
			);
			if ( ! is_array( $connection ) || ! $this->connections->set_enabled( $id, false, (int) ( $connection['revision'] ?? 0 ), (string) ( $connection['generation'] ?? '' ), $fence ) ) {
				return $this->error( 'connection_changed', __( 'This connection changed before disconnecting. Reload and try again.', 'superdav-ai-agent' ) );
			}
			$connection = $this->connections->get_for_execution( $id );
			if ( ! empty( $record['oauth']['revocation_endpoint'] ) ) {
				$oauth  = $record['oauth'];
				$params = array(
					'token'         => '' !== (string) ( $record['refresh_token'] ?? '' ) ? (string) $record['refresh_token'] : (string) ( $record['value'] ?? '' ),
					'client_id'     => $oauth['client_id'],
					'client_secret' => $oauth['client_secret'] ?? '',
				);
				// Revocation is best-effort; local cleanup must not depend on the remote server.
				$this->request( (string) $oauth['revocation_endpoint'], 'POST', $params );
			}
			return $this->connections->clear_secret( $id, (int) ( $connection['revision'] ?? 0 ), (string) ( $connection['generation'] ?? '' ), $fence );
		} finally {
			RemoteMcpLock::release( 'oauth-' . $id, $lock );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	private function discover( string $endpoint ): array|WP_Error {
		$probe = $this->request(
			$endpoint,
			'POST',
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => RemoteMcpClient::PROTOCOL_VERSION,
					'capabilities'    => new \stdClass(),
					'clientInfo'      => array(
						'name'    => 'sd-ai-agent',
						'version' => SD_AI_AGENT_VERSION,
					),
				),
			),
			true
			);
		if ( is_wp_error( $probe ) ) {
			return $probe;
		}
		$challenge = wp_remote_retrieve_header( $probe, 'www-authenticate' );
		$challenge = is_array( $challenge ) ? implode( ', ', array_filter( $challenge, 'is_string' ) ) : $challenge;
		$challenge = substr( $challenge, 0, 8192 );
		$parts     = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || ! isset( $parts['host'] ) ) {
			return $this->error( 'unsafe_endpoint', __( 'The MCP URL is invalid.', 'superdav-ai-agent' ) );
		}
		$origin     = 'https://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$candidates = array( $origin . '/.well-known/oauth-protected-resource' . ( $parts['path'] ?? '' ), $origin . '/.well-known/oauth-protected-resource' );
		if ( preg_match( '/\bresource_metadata="([^"\r\n]{1,2048})"/i', $challenge, $match ) ) {
			$candidates = array( $match[1] ); // Do not hide a broken/unsafe advertised location with fallback.
		}
		$resource = $this->first_metadata( array_values( array_unique( $candidates ) ) );
		if ( is_wp_error( $resource ) ) {
			return $resource;
		}
		$resource_parts   = is_string( $resource['resource'] ?? null ) ? wp_parse_url( $resource['resource'] ) : false;
		$resource_path    = is_array( $resource_parts ) ? rtrim( $resource_parts['path'] ?? '', '/' ) : '';
		$endpoint_path    = $parts['path'] ?? '';
		$resource_matches = is_array( $resource_parts ) && ( $resource_parts['scheme'] ?? '' ) === ( $parts['scheme'] ?? '' ) && ( $resource_parts['host'] ?? '' ) === $parts['host'] && ( $resource_parts['port'] ?? 443 ) === ( $parts['port'] ?? 443 ) && ! isset( $resource_parts['fragment'] ) && ! isset( $resource_parts['query'] ) && ( $endpoint_path === $resource_path || str_starts_with( $endpoint_path, $resource_path . '/' ) );
		if ( ! $resource_matches || ! is_array( $resource['authorization_servers'] ?? null ) || ! is_string( $resource['authorization_servers'][0] ?? null ) || '' === $resource['authorization_servers'][0] ) {
			return $this->error( 'resource_mismatch', __( 'The server’s OAuth resource metadata does not match this MCP URL.', 'superdav-ai-agent' ) );
		}
		$issuer = (string) $resource['authorization_servers'][0];
		$safe   = $this->safe_url( $issuer );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		$issuer_parts = wp_parse_url( $issuer );
		if ( ! is_array( $issuer_parts ) || ! isset( $issuer_parts['host'] ) || isset( $issuer_parts['query'] ) ) {
			return $this->error( 'invalid_metadata', __( 'The authorization issuer URL is invalid.', 'superdav-ai-agent' ) );
		}
		$issuer_root = 'https://' . $issuer_parts['host'] . ( isset( $issuer_parts['port'] ) ? ':' . $issuer_parts['port'] : '' );
		$path        = rtrim( (string) ( $issuer_parts['path'] ?? '' ), '/' );
		$urls        = array( $issuer_root . '/.well-known/oauth-authorization-server' . $path, $issuer_root . '/.well-known/openid-configuration' . $path );
		if ( '' !== $path ) {
			$urls[] = rtrim( $issuer, '/' ) . '/.well-known/openid-configuration';
		}
		$metadata = $this->first_metadata( $urls );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		if ( ( $metadata['issuer'] ?? '' ) !== $issuer || ! is_array( $metadata['code_challenge_methods_supported'] ?? null ) || ! in_array( 'S256', $metadata['code_challenge_methods_supported'], true ) || ( isset( $metadata['token_endpoint_auth_methods_supported'] ) && ! is_array( $metadata['token_endpoint_auth_methods_supported'] ) ) ) {
			return $this->error( 'invalid_metadata', __( 'The authorization metadata has a mismatched issuer or does not support S256 PKCE.', 'superdav-ai-agent' ) );
		}
		foreach ( array( 'authorization_endpoint', 'token_endpoint' ) as $field ) {
			if ( ! is_string( $metadata[ $field ] ?? null ) ) {
				return $this->error( 'invalid_metadata', __( 'The authorization metadata is incomplete.', 'superdav-ai-agent' ) );
			}
			$safe = $this->safe_url( (string) ( $metadata[ $field ] ?? '' ) );
			if ( is_wp_error( $safe ) ) {
				return $safe;
			}
		}
		if ( ! empty( $metadata['revocation_endpoint'] ) && is_wp_error( $this->safe_url( (string) $metadata['revocation_endpoint'] ) ) ) {
			return $this->error( 'unsafe_metadata', __( 'The authorization server advertised an unsafe revocation endpoint.', 'superdav-ai-agent' ) );
		}
		$scope = implode( ' ', array_filter( (array) ( $resource['scopes_supported'] ?? array() ), 'is_string' ) );
		if ( preg_match( '/\bscope="([^"\r\n]{0,2048})"/i', $challenge, $match ) ) {
			$scope = $match[1];
		}
		return array_merge(
			array_intersect_key( $metadata, array_flip( array( 'issuer', 'authorization_endpoint', 'token_endpoint', 'revocation_endpoint', 'token_endpoint_auth_methods_supported', 'client_id_metadata_document_supported', 'authorization_response_iss_parameter_supported' ) ) ),
			array(
				'scope'    => substr( $scope, 0, 2048 ),
				'resource' => $resource['resource'],
				'endpoint' => $endpoint,
			)
		);
	}

	/**
	 * @param string[] $urls Ordered well-known locations.
	 * @return array<string,mixed>|WP_Error
	 */
	private function first_metadata( array $urls ): array|WP_Error {
		foreach ( $urls as $url ) {
			$response = $this->request( $url, 'GET' );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status = wp_remote_retrieve_response_code( $response );
			if ( in_array( $status, array( 404, 405 ), true ) ) {
				continue;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true, 16 );
			if ( 200 !== $status || ! is_array( $data ) || ! RemoteMcpPolicy::bounded( $data ) ) {
				return $this->error( 'invalid_metadata', __( 'The server returned invalid OAuth metadata.', 'superdav-ai-agent' ) );
			}
			return RemoteMcpPolicy::record( $data );
		}
		return $this->error( 'metadata_missing', __( 'This server does not provide supported OAuth metadata. Check its URL or use its access-token setup.', 'superdav-ai-agent' ) );
	}

	/**
	 * @param array<string,mixed> $oauth Validated authorization metadata.
	 * @param array<string,mixed> $params Token request parameters.
	 * @param array<string,mixed> $previous Prior token, to retain a nonrotated refresh token.
	 * @return array<string,mixed>|WP_Error
	 */
	private function exchange( array $oauth, array $params, array $previous = array() ): array|WP_Error {
		$params['client_id'] = $oauth['client_id'];
		$params['resource']  = $oauth['resource'];
		$headers             = array();
		if ( 'client_secret_basic' === $oauth['token_auth_method'] ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 6749 client HTTP authentication.
			$headers['Authorization'] = 'Basic ' . base64_encode( rawurlencode( (string) $oauth['client_id'] ) . ':' . rawurlencode( (string) $oauth['client_secret'] ) );
		} elseif ( 'client_secret_post' === $oauth['token_auth_method'] ) {
			$params['client_secret'] = $oauth['client_secret'];
		}
		$response = $this->request( (string) $oauth['token_endpoint'], 'POST', $params, false, $headers );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true, 16 );
		if ( is_array( $data ) && isset( $data['refresh_token'] ) && ( ! is_string( $data['refresh_token'] ) || strlen( $data['refresh_token'] ) > 8192 ) ) {
			return $this->error( 'token_bounded', __( 'The server returned an unsupported refresh token size or shape. No replacement credential was saved.', 'superdav-ai-agent' ) );
		}
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 || ! is_array( $data ) || ! is_string( $data['access_token'] ?? null ) || 'bearer' !== strtolower( (string) ( $data['token_type'] ?? '' ) ) || ! preg_match( '/^[\x21-\x7e]{1,8192}$/D', $data['access_token'] ) ) {
			$invalid_grant = is_array( $data ) && 'invalid_grant' === ( $data['error'] ?? '' );
			return $this->error( $invalid_grant ? 'invalid_grant' : 'token_failed', __( 'Authorization could not be completed. Sign in again or try later.', 'superdav-ai-agent' ) );
		}
		return array(
			'value'         => $data['access_token'],
			'refresh_token' => is_string( $data['refresh_token'] ?? null ) ? $data['refresh_token'] : ( $previous['refresh_token'] ?? '' ),
			'expires_at'    => isset( $data['expires_in'] ) ? time() + max( 0, min( 31536000, (int) $data['expires_in'] ) ) : 0,
			'client_id'     => $oauth['client_id'] === self::client_metadata_url() ? '' : $oauth['client_id'],
			'client_secret' => $oauth['client_secret'],
			'oauth'         => $oauth,
		);
	}

	/**
	 * @param string              $id Connection identity.
	 * @param array<string,mixed> $record Internal credential record.
	 */
	private function bound_token( string $id, array $record, bool $require_token = true ): string|WP_Error {
		$connection = $this->connections->get_for_execution( $id );
		if ( ! is_array( $connection ) || 'oauth' !== ( $connection['auth_type'] ?? '' ) || ( $record['oauth']['endpoint'] ?? '' ) !== $connection['endpoint'] || ( $require_token && empty( $record['value'] ) ) ) {
			return $this->error( 'authentication_required', __( 'Sign in again to use this MCP server.', 'superdav-ai-agent' ) );
		}
		return (string) ( $record['value'] ?? '' );
	}

	/**
	 * @param string               $url Validated HTTPS endpoint.
	 * @param string               $method HTTP method.
	 * @param array<string,mixed>  $params Body fields.
	 * @param bool                 $json Whether to send JSON rather than form data.
	 * @param array<string,string> $headers Additional token-auth headers.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request( string $url, string $method, array $params = array(), bool $json = false, array $headers = array() ): array|WP_Error {
		$safe = $this->safe_url( $url );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		$body = $json ? wp_json_encode( $params ) : http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		if ( ! is_string( $body ) || strlen( $body ) > 32768 ) {
			return $this->error( 'request_bounded', __( 'The authorization request exceeded its safe size limit.', 'superdav-ai-agent' ) );
		}
		$response = wp_remote_request(
			$url,
			array(
				'method'                  => $method,
				'headers'                 => array_merge(
					array(
						'Accept'       => 'application/json',
						'Content-Type' => $json ? 'application/json' : 'application/x-www-form-urlencoded',
					),
					$headers
					),
				'body'                    => 'POST' === $method ? $body : '',
				'timeout'                 => 15,
				'redirection'             => 0,
				'limit_response_size'     => 65537,
				'sslverify'               => true,
				'reject_unsafe_urls'      => true,
				'sd_ai_agent_mcp_request' => true,
			)
		);
		if ( is_wp_error( $response ) || strlen( wp_remote_retrieve_body( $response ) ) > 65536 || ( wp_remote_retrieve_response_code( $response ) >= 300 && wp_remote_retrieve_response_code( $response ) < 400 ) ) {
			return $this->error( 'transport_failed', __( 'The authorization endpoint could not be reached safely. Redirects are not followed.', 'superdav-ai-agent' ) );
		}
		return $response;
	}

	private function safe_url( string $url ): true|WP_Error {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || is_wp_error( ( new SsrfGuard() )->assert_safe_url( $url ) ) ) {
			return $this->error( 'unsafe_endpoint', __( 'OAuth endpoints must be public HTTPS URLs without embedded credentials or fragments.', 'superdav-ai-agent' ) );
		}
		return true;
	}

	private function error( string $code, string $message ): WP_Error {
		return new WP_Error( 'sd_ai_agent_remote_mcp_oauth_' . $code, $message );
	}
}
