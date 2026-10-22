<?php

declare(strict_types=1);
/**
 * Integration tests for session-scoped durable plan REST endpoints.
 *
 * @package SdAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\REST;

use SdAiAgent\Core\BackgroundJobDispatcher;
use SdAiAgent\Core\ActiveJobFailureDiagnostic;
use SdAiAgent\Bootstrap\AutomationsHandler;
use SdAiAgent\Core\Database;
use SdAiAgent\Core\DurablePlanRunner;
use SdAiAgent\Core\ElementorCompletionGate;
use SdAiAgent\Bootstrap\SuperdavAiProviderHandler;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiProvider;
use SdAiAgent\Models\ActiveJobRepository;
use SdAiAgent\Models\DurablePlanRepository;
use SdAiAgent\REST\RestController;
use SdAiAgent\REST\SessionController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

class SessionControllerTest extends WP_UnitTestCase {

	protected WP_REST_Server $server;

	private int $admin_id;

	private int $other_admin_id;

	/** Jobs retain one durable action and receive an immediate kick on their own site. */
	public function test_background_dispatcher_queues_job_on_target_site_once(): void {
		$originating_blog_id    = get_current_blog_id();
		$tenant_id              = is_multisite() ? self::factory()->blog->create() : $originating_blog_id;
		$job_id                 = '11111111-2222-3333-4444-555555555555';
		$args                   = array( $tenant_id, $job_id );
		$requests               = array();
		$has_action_scheduler   = function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' );
		$switched_to_tenant     = false;
		$intercept = static function ( $preempt, $request, $url ) use ( &$requests ) {
			$requests[] = array( 'url' => $url, 'request' => $request );
			return new \WP_Error( 'http_request_failed', 'Loopbacks blocked for this test.' );
		};
		add_filter( 'pre_http_request', $intercept, 10, 3 );

		try {
			if ( is_multisite() ) {
				switch_to_blog( $tenant_id );
				$switched_to_tenant = true;
			}

			BackgroundJobDispatcher::dispatch( $job_id, 'test-token' );
			BackgroundJobDispatcher::dispatch( $job_id, 'test-token' );
			$this->assertCount( 2, $requests, 'Each dispatch must kick immediately even when a durable action already exists.' );
			foreach ( $requests as $request ) {
				$this->assertSame( rest_url( RestController::NAMESPACE . '/process' ), $request['url'] );
				$this->assertFalse( $request['request']['blocking'] );
				$this->assertSame( 0.01, $request['request']['timeout'] );
				$this->assertSame( array( 'job_id' => $job_id, 'token' => 'test-token' ), json_decode( $request['request']['body'], true ) );
			}
			$this->assertSame( $tenant_id, get_current_blog_id() );
			$this->assertTrue(
				( $has_action_scheduler && as_has_scheduled_action( BackgroundJobDispatcher::HOOK, $args, 'sd-ai-agent' ) )
				|| false !== wp_next_scheduled( BackgroundJobDispatcher::HOOK, $args )
			);
		} finally {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( BackgroundJobDispatcher::HOOK, $args, 'sd-ai-agent' );
			}
			wp_clear_scheduled_hook( BackgroundJobDispatcher::HOOK, $args );
			if ( $switched_to_tenant ) {
				restore_current_blog();
			}
			remove_filter( 'pre_http_request', $intercept );
		}

		$this->assertSame( $originating_blog_id, get_current_blog_id() );
	}

	/** @return array<string, array{bool}> */
	public static function background_job_starters(): array {
		return array(
			'immediate loopback wins' => array( false ),
			'blocked loopback falls back to the queue' => array( true ),
		);
	}

	/**
	 * Both dispatch paths share one claim, including an overlapping queue delivery.
	 *
	 * @dataProvider background_job_starters
	 */
	public function test_background_job_runs_once_across_loopback_and_queue( bool $block_loopback ): void {
		$session_id = $this->create_session();
		$original   = array();
		for ( $i = 0; $i < 264; ++$i ) {
			$original[] = array( 'role' => 'user', 'parts' => array( array( 'text' => "Saved prompt {$i}." ) ) );
			$original[] = array( 'role' => 'model', 'parts' => array( array( 'text' => "Saved answer {$i}." ) ) );
		}
		Database::append_to_session( $session_id, $original );
		update_option( SuperdavAiProvider::CREDENTIAL_OPTION, 'test-key' );
		( new SuperdavAiProviderHandler() )->register_provider();
		$base_url = static fn () => 'http://fake-ai-proxy.test';
		add_filter( 'sd_ai_agent_cloud_base_url', $base_url );
		add_filter( 'sd_ai_agent_openai_tool_search_enabled', '__return_false' );
		$provider_calls = 0;
		$loopbacks      = 0;
		$job_id         = '';
		$process        = null;
		$blog_id        = get_current_blog_id();
		$controller     = new SessionController( new Database() );
		$handler        = new AutomationsHandler();
		$queue_callback = array( $handler, 'process_background_job' );
		if ( ! has_action( BackgroundJobDispatcher::HOOK ) ) {
			add_action( BackgroundJobDispatcher::HOOK, $queue_callback, 10, 2 );
		}
		$intercept = function ( $preempt, $args, $url ) use ( $block_loopback, $controller, $blog_id, &$provider_calls, &$loopbacks, &$job_id, &$process ) {
			if ( rest_url( RestController::NAMESPACE . '/process' ) === $url ) {
				++$loopbacks;
				$body    = json_decode( $args['body'], true );
				$job_id  = $body['job_id'];
				$process = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/process' );
				$process->set_body_params( $body );
				$this->assertSame( 'queued', ActiveJobRepository::get_by_job_id( $job_id )->status );
				if ( $block_loopback ) {
					return new \WP_Error( 'http_request_failed', 'Loopbacks blocked for this test.' );
				}
				$this->assertTrue( $controller->check_process_permission( $process ) );
				$controller->handle_process( $process );
			} elseif ( str_contains( $url, 'fake-ai-proxy.test' ) && str_ends_with( $url, '/models' ) ) {
				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode( array( 'data' => array( array( 'id' => SuperdavAiProvider::FAST_MODEL_ID ) ) ) ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
				);
			} elseif ( str_contains( $url, 'fake-ai-proxy.test' ) && str_ends_with( $url, '/chat/completions' ) ) {
				++$provider_calls;
				$envelope = json_decode( $args['body'], true );
				$this->assertLessThan( 528, count( $envelope['messages'] ), 'Provider context must still be trimmed.' );
				$this->assertStringContainsString( 'Reply once.', $args['body'] );
				$this->assertSame( 'processing', ActiveJobRepository::get_by_job_id( $job_id )->status );
				// Deliver the safety-net action while AgentLoop is still running.
				do_action( BackgroundJobDispatcher::HOOK, $blog_id, $job_id );
				$this->assertFalse( $controller->handle_process( $process )->get_data()['ok'] );
				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode(
						array(
							'id'      => 'chatcmpl-background-job',
							'object'  => 'chat.completion',
							'choices' => array( array( 'index' => 0, 'message' => array( 'role' => 'assistant', 'content' => 'One completed turn.' ), 'finish_reason' => 'stop' ) ),
							'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15 ),
						)
					),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
				);
			}
			return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
		};
		add_filter( 'pre_http_request', $intercept, 10, 3 );
		try {
			$run = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/run' );
			$run->set_body_params( array( 'session_id' => $session_id, 'message' => 'Reply once.', 'provider_id' => SuperdavAiProvider::PROVIDER_ID, 'model_id' => SuperdavAiProvider::FAST_MODEL_ID, 'max_iterations' => 1 ) );
			$response = $controller->handle_run( $run );
			$this->assertInstanceOf( \WP_REST_Response::class, $response );
			$this->assertSame( 202, $response->get_status() );
			if ( $block_loopback ) {
				$this->assertSame( 'queued', ActiveJobRepository::get_by_job_id( $job_id )->status );
				do_action( BackgroundJobDispatcher::HOOK, $blog_id, $job_id );
			}
			do_action( BackgroundJobDispatcher::HOOK, $blog_id, $job_id );
			$this->assertFalse( $controller->handle_process( $process )->get_data()['ok'] );
			$this->assertSame( 1, $loopbacks );
			$this->assertSame( 1, $provider_calls, 'AgentLoop must make exactly one provider turn across all starters.' );
			$this->assertSame( 'complete', ActiveJobRepository::get_by_job_id( $job_id )->status );
			$session = Database::get_session( $session_id );
			$saved   = json_decode( $session->messages, true );
			$this->assertCount( 530, $saved );
			$this->assertSame( $original, array_slice( $saved, 0, 528 ) );
			$this->assertSame( 'Reply once.', $saved[528]['parts'][0]['text'] );
			$this->assertSame( 'One completed turn.', $saved[529]['parts'][0]['text'] );
			$this->assertSame( $job_id, $saved[529]['completion_job_id'] );
			$this->assertSame( SuperdavAiProvider::FAST_MODEL_ID, $saved[529]['model_id'] );
			$this->assertSame( 10, (int) $session->prompt_tokens );
			$this->assertSame( 5, (int) $session->completion_tokens );
		} finally {
			remove_filter( 'pre_http_request', $intercept, 10 );
			remove_action( BackgroundJobDispatcher::HOOK, $queue_callback, 10 );
			delete_transient( RestController::JOB_PREFIX . $job_id );
			ActiveJobRepository::delete( $job_id );
			wp_clear_scheduled_hook( BackgroundJobDispatcher::HOOK, array( $blog_id, $job_id ) );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( BackgroundJobDispatcher::HOOK, array( $blog_id, $job_id ), 'sd-ai-agent' );
			}
			remove_filter( 'sd_ai_agent_cloud_base_url', $base_url );
			remove_filter( 'sd_ai_agent_openai_tool_search_enabled', '__return_false' );
			delete_option( SuperdavAiProvider::CREDENTIAL_OPTION );
		}
	}

	/**
	 * A failed required claim row must not return a permanently unstartable job.
	 *
	 * @dataProvider background_job_starters
	 */
	public function test_background_job_insert_failure_does_not_dispatch_or_lose_recovery( bool $recoverable ): void {
		global $wpdb;
		$session_id = $this->create_session();
		$state      = array( 'history' => array( array( 'role' => 'user', 'parts' => array( array( 'text' => 'Retry this turn.' ) ) ) ) );
		if ( $recoverable ) {
			Database::save_paused_state( $session_id, $state );
		}
		$requests = 0;
		$fail_insert = static function ( $query ) {
			return str_starts_with( $query, 'INSERT INTO `' . ActiveJobRepository::table_name() . '`' )
				? 'INSERT INTO sd_ai_agent_missing_test_table (id) VALUES (1)'
				: $query;
		};
		$intercept = static function () use ( &$requests ) {
			++$requests;
			return new \WP_Error( 'unexpected_dispatch', 'No job should be dispatched.' );
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_insert );
		add_filter( 'pre_http_request', $intercept );
		try {
			$request = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/run' );
			$request->set_body_params( array( 'id' => $session_id, 'session_id' => $session_id, 'message' => 'Retry this turn.' ) );
			$controller = new SessionController( new Database() );
			$response   = $recoverable ? $controller->handle_resume_recoverable_job( $request ) : $controller->handle_run( $request );
			$this->assertWPError( $response );
			$this->assertSame( 'sd_ai_agent_job_enqueue_failed', $response->get_error_code() );
			$this->assertSame( 0, $requests );
			if ( $recoverable ) {
				$this->assertSame( $state, Database::load_and_clear_paused_state( $session_id ) );
			}
		} finally {
			remove_filter( 'query', $fail_insert );
			remove_filter( 'pre_http_request', $intercept );
			$wpdb->suppress_errors( $previous );
		}
	}

	/** A shortened 522-row result must not be sliced at the old 528-row count. */
	public function test_shorter_completed_history_and_accounting_are_idempotent(): void {
		global $wpdb;
		$this->assertNotFalse( $wpdb->query( 'SAVEPOINT sd_ai_agent_outer_completion_test' ) );
		$session_id = $this->create_session();
		$original   = array();
		for ( $i = 0; $i < 528; ++$i ) {
			$original[] = array( 'role' => 0 === $i % 2 ? 'user' : 'model', 'parts' => array( array( 'text' => "Saved row {$i}." ) ) );
		}
		$old_tool = array( 'type' => 'result', 'id' => 'saved-tool', 'sequence' => 1, 'name' => 'saved-tool', 'result' => array( 'done' => true ) );
		$new_tool = array( 'type' => 'result', 'id' => 'followup-tool', 'sequence' => 2, 'name' => 'followup-tool', 'result' => array( 'done' => true ) );
		Database::append_to_session( $session_id, $original, array( $old_tool ) );
		$history   = array_slice( $original, 8 );
		// A repeated chip may have the exact text of a previously saved prompt.
		$history[] = $original[0];
		$history[] = array( 'role' => 'model', 'parts' => array( array( 'text' => 'New answer and new suggestions.' ) ) );
		$this->assertCount( 522, $history );
		$result = array( 'reply' => 'New answer and new suggestions.', 'history' => $history, 'tool_calls' => array( $old_tool, $new_tool ), 'token_usage' => array( 'prompt' => 120, 'completion' => 8 ) );
		$controller = new SessionController( new Database() );
		$this->assertTrue( $controller->persist_completed_turn( $session_id, 'short-history-job', $result, 'test-provider', 'test-model' ) );
		$this->assertTrue( $controller->persist_completed_turn( $session_id, 'short-history-job', $result, 'test-provider', 'test-model' ) );
		$session = Database::get_session( $session_id );
		$saved   = json_decode( $session->messages, true );
		$this->assertCount( 530, $saved );
		$this->assertSame( $original, array_slice( $saved, 0, 528 ) );
		$this->assertSame( 'Saved row 0.', $saved[528]['parts'][0]['text'] );
		$this->assertSame( 'New answer and new suggestions.', $saved[529]['parts'][0]['text'] );
		$this->assertSame( array( $old_tool, $new_tool ), json_decode( $session->tool_calls, true ) );
		$this->assertSame( 120, (int) $session->prompt_tokens );
		$this->assertSame( 8, (int) $session->completion_tokens );
		$usage = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE session_id = %d', Database::usage_table_name(), $session_id ), ARRAY_A );
		$this->assertCount( 1, $usage );
		$this->assertSame( 120, (int) $usage[0]['prompt_tokens'] );
		$this->assertSame( 8, (int) $usage[0]['completion_tokens'] );
		$this->assertNotFalse( $wpdb->query( 'ROLLBACK TO SAVEPOINT sd_ai_agent_outer_completion_test' ) );
		$this->assertNull( Database::get_session( $session_id ), 'Completion must not commit caller-owned writes.' );
		$this->assertNotFalse( $wpdb->query( 'RELEASE SAVEPOINT sd_ai_agent_outer_completion_test' ) );
	}

	/** Any failed transcript/counter/ledger write rolls back the entire receipt. */
	public function test_completed_turn_usage_failure_does_not_publish_partial_success(): void {
		global $wpdb;
		$session_id = $this->create_session();
		$result     = array(
			'reply' => 'New answer.',
			'history' => array(
				array( 'role' => 'user', 'parts' => array( array( 'text' => 'New prompt.' ) ) ),
				array( 'role' => 'model', 'parts' => array( array( 'text' => 'New answer.' ) ) ),
			),
			'token_usage' => array( 'prompt' => 12, 'completion' => 3 ),
		);
		$fail_usage = static function ( $query ) {
			return str_starts_with( $query, 'INSERT INTO `' . Database::usage_table_name() . '`' )
				? 'INSERT INTO sd_ai_agent_missing_test_table (id) VALUES (1)' : $query;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_usage );
		try {
			$error = ( new SessionController( new Database() ) )->persist_completed_turn( $session_id, 'failed-save-job', $result, 'test-provider', 'test-model' );
			$this->assertWPError( $error );
			$this->assertSame( 'sd_ai_agent_turn_persistence_failed', $error->get_error_code() );
			$session = Database::get_session( $session_id );
			$this->assertSame( array(), json_decode( $session->messages, true ) );
			$this->assertSame( 0, (int) $session->prompt_tokens );
			$this->assertSame( 0, (int) $session->completion_tokens );
		} finally {
			remove_filter( 'query', $fail_usage );
			$wpdb->suppress_errors( $previous );
		}
		$this->assertTrue( ( new SessionController( new Database() ) )->persist_completed_turn( $session_id, 'failed-save-job', $result, 'test-provider', 'test-model' ) );
	}

	public function set_up(): void {
		// REST registration must happen before parent::set_up() snapshots hooks.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress test global.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress REST action.
		do_action( 'rest_api_init' );

		parent::set_up();
		Database::install();
		$this->admin_id       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->other_admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );

		global $wpdb;
		/** @var \wpdb $wpdb */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup for custom plan tables.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', DurablePlanRepository::steps_table_name() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup for custom plan tables.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', DurablePlanRepository::table_name() ) );
	}

	public function tear_down(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress test global.
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/** Completed mixed-model turns keep their own immutable model attribution. */
	public function test_turn_model_metadata_is_added_without_overwriting_existing_attribution(): void {
		$method = new \ReflectionMethod(
			\SdAiAgent\REST\SessionController::class,
			'add_turn_model_metadata'
		);
		$method->setAccessible( true );
		$messages = [
			[
				'role'  => 'user',
				'parts' => [ [ 'text' => 'First turn.' ] ],
			],
			[
				'role'        => 'model',
				'provider_id' => 'existing-provider',
				'model_id'    => 'existing-model',
				'parts'       => [ [ 'text' => 'Existing reply.' ] ],
			],
			[
				'role'  => 'system',
				'parts' => [ [ 'text' => 'System notice.' ] ],
			],
		];

		/** @var array<int, array<string, mixed>> $annotated */
		$annotated = $method->invoke( null, $messages, 'superdav', 'superdav-chat-fast' );

		$this->assertSame( 'superdav', $annotated[0]['provider_id'] );
		$this->assertSame( 'superdav-chat-fast', $annotated[0]['model_id'] );
		$this->assertSame( 'existing-provider', $annotated[1]['provider_id'] );
		$this->assertSame( 'existing-model', $annotated[1]['model_id'] );
		$this->assertArrayNotHasKey( 'model_id', $annotated[2] );
	}

	/** Queued user steering reaches AgentLoop once and in FIFO order. */
	public function test_job_interrupt_checker_consumes_queued_messages_in_order(): void {
		$job_id = '00000000-0000-4000-8000-000000000199';
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			[
				'status'     => 'processing',
				'user_id'    => $this->admin_id,
				'interrupts' => [
					[ 'message' => 'Stop inspecting and mutate.', 'timestamp' => 1 ],
					[ 'message' => 'Then validate the preview.', 'timestamp' => 2 ],
				],
			],
			RestController::JOB_TTL
		);

		$method = new \ReflectionMethod(
			\SdAiAgent\REST\SessionController::class,
			'build_job_interrupt_checker'
		);
		$method->setAccessible( true );
		/** @var \Closure(): ?array $checker */
		$checker = $method->invoke( null, $job_id );

		$first = $checker();
		$this->assertIsArray( $first );
		$this->assertSame( 'Stop inspecting and mutate.', $first['message'] );
		$second = $checker();
		$this->assertIsArray( $second );
		$this->assertSame( 'Then validate the preview.', $second['message'] );
		$this->assertNull( $checker() );

		$job = get_transient( RestController::JOB_PREFIX . $job_id );
		$this->assertIsArray( $job );
		$this->assertSame( [], $job['interrupts'] );
		delete_transient( RestController::JOB_PREFIX . $job_id );
	}

	/** Durable-plan routes remain private session endpoints. */
	public function test_durable_plan_routes_are_registered(): void {
		$routes = $this->server->get_routes();
		foreach (
			[
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/status',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/continue',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/approve',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/reject',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/retry',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/cancel',
				'/sd-ai-agent/v1/sessions/(?P<id>\d+)/plan/scope',
			] as $route
		) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} should be registered." );
		}
	}

	/** A plan remains outside session messages and a pending scope blocks continue. */
	public function test_create_status_and_scope_approval_are_session_bound(): void {
		$session_id = $this->create_session();
		$created    = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan",
			$this->plan_definition()
		);
		$this->assert_status( 201, $created );
		$plan = $created->get_data()['plan'];
		$this->assertSame( 'pending', $plan['status'] );
		$this->assertCount( 2, $plan['steps'] );
		$this->assertArrayNotHasKey( 'idempotency_key', $plan['steps'][0] );

		$session = Database::get_session( $session_id );
		$this->assertNotNull( $session );
		$this->assertSame( '[]', $session->messages );

		$status = $this->dispatch( 'GET', "/sd-ai-agent/v1/sessions/{$session_id}/plan" );
		$this->assert_status( 200, $status );
		$this->assertSame( $plan['plan_id'], $status->get_data()['plan']['plan_id'] );

		$scope = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan/scope",
			[
				'plan_id' => $plan['plan_id'],
				'scope'   => 'Also update the production footer.',
			]
		);
		$this->assert_status( 200, $scope );
		$this->assertSame( 'awaiting_approval', $scope->get_data()['status'] );

		$continue = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan/continue",
			[ 'plan_id' => $plan['plan_id'] ]
		);
		$this->assert_status( 200, $continue );
		$this->assertSame( 'awaiting_approval', $continue->get_data()['status'] );
		$this->assertSame( 'pending', $continue->get_data()['plan']['steps'][0]['status'] );

		$approved = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan/approve",
			[
				'plan_id'             => $plan['plan_id'],
				'approval_request_id' => $scope->get_data()['plan']['approval_request_id'],
			]
		);
		$this->assert_status( 200, $approved );
		$this->assertSame( 'pending', $approved->get_data()['status'] );
		$this->assertSame( 'Also update the production footer.', $approved->get_data()['plan']['scope'] );
	}

	/** Shared-session permissions do not allow another administrator to create a plan. */
	public function test_other_administrator_cannot_create_plan_for_session_owner(): void {
		$session_id = $this->create_session();
		wp_set_current_user( $this->other_admin_id );

		$response = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan",
			$this->plan_definition()
		);

		$this->assert_status( 403, $response );
	}

	/** Shared-session access never grants another administrator plan mutation rights. */
	public function test_shared_administrator_cannot_mutate_durable_plan(): void {
		$session_id = $this->create_session();
		$created    = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan",
			$this->plan_definition()
		);
		$this->assert_status( 201, $created );
		$plan_id = (string) $created->get_data()['plan']['plan_id'];
		$this->assertTrue( Database::share_session( $session_id, $this->admin_id ) );

		wp_set_current_user( $this->other_admin_id );
		$requests = [
			[ 'plan', $this->plan_definition() ],
			[ 'plan/continue', [ 'plan_id' => $plan_id ] ],
			[ 'plan/approve', [ 'plan_id' => $plan_id, 'approval_request_id' => 1 ] ],
			[ 'plan/reject', [ 'plan_id' => $plan_id, 'approval_request_id' => 1 ] ],
			[ 'plan/retry', [ 'plan_id' => $plan_id ] ],
			[ 'plan/cancel', [ 'plan_id' => $plan_id ] ],
			[ 'plan/scope', [ 'plan_id' => $plan_id, 'scope' => 'Change the approved scope.' ] ],
		];

		foreach ( $requests as [ $suffix, $params ] ) {
			$response = $this->dispatch( 'POST', "/sd-ai-agent/v1/sessions/{$session_id}/{$suffix}", $params );
			$this->assert_status( 403, $response );
		}
	}

	/** Browser-supplied phase metadata cannot disable durable approval safeguards. */
	public function test_client_plan_metadata_cannot_disable_approval_or_safe_resume(): void {
		$session_id = $this->create_session();
		$definition = $this->plan_definition();
		$definition['steps'][0]['classification']      = 'read';
		$definition['steps'][0]['idempotency_key']     = 'browser-controlled-key';
		$definition['steps'][0]['requires_approval']   = false;
		$definition['steps'][0]['safe_to_resume']      = true;

		$created = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan",
			$definition
		);
		$this->assert_status( 201, $created );
		$plan = $created->get_data()['plan'];

		$stored = DurablePlanRepository::get_by_plan_id( (string) $plan['plan_id'] );
		$this->assertIsArray( $stored );
		$this->assertSame( 1, $stored['steps'][0]['requires_approval'] );
		$this->assertSame( 0, $stored['steps'][0]['safe_to_resume'] );
		$this->assertNotSame( 'browser-controlled-key', $stored['steps'][0]['idempotency_key'] );

		$continue = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan/continue",
			[ 'plan_id' => $plan['plan_id'] ]
		);
		$this->assert_status( 200, $continue );
		$this->assertSame( 'awaiting_approval', $continue->get_data()['status'] );
	}

	/** Planning jobs are session-owner-only and carry the server-recognised planning flag. */
	public function test_durable_plan_run_is_session_owner_only_and_marks_the_job(): void {
		$session_id     = $this->create_session();
		$block_loopback = static function () {
			return [
				'headers'  => [],
				'body'     => '',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $block_loopback, 10, 3 );
		try {
			$response = $this->dispatch(
				'POST',
				'/sd-ai-agent/v1/run',
				[
					'message'      => 'Prepare a phased plan to review site navigation.',
					'session_id'   => $session_id,
					'durable_plan' => true,
					'attachments'  => [
						[
							'name'     => 'planner-attachment.txt',
							'type'     => 'text/plain',
							'data_url' => 'data:text/plain;base64,cGxhbm5lciBhdHRhY2htZW50',
							'is_image' => false,
						],
					],
				]
			);
		} finally {
			remove_filter( 'pre_http_request', $block_loopback, 10 );
		}

		$this->assert_status( 202, $response );
		$job_id = (string) $response->get_data()['job_id'];
		$job    = get_transient( RestController::JOB_PREFIX . $job_id );
		$this->assertIsArray( $job );
		$this->assertTrue( $job['params']['durable_plan'] );
		$this->assertSame( [], $job['params']['attachments'] );
		$active_job = ActiveJobRepository::get_by_job_id( $job_id );
		$this->assertNotNull( $active_job );
		$this->assertSame( 'queued', $active_job->status );

		delete_transient( RestController::JOB_PREFIX . $job_id );
		ActiveJobRepository::delete( $job_id );

		wp_set_current_user( $this->other_admin_id );
		$forbidden = $this->dispatch(
			'POST',
			'/sd-ai-agent/v1/run',
			[
				'message'      => 'Prepare a phased plan to review site navigation.',
				'session_id'   => $session_id,
				'durable_plan' => true,
			]
		);
		$this->assert_status( 403, $forbidden );
	}

	/** CSV attachments reach the agent as bounded context without retaining their data URL. */
	public function test_csv_attachment_is_available_to_the_agent_without_raw_data_url(): void {
		$session_id     = $this->create_session();
		$block_loopback = static function () {
			return [
				'headers'  => [],
				'body'     => '',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $block_loopback, 10, 3 );
		try {
			$response = $this->dispatch(
				'POST',
				'/sd-ai-agent/v1/run',
				[
					'message'      => 'Update product categories from the attached CSV.',
					'session_id'   => $session_id,
					'attachments'  => [
						[
							'name'     => 'product-categories.csv',
							'type'     => 'text/csv',
							'data_url' => 'data:text/csv;base64,c2t1LGNhdGVnb3J5ClAxLFNob2Vz',
							'is_image' => false,
						],
					],
				]
			);
		} finally {
			remove_filter( 'pre_http_request', $block_loopback, 10 );
		}

		$this->assert_status( 202, $response );
		$job_id = (string) $response->get_data()['job_id'];
		$job    = get_transient( RestController::JOB_PREFIX . $job_id );
		$this->assertIsArray( $job );
		$this->assertArrayNotHasKey( 'data_url', $job['params']['attachments'][0] );
		$this->assertStringContainsString(
			"sku,category\nP1,Shoes",
			RestController::get_agent_attachment_context( $job['params']['attachments'] )
		);

		delete_transient( RestController::JOB_PREFIX . $job_id );
		ActiveJobRepository::delete( $job_id );
	}

	/** A durable phase remains queued in storage until its worker claims it. */
	public function test_durable_plan_continue_queues_the_worker_until_process_claims_it(): void {
		$session_id = $this->create_session();
		$created    = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan",
			$this->plan_definition()
		);
		$this->assert_status( 201, $created );
		$plan_id = (string) $created->get_data()['plan']['plan_id'];
		$awaiting = $this->dispatch(
			'POST',
			"/sd-ai-agent/v1/sessions/{$session_id}/plan/continue",
			[ 'plan_id' => $plan_id ]
		);
		$this->assert_status( 200, $awaiting );
		$this->assertSame( 'awaiting_approval', $awaiting->get_data()['status'] );

		$block_loopback = static function () {
			return [
				'headers'  => [],
				'body'     => '',
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $block_loopback, 10, 3 );
		try {
			$response = $this->dispatch(
				'POST',
				"/sd-ai-agent/v1/sessions/{$session_id}/plan/approve",
				[
					'plan_id'             => $plan_id,
					'approval_request_id' => $awaiting->get_data()['plan']['approval_request_id'],
				]
			);
		} finally {
			remove_filter( 'pre_http_request', $block_loopback, 10 );
		}

		$this->assert_status( 202, $response );
		$this->assertSame( 'processing', $response->get_data()['status'] );
		$job_id = (string) $response->get_data()['job_id'];
		$job    = ActiveJobRepository::get_by_job_id( $job_id );

		$this->assertNotNull( $job );
		$this->assertSame( 'queued', $job->status );

		$active_job = $this->dispatch( 'GET', "/sd-ai-agent/v1/sessions/{$session_id}/active-job" );
		$this->assert_status( 200, $active_job );
		$this->assertSame( 'processing', $active_job->get_data()['status'] );
	}

	/** Job polling keeps durable plan details scoped to the job owner. */
	public function test_job_status_hides_durable_plan_details_from_other_administrators(): void {
		$session_id = $this->create_session();
		$plan       = DurablePlanRunner::create( $session_id, $this->admin_id, $this->plan_definition() );
		$this->assertIsArray( $plan );
		$next = DurablePlanRunner::prepare_next( (string) $plan['plan_id'], $this->admin_id );
		$this->assertIsArray( $next );
		$this->assertSame( 'ready', $next['status'] );

		$job_id = '00000000-0000-4000-8000-000000000101';
		$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'queued' ) );
		$this->assertTrue( DurablePlanRunner::assign_job( (string) $plan['plan_id'], (int) $next['step']['id'], $job_id ) );
		$public_plan = DurablePlanRunner::public_plan( (string) $plan['plan_id'] );
		$this->assertIsArray( $public_plan );

		set_transient(
			RestController::JOB_PREFIX . $job_id,
			[
				'status'       => 'processing',
				'user_id'      => $this->admin_id,
				'durable_plan' => [ 'plan' => $public_plan ],
			],
			RestController::JOB_TTL
		);

		$owner_inline = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $owner_inline );
		$this->assertArrayHasKey( 'durable_plan', $owner_inline->get_data() );

		wp_set_current_user( $this->other_admin_id );
		$other_inline = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $other_inline );
		$this->assertArrayNotHasKey( 'durable_plan', $other_inline->get_data() );

		delete_transient( RestController::JOB_PREFIX . $job_id );
		wp_set_current_user( $this->admin_id );
		$owner_fallback = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $owner_fallback );
		$this->assertArrayHasKey( 'durable_plan', $owner_fallback->get_data() );

		wp_set_current_user( $this->other_admin_id );
		$other_fallback = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $other_fallback );
		$this->assertArrayNotHasKey( 'durable_plan', $other_fallback->get_data() );

		ActiveJobRepository::delete( $job_id );
	}

	/** Elementor preview URLs remain sealed in job stores and restore only in the owner response. */
	public function test_job_status_restores_sealed_elementor_preview_only_for_browser_delivery(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$this->markTestSkipped( 'Sodium is unavailable.' );
		}

		$preview_url = 'https://example.test/?elementor-preview=41&preview-token=private-token';
		$gate        = new ElementorCompletionGate(
			array( ElementorCompletionGate::SCREENSHOT_ABILITY ),
			array( ElementorCompletionGate::PREVIEW_ABILITY, ElementorCompletionGate::PUBLISH_ABILITY )
		);
		$gate->record_tool_call( 'elementor/build-composition', array( 'post_id' => 41, 'revision_id' => 101 ) );
		$gate->record_tool_response( 'elementor/build-composition', array( 'success' => true, 'post_id' => 41, 'revision_id' => 101 ) );
		$gate->record_tool_call( ElementorCompletionGate::PREVIEW_ABILITY, array( 'post_id' => 41, 'revision_id' => 101 ) );
		$gate->record_tool_response(
			ElementorCompletionGate::PREVIEW_ABILITY,
			array( 'success' => true, 'post_id' => 41, 'revision_id' => 101, 'preview_url' => $preview_url )
		);
		$pending = $gate->seal_pending_client_tool_calls(
			array(
				array(
					'id'          => 'elementor-preview-job-call',
					'name'        => ElementorCompletionGate::SCREENSHOT_ABILITY,
					'args'        => $gate->get_render_validation_calls()[0],
					'annotations' => array( 'readonly' => true ),
				)
			)
		);
		$this->assertStringNotContainsString( $preview_url, wp_json_encode( $pending ) );

		$session_id = $this->create_session();
		$job_id     = '00000000-0000-4000-8000-000000000105';
		$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'awaiting_client_tools' ) );
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			array(
				'status'                    => 'awaiting_client_tools',
				'user_id'                   => $this->admin_id,
				'pending_client_tool_calls' => $pending,
			),
			RestController::JOB_TTL
		);
		$this->assertTrue(
			ActiveJobRepository::update_status(
				$job_id,
				'awaiting_client_tools',
				array( 'pending_tools' => wp_json_encode( $pending ) )
			)
		);

		$stored_job = get_transient( RestController::JOB_PREFIX . $job_id );
		$this->assertStringNotContainsString( $preview_url, wp_json_encode( $stored_job ) );
		$stored_row = ActiveJobRepository::get_by_job_id( $job_id );
		$this->assertNotNull( $stored_row );
		$this->assertStringNotContainsString( $preview_url, $stored_row->pending_tools );
		$inline = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $inline );
		$this->assertSame( $preview_url, $inline->get_data()['pending_client_tool_calls'][0]['args']['url'] );

		wp_set_current_user( $this->other_admin_id );
		$other_administrator = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $other_administrator );
		$this->assertArrayNotHasKey( 'pending_client_tool_calls', $other_administrator->get_data() );
		$this->assertStringNotContainsString( $preview_url, wp_json_encode( $other_administrator->get_data() ) );
		delete_transient( RestController::JOB_PREFIX . $job_id );
		$other_fallback = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $other_fallback );
		$this->assertArrayNotHasKey( 'pending_client_tool_calls', $other_fallback->get_data() );
		$this->assertStringNotContainsString( $preview_url, wp_json_encode( $other_fallback->get_data() ) );
		wp_set_current_user( $this->admin_id );
		$owner_fallback = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 404, $owner_fallback );

		ActiveJobRepository::delete( $job_id );
	}

	/** Shared-session viewers retain ordinary pending browser calls without private capabilities. */
	public function test_job_status_keeps_ordinary_browser_calls_available_to_other_administrators(): void {
		$session_id = $this->create_session();
		$this->assertTrue( Database::share_session( $session_id, $this->admin_id ) );
		$job_id     = '00000000-0000-4000-8000-000000000106';
		$pending    = array(
			array(
				'id'          => 'ordinary-browser-call',
				'name'        => 'sd-ai-agent-js/refresh-page',
				'args'        => array(),
				'annotations' => array( 'readonly' => true ),
			)
		);
		$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'awaiting_client_tools' ) );
		$this->assertTrue(
			ActiveJobRepository::update_status(
				$job_id,
				'awaiting_client_tools',
				array( 'pending_tools' => wp_json_encode( $pending ) )
			)
		);
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			array(
				'status'                    => 'awaiting_client_tools',
				'user_id'                   => $this->admin_id,
				'pending_client_tool_calls' => $pending,
			),
			RestController::JOB_TTL
		);

		wp_set_current_user( $this->other_admin_id );
		$inline = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $inline );
		$this->assertSame( $pending, $inline->get_data()['pending_client_tool_calls'] );

		delete_transient( RestController::JOB_PREFIX . $job_id );
		$fallback = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $fallback );
		$this->assertArrayNotHasKey( 'pending_client_tool_calls', $fallback->get_data() );
		$this->assertSame( 'error', $fallback->get_data()['status'] );
		wp_set_current_user( $this->admin_id );

		ActiveJobRepository::delete( $job_id );
	}

	/** A stale confirmation cannot return a claimed durable worker to the queue. */
	public function test_stale_durable_confirmation_does_not_requeue_an_active_worker(): void {
		$job_id = '00000000-0000-4000-8000-000000000102';
		$this->assertNotFalse( ActiveJobRepository::create( $this->create_session(), $job_id, $this->admin_id, 'processing' ) );
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			[
				'status'  => 'awaiting_confirmation',
				'user_id' => $this->admin_id,
				'params'  => [
					'durable_plan_phase' => [
						'plan_id' => '00000000-0000-0000-0000-000000000001',
						'step_id' => 1,
					],
				],
			],
			RestController::JOB_TTL
		);

		$response = $this->dispatch( 'POST', "/sd-ai-agent/v1/job/{$job_id}/confirm" );
		$this->assert_status( 409, $response );
		if ( is_wp_error( $response ) ) {
			$this->assertSame( 'sd_ai_agent_job_not_resumable', $response->get_error_code() );
		}

		$row = ActiveJobRepository::get_by_job_id( $job_id );
		$this->assertNotNull( $row );
		$this->assertSame( 'processing', $row->status );
		delete_transient( RestController::JOB_PREFIX . $job_id );
		ActiveJobRepository::delete( $job_id );
	}

	/** Expired durable confirmation pauses update their associated plan state. */
	public function test_expired_paused_durable_job_updates_its_plan_state(): void {
		$session_id = $this->create_session();
		$cases      = [
			[ 'classification' => 'read', 'plan_status' => 'pending', 'step_status' => 'interrupted' ],
			[ 'classification' => 'write', 'plan_status' => 'blocked', 'step_status' => 'failed' ],
		];

		foreach ( $cases as $index => $case ) {
			$plan = DurablePlanRunner::create(
				$session_id,
				$this->admin_id,
				[
					'scope' => 'Test expired paused durable job recovery.',
					'steps' => [
						[
							'key'            => 'paused-' . $case['classification'],
							'title'          => 'Paused ' . $case['classification'] . ' phase',
							'instruction'    => 'Execute only this bounded test phase.',
							'classification' => $case['classification'],
						],
					],
				]
			);
			$this->assertIsArray( $plan );
			$next = DurablePlanRunner::prepare_next( (string) $plan['plan_id'], $this->admin_id );
			$this->assertIsArray( $next );
			if ( 'awaiting_approval' === $next['status'] ) {
				$next = DurablePlanRunner::approve( (string) $plan['plan_id'], (int) $next['plan']['approval_request_id'], $this->admin_id );
				$this->assertIsArray( $next );
			}
			$this->assertSame( 'ready', $next['status'] );

			$job_id = 'test-expired-paused-plan-' . $index;
			$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'awaiting_confirmation' ) );
			$this->assertTrue( DurablePlanRunner::assign_job( (string) $plan['plan_id'], (int) $next['step']['id'], $job_id ) );

			$expired = $this->dispatch( 'GET', "/sd-ai-agent/v1/sessions/{$session_id}/active-job" );
			$this->assert_status( 404, $expired );
			$this->assertNull( ActiveJobRepository::get_by_job_id( $job_id ) );

			$updated = DurablePlanRepository::get_by_plan_id( (string) $plan['plan_id'] );
			$this->assertIsArray( $updated );
			$this->assertSame( $case['plan_status'], $updated['status'] );
			$this->assertSame( $case['step_status'], $updated['steps'][0]['status'] );
		}
	}

	public function test_gateway_failure_job_status_returns_only_the_allowlisted_diagnostic(): void {
		$session_id = $this->create_session();
		$job_id     = '00000000-0000-4000-8000-000000000104';
		$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'processing' ) );
		ActiveJobRepository::record_failure(
			$job_id,
			'error',
			ActiveJobFailureDiagnostic::REASON_GATEWAY_REJECTION,
			array(
				'last_safe_phase' => 'before_provider_call',
				'provider_id'     => 'sd-ai-agent-cloud',
				'model_id'        => 'managed-model',
				'status_code'     => 403,
				'failure_class'   => 'gateway_rejection',
				'failure_source'  => 'http',
				'attempts'        => 1,
				'response_body'   => '<html>Imunify360 PRIVATE_PROVIDER_RESPONSE</html>',
				'prompt'          => 'PRIVATE_PROMPT_CONTENT',
				'authorization'   => 'Bearer PRIVATE_TOKEN',
				'trace'           => '/private/path.php:99',
			)
		);

		$response = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $response );
		$data = $response->get_data();

		$this->assertSame( 'error', $data['status'] );
		$this->assertSame( ActiveJobFailureDiagnostic::REASON_GATEWAY_REJECTION, $data['diagnostic']['reason'] );
		$this->assertSame( 403, $data['diagnostic']['status_code'] );
		$this->assertSame( 'gateway_rejection', $data['diagnostic']['failure_class'] );
		$this->assertSame( 'http', $data['diagnostic']['failure_source'] );
		$this->assertSame( 1, $data['diagnostic']['attempts'] );
		$this->assertSame( 'contact_support', $data['diagnostic']['next_action'] );
		$this->assertFalse( $data['diagnostic']['retryable'] );
		$this->assertStringContainsString( 'security gateway', $data['message'] );
		$this->assertStringNotContainsString( 'disable', strtolower( $data['message'] ) );
		$payload = (string) wp_json_encode( $data );
		$this->assertStringNotContainsString( 'PRIVATE_PROVIDER_RESPONSE', $payload );
		$this->assertStringNotContainsString( 'PRIVATE_PROMPT_CONTENT', $payload );
		$this->assertStringNotContainsString( 'PRIVATE_TOKEN', $payload );
		$this->assertStringNotContainsString( '/private/path.php', $payload );
		$this->assertNull( ActiveJobRepository::get_by_job_id( $job_id ) );
	}

	/** Completed job and persisted-session display projections hide textual reasoning. */
	public function test_job_and_session_display_responses_scrub_textual_thinking(): void {
		$session_id = $this->create_session();
		$messages   = [
			[
				'role'  => 'assistant',
				'parts' => [
					[ 'text' => 'Saved answer.<thinking>Persisted private reasoning.</thinking>' ],
				],
			],
		];
		$this->assertTrue( Database::append_to_session( $session_id, $messages ) );

		$session_response = $this->dispatch( 'GET', "/sd-ai-agent/v1/sessions/{$session_id}" );
		$this->assert_status( 200, $session_response );
		$this->assertSame( 'Saved answer.', $session_response->get_data()['messages'][0]['parts'][0]['text'] );

		$job_id = '00000000-0000-4000-8000-000000000103';
		$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'processing' ) );
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			[
				'status'   => 'complete',
				'user_id'  => $this->admin_id,
				'result'   => [
					'reply'      => "Completed answer.<thinking>\nDo not expose this.\n</thinking>",
					'history'    => $messages,
					'messages'   => [ [ 'type' => 'preamble', 'text' => '<thinking>Live private reasoning.</thinking>' ] ],
					'tool_calls' => [],
				],
			],
			RestController::JOB_TTL
		);

		$job_response = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $job_response );
		$this->assertSame( 'Completed answer.', $job_response->get_data()['reply'] );
		$this->assertSame( 'Saved answer.', $job_response->get_data()['history'][0]['parts'][0]['text'] );
		$this->assertSame( '', $job_response->get_data()['messages'][0]['text'] );
	}

	/** Completed replies reach their owner or shared-session administrators without leaking to other admins. */
	public function test_completed_job_returns_transient_final_response_when_db_row_is_complete(): void {
		$session_id = $this->create_session();
		$job_id     = '00000000-0000-4000-8000-000000000105';
		$this->assertNotFalse( ActiveJobRepository::create( $session_id, $job_id, $this->admin_id, 'processing' ) );
		$this->assertTrue( ActiveJobRepository::update_status( $job_id, 'complete' ) );
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			[
				'status' => 'complete',
				'result' => [
					'reply'      => 'The menu options are ready to review.',
					'history'    => [],
					'messages'   => [],
					'tool_calls' => [ [ 'name' => 'wpab__sd-ai-agent__list-options' ] ],
				],
			],
			RestController::JOB_TTL
		);

		wp_set_current_user( $this->other_admin_id );
		$forbidden = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 403, $forbidden );
		$this->assertIsArray( get_transient( RestController::JOB_PREFIX . $job_id ) );
		$this->assertNotNull( ActiveJobRepository::get_by_job_id( $job_id ) );

		wp_set_current_user( $this->admin_id );
		$this->assertTrue( Database::share_session( $session_id, $this->admin_id ) );
		wp_set_current_user( $this->other_admin_id );
		$response = $this->dispatch( 'GET', "/sd-ai-agent/v1/job/{$job_id}" );
		$this->assert_status( 200, $response );
		$data = $response->get_data();

		$this->assertSame( 'complete', $data['status'] );
		$this->assertSame( 'The menu options are ready to review.', $data['reply'] );
		$this->assertSame( 'wpab__sd-ai-agent__list-options', $data['tool_calls'][0]['name'] );
	}

	/**
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
	 * @param \WP_REST_Response|\WP_Error $response REST response.
	 */
	private function assert_status( int $expected, $response ): void {
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		} else {
			$status = $response->get_status();
		}

		$this->assertSame( $expected, $status, "Expected HTTP {$expected}, got {$status}." );
	}

	private function create_session(): int {
		$session_id = Database::create_session(
			[
				'user_id'     => $this->admin_id,
				'title'       => 'Durable REST plan test',
				'provider_id' => 'test-provider',
				'model_id'    => 'test-model',
			]
		);
		$this->assertIsInt( $session_id );

		return (int) $session_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function plan_definition(): array {
		return [
			'scope'   => 'Review and update the site navigation.',
			'summary' => 'Inspect before applying the reviewed update.',
			'steps'   => [
				[
					'key'               => 'inspect',
					'title'             => 'Inspect navigation',
					'instruction'       => 'Inspect the current navigation without making changes.',
					'classification'    => 'read',
					'idempotency_key'   => 'rest-inspect-navigation',
					'preconditions'     => 'Administrator session available.',
					'expected_evidence' => 'Navigation inventory.',
					'rollback_guidance' => 'No rollback required.',
				],
				[
					'key'               => 'configure',
					'title'             => 'Configure navigation',
					'instruction'       => 'Apply the reviewed navigation update.',
					'classification'    => 'write',
					'idempotency_key'   => 'rest-configure-navigation',
					'preconditions'     => 'Inspection evidence reviewed.',
					'expected_evidence' => 'Configuration confirmation.',
					'rollback_guidance' => 'Restore the prior navigation.',
				],
			],
		];
	}
}
