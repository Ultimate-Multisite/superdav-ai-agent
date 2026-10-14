<?php

declare(strict_types=1);
/**
 * Bounded Streamable HTTP JSON-RPC transport for outbound MCP clients.
 *
 * @package SdAiAgent\Mcp
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use SdAiAgent\Core\Net\SsrfGuard;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpHttpTransport {

	private const MAX_RESPONSE_BYTES = 262144;
	private const MAX_SSE_EVENTS     = 64;

	private string $last_session_id = '';

	public function __construct( private readonly RemoteMcpConnectionRepository $connections, private readonly ?SsrfGuard $guard = null ) {}

	/**
	 * Send one Streamable HTTP JSON-RPC request and extract the matching response.
	 *
	 * @param array<string, mixed>  $connection Safe connection metadata.
	 * @param array<string, mixed>  $message JSON-RPC request.
	 * @param array<string, string> $session_headers Transient session headers.
	 * @return array<string, mixed>|WP_Error
	 */
	public function request( array $connection, array $message, array $session_headers = array() ): array|WP_Error {
		$endpoint = (string) ( $connection['endpoint'] ?? '' );
		$current  = $this->connections->get_for_execution( (string) ( $connection['id'] ?? '' ) );
		if ( ! is_array( $current ) || $endpoint !== ( $current['endpoint'] ?? '' ) || ( $connection['auth_type'] ?? '' ) !== ( $current['auth_type'] ?? '' ) || (int) ( $connection['revision'] ?? 0 ) !== (int) ( $current['revision'] ?? 0 ) || ( 'tools/call' === ( $message['method'] ?? '' ) && empty( $current['enabled'] ) ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_connection_changed', __( 'This MCP connection changed or was disabled. Review it before trying again.', 'superdav-ai-agent' ) );
		}
		$safe = ( $this->guard ?? new SsrfGuard() )->assert_safe_url( $endpoint );
		if ( is_wp_error( $safe ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_unsafe_endpoint', __( 'The MCP endpoint is not permitted.', 'superdav-ai-agent' ) );
		}

		$credentials = $this->connections->authorization_headers( (string) ( $connection['id'] ?? '' ) );
		if ( 'oauth' === ( $connection['auth_type'] ?? '' ) ) {
			$token = ( new RemoteMcpOAuthClient( $this->connections ) )->access_token( (string) $connection['id'] );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$credentials = array( 'Authorization' => 'Bearer ' . $token );
		}
		$scheme = wp_parse_url( $endpoint, PHP_URL_SCHEME );
		if ( ! empty( $credentials ) && 'https' !== strtolower( (string) $scheme ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_insecure_credentials', __( 'Credentials require an HTTPS MCP endpoint.', 'superdav-ai-agent' ) );
		}

		$id      = $message['id'] ?? null;
		$headers = array_merge(
			array(
				'Accept'               => 'application/json, text/event-stream',
				'Content-Type'         => 'application/json',
				'MCP-Protocol-Version' => RemoteMcpClient::PROTOCOL_VERSION,
			),
			$credentials,
			$session_headers
		);
		$body    = wp_json_encode( $message );
		if ( ! is_string( $body ) || strlen( $body ) > 65536 ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_request_bounded', __( 'The MCP request exceeded its safe size limit.', 'superdav-ai-agent' ) );
		}
		$response = $this->post( $endpoint, $headers, $body );
		// Only retry a rejected non-tool request. Never replay tools/call, even on 401.
		if ( ! is_wp_error( $response ) && 401 === wp_remote_retrieve_response_code( $response ) && 'oauth' === ( $connection['auth_type'] ?? '' ) && 'tools/call' !== ( $message['method'] ?? '' ) ) {
			$token = ( new RemoteMcpOAuthClient( $this->connections ) )->access_token( (string) $connection['id'], true );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$headers['Authorization'] = 'Bearer ' . $token;
			$response                 = $this->post( $endpoint, $headers, $body );
		}
		$is_call = 'tools/call' === ( $message['method'] ?? '' );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_transport_failed', $is_call ? __( 'The tool outcome is unknown. Check the remote server before retrying; the call was not replayed.', 'superdav-ai-agent' ) : __( 'The remote MCP server could not be reached.', 'superdav-ai-agent' ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				in_array( $code, array( 401, 403 ), true ) ? 'sd_ai_agent_remote_mcp_authentication_required' : 'sd_ai_agent_remote_mcp_http_error',
				in_array( $code, array( 401, 403 ), true ) ? __( 'Authentication or additional permission is required. Review this connection and sign in again if needed.', 'superdav-ai-agent' ) : ( $is_call ? __( 'The server did not return a successful tool response. Check its outcome before retrying; the call was not replayed.', 'superdav-ai-agent' ) : __( 'The remote MCP server rejected the request.', 'superdav-ai-agent' ) ),
				array(
					'status'      => 502,
					'http_status' => $code,
				)
			);
		}
		$session = wp_remote_retrieve_header( $response, 'mcp-session-id' );
		$content = wp_remote_retrieve_header( $response, 'content-type' );
		if ( ! is_string( $session ) || ! is_string( $content ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_response', __( 'The server returned ambiguous response headers.', 'superdav-ai-agent' ) );
		}
		$this->last_session_id = substr( $session, 0, 256 );
		if ( ! preg_match( '/^[\x21-\x7e]*$/D', $this->last_session_id ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_response', __( 'The server returned an invalid session identifier.', 'superdav-ai-agent' ) );
		}
		if ( ! array_key_exists( 'id', $message ) ) {
			return array( 'accepted' => true );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_response_bounded', __( 'The remote MCP response exceeded its safe size limit.', 'superdav-ai-agent' ) );
		}
		$messages = str_contains( strtolower( $content ), 'text/event-stream' ) ? $this->parse_sse( $body ) : array( json_decode( $body, true, 16 ) );
		foreach ( $messages as $candidate ) {
			if ( is_array( $candidate ) && ( $candidate['jsonrpc'] ?? '' ) === '2.0' && array_key_exists( 'id', $candidate ) && $candidate['id'] === $id && ( array_key_exists( 'result', $candidate ) xor array_key_exists( 'error', $candidate ) ) ) {
				return RemoteMcpPolicy::record( $candidate );
			}
		}
		return new WP_Error( 'sd_ai_agent_remote_mcp_response_missing', $is_call ? __( 'The tool outcome is unknown because its response was invalid or missing. Check the remote server before retrying.', 'superdav-ai-agent' ) : __( 'The remote MCP server did not return a valid response for the request.', 'superdav-ai-agent' ) );
	}

	/**
	 * @param string               $endpoint MCP URL.
	 * @param array<string,string> $headers Validated headers.
	 * @param string               $body Encoded protocol message.
	 * @return array<string,mixed>|WP_Error
	 */
	private function post( string $endpoint, array $headers, string $body ): array|WP_Error {
		return wp_remote_post(
			$endpoint,
			array(
				'headers'                 => $headers,
				'body'                    => $body,
				'timeout'                 => 15,
				'redirection'             => 0,
				'limit_response_size'     => self::MAX_RESPONSE_BYTES + 1,
				'sslverify'               => true,
				'reject_unsafe_urls'      => true,
				'sd_ai_agent_mcp_request' => true,
			)
		); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_post_wp_remote_post
	}

	public function last_session_id(): string {
		return $this->last_session_id;
	}

	/** @return list<array<string, mixed>> */
	private function parse_sse( string $body ): array {
		/** @var list<array<string, mixed>> $messages */
		$messages = array();
		$event    = '';
		foreach ( preg_split( '/\r?\n/', $body ) ?: array() as $line ) {
			if ( str_starts_with( $line, 'data:' ) ) {
				$event .= ltrim( substr( $line, 5 ) ) . "\n";
				continue;
			}
			if ( '' !== trim( $line ) || '' === $event ) {
				continue;
			}
			$decoded = json_decode( $event, true, 16 );
			if ( is_array( $decoded ) ) {
				/** @var array<string, mixed> $decoded */
				$messages[] = $decoded;
			}
			$event = '';
			if ( count( $messages ) >= self::MAX_SSE_EVENTS ) {
				break;
			}
		}
		if ( '' !== $event && count( $messages ) < self::MAX_SSE_EVENTS ) {
			$decoded = json_decode( $event, true, 16 );
			if ( is_array( $decoded ) ) {
				/** @var array<string, mixed> $decoded */
				$messages[] = $decoded;
			}
		}
		return $messages;
	}
}
