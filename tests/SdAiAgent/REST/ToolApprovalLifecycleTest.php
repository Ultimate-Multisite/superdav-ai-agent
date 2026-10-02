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
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'error', $response->get_data()['status'] );
		$this->assertSame( 'error', ActiveJobRepository::get_by_job_id( $this->job_id )->status );
		$this->assert_terminal_diagnostic_survives_reload( 'approval_expired' );
		$this->assertFalse( wp_next_scheduled( BackgroundJobDispatcher::HOOK, [ get_current_blog_id(), $this->job_id ] ) );
	}

	public function test_interrupted_worker_diagnostic_survives_polling_and_reload(): void {
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		ActiveJobRepository::record_failure( $this->job_id, 'interrupted', 'worker_terminated' );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/sessions/' . $this->session_id . '/active-job' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->job_id, $response->get_data()['job_id'] );
		$this->assert_terminal_diagnostic_survives_reload( 'worker_terminated' );
	}

	public function test_expired_approval_remains_discoverable_until_delivered(): void {
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		for ( $attempt = 0; $attempt < 2; ++$attempt ) {
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/sessions/' . $this->session_id . '/active-job' ) );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( 'error', $response->get_data()['status'] );
			$this->assertArrayNotHasKey( 'pending_tools', $response->get_data() );
		}
		$confirm = $this->server->dispatch( new WP_REST_Request( 'POST', '/sd-ai-agent/v1/job/' . $this->job_id . '/confirm' ) );
		$this->assertSame( 404, $confirm->get_status() );
		$this->assert_terminal_diagnostic_survives_reload( 'approval_expired' );
	}

	public function test_old_failure_does_not_append_to_replacement_job_conversation(): void {
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		ActiveJobRepository::record_failure( $this->job_id, 'interrupted', 'worker_terminated' );
		$replacement = wp_generate_uuid4();
		ActiveJobRepository::create( $this->session_id, $replacement, get_current_user_id(), 'processing' );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/job/' . $this->job_id ) );
		$this->assertFalse( $response->get_data()['failure_message_persisted'] );
		$this->assertSame( 'processing', ActiveJobRepository::get_by_job_id( $replacement )->status );
		$this->assertSame( [], json_decode( Database::get_session( $this->session_id )->messages, true ) );
		ActiveJobRepository::delete( $replacement );
	}

	/** A replacement created between the snapshot and its write must win. */
	public function test_replacement_created_during_terminal_delivery_blocks_stale_write(): void {
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		ActiveJobRepository::record_failure( $this->job_id, 'interrupted', 'worker_terminated' );
		$replacement = wp_generate_uuid4();
		$filter = function ( string $query ) use ( &$filter, $replacement ): string {
			if ( str_contains( $query, 'BINARY session.messages' ) ) {
				remove_filter( 'query', $filter );
				ActiveJobRepository::create( $this->session_id, $replacement, get_current_user_id(), 'processing' );
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/job/' . $this->job_id ) );
			$this->assertFalse( $response->get_data()['failure_message_persisted'] );
			$this->assertSame( [], json_decode( Database::get_session( $this->session_id )->messages, true ) );
			$this->assertSame( 'processing', ActiveJobRepository::get_by_job_id( $replacement )->status );
		} finally {
			remove_filter( 'query', $filter );
			ActiveJobRepository::delete( $replacement );
		}
	}

	/** A changed message snapshot is retried, not overwritten by a stale poll. */
	public function test_concurrent_message_append_survives_terminal_delivery_retry(): void {
		delete_transient( RestController::JOB_PREFIX . $this->job_id );
		ActiveJobRepository::record_failure( $this->job_id, 'interrupted', 'worker_terminated' );
		$filter = function ( string $query ) use ( &$filter ): string {
			if ( str_contains( $query, 'BINARY session.messages' ) ) {
				remove_filter( 'query', $filter );
				Database::append_to_session( $this->session_id, [ [ 'role' => 'user', 'parts' => [ [ 'text' => 'Concurrent follow-up' ] ] ] ] );
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/job/' . $this->job_id ) );
			$this->assertFalse( $response->get_data()['failure_message_persisted'] );
			$this->assertNotNull( ActiveJobRepository::get_by_job_id( $this->job_id ) );
			$retry = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/job/' . $this->job_id ) );
			$this->assertTrue( $retry->get_data()['failure_message_persisted'] );
			$saved = json_decode( Database::get_session( $this->session_id )->messages, true );
			$this->assertCount( 2, $saved );
			$this->assertSame( 'Concurrent follow-up', $saved[0]['parts'][0]['text'] );
			$this->assertSame( 'worker_terminated', $saved[1]['diagnostic']['reason'] );
		} finally {
			remove_filter( 'query', $filter );
		}
	}

	private function assert_terminal_diagnostic_survives_reload( string $reason ): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/sd-ai-agent/v1/job/' . $this->job_id ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $reason, $response->get_data()['diagnostic']['reason'] );
		$this->assertTrue( $response->get_data()['failure_message_persisted'] );
		$this->assertNull( ActiveJobRepository::get_by_job_id( $this->job_id ) );
		$session = Database::get_session( $this->session_id );
		$messages = json_decode( $session->messages, true );
		$this->assertCount( 1, $messages );
		$this->assertSame( $response->get_data()['message'], $messages[0]['parts'][0]['text'] );
		$this->assertSame( $reason, $messages[0]['diagnostic']['reason'] );
		$this->assertNotSame( '', trim( $messages[0]['parts'][0]['text'] ) );
	}
}
