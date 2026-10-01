<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\REST;

use SdAiAgent\Core\BackgroundJobDispatcher;
use SdAiAgent\Core\Database;
use SdAiAgent\Models\ActiveJobRepository;
use SdAiAgent\REST\RestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/** Approval lifecycle integration coverage without provider or CRM mutations. */
class ToolApprovalLifecycleTest extends WP_UnitTestCase {

	private WP_REST_Server $server;
	private string $job_id;
	private int $session_id;

	public function set_up(): void {
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server = $wp_rest_server;
		do_action( 'rest_api_init' );
		parent::set_up();
		Database::install();
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		$this->session_id = (int) Database::create_session( [ 'user_id' => $user_id, 'title' => 'Approval diagnostic' ] );
		$this->job_id = wp_generate_uuid4();
		$this->assertNotFalse( ActiveJobRepository::create( $this->session_id, $this->job_id, $user_id, 'awaiting_confirmation' ) );
		set_transient( RestController::JOB_PREFIX . $this->job_id, [
			'status' => 'awaiting_confirmation',
			'user_id' => $user_id,
			'pending_tools' => [ [ 'name' => 'wpab__sd-ai-agent__ability-call', 'ability' => 'fluent-crm/manage-tag' ] ],
			'params' => [ 'session_id' => $this->session_id ],
		], RestController::JOB_TTL );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		wp_clear_scheduled_hook( BackgroundJobDispatcher::HOOK, [ get_current_blog_id(), $this->job_id ] );
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		ActiveJobRepository::delete( $this->job_id );
		parent::tear_down();
	}

	public function test_first_approval_resumes_but_repeated_approval_returns_reported_error(): void {
		$first = $this->server->dispatch( new WP_REST_Request( 'POST', '/sd-ai-agent/v1/job/' . $this->job_id . '/confirm' ) );
		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 'processing', $first->get_data()['status'] );
		$second = $this->server->dispatch( new WP_REST_Request( 'POST', '/sd-ai-agent/v1/job/' . $this->job_id . '/confirm' ) );
		$this->assertSame( 404, $second->get_status() );
		$this->assertSame( 'Job not found or not awaiting confirmation.', $second->get_data()['message'] );
		$this->assertSame( 'processing', get_transient( RestController::JOB_PREFIX . $this->job_id )['status'] );
		$this->assertSame( 'processing', ActiveJobRepository::get_by_job_id( $this->job_id )->status );
		$this->assertNotFalse( wp_next_scheduled( BackgroundJobDispatcher::HOOK, [ get_current_blog_id(), $this->job_id ] ) );
	}

	public function test_waiting_job_remains_paused_without_approval(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/sessions/' . $this->session_id . '/active-job' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'awaiting_confirmation', $response->get_data()['status'] );
		$this->assertFalse( wp_next_scheduled( BackgroundJobDispatcher::HOOK, [ get_current_blog_id(), $this->job_id ] ) );
		$this->assertSame( 'awaiting_confirmation', get_transient( RestController::JOB_PREFIX . $this->job_id )['status'] );
	}

	public function test_expired_approval_is_not_restored_from_database(): void {
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/sessions/' . $this->session_id . '/active-job' ) );
		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'error', ActiveJobRepository::get_by_job_id( $this->job_id )->status );
	}
}
