<?php

declare(strict_types=1);
/**
 * Protocol lifecycle client for persisted outbound Streamable HTTP MCP servers.
 *
 * @package SdAiAgent\Mcp
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpClient {

	private int $request_id = 1;

	/** @var array<string, string> */
	private array $session_headers = array();

	public function __construct( private readonly RemoteMcpConnectionRepository $connections, private readonly RemoteMcpHttpTransport $transport ) {}

	/**
	 * Discover a complete, bounded tool list and atomically persist it on success.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function discover( string $connection_id ): array|WP_Error {
		$connection = $this->connections->get_for_execution( $connection_id );
		if ( ! is_array( $connection ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_not_found', __( 'The remote MCP connection no longer exists.', 'superdav-ai-agent' ), array( 'status' => 404 ) );
		}

		$initialized = $this->initialize( $connection );
		if ( is_wp_error( $initialized ) ) {
			$this->connections->mark_failed( $connection_id, $initialized->get_error_code() );
			return $initialized;
		}

		$tools  = array();
		$cursor = '';
		for ( $page = 0; $page < 10; ++$page ) {
			$params = '' === $cursor ? array() : array( 'cursor' => $cursor );
			$result = $this->send_request( $connection, 'tools/list', $params );
			if ( is_wp_error( $result ) ) {
				$this->connections->mark_failed( $connection_id, $result->get_error_code() );
				return $result;
			}
			$listed = isset( $result['tools'] ) && is_array( $result['tools'] ) ? $result['tools'] : null;
			if ( null === $listed ) {
				$error = new WP_Error( 'sd_ai_agent_remote_mcp_invalid_tools', __( 'The remote MCP server returned an invalid tool list.', 'superdav-ai-agent' ) );
				$this->connections->mark_failed( $connection_id, $error->get_error_code() );
				return $error;
			}
			foreach ( $listed as $tool ) {
				$normalised = $this->normalise_tool( $tool );
				if ( null !== $normalised ) {
					$tools[] = $normalised;
				}
				if ( count( $tools ) >= 100 ) {
					break 2;
				}
			}
			$cursor = isset( $result['nextCursor'] ) ? sanitize_text_field( (string) $result['nextCursor'] ) : '';
			if ( '' === $cursor ) {
				break;
			}
		}

		$protocol     = isset( $initialized['protocolVersion'] ) ? (string) $initialized['protocolVersion'] : '2025-06-18';
		$capabilities = isset( $initialized['capabilities'] ) && is_array( $initialized['capabilities'] ) ? $initialized['capabilities'] : array();
		$this->connections->replace_snapshot( $connection_id, $tools, $protocol, $capabilities );
		return array(
			'tools'            => $tools,
			'protocol_version' => $protocol,
			'capabilities'     => $capabilities,
		);
	}

	/**
	 * Invoke one discovered remote tool; calls are never automatically retried.
	 *
	 * @param string               $connection_id Connection ID.
	 * @param string               $tool_name Exact remote tool name.
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	public function call( string $connection_id, string $tool_name, array $arguments ): array|WP_Error {
		$connection = $this->connections->get_for_execution( $connection_id );
		if ( ! is_array( $connection ) || empty( $connection['enabled'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_disabled', __( 'This remote MCP connection is disabled.', 'superdav-ai-agent' ), array( 'status' => 403 ) );
		}
		$initialized = $this->initialize( $connection );
		if ( is_wp_error( $initialized ) ) {
			return $initialized;
		}
		return $this->send_request(
			$connection,
			'tools/call',
			array(
				'name'      => $tool_name,
				'arguments' => $arguments,
			)
		);
	}

	/**
	 * Initialize a transient MCP session.
	 *
	 * @param array<string, mixed> $connection Connection metadata.
	 * @return array<string, mixed>|WP_Error Initialize result.
	 */
	private function initialize( array $connection ): array|WP_Error {
		$result = $this->send_request(
			$connection,
			'initialize',
			array(
				'protocolVersion' => '2025-06-18',
				'capabilities'    => array(),
				'clientInfo'      => array(
					'name'    => 'sd-ai-agent',
					'version' => defined( 'SD_AI_AGENT_VERSION' ) ? (string) constant( 'SD_AI_AGENT_VERSION' ) : 'unknown',
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! isset( $result['protocolVersion'] ) || ! is_string( $result['protocolVersion'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_initialize_failed', __( 'The remote MCP server did not negotiate a protocol version.', 'superdav-ai-agent' ) );
		}
		$session_id = $this->transport->last_session_id();
		if ( '' !== $session_id ) {
			$this->session_headers = array( 'Mcp-Session-Id' => $session_id );
		}
		$notification = $this->send_notification( $connection, 'notifications/initialized', array() );
		return is_wp_error( $notification ) ? $notification : $result;
	}

	/**
	 * Send a JSON-RPC request and return its result.
	 *
	 * @param array<string, mixed> $connection Connection metadata.
	 * @param string               $method MCP method.
	 * @param array<string, mixed> $params MCP request parameters.
	 * @return array<string, mixed>|WP_Error Remote result.
	 */
	private function send_request( array $connection, string $method, array $params ): array|WP_Error {
		$id       = $this->request_id++;
		$response = $this->transport->request(
			$connection,
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => $params,
			),
			$this->session_headers
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( isset( $response['error'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_protocol_error', __( 'The remote MCP server returned a protocol error.', 'superdav-ai-agent' ) );
		}
		return isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : new WP_Error( 'sd_ai_agent_remote_mcp_invalid_response', __( 'The remote MCP server returned an invalid result.', 'superdav-ai-agent' ) );
	}

	/**
	 * Send a one-way MCP lifecycle notification.
	 *
	 * @param array<string, mixed> $connection Connection metadata.
	 * @param string               $method MCP notification method.
	 * @param array<string, mixed> $params MCP notification parameters.
	 * @return true|WP_Error True when accepted by the server.
	 */
	private function send_notification( array $connection, string $method, array $params ): true|WP_Error {
		$response = $this->transport->request(
			$connection,
			array(
				'jsonrpc' => '2.0',
				'method'  => $method,
				'params'  => $params,
			),
			$this->session_headers
		);
		return is_wp_error( $response ) ? $response : true;
	}

	/** @return array<string, mixed>|null */
	private function normalise_tool( mixed $tool ): ?array {
		if ( ! is_array( $tool ) || ! isset( $tool['name'] ) || ! is_string( $tool['name'] ) || ! preg_match( '/^[A-Za-z0-9_.:-]{1,128}$/', $tool['name'] ) ) {
			return null;
		}
		$schema = isset( $tool['inputSchema'] ) && is_array( $tool['inputSchema'] ) ? $tool['inputSchema'] : array(
			'type'       => 'object',
			'properties' => array(),
		);
		if ( ! $this->is_bounded_schema( $schema ) ) {
			return null;
		}
		return array(
			'name'         => $tool['name'],
			'description'  => isset( $tool['description'] ) ? substr( sanitize_textarea_field( (string) $tool['description'] ), 0, 4096 ) : '',
			'input_schema' => $schema,
			'annotations'  => isset( $tool['annotations'] ) && is_array( $tool['annotations'] ) ? array_intersect_key( $tool['annotations'], array_flip( array( 'readOnlyHint', 'destructiveHint', 'idempotentHint', 'openWorldHint' ) ) ) : array(),
			'enabled'      => true,
		);
	}

	/** @param array<string, mixed> $value */
	private function is_bounded_schema( array $value, int $depth = 0 ): bool {
		if ( $depth > 8 || count( $value ) > 100 ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) || strlen( $key ) > 128 ) {
				return false;
			}
			if ( is_array( $item ) && ! $this->is_bounded_schema( $item, $depth + 1 ) ) {
				return false;
			}
			if ( is_string( $item ) && strlen( $item ) > 8192 ) {
				return false;
			}
		}
		return true;
	}
}
