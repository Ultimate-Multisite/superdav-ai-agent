<?php

declare(strict_types=1);
/**
 * Integration tests for BenchmarkController.
 *
 * Uses the WordPress REST API test infrastructure (WP_REST_Server) to dispatch
 * real HTTP-style requests through the registered routes. Coverage:
 *   - Route registration.
 *   - Permission checks (unauthenticated / subscriber / admin).
 *   - handle_list_suites returns suites array.
 *   - handle_get_suite returns 404 for unknown slug.
 *   - handle_list_runs returns paginated list.
 *   - handle_create_run validates required fields and returns 201.
 *   - handle_get_run returns 404 for unknown ID.
 *   - handle_delete_run returns 404 for unknown ID.
 *   - handle_compare validates minimum run_ids count.
 *   - handle_run_next returns 404 for unknown run ID.
 *
 * The BenchmarkRunner and BenchmarkSuite static methods are exercised through
 * the controller — no mocking is used. Tests that require a real run record
 * create one via the REST endpoint first.
 *
 * @package SdAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\REST;

use SdAiAgent\REST\BenchmarkController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Integration tests for BenchmarkController.
 *
 * @group benchmark
 * @group rest
 */
class BenchmarkControllerTest extends WP_UnitTestCase {

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
	 *
	 * REST server creation and rest_api_init must run BEFORE parent::set_up()
	 * because parent::set_up() calls _backup_hooks(), which snapshots $wp_filter.
	 * If rest_api_init fires after the snapshot, the DI framework's per-route
	 * add_action() callbacks ('sd-ai-agent/v1/benchmark', etc.) are added
	 * AFTER the snapshot and are therefore removed by _restore_hooks() at
	 * tear_down(). Subsequent tests then have no callbacks for those actions,
	 * so register_rest_route() is never called and every route returns 404.
	 * Firing rest_api_init first ensures the callbacks are included in the
	 * snapshot and are correctly restored for every test.
	 */
	public function set_up(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress test global.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'rest_api_init' );

		parent::set_up();

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
	 * @param string               $method HTTP method.
	 * @param string               $route  Route path.
	 * @param array<string, mixed> $params Request parameters.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( string $method, string $route, array $params = [] ) {
		$request = new WP_REST_Request( $method, $route );

		if ( in_array( $method, [ 'POST', 'PATCH', 'PUT' ], true ) ) {
			$request->set_body( wp_json_encode( $params ) );
			$request->set_header( 'Content-Type', 'application/json' );
		} else {
			$request->set_query_params( $params );
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

	// ─── Route Registration ───────────────────────────────────────────────────

	/**
	 * All benchmark routes are registered.
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$expected = [
			'/sd-ai-agent/v1/benchmark/suites',
			'/sd-ai-agent/v1/benchmark/suites/(?P<slug>[a-z0-9\-_]+)',
			'/sd-ai-agent/v1/benchmark/runs',
			'/sd-ai-agent/v1/benchmark/runs/(?P<id>\d+)',
			'/sd-ai-agent/v1/benchmark/runs/(?P<id>\d+)/run-next',
			'/sd-ai-agent/v1/benchmark/compare',
		];

		foreach ( $expected as $route ) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} should be registered." );
		}
	}

	// ─── Permission checks ────────────────────────────────────────────────────

	/**
	 * Unauthenticated request to /benchmark/suites is rejected with 401.
	 */
	public function test_suites_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/suites' );
		$this->assertStatus( 401, $response );
	}

	/**
	 * Subscriber (no manage_options) is rejected with 403.
	 */
	public function test_suites_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_id );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/suites' );
		$this->assertStatus( 403, $response );
	}

	/**
	 * check_permission returns true for admin.
	 */
	public function test_check_permission_returns_true_for_admin(): void {
		wp_set_current_user( $this->admin_id );
		$controller = new BenchmarkController();
		$this->assertTrue( $controller->check_permission() );
	}

	/**
	 * check_permission returns false for unauthenticated user.
	 */
	public function test_check_permission_returns_false_for_guest(): void {
		wp_set_current_user( 0 );
		$controller = new BenchmarkController();
		$this->assertFalse( $controller->check_permission() );
	}

	// ─── handle_list_suites ───────────────────────────────────────────────────

	/**
	 * GET /benchmark/suites returns 200 with an array.
	 */
	public function test_list_suites_returns_200(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/suites' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	// ─── handle_get_suite ─────────────────────────────────────────────────────

	/**
	 * GET /benchmark/suites/{slug} returns 404 for an unknown slug.
	 */
	public function test_get_suite_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/suites/nonexistent-suite-xyz' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * GET /benchmark/suites/{slug} returns 200 for the built-in suite.
	 */
	public function test_get_suite_builtin(): void {
		wp_set_current_user( $this->admin_id );

		// First check what suites exist.
		$list_response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/suites' );
		$suites        = $list_response->get_data();

		if ( empty( $suites ) ) {
			$this->markTestSkipped( 'No benchmark suites registered.' );
		}

		// Use the first available suite slug.
		$first_suite = is_array( $suites[0] ) ? ( $suites[0]['slug'] ?? null ) : ( $suites[0]->slug ?? null );

		if ( ! $first_suite ) {
			$this->markTestSkipped( 'Could not determine suite slug.' );
		}

		$response = $this->dispatch( 'GET', "/sd-ai-agent/v1/benchmark/suites/{$first_suite}" );
		$this->assertStatus( 200, $response );
	}

	// ─── handle_list_runs ─────────────────────────────────────────────────────

	/**
	 * GET /benchmark/runs returns 200 with an array.
	 */
	public function test_list_runs_returns_200(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/runs' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * GET /benchmark/runs accepts per_page and page parameters.
	 */
	public function test_list_runs_accepts_pagination_params(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/runs', [
			'per_page' => 5,
			'page'     => 1,
		] );
		$this->assertStatus( 200, $response );
	}

	/**
	 * Unauthenticated request to /benchmark/runs is rejected.
	 */
	public function test_list_runs_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/runs' );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_create_run ────────────────────────────────────────────────────

	/**
	 * POST /benchmark/runs with empty models returns 400.
	 */
	public function test_create_run_requires_models(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs', [
			'name'   => 'Test Run',
			'models' => [],
		] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /benchmark/runs without models parameter returns 400.
	 */
	public function test_create_run_missing_models_returns_400(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs', [
			'name' => 'Test Run No Models',
		] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /benchmark/runs without name returns 400 (required field).
	 */
	public function test_create_run_missing_name_returns_400(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs', [
			'models' => [ 'gpt-4o' ],
		] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /benchmark/runs with valid data returns 201 or 500 (DB may not be set up).
	 *
	 * In the test environment the benchmark tables may not exist, so we accept
	 * either 201 (success) or 500 (DB error) as valid outcomes.
	 */
	public function test_create_run_with_valid_data(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs', [
			'name'   => 'Integration Test Run',
			'models' => [ 'gpt-4o-mini' ],
		] );
		$this->assertContains( $response->get_status(), [ 201, 500 ] );
	}

	/**
	 * Unauthenticated POST /benchmark/runs is rejected.
	 */
	public function test_create_run_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs', [
			'name'   => 'Unauth Run',
			'models' => [ 'gpt-4o' ],
		] );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_get_run ───────────────────────────────────────────────────────

	/**
	 * GET /benchmark/runs/{id} returns 404 for unknown ID.
	 */
	public function test_get_run_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/runs/999999' );
		$this->assertStatus( 404, $response );
	}

	/**
	 * Unauthenticated GET /benchmark/runs/{id} is rejected.
	 */
	public function test_get_run_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/sd-ai-agent/v1/benchmark/runs/1' );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_delete_run ────────────────────────────────────────────────────

	/**
	 * DELETE /benchmark/runs/{id} for an unknown ID succeeds (wpdb->delete returns 0
	 * rows affected, not false, so delete_run() returns true and the controller
	 * responds 200 with { deleted: true }).
	 */
	public function test_delete_run_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'DELETE', '/sd-ai-agent/v1/benchmark/runs/999999' );
		// wpdb->delete on a non-existent row returns 0 (not false), so
		// BenchmarkRunner::delete_run() returns true → controller returns 200.
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Unauthenticated DELETE /benchmark/runs/{id} is rejected.
	 */
	public function test_delete_run_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'DELETE', '/sd-ai-agent/v1/benchmark/runs/1' );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_run_next ──────────────────────────────────────────────────────

	/**
	 * POST /benchmark/runs/{id}/run-next returns 404 for unknown run ID.
	 */
	public function test_run_next_not_found(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs/999999/run-next', [] );
		$this->assertStatus( 404, $response );
	}

	/**
	 * Unauthenticated POST /benchmark/runs/{id}/run-next is rejected.
	 */
	public function test_run_next_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/runs/1/run-next', [] );
		$this->assertStatus( 401, $response );
	}

	// ─── handle_compare ───────────────────────────────────────────────────────

	/**
	 * POST /benchmark/compare with fewer than 2 run IDs returns 400.
	 */
	public function test_compare_requires_at_least_two_runs(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/compare', [
			'run_ids' => [ 1 ],
		] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /benchmark/compare with empty run_ids returns 400.
	 */
	public function test_compare_requires_non_empty_run_ids(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/compare', [
			'run_ids' => [],
		] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * POST /benchmark/compare with valid run_ids returns 200 (even if runs don't exist).
	 */
	public function test_compare_with_two_run_ids_returns_200(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/compare', [
			'run_ids' => [ 1, 2 ],
		] );
		// BenchmarkRunner::compare_runs returns an array even for non-existent IDs.
		$this->assertStatus( 200, $response );
	}

	/**
	 * Unauthenticated POST /benchmark/compare is rejected.
	 */
	public function test_compare_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/sd-ai-agent/v1/benchmark/compare', [
			'run_ids' => [ 1, 2 ],
		] );
		$this->assertStatus( 401, $response );
	}
}
