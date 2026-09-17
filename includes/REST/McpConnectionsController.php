<?php

declare(strict_types=1);
/**
 * Admin REST API for outbound MCP connection lifecycle operations.
 *
 * @package SdAiAgent\REST
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\REST;

use SdAiAgent\Mcp\RemoteMcpClient;
use SdAiAgent\Mcp\RemoteMcpConnectionRepository;
use SdAiAgent\Mcp\RemoteMcpHttpTransport;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class McpConnectionsController {

	use PermissionTrait;

	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/mcp-connections',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_save' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/mcp-connections/(?P<id>[a-z0-9-]+)/(?P<action>refresh|test|enable|disable)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_action' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/mcp-connections/(?P<id>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_delete' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/mcp-connections/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_import' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/mcp-connections/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_export' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);
	}

	public function handle_list(): WP_REST_Response {
		return new WP_REST_Response( array( 'connections' => $this->repository()->list() ), 200 );
	}

	public function handle_save( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : $request->get_params();
		$secret = array();
		$value  = isset( $params['secret'] ) ? (string) $params['secret'] : '';
		$header = isset( $params['header_name'] ) ? (string) $params['header_name'] : '';
		if ( '' !== $value ) {
			$secret = array(
				'value'       => $value,
				'header_name' => $header,
			);
		}
		$result = $this->repository()->save( $params, $secret );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'connection' => $result ), 200 );
	}

	public function handle_action( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = sanitize_key( (string) $request->get_param( 'id' ) );
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		if ( ! $this->repository()->get( $id ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_not_found', __( 'The remote MCP connection was not found.', 'superdav-ai-agent' ), array( 'status' => 404 ) );
		}
		if ( 'enable' === $action || 'disable' === $action ) {
			$this->repository()->set_enabled( $id, 'enable' === $action );
			return new WP_REST_Response( array( 'connection' => $this->repository()->get( $id ) ), 200 );
		}
		$result = $this->client()->discover( $id );
		return is_wp_error( $result ) ? $result : new WP_REST_Response(
			array(
				'connection' => $this->repository()->get( $id ),
				'discovery'  => $result,
			),
			200
		);
	}

	public function handle_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = sanitize_key( (string) $request->get_param( 'id' ) );
		if ( ! $this->repository()->delete( $id ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_not_found', __( 'The remote MCP connection was not found.', 'superdav-ai-agent' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	public function handle_import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$servers = isset( $params['mcpServers'] ) && is_array( $params['mcpServers'] ) ? $params['mcpServers'] : array( $params['server'] ?? array() );
		$created = array();
		foreach ( $servers as $key => $server ) {
			if ( ! is_array( $server ) ) {
				continue;
			}
			$input  = array(
				'name'      => $server['name'] ?? $server['displayName'] ?? ( is_string( $key ) ? $key : '' ),
				'endpoint'  => $server['url'] ?? $server['endpoint'] ?? '',
				'auth_type' => $server['auth_type'] ?? 'none',
				'enabled'   => false,
			);
			$result = $this->repository()->save( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$created[] = $result;
		}
		if ( empty( $created ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_connection', __( 'At least one valid MCP server is required.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'connections' => $created ), 201 );
	}

	public function handle_export(): WP_REST_Response {
		$servers = array_map(
			static fn( array $connection ): array => array(
				'name'       => $connection['name'],
				'url'        => $connection['endpoint'],
				'transport'  => $connection['transport'],
				'auth_type'  => $connection['auth_type'],
				'configured' => ! empty( $connection['configured'] ),
			),
			$this->repository()->list()
		);
		return new WP_REST_Response( array( 'mcpServers' => $servers ), 200 );
	}

	private function repository(): RemoteMcpConnectionRepository {
		return new RemoteMcpConnectionRepository();
	}

	private function client(): RemoteMcpClient {
		$repository = $this->repository();
		return new RemoteMcpClient( $repository, new RemoteMcpHttpTransport( $repository ) );
	}
}
