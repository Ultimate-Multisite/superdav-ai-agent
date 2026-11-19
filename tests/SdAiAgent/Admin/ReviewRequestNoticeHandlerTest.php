<?php

declare(strict_types=1);
/**
 * Tests for the review request admin notice.
 *
 * @package SdAiAgent
 * @subpackage Tests\Admin
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Admin;

use RuntimeException;
use SdAiAgent\Admin\ReviewRequestNoticeHandler;
use SdAiAgent\Core\Database;
use WP_UnitTestCase;

/**
 * Verifies eligibility and per-user dismissal behavior.
 *
 * @group admin
 */
final class ReviewRequestNoticeHandlerTest extends WP_UnitTestCase {

	private int $admin_id = 0;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		$GLOBALS['pagenow'] = 'admin.php';
		$_GET['page']       = 'sd-ai-agent';

		update_option( ReviewRequestNoticeHandler::ACTIVATED_AT_OPTION, time() - ( 8 * DAY_IN_SECONDS ) );
		$this->clear_change_log();
		$this->insert_changes( 5 );
	}

	public function tear_down(): void {
		$this->clear_change_log();
		delete_option( ReviewRequestNoticeHandler::ACTIVATED_AT_OPTION );
		delete_user_meta( $this->admin_id, 'sd_ai_agent_review_request_dismissed' );
		delete_user_meta( $this->admin_id, 'sd_ai_agent_review_request_snoozed_until' );
		unset( $_GET['page'], $_GET['sd_ai_agent_review_request_action'], $_GET['_wpnonce'] );
		unset( $GLOBALS['pagenow'] );

		parent::tear_down();
	}

	public function test_notice_displays_when_all_conditions_are_met(): void {
		$this->assertStringContainsString( 'sd-ai-agent-review-request-notice', $this->render_notice() );
	}

	public function test_capability_condition_blocks_notice(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_activation_age_condition_blocks_notice(): void {
		update_option( ReviewRequestNoticeHandler::ACTIVATED_AT_OPTION, time() - ( 6 * DAY_IN_SECONDS ) );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_change_count_condition_blocks_notice(): void {
		$this->clear_change_log();
		$this->insert_changes( 4 );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_permanent_dismissal_condition_blocks_notice(): void {
		update_user_meta( $this->admin_id, 'sd_ai_agent_review_request_dismissed', '1' );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_active_snooze_condition_blocks_notice(): void {
		update_user_meta( $this->admin_id, 'sd_ai_agent_review_request_snoozed_until', time() + DAY_IN_SECONDS );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_notice_only_displays_on_ai_agent_admin_screen(): void {
		$GLOBALS['pagenow'] = 'index.php';

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_maybe_later_snoozes_for_thirty_days(): void {
		$before = time() + ( 30 * DAY_IN_SECONDS );
		$this->run_action( 'snooze' );
		$until = (int) get_user_meta( $this->admin_id, 'sd_ai_agent_review_request_snoozed_until', true );

		$this->assertGreaterThanOrEqual( $before, $until );
		$this->assertLessThanOrEqual( time() + ( 30 * DAY_IN_SECONDS ), $until );
	}

	public function test_dont_ask_again_dismisses_permanently(): void {
		$this->run_action( 'dismiss' );

		$this->assertSame( '1', get_user_meta( $this->admin_id, 'sd_ai_agent_review_request_dismissed', true ) );
	}

	public function test_write_review_dismisses_permanently(): void {
		$redirect = $this->run_action( 'review' );

		$this->assertSame( '1', get_user_meta( $this->admin_id, 'sd_ai_agent_review_request_dismissed', true ) );
		$this->assertSame( 'https://wordpress.org/support/plugin/superdav-ai-agent/reviews/#new-post', $redirect );
	}

	public function test_action_requires_a_valid_nonce(): void {
		$_GET['sd_ai_agent_review_request_action'] = 'dismiss';
		$_GET['_wpnonce']                          = 'invalid';

		ReviewRequestNoticeHandler::handle_action();

		$this->assertSame( '', get_user_meta( $this->admin_id, 'sd_ai_agent_review_request_dismissed', true ) );
	}

	private function render_notice(): string {
		ob_start();
		ReviewRequestNoticeHandler::maybe_display_notice();

		return (string) ob_get_clean();
	}

	private function run_action( string $action ): string {
		$_GET['sd_ai_agent_review_request_action'] = $action;
		$_GET['_wpnonce']                          = wp_create_nonce( 'sd_ai_agent_review_request_' . $action );

		$redirect_filter = static function ( string $location ): string {
			throw new RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $redirect_filter );

		try {
			ReviewRequestNoticeHandler::handle_action();
		} catch ( RuntimeException $exception ) {
			return $exception->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $redirect_filter );
		}

		$this->fail( 'Expected the action handler to redirect.' );
	}

	private function insert_changes( int $count ): void {
		global $wpdb;

		for ( $index = 0; $index < $count; ++$index ) {
			$wpdb->insert(
				Database::changes_log_table_name(),
				array(
					'object_type' => 'post',
					'before_value' => '',
					'after_value'  => 'changed',
					'created_at'   => current_time( 'mysql', true ),
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	private function clear_change_log(): void {
		global $wpdb;

		$query = $wpdb->prepare( 'DELETE FROM %i', Database::changes_log_table_name() );
		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
