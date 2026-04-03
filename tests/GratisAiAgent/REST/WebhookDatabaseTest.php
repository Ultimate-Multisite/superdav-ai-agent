<?php

declare(strict_types=1);
/**
 * Unit tests for WebhookDatabase.
 *
 * Exercises the database layer directly (no REST server). Coverage:
 *   - table_name / logs_table_name return expected table names.
 *   - get_schema returns non-empty SQL containing both table names.
 *   - create_webhook inserts a row and returns an integer ID.
 *   - get_webhook returns the row by ID.
 *   - get_webhook returns null for unknown ID.
 *   - list_webhooks returns all webhooks ordered by name.
 *   - update_webhook updates fields and returns true.
 *   - delete_webhook removes the row and returns true.
 *   - delete_webhook also removes associated logs.
 *   - log_execution inserts a log row and increments run_count on the webhook.
 *   - get_logs returns paginated rows for a webhook.
 *   - count_logs returns the total row count.
 *   - count_logs returns 0 for a webhook with no logs.
 *
 * @package GratisAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\REST;

use GratisAiAgent\REST\WebhookDatabase;
use WP_UnitTestCase;

/**
 * Unit tests for WebhookDatabase.
 *
 * @group webhook
 * @group database
 */
class WebhookDatabaseTest extends WP_UnitTestCase {

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Create a test webhook and return its ID.
	 *
	 * @param array<string, mixed> $overrides Optional field overrides.
	 * @return int Webhook ID.
	 */
	private function create_webhook( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'    => 'Test Webhook ' . wp_generate_password( 6, false ),
				'secret'  => 'wh_' . wp_generate_password( 32, false ),
				'enabled' => 1,
			],
			$overrides
		);

		$id = WebhookDatabase::create_webhook( $data );
		$this->assertNotFalse( $id, 'create_webhook should return an integer ID.' );
		return (int) $id;
	}

	// ─── Table names ──────────────────────────────────────────────────────────

	/**
	 * table_name returns a non-empty string with the expected suffix.
	 */
	public function test_table_name_returns_expected_suffix(): void {
		$name = WebhookDatabase::table_name();
		$this->assertStringEndsWith( 'gratis_ai_agent_webhooks', $name );
	}

	/**
	 * logs_table_name returns a non-empty string with the expected suffix.
	 */
	public function test_logs_table_name_returns_expected_suffix(): void {
		$name = WebhookDatabase::logs_table_name();
		$this->assertStringEndsWith( 'gratis_ai_agent_webhook_logs', $name );
	}

	// ─── get_schema ───────────────────────────────────────────────────────────

	/**
	 * get_schema returns a non-empty SQL string containing both table names.
	 */
	public function test_get_schema_returns_sql(): void {
		$sql = WebhookDatabase::get_schema( 'DEFAULT CHARSET=utf8mb4' );
		$this->assertNotEmpty( $sql );
		$this->assertStringContainsString( 'gratis_ai_agent_webhooks', $sql );
		$this->assertStringContainsString( 'gratis_ai_agent_webhook_logs', $sql );
	}

	// ─── create_webhook ───────────────────────────────────────────────────────

	/**
	 * create_webhook returns an integer ID greater than zero.
	 */
	public function test_create_webhook_returns_integer_id(): void {
		$id = $this->create_webhook();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * create_webhook stores the provided name.
	 */
	public function test_create_webhook_stores_name(): void {
		$id      = $this->create_webhook( [ 'name' => 'Stored Name Webhook' ] );
		$webhook = WebhookDatabase::get_webhook( $id );
		$this->assertNotNull( $webhook );
		$this->assertSame( 'Stored Name Webhook', $webhook->name );
	}

	/**
	 * create_webhook stores the secret.
	 */
	public function test_create_webhook_stores_secret(): void {
		$secret  = 'wh_' . wp_generate_password( 32, false );
		$id      = $this->create_webhook( [ 'secret' => $secret ] );
		$webhook = WebhookDatabase::get_webhook( $id );
		$this->assertNotNull( $webhook );
		$this->assertSame( $secret, $webhook->secret );
	}

	/**
	 * create_webhook defaults enabled to 1 when not supplied.
	 *
	 * Calls WebhookDatabase::create_webhook() directly without an 'enabled'
	 * key so the production default — not the test fixture — is exercised.
	 */
	public function test_create_webhook_defaults_enabled(): void {
		$id = WebhookDatabase::create_webhook( [
			'name'   => 'Stored Name Webhook',
			'secret' => 'wh_' . wp_generate_password( 32, false ),
		] );
		$this->assertNotFalse( $id );
		$webhook = WebhookDatabase::get_webhook( (int) $id );
		$this->assertNotNull( $webhook );
		$this->assertSame( '1', (string) $webhook->enabled );
	}

	// ─── get_webhook ──────────────────────────────────────────────────────────

	/**
	 * get_webhook returns null for an unknown ID.
	 */
	public function test_get_webhook_returns_null_for_unknown_id(): void {
		$this->assertNull( WebhookDatabase::get_webhook( 999999 ) );
	}

	/**
	 * get_webhook returns the webhook object for a known ID.
	 */
	public function test_get_webhook_returns_object(): void {
		$id      = $this->create_webhook();
		$webhook = WebhookDatabase::get_webhook( $id );
		$this->assertIsObject( $webhook );
		$this->assertSame( (string) $id, (string) $webhook->id );
	}

	// ─── list_webhooks ────────────────────────────────────────────────────────

	/**
	 * list_webhooks returns an array (possibly empty).
	 */
	public function test_list_webhooks_returns_array(): void {
		$this->assertIsArray( WebhookDatabase::list_webhooks() );
	}

	/**
	 * list_webhooks includes newly created webhooks.
	 */
	public function test_list_webhooks_includes_new_webhook(): void {
		$id = $this->create_webhook( [ 'name' => 'Listed Webhook' ] );

		$webhooks = WebhookDatabase::list_webhooks();
		$ids      = array_map( static fn( $w ) => (int) $w->id, $webhooks );
		$this->assertContains( $id, $ids );
	}

	// ─── update_webhook ───────────────────────────────────────────────────────

	/**
	 * update_webhook returns true and persists the change.
	 */
	public function test_update_webhook_persists_change(): void {
		$id = $this->create_webhook( [ 'name' => 'Before Update' ] );

		$result = WebhookDatabase::update_webhook( $id, [ 'name' => 'After Update' ] );
		$this->assertTrue( $result );

		$webhook = WebhookDatabase::get_webhook( $id );
		$this->assertNotNull( $webhook );
		$this->assertSame( 'After Update', $webhook->name );
	}

	/**
	 * update_webhook can update the enabled flag.
	 */
	public function test_update_webhook_updates_enabled_flag(): void {
		$id = $this->create_webhook( [ 'enabled' => 1 ] );

		WebhookDatabase::update_webhook( $id, [ 'enabled' => 0 ] );

		$webhook = WebhookDatabase::get_webhook( $id );
		$this->assertNotNull( $webhook );
		$this->assertSame( '0', (string) $webhook->enabled );
	}

	/**
	 * update_webhook can rotate the secret.
	 */
	public function test_update_webhook_rotates_secret(): void {
		$original_secret = 'wh_' . wp_generate_password( 32, false );
		$id              = $this->create_webhook( [ 'secret' => $original_secret ] );

		$new_secret = 'wh_' . wp_generate_password( 32, false );
		WebhookDatabase::update_webhook( $id, [ 'secret' => $new_secret ] );

		$webhook = WebhookDatabase::get_webhook( $id );
		$this->assertNotNull( $webhook );
		$this->assertSame( $new_secret, $webhook->secret );
		$this->assertNotSame( $original_secret, $webhook->secret );
	}

	// ─── delete_webhook ───────────────────────────────────────────────────────

	/**
	 * delete_webhook removes the webhook row.
	 */
	public function test_delete_webhook_removes_row(): void {
		$id = $this->create_webhook();

		$result = WebhookDatabase::delete_webhook( $id );
		$this->assertTrue( $result );

		$this->assertNull( WebhookDatabase::get_webhook( $id ) );
	}

	/**
	 * delete_webhook also removes associated execution logs.
	 */
	public function test_delete_webhook_removes_logs(): void {
		$id = $this->create_webhook();

		// Log an execution.
		WebhookDatabase::log_execution( $id, 'success', 'reply', [], 10, 5, 100, '' );

		$this->assertGreaterThan( 0, WebhookDatabase::count_logs( $id ) );

		WebhookDatabase::delete_webhook( $id );

		$this->assertSame( 0, WebhookDatabase::count_logs( $id ) );
	}

	// ─── log_execution ────────────────────────────────────────────────────────

	/**
	 * log_execution returns an integer log row ID.
	 */
	public function test_log_execution_returns_integer_id(): void {
		$webhook_id = $this->create_webhook();

		$log_id = WebhookDatabase::log_execution(
			$webhook_id,
			'success',
			'Test reply',
			[],
			100,
			50,
			250,
			''
		);

		$this->assertNotFalse( $log_id );
		$this->assertGreaterThan( 0, (int) $log_id );
	}

	/**
	 * log_execution increments run_count on the webhook.
	 */
	public function test_log_execution_increments_run_count(): void {
		$webhook_id = $this->create_webhook();

		$before = WebhookDatabase::get_webhook( $webhook_id );
		$this->assertNotNull( $before );
		$count_before = (int) $before->run_count;

		WebhookDatabase::log_execution( $webhook_id, 'success', 'reply', [], 10, 5, 100, '' );

		$after = WebhookDatabase::get_webhook( $webhook_id );
		$this->assertNotNull( $after );
		$this->assertSame( $count_before + 1, (int) $after->run_count );
	}

	/**
	 * log_execution updates last_run_at on the webhook.
	 */
	public function test_log_execution_updates_last_run_at(): void {
		$webhook_id = $this->create_webhook();

		$before = WebhookDatabase::get_webhook( $webhook_id );
		$this->assertNotNull( $before );
		$this->assertNull( $before->last_run_at );

		WebhookDatabase::log_execution( $webhook_id, 'success', 'reply', [], 10, 5, 100, '' );

		$after = WebhookDatabase::get_webhook( $webhook_id );
		$this->assertNotNull( $after );
		$this->assertNotNull( $after->last_run_at );
	}

	/**
	 * log_execution stores error status and message.
	 */
	public function test_log_execution_stores_error_status(): void {
		$webhook_id = $this->create_webhook();

		WebhookDatabase::log_execution( $webhook_id, 'error', '', [], 0, 0, 50, 'Something failed' );

		$logs = WebhookDatabase::get_logs( $webhook_id );
		$this->assertNotEmpty( $logs );
		$this->assertSame( 'error', $logs[0]->status );
		$this->assertSame( 'Something failed', $logs[0]->error_message );
	}

	// ─── get_logs ─────────────────────────────────────────────────────────────

	/**
	 * get_logs returns an empty array for a webhook with no logs.
	 */
	public function test_get_logs_returns_empty_for_new_webhook(): void {
		$webhook_id = $this->create_webhook();
		$this->assertSame( [], WebhookDatabase::get_logs( $webhook_id ) );
	}

	/**
	 * get_logs returns logged rows for a webhook.
	 */
	public function test_get_logs_returns_logged_rows(): void {
		$webhook_id = $this->create_webhook();

		WebhookDatabase::log_execution( $webhook_id, 'success', 'reply 1', [], 10, 5, 100, '' );
		WebhookDatabase::log_execution( $webhook_id, 'success', 'reply 2', [], 20, 10, 200, '' );

		$logs = WebhookDatabase::get_logs( $webhook_id );
		$this->assertCount( 2, $logs );
	}

	/**
	 * get_logs respects limit parameter.
	 */
	public function test_get_logs_respects_limit(): void {
		$webhook_id = $this->create_webhook();

		for ( $i = 0; $i < 5; $i++ ) {
			WebhookDatabase::log_execution( $webhook_id, 'success', "reply {$i}", [], 10, 5, 100, '' );
		}

		$logs = WebhookDatabase::get_logs( $webhook_id, 2, 0 );
		$this->assertCount( 2, $logs );
	}

	/**
	 * get_logs returns rows ordered by created_at DESC (most recent first).
	 */
	public function test_get_logs_ordered_desc(): void {
		$webhook_id = $this->create_webhook();

		WebhookDatabase::log_execution( $webhook_id, 'success', 'first', [], 10, 5, 100, '' );
		WebhookDatabase::log_execution( $webhook_id, 'success', 'second', [], 10, 5, 100, '' );

		$logs = WebhookDatabase::get_logs( $webhook_id );
		// Most recent (second) should come first.
		$this->assertSame( 'second', $logs[0]->reply );
	}

	// ─── count_logs ───────────────────────────────────────────────────────────

	/**
	 * count_logs returns 0 for a webhook with no logs.
	 */
	public function test_count_logs_returns_zero_for_new_webhook(): void {
		$webhook_id = $this->create_webhook();
		$this->assertSame( 0, WebhookDatabase::count_logs( $webhook_id ) );
	}

	/**
	 * count_logs returns the correct count after logging.
	 */
	public function test_count_logs_returns_correct_count(): void {
		$webhook_id = $this->create_webhook();

		WebhookDatabase::log_execution( $webhook_id, 'success', 'reply', [], 10, 5, 100, '' );
		WebhookDatabase::log_execution( $webhook_id, 'error', '', [], 0, 0, 50, 'err' );

		$this->assertSame( 2, WebhookDatabase::count_logs( $webhook_id ) );
	}

	/**
	 * count_logs is isolated per webhook.
	 */
	public function test_count_logs_is_isolated_per_webhook(): void {
		$webhook_a = $this->create_webhook();
		$webhook_b = $this->create_webhook();

		WebhookDatabase::log_execution( $webhook_a, 'success', 'reply', [], 10, 5, 100, '' );
		WebhookDatabase::log_execution( $webhook_a, 'success', 'reply', [], 10, 5, 100, '' );
		WebhookDatabase::log_execution( $webhook_b, 'success', 'reply', [], 10, 5, 100, '' );

		$this->assertSame( 2, WebhookDatabase::count_logs( $webhook_a ) );
		$this->assertSame( 1, WebhookDatabase::count_logs( $webhook_b ) );
	}
}
