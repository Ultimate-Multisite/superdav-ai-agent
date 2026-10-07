<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\REST;

use SdAiAgent\Core\BackgroundJobDispatcher;
use SdAiAgent\Core\Database;
use SdAiAgent\Core\SessionTitleGenerator;
use SdAiAgent\Models\ActiveJobRepository;
use SdAiAgent\Repositories\SessionRepository;
use SdAiAgent\REST\RestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/** Independent title delivery, worker authorization and concurrent edit guards. */
class SessionTitleTest extends WP_UnitTestCase {

	private WP_REST_Server $server;
	private int $owner;
	private int $session_id;
	private array $requests = array();

	public function set_up(): void {
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server = $wp_rest_server;
		do_action( 'rest_api_init' );
		parent::set_up();
		Database::install();
		$this->owner = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->owner );
		$this->session_id = (int) Database::create_session( array( 'user_id' => $this->owner ) );
		add_filter( 'pre_http_request', array( $this, 'capture_request' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'capture_request' ), 10 );
		foreach ( $this->requests as $request ) {
			$id = $request['body']['job_id'] ?? '';
			delete_transient( RestController::JOB_PREFIX . $id );
			wp_clear_scheduled_hook( BackgroundJobDispatcher::HOOK, array( get_current_blog_id(), $id ) );
		}
		delete_transient( 'sd_ai_agent_title_pending_' . $this->session_id );
		parent::tear_down();
	}

	/** Capture loopbacks and reject inference without contacting a provider. */
	public function capture_request( $preempt, array $args, string $url ) {
		$this->requests[] = array( 'url' => $url, 'args' => $args, 'body' => json_decode( $args['body'] ?? '{}', true ), 'user_id' => get_current_user_id() );
		return new \WP_Error( 'test_http_blocked', 'No external requests in this test.' );
	}

	public function test_run_publishes_title_and_launches_worker_before_main_job_completes(): void {
		$request = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/run' );
		$request->set_body_params( array( 'message' => 'Review installed plugins', 'session_id' => $this->session_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 202, $response->get_status() );
		$this->assertSame( 'Review installed plugins', $response->get_data()['title'] );
		$this->assertSame( 'processing', ActiveJobRepository::get_by_job_id( $response->get_data()['job_id'] )->status );
		$this->assertCount( 1, $this->requests, 'Queuing a title must not synchronously call the model.' );
		$this->assertFalse( $this->requests[0]['args']['blocking'] );
		$this->assertStringContainsString( '/process', $this->requests[0]['url'] );
		$job_id = $this->requests[0]['body']['job_id'];
		$this->assertTrue( get_transient( RestController::JOB_PREFIX . $job_id )['title_only'] );
		$this->assertNull( ActiveJobRepository::get_by_job_id( $job_id ), 'Title tasks must not replace the active chat job.' );
		$this->assertGreaterThanOrEqual( time() + 55, wp_next_scheduled( BackgroundJobDispatcher::HOOK, array( get_current_blog_id(), $job_id ) ) );
		$progress = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/sessions/' . $this->session_id . '/title' ) );
		$this->assertSame( 'Review installed plugins', $progress->get_data()['title'] );
		$this->assertTrue( $progress->get_data()['pending'] );
		wp_clear_scheduled_hook( BackgroundJobDispatcher::HOOK, array( get_current_blog_id(), $response->get_data()['job_id'] ) );
		delete_transient( RestController::JOB_PREFIX . $response->get_data()['job_id'] );
		ActiveJobRepository::delete( $response->get_data()['job_id'] );
	}

	public function test_existing_title_and_duplicate_start_do_not_launch_more_workers(): void {
		$this->assertSame( 'Review plugins', SessionTitleGenerator::start( $this->session_id, 'Review plugins', '', '' ) );
		$this->assertNull( SessionTitleGenerator::start( $this->session_id, 'A follow-up', '', '' ) );
		$this->assertCount( 1, $this->requests );
		Database::update_session( $this->session_id, array( 'title' => 'My title' ) );
		$this->assertNull( SessionTitleGenerator::start( $this->session_id, 'Another follow-up', '', '' ) );
		$this->assertSame( 'My title', Database::get_session( $this->session_id )->title );
	}

	public function test_worker_preserves_manual_edit_and_consumes_its_token_once(): void {
		SessionTitleGenerator::start( $this->session_id, 'Review plugins', '', '' );
		Database::update_session( $this->session_id, array( 'title' => 'review plugins' ) );
		$request = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/process' );
		$request->set_body_params( $this->requests[0]['body'] );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'review plugins', Database::get_session( $this->session_id )->title );
		$this->assertCount( 1, $this->requests, 'An edited title must skip inference entirely.' );
		$this->assertFalse( SessionTitleGenerator::is_pending( $this->session_id ) );
		$this->assertSame( 403, $this->server->dispatch( $request )->get_status() );
	}

	public function test_atomic_title_update_protects_owner_edits_and_trashed_sessions(): void {
		SessionTitleGenerator::start( $this->session_id, 'Review plugins', '', '' );
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertFalse( SessionRepository::replace_title( $this->session_id, $other, 'Review plugins', 'Other title' ) );
		Database::update_session( $this->session_id, array( 'title' => 'review plugins' ) );
		$this->assertFalse( SessionRepository::replace_title( $this->session_id, $this->owner, 'Review plugins', 'AI title' ) );
		Database::update_session( $this->session_id, array( 'status' => 'trash' ) );
		$this->assertFalse( SessionRepository::replace_title( $this->session_id, $this->owner, 'review plugins', 'AI title' ) );
	}

	public function test_title_failure_keeps_provisional_title_and_restores_worker_user(): void {
		SessionTitleGenerator::start( $this->session_id, 'Review plugins', 'missing-test-provider', 'missing-test-model' );
		$worker_user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $worker_user );
		$request = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/process' );
		$request->set_body_params( $this->requests[0]['body'] );
		$this->assertSame( 200, $this->server->dispatch( $request )->get_status() );
		$this->assertSame( 'Review plugins', Database::get_session( $this->session_id )->title );
		$this->assertFalse( SessionTitleGenerator::is_pending( $this->session_id ) );
		$this->assertSame( $worker_user, get_current_user_id() );
	}

	public function test_title_progress_and_worker_reject_unrelated_users_and_forged_tokens(): void {
		SessionTitleGenerator::start( $this->session_id, 'Review plugins', '', '' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 403, $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/sessions/' . $this->session_id . '/title' ) )->get_status() );
		$body = $this->requests[0]['body'];
		$body['token'] = 'forged';
		$request = new WP_REST_Request( 'POST', '/sd-ai-agent/v1/process' );
		$request->set_body_params( $body );
		$this->assertSame( 403, $this->server->dispatch( $request )->get_status() );
		$this->assertTrue( SessionTitleGenerator::is_pending( $this->session_id ) );
	}
}
