<?php

declare(strict_types=1);
/**
 * Protocol lifecycle client for persisted outbound Streamable HTTP MCP servers.
 *
 * @package SdAiAgent\Mcp
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use SdAiAgent\Abilities\ToolCapabilities;
use SdAiAgent\Core\AgentEventLog;
use SdAiAgent\Tools\AbilityUsageTracker;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpClient {

	public const PROTOCOL_VERSION = '2026-07-28';

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
		$lock = RemoteMcpLock::acquire( 'refresh-' . $connection_id, 240 );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			return $this->discover_locked( $connection_id, $lock );
		} finally {
			RemoteMcpLock::release( 'refresh-' . $connection_id, $lock );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	private function discover_locked( string $connection_id, string $owner ): array|WP_Error {
		$connection = $this->connections->get_for_execution( $connection_id );
		if ( ! is_array( $connection ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_not_found', __( 'The remote MCP connection no longer exists.', 'superdav-ai-agent' ), array( 'status' => 404 ) );
		}

		$initialized = $this->initialize( $connection );
		if ( is_wp_error( $initialized ) ) {
			$this->connections->mark_failed( $connection_id, (string) $initialized->get_error_code() );
			return $initialized;
		}

		$tools  = array();
		$cursor = '';
		for ( $page = 0; $page < 10; ++$page ) {
			$params = '' === $cursor ? array() : array( 'cursor' => $cursor );
			$result = $this->send_request( $connection, 'tools/list', $params );
			if ( is_wp_error( $result ) ) {
				$this->connections->mark_failed( $connection_id, (string) $result->get_error_code() );
				return $result;
			}
			$listed = isset( $result['tools'] ) && is_array( $result['tools'] ) ? $result['tools'] : null;
			if ( null === $listed ) {
				$error = new WP_Error( 'sd_ai_agent_remote_mcp_invalid_tools', __( 'The remote MCP server returned an invalid tool list.', 'superdav-ai-agent' ) );
				$this->connections->mark_failed( $connection_id, (string) $error->get_error_code() );
				return $error;
			}
			foreach ( $listed as $tool ) {
				$normalised = $this->normalise_tool( $tool );
				if ( null === $normalised ) {
					$error = new WP_Error( 'sd_ai_agent_remote_mcp_unsupported_schema', __( 'The server advertised an unsafe or unsupported tool schema. Review its tools before connecting.', 'superdav-ai-agent' ) );
					$this->connections->mark_failed( $connection_id, (string) $error->get_error_code() );
					return $error;
				}
				$tools[] = $normalised;
				if ( count( $tools ) >= 100 ) {
					$error = new WP_Error( 'sd_ai_agent_remote_mcp_discovery_bounded', __( 'The remote MCP server returned more tools than can be safely discovered.', 'superdav-ai-agent' ) );
					$this->connections->mark_failed( $connection_id, (string) $error->get_error_code() );
					return $error;
				}
			}
			$cursor = isset( $result['nextCursor'] ) ? sanitize_text_field( (string) $result['nextCursor'] ) : '';
			if ( '' === $cursor ) {
				break;
			}
		}
		if ( '' !== $cursor ) {
			$error = new WP_Error( 'sd_ai_agent_remote_mcp_discovery_bounded', __( 'The remote MCP server returned more tool-list pages than can be safely discovered.', 'superdav-ai-agent' ) );
			$this->connections->mark_failed( $connection_id, (string) $error->get_error_code() );
			return $error;
		}

		$protocol     = (string) $initialized['protocolVersion'];
		$capabilities = isset( $initialized['capabilities'] ) && is_array( $initialized['capabilities'] ) ? $initialized['capabilities'] : array();
		$names        = array_map( static fn( array $tool ): string => $tool['name'], $tools );
		if ( ! RemoteMcpPolicy::bounded( $capabilities ) || count( array_unique( $names ) ) !== count( $tools ) ) {
			$error = new WP_Error( 'sd_ai_agent_remote_mcp_invalid_tools', __( 'The server returned duplicate tools or invalid capabilities.', 'superdav-ai-agent' ) );
			$this->connections->mark_failed( $connection_id, (string) $error->get_error_code() );
			return $error;
		}
		if ( ! $this->connections->replace_snapshot(
			$connection_id,
			$tools,
			$protocol,
			$capabilities,
			(int) ( $connection['revision'] ?? 0 ),
			array(
				'key'   => 'refresh-' . $connection_id,
				'owner' => $owner,
			)
			) ) {
			$error = new WP_Error( 'sd_ai_agent_remote_mcp_snapshot_failed', __( 'The discovered MCP tools could not be saved.', 'superdav-ai-agent' ) );
			$this->connections->mark_failed( $connection_id, (string) $error->get_error_code() );
			return $error;
		}
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
		$ability = RemoteMcpAbilityRegistrar::ability_name( $connection_id, $tool_name );
		if ( ! ToolCapabilities::current_user_can( $ability ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_forbidden', __( 'You do not have permission to use this tool.', 'superdav-ai-agent' ), array( 'status' => 403 ) );
		}
		$started = microtime( true );
		$result  = $this->call_validated( $connection_id, $tool_name, $arguments );
		AgentEventLog::log(
			'remote_mcp_call',
			is_wp_error( $result ) ? AgentEventLog::SEVERITY_WARNING : AgentEventLog::SEVERITY_INFO,
			array(
				'ability'     => $ability,
				'code'        => is_wp_error( $result ) ? $result->get_error_code() : 'success',
				'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
		if ( ! is_wp_error( $result ) && empty( $result['isError'] ) ) {
			AbilityUsageTracker::record( $ability );
		}
		return $result;
	}

	/**
	 * @param string              $connection_id Connection ID.
	 * @param string              $tool_name Exact remote name.
	 * @param array<string,mixed> $arguments Input to validate before network I/O.
	 * @return array<string,mixed>|WP_Error
	 */
	private function call_validated( string $connection_id, string $tool_name, array $arguments ): array|WP_Error {
		$connection = $this->connections->get_for_execution( $connection_id );
		if ( ! is_array( $connection ) || empty( $connection['enabled'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_disabled', __( 'This remote MCP connection is disabled.', 'superdav-ai-agent' ), array( 'status' => 403 ) );
		}
		$tool  = $this->find_tool( $connection, $tool_name );
		$valid = is_array( $tool ) ? RemoteMcpPolicy::validate_arguments( $arguments, (array) ( $tool['input_schema'] ?? array() ) ) : new WP_Error( 'sd_ai_agent_remote_mcp_tool_disabled', __( 'This tool is disabled or no longer available.', 'superdav-ai-agent' ) );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! $this->connections->is_fresh( $connection ) ) {
			$refreshed = $this->discover( $connection_id );
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
			$connection = $this->connections->get_for_execution( $connection_id );
			if ( ! is_array( $connection ) || empty( $connection['enabled'] ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_disabled', __( 'This connection changed while refreshing.', 'superdav-ai-agent' ) );
			}
			$tool  = $this->find_tool( $connection, $tool_name );
			$valid = is_array( $tool ) ? RemoteMcpPolicy::validate_arguments( $arguments, (array) ( $tool['input_schema'] ?? array() ) ) : new WP_Error( 'sd_ai_agent_remote_mcp_tool_removed', __( 'The server no longer advertises this tool. Refresh the conversation’s tools.', 'superdav-ai-agent' ) );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
		$initialized = $this->initialize( $connection );
		if ( is_wp_error( $initialized ) ) {
			$this->connections->mark_failed( $connection_id, (string) $initialized->get_error_code() );
			return $initialized;
		}
		$result = $this->send_request(
			$connection,
			'tools/call',
			array(
				'name'      => $tool_name,
				'arguments' => empty( $arguments ) ? new \stdClass() : $arguments,
			)
		);
		if ( is_wp_error( $result ) ) {
			$this->connections->mark_failed( $connection_id, (string) $result->get_error_code() );
			return $result;
		}
		$valid = RemoteMcpPolicy::validate_result( $result );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! empty( $result['isError'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_tool_error', __( 'The remote tool reported an error. Review the server before retrying; this call was not replayed.', 'superdav-ai-agent' ) );
		}
		return RemoteMcpPolicy::redact_credentials( $result, $this->connections->get_secret( $connection_id ) );
	}

	/**
	 * @param array<string,mixed> $connection Current snapshot.
	 * @param string              $name Exact tool name.
	 * @return array<string,mixed>|null
	 */
	private function find_tool( array $connection, string $name ): ?array {
		foreach ( $connection['tools'] ?? array() as $tool ) {
			if ( is_array( $tool ) && ( $tool['name'] ?? '' ) === $name && ! empty( $tool['enabled'] ) ) {
				return RemoteMcpPolicy::record( $tool );
			}
		}
		return null;
	}

	/**
	 * Initialize a transient MCP session.
	 *
	 * @param array<string, mixed> $connection Connection metadata.
	 * @return array<string, mixed>|WP_Error Initialize result.
	 */
	private function initialize( array $connection ): array|WP_Error {
		$this->session_headers = array(); // Never carry a previous server's session into initialization.
		$result                = $this->send_request(
			$connection,
			'initialize',
			array(
				'protocolVersion' => self::PROTOCOL_VERSION,
				'capabilities'    => new \stdClass(),
				'clientInfo'      => array(
					'name'    => 'sd-ai-agent',
					'version' => defined( 'SD_AI_AGENT_VERSION' ) ? (string) constant( 'SD_AI_AGENT_VERSION' ) : 'unknown',
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! in_array( $result['protocolVersion'] ?? '', array( '2025-06-18', '2025-11-25', self::PROTOCOL_VERSION ), true ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_initialize_failed', __( 'The remote MCP server did not negotiate a protocol version.', 'superdav-ai-agent' ) );
		}
		$this->session_headers['MCP-Protocol-Version'] = $result['protocolVersion'];
		$session_id                                    = $this->transport->last_session_id();
		if ( '' !== $session_id ) {
			$this->session_headers['Mcp-Session-Id'] = $session_id;
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
				'params'  => empty( $params ) ? new \stdClass() : $params,
			),
			$this->session_headers
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( isset( $response['error'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_protocol_error', __( 'The remote MCP server returned a protocol error.', 'superdav-ai-agent' ) );
		}
		if ( ! isset( $response['result'] ) || ! is_array( $response['result'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_response', __( 'The remote MCP server returned an invalid result.', 'superdav-ai-agent' ) );
		}
		return RemoteMcpPolicy::record( $response['result'] );
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
				'params'  => empty( $params ) ? new \stdClass() : $params,
			),
			$this->session_headers
		);
		return is_wp_error( $response ) ? $response : true;
	}

	/** @return array{name:string,description:string,input_schema:array<mixed>,annotations:array<mixed>,enabled:bool}|null */
	private function normalise_tool( mixed $tool ): ?array {
		if ( ! is_array( $tool ) || ! isset( $tool['name'] ) || ! is_string( $tool['name'] ) || ! preg_match( '/^[A-Za-z0-9_.:-]{1,128}$/', $tool['name'] ) ) {
			return null;
		}
		$schema = isset( $tool['inputSchema'] ) && is_array( $tool['inputSchema'] ) ? $tool['inputSchema'] : array(
			'type'       => 'object',
			'properties' => array(),
		);
		$types  = (array) ( $schema['type'] ?? 'object' );
		if ( ! RemoteMcpPolicy::supported_schema( $schema ) || ! in_array( 'object', $types, true ) || ! RemoteMcpPolicy::bounded( $tool ) ) {
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
}
