<?php

declare(strict_types=1);
/**
 * Integration tests for WebhookController.
 *
 * Uses the WordPress REST API test infrastructure (WP_REST_Server) to dispatch
 * real HTTP-style requests through the registered routes. Coverage:
 *   - Route registration (admin CRUD + public trigger).
 *   - Admin permission checks (unauthenticated / subscriber / admin).
 *   - handle_list returns 200 with array; secrets are stripped.
 *   - handle_create requires name; returns 201 with secret + trigger_url.
 *   - handle_get returns 404 for unknown ID; 200 with trigger_url for known.
 *   - handle_update returns 404 for unknown ID; 400 with no fields; 200 on success.
 *   - handle_delete returns 404 for unknown ID; 200 on success.
 *   - handle_logs returns 404 for unknown ID; 200 with paginated structure.
 *   - handle_rotate_secret returns 404 for unknown ID; 200 with new secret.
 *   - handle_trigger returns 400 when webhook_id is missing.
 *   - handle_trigger returns 401 for unknown webhook_id (no info leak).
 *   - handle_trigger returns 401 when X-Webhook-Secret is wrong.
 *   - handle_trigger returns 403 when webhook is disabled.
 *   - handle_trigger returns 400 when no message and no prompt template.
 *   - handle_trigger returns 202 (async) when valid secret + message provided.
 *
 * @package GratisAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\REST;

use GratisAiAgent\REST\WebhookController;
use GratisAiAgent\REST\WebhookDatabase;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Integration tests for WebhookController.
 *
 * @group webhook
 * @group rest
 */
class WebhookControllerTest extends WP_UnitTestCase {

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
	 * Create a webhook directly via the database layer and return its ID and secret.
	 *
	 * @param array<string, mixed> $overrides Optional field overrides.
	 * @return array{id: int, secret: string}
	 */
	private function create_test_webhook( array $overrides = [] ): array {
		$secret = 'wh_' . wp_generate_password( 32, false );
		$data   = array_merge(
			[
				'name'    => 'Test Webhook ' . wp_generate_password( 6, false ),
				'secret'  => $secret,
				'enabled' => 1,
			],
			$overrides
		);

		$id = WebhookDatabase::create_webhook( $data );
		$this->assertNotFalse( $id, 'Failed to create test webhook.' );
		return [ 'id' => (int) $id, 'secret' => $data['secret'] ];
	}

	// ─── Route Registration ───────────────────────────────────────────────────

	/**
	 * All webhook routes are registered.
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$expected = [
			'/gratis-ai-agent/v1/webhook/trigger',
			'/gratis-ai-agent/v1/webhooks',
			'/gratis-ai-agent/v1/webhooks/(?P<id>\d+)',
			'/gratis-ai-agent/v1/webhooks/(?P<id>\d+)/logs',
			'/gratis-ai-agent/v1/webhooks/(?P<id>\d+)/rotate-secret',
		];

		foreach ( $expected as $route ) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} should be registered." );
		}
	}

	// ─── Permission checks ────────────────────────────────────────────────────

	/**
	 * Unauthenticated request to /webhooks is rejected with 401.
	 */
	public function test_webhooks_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks' );
		$this->assertStatus( 401, $response );
	}

	/**
	 * Subscriber (no manage_options) is rejected with 403.
	 */
	public function test_webhooks_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks' );
		$this->assertStatus( 403, $response );
	}

	/**
	 * check_admin_permission returns true for admin.
	 */
	public function test_check_admin_permission_returns_true_for_admin(): void {
		wp_set_current_user( $this->admin_id );
		$controller = new WebhookController();
		$this->assertTrue( $controller->check_admin_permission() );
	}

	/**
	 * check_admin_permission returns false for unauthenticated user.
	 */
	public function test_check_admin_permission_returns_false_for_guest(): void {
		wp_set_current_user( 0 );
		$controller = new WebhookController();
		$this->assertFalse( $controller->check_admin_permission() );
	}

	// ─── handle_list ──────────────────────────────────────────────────────────

	/**
	 * GET /webhooks returns 200 with an array.
	 */
	public function test_list_webhooks_returns_200(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * GET /webhooks does not expose secrets in the response.
	 */
	public function test_list_webhooks_does_not_expose_secret(): void {
		wp_set_current_user( $this->admin_id );

		$this->create_test_webhook();

		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks' );
		$webhooks = $response->get_data();

		$this->assertNotEmpty( $webhooks );
		foreach ( $webhooks as $webhook ) {
			$webhook_array = (array) $webhook;
			$this->assertArrayNotHasKey( 'secret', $webhook_array, 'secret must not be exposed in list response.' );
		}
	}

	// ─── handle_create ────────────────────────────────────────────────────────

	/**
	 * POST /webhooks requires name.
	 */
	public function test_create_webhook_requires_name(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhooks', [] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /webhooks with valid name returns 201 with secret and trigger_url.
	 */
	public function test_create_webhook_returns_201_with_secret(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhooks', [
			'name' => 'New Test Webhook',
		] );
		$this->assertStatus( 201, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertGreaterThan( 0, $data['id'] );
		// Secret is returned on creation only.
		$this->assertArrayHasKey( 'secret', $data );
		$this->assertNotEmpty( $data['secret'] );
		// trigger_url is included.
		$this->assertArrayHasKey( 'trigger_url', $data );
		$this->assertStringContainsString( 'webhook/trigger', $data['trigger_url'] );
	}

	/**
	 * POST /webhooks with all optional fields returns 201.
	 */
	public function test_create_webhook_with_all_fields(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhooks', [
			'name'               => 'Full Webhook',
			'description'        => 'A test webhook',
			'prompt_template'    => 'Process: {{message}}',
			'system_instruction' => 'You are a helper.',
			'max_iterations'     => 5,
			'enabled'            => true,
		] );
		$this->assertStatus( 201, $response );
	}

	/**
	 * Unauthenticated POST /webhooks is rejected.
	 */
	public function test_create_webhook_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhooks', [
			'name' => 'Unauth Webhook',
		] );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_get ───────────────────────────────────────────────────────────

	/**
	 * GET /webhooks/{id} returns 404 for unknown ID.
	 */
	public function test_get_webhook_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks/999999' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * GET /webhooks/{id} returns 200 with trigger_url for existing webhook.
	 */
	public function test_get_webhook_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook( [ 'name' => 'Get Test Webhook' ] );

		$response = $this->dispatch( 'GET', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}" );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertSame( $webhook['id'], $data['id'] );
		// Secret must not be exposed.
		$this->assertArrayNotHasKey( 'secret', $data );
		// trigger_url is included.
		$this->assertArrayHasKey( 'trigger_url', $data );
	}

	/**
	 * Unauthenticated GET /webhooks/{id} is rejected.
	 */
	public function test_get_webhook_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks/1' );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_update ────────────────────────────────────────────────────────

	/**
	 * PATCH /webhooks/{id} returns 404 for unknown ID.
	 */
	public function test_update_webhook_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'PATCH', '/gratis-ai-agent/v1/webhooks/999999' );
		$request->set_body( wp_json_encode( [ 'name' => 'Ghost' ] ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 404, $response );
	}

	/**
	 * PATCH /webhooks/{id} with no valid fields returns 400.
	 */
	public function test_update_webhook_no_fields_returns_400(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook();

		$request = new WP_REST_Request( 'PATCH', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}" );
		$request->set_body( wp_json_encode( [] ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 400, $response );
	}

	/**
	 * PATCH /webhooks/{id} with valid fields returns 200.
	 */
	public function test_update_webhook_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook( [ 'name' => 'Original Name' ] );

		$request = new WP_REST_Request( 'PATCH', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}" );
		$request->set_body( wp_json_encode( [ 'name' => 'Updated Name' ] ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Updated Name', $data['name'] );
		// Secret must not be exposed.
		$this->assertArrayNotHasKey( 'secret', $data );
	}

	// ─── handle_delete ────────────────────────────────────────────────────────

	/**
	 * DELETE /webhooks/{id} returns 404 for unknown ID.
	 */
	public function test_delete_webhook_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'DELETE', '/gratis-ai-agent/v1/webhooks/999999' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 404, $response );
	}

	/**
	 * DELETE /webhooks/{id} returns 200 for existing webhook.
	 */
	public function test_delete_webhook_returns_200(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook();

		$request  = new WP_REST_Request( 'DELETE', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertTrue( $data['deleted'] );
	}

	/**
	 * Unauthenticated DELETE /webhooks/{id} is rejected.
	 */
	public function test_delete_webhook_requires_auth(): void {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'DELETE', '/gratis-ai-agent/v1/webhooks/1' );
		$response = $this->server->dispatch( $request );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_logs ──────────────────────────────────────────────────────────

	/**
	 * GET /webhooks/{id}/logs returns 404 for unknown ID.
	 */
	public function test_logs_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks/999999/logs' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * GET /webhooks/{id}/logs returns 200 with paginated structure.
	 */
	public function test_logs_returns_200_with_structure(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook();

		$response = $this->dispatch( 'GET', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}/logs" );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'logs', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'limit', $data );
		$this->assertArrayHasKey( 'offset', $data );
		$this->assertIsArray( $data['logs'] );
	}

	/**
	 * GET /webhooks/{id}/logs returns logs after execution.
	 */
	public function test_logs_returns_execution_logs(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook();

		// Log an execution directly.
		WebhookDatabase::log_execution( $webhook['id'], 'success', 'Test reply', [], 10, 5, 100, '' );

		$response = $this->dispatch( 'GET', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}/logs" );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertGreaterThan( 0, $data['total'] );
		$this->assertNotEmpty( $data['logs'] );
	}

	/**
	 * Unauthenticated GET /webhooks/{id}/logs is rejected.
	 */
	public function test_logs_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/gratis-ai-agent/v1/webhooks/1/logs' );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_rotate_secret ─────────────────────────────────────────────────

	/**
	 * POST /webhooks/{id}/rotate-secret returns 404 for unknown ID.
	 */
	public function test_rotate_secret_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhooks/999999/rotate-secret', [] );
		$this->assertStatus( 404, $response );
	}

	/**
	 * POST /webhooks/{id}/rotate-secret returns new secret.
	 */
	public function test_rotate_secret_returns_new_secret(): void {
		wp_set_current_user( $this->admin_id );

		$webhook = $this->create_test_webhook();

		$response = $this->dispatch( 'POST', "/gratis-ai-agent/v1/webhooks/{$webhook['id']}/rotate-secret", [] );
		$this->assertStatus( 200, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'secret', $data );
		$this->assertNotEmpty( $data['secret'] );
		$this->assertSame( $webhook['id'], $data['id'] );
		// New secret must differ from the original.
		$this->assertNotSame( $webhook['secret'], $data['secret'] );
	}

	/**
	 * Unauthenticated POST /webhooks/{id}/rotate-secret is rejected.
	 */
	public function test_rotate_secret_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhooks/1/rotate-secret', [] );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_trigger ───────────────────────────────────────────────────────

	/**
	 * POST /webhook/trigger without webhook_id returns 400.
	 */
	public function test_trigger_missing_webhook_id_returns_400(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/gratis-ai-agent/v1/webhook/trigger', [
			'message' => 'Hello',
		] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /webhook/trigger with unknown webhook_id returns 401 (no info leak).
	 */
	public function test_trigger_unknown_webhook_id_returns_401(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/webhook/trigger',
			[
				'webhook_id' => 999999,
				'message'    => 'Hello',
			],
			[ 'X-Webhook-Secret' => 'any-secret' ]
		);
		$this->assertStatus( 401, $response );
	}

	/**
	 * POST /webhook/trigger with wrong secret returns 401.
	 */
	public function test_trigger_wrong_secret_returns_401(): void {
		wp_set_current_user( 0 );

		$webhook = $this->create_test_webhook( [ 'enabled' => 1 ] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/webhook/trigger',
			[
				'webhook_id' => $webhook['id'],
				'message'    => 'Hello',
			],
			[ 'X-Webhook-Secret' => 'wrong-secret-value' ]
		);
		$this->assertStatus( 401, $response );
	}

	/**
	 * POST /webhook/trigger with disabled webhook returns 403.
	 */
	public function test_trigger_disabled_webhook_returns_403(): void {
		wp_set_current_user( 0 );

		$webhook = $this->create_test_webhook( [ 'enabled' => 0 ] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/webhook/trigger',
			[
				'webhook_id' => $webhook['id'],
				'message'    => 'Hello',
			],
			[ 'X-Webhook-Secret' => $webhook['secret'] ]
		);
		$this->assertStatus( 403, $response );
	}

	/**
	 * POST /webhook/trigger with no message and no prompt template returns 400.
	 */
	public function test_trigger_no_message_no_template_returns_400(): void {
		wp_set_current_user( 0 );

		$webhook = $this->create_test_webhook( [
			'enabled'         => 1,
			'prompt_template' => '',
		] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/webhook/trigger',
			[
				'webhook_id' => $webhook['id'],
			],
			[ 'X-Webhook-Secret' => $webhook['secret'] ]
		);
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /webhook/trigger with valid secret and message returns 202 (async).
	 */
	public function test_trigger_valid_request_returns_202(): void {
		wp_set_current_user( 0 );

		$webhook = $this->create_test_webhook( [ 'enabled' => 1 ] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/webhook/trigger',
			[
				'webhook_id' => $webhook['id'],
				'message'    => 'Hello from test',
				'async'      => true,
			],
			[ 'X-Webhook-Secret' => $webhook['secret'] ]
		);
		$this->assertStatus( 202, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'job_id', $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 'processing', $data['status'] );
		$this->assertSame( $webhook['id'], $data['webhook_id'] );
	}

	/**
	 * POST /webhook/trigger with prompt template interpolates {{message}}.
	 *
	 * Verifies that the stored job message equals the rendered template
	 * (e.g. 'Summarise: some content') rather than the raw input, ensuring
	 * {{message}} placeholder substitution actually occurs.
	 */
	public function test_trigger_uses_prompt_template(): void {
		wp_set_current_user( 0 );

		$webhook = $this->create_test_webhook( [
			'enabled'         => 1,
			'prompt_template' => 'Summarise: {{message}}',
		] );

		$response = $this->dispatch(
			'POST',
			'/gratis-ai-agent/v1/webhook/trigger',
			[
				'webhook_id' => $webhook['id'],
				'message'    => 'some content',
				'async'      => true,
			],
			[ 'X-Webhook-Secret' => $webhook['secret'] ]
		);
		$this->assertStatus( 202, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'job_id', $data );
		$job = get_transient( WebhookController::JOB_PREFIX . $data['job_id'] );
		$this->assertIsArray( $job );
		$this->assertSame( 'Summarise: some content', $job['params']['message'] );
	}
}
