<?php

declare(strict_types=1);
/**
 * Neutral WordPress.org review request shown after meaningful plugin use.
 *
 * @package SdAiAgent\Admin
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Admin;

use SdAiAgent\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays and processes the per-user review request notice.
 */
final class ReviewRequestNoticeHandler {

	public const ACTIVATED_AT_OPTION = 'sd_ai_agent_activated_at';

	private const ACTION_QUERY_ARG = 'sd_ai_agent_review_request_action';

	private const NONCE_ACTION = 'sd_ai_agent_review_request';

	private const DISMISSED_META = 'sd_ai_agent_review_request_dismissed';

	private const SNOOZED_UNTIL_META = 'sd_ai_agent_review_request_snoozed_until';

	private const REVIEW_URL = 'https://wordpress.org/support/plugin/superdav-ai-agent/reviews/#new-post';

	private const MINIMUM_ACTIVATION_AGE = 7 * DAY_IN_SECONDS;

	private const SNOOZE_DURATION = 30 * DAY_IN_SECONDS;

	private const MINIMUM_CHANGE_COUNT = 5;

	/**
	 * Preserve the first known activation time.
	 *
	 * Existing installations get a conservative baseline on their first admin
	 * request after upgrading, so they are never prompted immediately.
	 */
	public static function ensure_activation_timestamp(): void {
		if ( false === get_option( self::ACTIVATED_AT_OPTION, false ) ) {
			add_option( self::ACTIVATED_AT_OPTION, time(), '', false );
		}
	}

	/**
	 * Render the notice when every eligibility condition is met.
	 */
	public static function maybe_display_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_plugin_screen() ) {
			return;
		}

		$activated_at = get_option( self::ACTIVATED_AT_OPTION, 0 );
		if ( ! is_numeric( $activated_at ) || (int) $activated_at > time() - self::MINIMUM_ACTIVATION_AGE ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( 0 === $user_id || get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return;
		}

		$snoozed_until = (int) get_user_meta( $user_id, self::SNOOZED_UNTIL_META, true );
		if ( $snoozed_until > time() || self::MINIMUM_CHANGE_COUNT > self::get_change_count() ) {
			return;
		}

		self::render_notice();
	}

	/**
	 * Process a nonce-protected review, snooze, or permanent dismissal action.
	 */
	public static function handle_action(): void {
		if ( ! isset( $_GET[ self::ACTION_QUERY_ARG ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET[ self::ACTION_QUERY_ARG ] ) );
		if ( ! in_array( $action, array( 'review', 'snooze', 'dismiss' ), true ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
				self::NONCE_ACTION . '_' . $action
			)
		) {
			return;
		}

		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return;
		}

		if ( 'snooze' === $action ) {
			update_user_meta( $user_id, self::SNOOZED_UNTIL_META, time() + self::SNOOZE_DURATION );
		} else {
			update_user_meta( $user_id, self::DISMISSED_META, '1' );
			delete_user_meta( $user_id, self::SNOOZED_UNTIL_META );
		}

		if ( 'review' === $action ) {
			wp_redirect( self::REVIEW_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed WordPress.org URL.
			exit;
		}

		$redirect = remove_query_arg( array( self::ACTION_QUERY_ARG, '_wpnonce' ) );
		wp_safe_redirect( is_string( $redirect ) && '' !== $redirect ? $redirect : admin_url( 'admin.php?page=sd-ai-agent' ) );
		exit;
	}

	/**
	 * Check whether the request is for the unified AI Agent admin page.
	 */
	private static function is_plugin_screen(): bool {
		global $pagenow;

		if ( 'admin.php' !== $pagenow || ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
			return false;
		}

		return 'sd-ai-agent' === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
	}

	/**
	 * Return the number of recorded completed changes, capped at the threshold.
	 */
	private static function get_change_count(): int {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT COUNT(*) FROM (SELECT 1 FROM %i LIMIT %d) AS review_request_changes',
			Database::changes_log_table_name(),
			self::MINIMUM_CHANGE_COUNT
		);

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Render neutral copy and the three explicit choices.
	 */
	private static function render_notice(): void {
		$review_url  = self::get_action_url( 'review' );
		$snooze_url  = self::get_action_url( 'snooze' );
		$dismiss_url = self::get_action_url( 'dismiss' );
		?>
		<div class="notice notice-info is-dismissible" id="sd-ai-agent-review-request-notice">
			<p><?php esc_html_e( 'Is SD AI Agent useful on this site? An honest review on WordPress.org helps other site owners decide, and tells us what to fix.', 'superdav-ai-agent' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $review_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Write a review', 'superdav-ai-agent' ); ?>
				</a>
				<a href="<?php echo esc_url( $snooze_url ); ?>" class="button">
					<?php esc_html_e( 'Maybe later', 'superdav-ai-agent' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link">
					<?php esc_html_e( "Don't ask again", 'superdav-ai-agent' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Build a nonce-protected local action URL.
	 */
	private static function get_action_url( string $action ): string {
		return wp_nonce_url(
			add_query_arg( self::ACTION_QUERY_ARG, $action ),
			self::NONCE_ACTION . '_' . $action
		);
	}
}
