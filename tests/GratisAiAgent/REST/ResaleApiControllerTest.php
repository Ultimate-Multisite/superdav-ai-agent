<?php

declare(strict_types=1);
/**
 * Integration tests for ResaleApiController.
 *
 * Uses the WordPress REST API test infrastructure (WP_REST_Server) to dispatch
 * real HTTP-style requests through the registered routes. Coverage:
 *   - Route registration.
 *   - Admin permission checks (unauthenticated / subscriber / admin).
 *   - handle_list_clients returns 200 with array.
 *   - handle_create_client validates required fields and returns 201.
 *   - handle_get_client returns 404 for unknown ID.
 *   - handle_update_client returns 404 for unknown ID.
 *   - handle_update_client returns 400 when no valid fields provided.
 *   - handle_delete_client returns 404 for unknown ID.
 *   - handle_rotate_key returns 404 for unknown ID.
 *   - handle_get_usage returns 404 for unknown client ID.
 *   - handle_get_usage_summary returns 404 for unknown client ID.
 *   - handle_proxy returns 401 when X-Resale-API-Key header is missing.
 *   - handle_proxy returns 401 for invalid API key.
 *   - sanitize_client_for_response strips api_key and casts types.
 *
 * @package GratisAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\REST;

use GratisAiAgent\REST\ResaleApiController;
use GratisAiAgent\REST\ResaleApiDatabase;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Integration tests for ResaleApiController.
 *
 * @group resale
 * @group rest
 */
class ResaleApiControllerTest extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected WP_REST_Server $server;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected int $admin_id;

	/**
	 * Subscriber user ID (no manage_options).
	 *
	 * @var int
	 */
	protected int $subscriber_id;

	/**
	 * Set up REST server and test users before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress test global.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'rest_api_init' );

		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	/**
	 * Tear down REST server after each test.
	 */
	public function tear_down(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress test global.
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Dispatch a REST request and return the response.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $route   Route path.
	 * @param array<string, mixed> $params  Request parameters.
	 * @param array<string, mixed> $headers Additional headers.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( string $method, string $route, array $params = [], array $headers = [] ) {
		$request = new WP_REST_Request( $method, $route );

		if ( in_array( $method, [ 'POST', 'PATCH', 'PUT' ], true ) ) {
			$request->set_body( wp_json_encode( $params ) );
			$request->set_header( 'Content-Type', 'application/json' );
		} else {
			$request->set_query_params( $params );
		}

		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Assert a response has the expected HTTP status code.
	 *
	 * @param int                        $expected Expected status code.
	 * @param \WP_REST_Response|\WP_Error $response Response to check.
	 */
	private function assertStatus( int $expected, $response ): void {
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) ? ( $data['status'] ?? 0 ) : 0;
		} else {
			$status = $response->get_status();
		}
		$this->assertSame( $expected, $status, "Expected HTTP {$expected}, got {$status}." );
	}

	/**
	 * Create a resale client directly via the database layer and return its ID.
	 *
	 * @param array<string, mixed> $overrides Optional field overrides.
	 * @return int Client ID.
	 */
	private function create_test_client( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'    => 'Test Client ' . wp_generate_password( 6, false ),
				'api_key' => 'gaa_' . wp_generate_password( 32, false ),
				'enabled' => 1,
			],
			$overrides
		);

		$id = ResaleApiDatabase::create_client( $data );
		$this->assertNotFalse( $id, 'Failed to create test client.' );
		return (int) $id;
	}

	// ─── Route Registration ───────────────────────────────────────────────────

	/**
	 * All resale API routes are registered.
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$expected = [
			'/gratis-ai-agent/v1/resale/proxy',
			'/gratis-ai-agent/v1/resale/clients',
			'/gratis-ai-agent/v1/resale/clients/(?P<id>\d+)',
			'/gratis-ai-agent/v1/resale/clients/(?P<id>\d+)/rotate-key',
			'/gratis-ai-agent/v1/resale/clients/(?P<id>\d+)/usage',
			'/gratis-ai-agent/v1/resale/clients/(?P<id>\d+)/usage/summary',
		];

		foreach ( $expected as $route ) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} should be registered." );
		}
	}

	// ─── Permission checks ────────────────────────────────────────────────────

	/**
	 * Unauthenticated request to /resale/clients is rejected with 401.
	 */
	public function test_clients_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients' );
		$this->assertStatus( 401, $response );
	}

	/**
	 * Subscriber (no manage_options) is rejected with 403.
	 */
	public function test_clients_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients' );
		$this->assertStatus( 403, $response );
	}

	/**
	 * check_admin_permission returns true for admin.
	 */
	public function test_check_admin_permission_returns_true_for_admin(): void {
		wp_set_current_user( $this->admin_id );
		$controller = new ResaleApiController();
		$this->assertTrue( $controller->check_admin_permission() );
	}

	/**
	 * check_admin_permission returns false for unauthenticated user.
	 */
	public function test_check_admin_permission_returns_false_for_guest(): void {
		wp_set_current_user( 0 );
		$controller = new ResaleApiController();
		$this->assertFalse( $controller->check_admin_permission() );
	}

	// ─── handle_list_clients ─────────────────────────────────────────────────

	/**
	 * GET /resale/clients returns 200 with an array.
	 */
	public function test_list_clients_returns_200(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * GET /resale/clients does not expose api_key in response.
	 */
	public function test_list_clients_does_not_expose_api_key(): void {
		wp_set_current_user( $this->admin_id );

		$this->create_test_client();

		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients' );
		$clients  = $response->get_data();

		$this->assertNotEmpty( $clients );
		foreach ( $clients as $client ) {
			$client_array = (array) $client;
			$this->assertArrayNotHasKey( 'api_key', $client_array, 'api_key must not be exposed in list response.' );
			$this->assertArrayHasKey( 'has_key', $client_array, 'has_key indicator should be present.' );
		}
	}

	// ─── handle_create_client ─────────────────────────────────────────────────

	/**
	 * POST /resale/clients requires name.
	 */
	public function test_create_client_requires_name(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/resale/clients', [] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /resale/clients with valid name returns 201 and includes api_key once.
	 */
	public function test_create_client_returns_201_with_api_key(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/resale/clients', [
			'name' => 'New Test Client',
		] );
		$this->assertStatus( 201, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertGreaterThan( 0, $data['id'] );
		// api_key is returned on creation only.
		$this->assertArrayHasKey( 'api_key', $data );
		$this->assertStringStartsWith( 'gaa_', $data['api_key'] );
		// proxy_url is included.
		$this->assertArrayHasKey( 'proxy_url', $data );
	}

	/**
	 * POST /resale/clients with quota sets quota_reset_at.
	 */
	public function test_create_client_with_quota_sets_reset_date(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/resale/clients', [
			'name'                => 'Quota Client',
			'monthly_token_quota' => 10000,
		] );
		$this->assertStatus( 201, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
	}

	/**
	 * Unauthenticated POST /resale/clients is rejected.
	 */
	public function test_create_client_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/resale/clients', [
			'name' => 'Unauth Client',
		] );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_get_client ────────────────────────────────────────────────────

	/**
	 * GET /resale/clients/{id} returns 404 for unknown ID.
	 */
	public function test_get_client_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients/999999' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * GET /resale/clients/{id} returns 200 for existing client.
	 */
	public function test_get_client_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client( [ 'name' => 'Get Test Client' ] );

		$response = $this->dispatch( 'GET', "/gratis-ai-agent/v1/resale/clients/{$client_id}" );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertSame( $client_id, $data['id'] );
		// api_key must not be exposed.
		$this->assertArrayNotHasKey( 'api_key', $data );
		// proxy_url is included.
		$this->assertArrayHasKey( 'proxy_url', $data );
	}

	// ─── handle_update_client ─────────────────────────────────────────────────

	/**
	 * PATCH /resale/clients/{id} returns 404 for unknown ID.
	 */
	public function test_update_client_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'PATCH', '/gratis-ai-agent/v1/resale/clients/999999' );
		$request->set_body( wp_json_encode( [ 'name' => 'Ghost' ] ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 404, $response );
	}

	/**
	 * PATCH /resale/clients/{id} with no valid fields returns 400.
	 */
	public function test_update_client_no_fields_returns_400(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client();

		$request = new WP_REST_Request( 'PATCH', "/gratis-ai-agent/v1/resale/clients/{$client_id}" );
		$request->set_body( wp_json_encode( [] ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 400, $response );
	}

	/**
	 * PATCH /resale/clients/{id} with valid fields returns 200.
	 */
	public function test_update_client_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client( [ 'name' => 'Original Name' ] );

		$request = new WP_REST_Request( 'PATCH', "/gratis-ai-agent/v1/resale/clients/{$client_id}" );
		$request->set_body( wp_json_encode( [ 'name' => 'Updated Name' ] ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Updated Name', $data['name'] );
	}

	// ─── handle_delete_client ─────────────────────────────────────────────────

	/**
	 * DELETE /resale/clients/{id} returns 404 for unknown ID.
	 */
	public function test_delete_client_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'DELETE', '/gratis-ai-agent/v1/resale/clients/999999' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 404, $response );
	}

	/**
	 * DELETE /resale/clients/{id} returns 200 for existing client.
	 */
	public function test_delete_client_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client();

		$request  = new WP_REST_Request( 'DELETE', "/gratis-ai-agent/v1/resale/clients/{$client_id}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertTrue( $data['deleted'] );
	}

	// ─── handle_rotate_key ────────────────────────────────────────────────────

	/**
	 * POST /resale/clients/{id}/rotate-key returns 404 for unknown ID.
	 */
	public function test_rotate_key_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/resale/clients/999999/rotate-key', [] );
		$this->assertStatus( 404, $response );
	}

	/**
	 * POST /resale/clients/{id}/rotate-key returns new api_key.
	 */
	public function test_rotate_key_returns_new_key(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client();

		$response = $this->dispatch( 'POST', "/gratis-ai-agent/v1/resale/clients/{$client_id}/rotate-key", [] );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'api_key', $data );
		$this->assertStringStartsWith( 'gaa_', $data['api_key'] );
		$this->assertSame( $client_id, $data['id'] );
	}

	// ─── handle_get_usage ─────────────────────────────────────────────────────

	/**
	 * GET /resale/clients/{id}/usage returns 404 for unknown client.
	 */
	public function test_get_usage_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients/999999/usage' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * GET /resale/clients/{id}/usage returns paginated log for existing client.
	 */
	public function test_get_usage_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client();

		$response = $this->dispatch( 'GET', "/gratis-ai-agent/v1/resale/clients/{$client_id}/usage" );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'logs', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'limit', $data );
		$this->assertArrayHasKey( 'offset', $data );
	}

	// ─── handle_get_usage_summary ─────────────────────────────────────────────

	/**
	 * GET /resale/clients/{id}/usage/summary returns 404 for unknown client.
	 */
	public function test_get_usage_summary_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/resale/clients/999999/usage/summary' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * GET /resale/clients/{id}/usage/summary returns aggregated totals.
	 */
	public function test_get_usage_summary_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$client_id = $this->create_test_client();

		$response = $this->dispatch( 'GET', "/gratis-ai-agent/v1/resale/clients/{$client_id}/usage/summary" );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'client_id', $data );
		$this->assertArrayHasKey( 'monthly_token_quota', $data );
		$this->assertArrayHasKey( 'tokens_used_this_month', $data );
	}

	// ─── handle_proxy ─────────────────────────────────────────────────────────

	/**
	 * POST /resale/proxy without X-Resale-API-Key header returns 401.
	 */
	public function test_proxy_missing_api_key_returns_401(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/resale/proxy', [
			'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ],
		] );
		$this->assertStatus( 401, $response );
	}

	/**
	 * POST /resale/proxy with invalid API key returns 401.
	 */
	public function test_proxy_invalid_api_key_returns_401(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/resale/proxy',
			[
				'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ],
			],
			[ 'X-Resale-API-Key' => 'gaa_invalid_key_xyz' ]
		);
		$this->assertStatus( 401, $response );
	}

	/**
	 * POST /resale/proxy with disabled client returns 403.
	 */
	public function test_proxy_disabled_client_returns_403(): void {
		wp_set_current_user( 0 );

		$api_key   = 'gaa_' . wp_generate_password( 32, false );
		$client_id = $this->create_test_client( [
			'api_key' => $api_key,
			'enabled' => 0,
		] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/resale/proxy',
			[
				'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ],
			],
			[ 'X-Resale-API-Key' => $api_key ]
		);
		$this->assertStatus( 403, $response );
	}

	/**
	 * POST /resale/proxy with quota exceeded returns 429.
	 */
	public function test_proxy_quota_exceeded_returns_429(): void {
		wp_set_current_user( 0 );

		$api_key   = 'gaa_' . wp_generate_password( 32, false );
		$client_id = $this->create_test_client( [
			'api_key'             => $api_key,
			'enabled'             => 1,
			'monthly_token_quota' => 100,
		] );

		// Prime the quota: create_client() starts tokens_used_this_month at 0.
		// Use log_usage() to record 100 tokens so the quota is fully exhausted
		// before the proxy request, ensuring the controller returns 429.
		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 50, 50, 0.0, 'success', '', 100 );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/resale/proxy',
			[
				'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ],
			],
			[ 'X-Resale-API-Key' => $api_key ]
		);
		$this->assertStatus( 429, $response );
	}

	/**
	 * POST /resale/proxy with model not in allowed_models returns 403.
	 */
	public function test_proxy_model_not_allowed_returns_403(): void {
		wp_set_current_user( 0 );

		$api_key   = 'gaa_' . wp_generate_password( 32, false );
		$client_id = $this->create_test_client( [
			'api_key'        => $api_key,
			'enabled'        => 1,
			'allowed_models' => [ 'gpt-4o' ],
		] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/resale/proxy',
			[
				'model'    => 'claude-3-5-sonnet',
				'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ],
			],
			[ 'X-Resale-API-Key' => $api_key ]
		);
		$this->assertStatus( 403, $response );
	}

	/**
	 * POST /resale/proxy with no user message in messages array returns 400.
	 */
	public function test_proxy_no_user_message_returns_400(): void {
		wp_set_current_user( 0 );

		$api_key   = 'gaa_' . wp_generate_password( 32, false );
		$client_id = $this->create_test_client( [
			'api_key' => $api_key,
			'enabled' => 1,
		] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/resale/proxy',
			[
				'messages' => [ [ 'role' => 'system', 'content' => 'You are helpful.' ] ],
			],
			[ 'X-Resale-API-Key' => $api_key ]
		);
		$this->assertStatus( 400, $response );
	}
}
