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
use SdAiAgent\Mcp\RemoteMcpOAuthClient;
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
			'/mcp-connections/(?P<id>[a-z0-9-]+)/(?P<action>refresh|test|enable|disable|authorize|disconnect)',
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
		return new WP_REST_Response(
			array(
				'connections' => $this->repository()->list(),
				'oauth_setup' => array(
					'redirect_uri'        => RemoteMcpOAuthClient::callback_url(),
					'client_metadata_uri' => RemoteMcpOAuthClient::client_metadata_url(),
				),
			),
			200
		);
	}

	public function handle_save( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : $request->get_params();
		foreach ( array( 'id', 'name', 'endpoint', 'auth_type', 'secret', 'header_name', 'client_id', 'client_secret' ) as $field ) {
			if ( isset( $params[ $field ] ) && ! is_string( $params[ $field ] ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_connection', __( 'Connection fields must be text values.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
			}
		}
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
		if ( ! is_wp_error( $result ) && 'oauth' === ( $result['auth_type'] ?? '' ) && ( ! empty( $params['client_id'] ) || ! empty( $params['client_secret'] ) ) ) {
			$client_id     = (string) ( $params['client_id'] ?? '' );
			$client_secret = (string) ( $params['client_secret'] ?? '' );
			if ( strlen( $client_id ) > 2048 || strlen( $client_secret ) > 8192 || preg_match( '/[\r\n\x00]/', $client_id . $client_secret ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_credential', __( 'Enter valid registered client details without line breaks.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
			}
			$stored = $this->repository()->store_secret(
				(string) $result['id'],
				array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
				),
				(int) $result['revision']
				);
			if ( is_wp_error( $stored ) ) {
				return $stored;
			}
			$result = $this->repository()->get( (string) $result['id'] );
		}
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'connection' => $result ), 200 );
	}

	public function handle_action( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = sanitize_key( (string) $request->get_param( 'id' ) );
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		if ( ! $this->repository()->get( $id ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_not_found', __( 'The remote MCP connection was not found.', 'superdav-ai-agent' ), array( 'status' => 404 ) );
		}
		if ( 'authorize' === $action ) {
			$result = ( new RemoteMcpOAuthClient( $this->repository() ) )->begin( $id );
			return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
		}
		if ( 'disconnect' === $action ) {
			$result = ( new RemoteMcpOAuthClient( $this->repository() ) )->disconnect( $id );
			return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'connection' => $this->repository()->get( $id ) ), 200 );
		}
		if ( 'enable' === $action || 'disable' === $action ) {
			$connection = $this->repository()->get( $id );
			if ( 'enable' === $action && ! $this->repository()->is_fresh( $connection ) ) {
				$result = $this->client()->discover( $id );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			if ( ! $this->repository()->set_enabled( $id, 'enable' === $action ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_connection_changed', __( 'The connection could not be updated. Reload its status and try again.', 'superdav-ai-agent' ) );
			}
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
		$id         = sanitize_key( (string) $request->get_param( 'id' ) );
		$connection = $this->repository()->get( $id );
		if ( is_array( $connection ) && 'oauth' === ( $connection['auth_type'] ?? '' ) ) {
			$result = ( new RemoteMcpOAuthClient( $this->repository() ) )->disconnect( $id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
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
		if ( count( $servers ) > 20 ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_import_bounded', __( 'Import at most 20 servers at a time.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}
		// Prevalidate the entire import before writing; stdio/commands are never executed.
		foreach ( $servers as $server ) {
			if ( ! is_array( $server ) || isset( $server['command'], $server['args'] ) || isset( $server['command'] ) || ( isset( $server['transport'] ) && 'streamable-http' !== $server['transport'] ) || ! is_string( $server['url'] ?? $server['endpoint'] ?? null ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_import', __( 'Import only modern HTTP server URLs; command/stdio configurations are not supported.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
			}
		}
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
