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
		$safe     = ( $this->guard ?? new SsrfGuard() )->assert_safe_url( $endpoint );
		if ( is_wp_error( $safe ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_unsafe_endpoint', __( 'The MCP endpoint is not permitted.', 'superdav-ai-agent' ) );
		}

		$id       = $message['id'] ?? null;
		$headers  = array_merge(
			array(
				'Accept'               => 'application/json, text/event-stream',
				'Content-Type'         => 'application/json',
				'MCP-Protocol-Version' => '2025-06-18',
			),
			$this->connections->authorization_headers( (string) ( $connection['id'] ?? '' ) ),
			$session_headers
		);
		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'             => $headers,
				'body'                => wp_json_encode( $message ),
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_RESPONSE_BYTES,
				'sslverify'           => true,
				'reject_unsafe_urls'  => true,
			)
		); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_post_wp_remote_post
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_transport_failed', __( 'The remote MCP server could not be reached.', 'superdav-ai-agent' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'sd_ai_agent_remote_mcp_http_error',
				__( 'The remote MCP server rejected the request.', 'superdav-ai-agent' ),
				array(
					'status'      => 502,
					'http_status' => $code,
				)
			);
		}
		$headers               = wp_remote_retrieve_headers( $response );
		$this->last_session_id = is_object( $headers ) && isset( $headers['mcp-session-id'] ) ? substr( (string) $headers['mcp-session-id'], 0, 256 ) : ( is_array( $headers ) ? substr( (string) ( $headers['mcp-session-id'] ?? '' ), 0, 256 ) : '' );
		if ( ! array_key_exists( 'id', $message ) ) {
			return array();
		}
		$body     = wp_remote_retrieve_body( $response );
		$content  = is_object( $headers ) && isset( $headers['content-type'] ) ? (string) $headers['content-type'] : ( is_array( $headers ) ? (string) ( $headers['content-type'] ?? '' ) : '' );
		$messages = str_contains( strtolower( $content ), 'text/event-stream' ) ? $this->parse_sse( $body ) : array( json_decode( $body, true ) );
		if ( JSON_ERROR_NONE !== json_last_error() && ! str_contains( strtolower( $content ), 'text/event-stream' ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_response', __( 'The remote MCP server returned an invalid protocol response.', 'superdav-ai-agent' ) );
		}
		foreach ( $messages as $candidate ) {
			if ( is_array( $candidate ) && array_key_exists( 'id', $candidate ) && (string) $candidate['id'] === (string) $id ) {
				return $candidate;
			}
		}
		return new WP_Error( 'sd_ai_agent_remote_mcp_response_missing', __( 'The remote MCP server did not return a response for the request.', 'superdav-ai-agent' ) );
	}

	public function last_session_id(): string {
		return $this->last_session_id;
	}

	/** @return list<array<string, mixed>> */
	private function parse_sse( string $body ): array {
		$messages = array();
		$event    = '';
		foreach ( preg_split( '/\r?\n/', $body ) ?: array() as $line ) {
			if ( str_starts_with( $line, 'data:' ) ) {
				$event .= ltrim( substr( $line, 5 ) );
				continue;
			}
			if ( '' !== trim( $line ) || '' === $event ) {
				continue;
			}
			$decoded = json_decode( $event, true );
			if ( is_array( $decoded ) ) {
				$messages[] = $decoded;
			}
			$event = '';
			if ( count( $messages ) >= self::MAX_SSE_EVENTS ) {
				break;
			}
		}
		if ( '' !== $event && count( $messages ) < self::MAX_SSE_EVENTS ) {
			$decoded = json_decode( $event, true );
			if ( is_array( $decoded ) ) {
				$messages[] = $decoded;
			}
		}
		return $messages;
	}
}
