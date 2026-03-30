<?php

declare(strict_types=1);
/**
 * Test case for ChangeLogger class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\ChangeLogger;
use GratisAiAgent\Models\ChangesLog;
use WP_UnitTestCase;

/**
 * Test ChangeLogger functionality.
 */
class ChangeLoggerTest extends WP_UnitTestCase {

	/**
	 * Reset static state before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		ChangeLogger::end();
	}

	/**
	 * Reset static state after each test.
	 */
	public function tear_down(): void {
		ChangeLogger::end();
		parent::tear_down();
	}

	// ── begin / end / is_active ───────────────────────────────────────────────

	/**
	 * is_active() returns false before begin() is called.
	 */
	public function test_is_active_returns_false_initially(): void {
		$this->assertFalse( ChangeLogger::is_active() );
	}

	/**
	 * is_active() returns true after begin().
	 */
	public function test_is_active_returns_true_after_begin(): void {
		ChangeLogger::begin( 1, 'test_ability' );
		$this->assertTrue( ChangeLogger::is_active() );
	}

	/**
	 * is_active() returns false after end().
	 */
	public function test_is_active_returns_false_after_end(): void {
		ChangeLogger::begin( 1, 'test_ability' );
		ChangeLogger::end();
		$this->assertFalse( ChangeLogger::is_active() );
	}

	/**
	 * begin() with no arguments still activates logging.
	 */
	public function test_begin_with_defaults_activates_logging(): void {
		ChangeLogger::begin();
		$this->assertTrue( ChangeLogger::is_active() );
	}

	// ── register ─────────────────────────────────────────────────────────────

	/**
	 * register() hooks into the expected WordPress actions.
	 */
	public function test_register_adds_expected_hooks(): void {
		ChangeLogger::register();

		$this->assertNotFalse( has_action( 'post_updated', [ ChangeLogger::class, 'on_post_updated' ] ) );
		$this->assertNotFalse( has_action( 'updated_option', [ ChangeLogger::class, 'on_updated_option' ] ) );
		$this->assertNotFalse( has_action( 'added_option', [ ChangeLogger::class, 'on_added_option' ] ) );
		$this->assertNotFalse( has_action( 'edited_term', [ ChangeLogger::class, 'on_edited_term' ] ) );
		$this->assertNotFalse( has_action( 'profile_update', [ ChangeLogger::class, 'on_profile_update' ] ) );
	}

	// ── on_post_updated ───────────────────────────────────────────────────────

	/**
	 * on_post_updated() does nothing when logging is inactive.
	 */
	public function test_on_post_updated_skips_when_inactive(): void {
		$post_id = self::factory()->post->create( [ 'post_title' => 'Before' ] );
		$before  = get_post( $post_id );
		$after   = clone $before;
		/** @var \WP_Post $after */
		$after->post_title = 'After';

		// Logging is inactive — no DB write should occur.
		ChangeLogger::on_post_updated( $post_id, $after, $before );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE object_id = %d',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				$post_id
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_post_updated() records a change when logging is active and title differs.
	 */
	public function test_on_post_updated_records_title_change_when_active(): void {
		ChangeLogger::begin( 42, 'update_post' );

		$post_id = self::factory()->post->create( [ 'post_title' => 'Original Title' ] );
		$before  = get_post( $post_id );
		$after   = clone $before;
		/** @var \WP_Post $after */
		$after->post_title = 'Updated Title';

		ChangeLogger::on_post_updated( $post_id, $after, $before );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE object_id = %d AND field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				$post_id,
				'post_title'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( 'Original Title', $row->before_value );
		$this->assertSame( 'Updated Title', $row->after_value );
		$this->assertSame( '42', (string) $row->session_id );
		$this->assertSame( 'update_post', $row->ability_name );
	}

	/**
	 * on_post_updated() does not record a change when field values are identical.
	 */
	public function test_on_post_updated_skips_unchanged_fields(): void {
		ChangeLogger::begin( 1, 'test' );

		$post_id = self::factory()->post->create( [ 'post_title' => 'Same Title' ] );
		$before  = get_post( $post_id );
		$after   = clone $before;
		// No change to any tracked field.

		ChangeLogger::on_post_updated( $post_id, $after, $before );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE object_id = %d',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				$post_id
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	// ── on_updated_option ─────────────────────────────────────────────────────

	/**
	 * on_updated_option() skips when logging is inactive.
	 */
	public function test_on_updated_option_skips_when_inactive(): void {
		ChangeLogger::on_updated_option( 'blogname', 'Old Name', 'New Name' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'blogname'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_updated_option() records a change when active.
	 */
	public function test_on_updated_option_records_change_when_active(): void {
		ChangeLogger::begin( 5, 'update_option' );

		ChangeLogger::on_updated_option( 'blogname', 'Old Name', 'New Name' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'blogname'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( 'Old Name', $row->before_value );
		$this->assertSame( 'New Name', $row->after_value );
	}

	/**
	 * on_updated_option() skips transient options.
	 */
	public function test_on_updated_option_skips_transients(): void {
		ChangeLogger::begin( 1, 'test' );

		ChangeLogger::on_updated_option( '_transient_some_key', 'old', 'new' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'_transient_some_key'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_updated_option() skips gratis_ai_agent_ prefixed options.
	 */
	public function test_on_updated_option_skips_plugin_internal_options(): void {
		ChangeLogger::begin( 1, 'test' );

		ChangeLogger::on_updated_option( 'gratis_ai_agent_settings', 'old', 'new' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'gratis_ai_agent_settings'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_updated_option() redacts sensitive option values.
	 */
	public function test_on_updated_option_redacts_sensitive_options(): void {
		ChangeLogger::begin( 1, 'test' );

		ChangeLogger::on_updated_option( 'my_api_key', 'old-secret', 'new-secret' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'my_api_key'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( '[REDACTED]', $row->before_value );
		$this->assertSame( '[REDACTED]', $row->after_value );
	}

	/**
	 * on_updated_option() skips when old and new values are identical.
	 */
	public function test_on_updated_option_skips_identical_values(): void {
		ChangeLogger::begin( 1, 'test' );

		ChangeLogger::on_updated_option( 'blogname', 'Same', 'Same' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'blogname'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	// ── on_added_option ───────────────────────────────────────────────────────

	/**
	 * on_added_option() records a new option when active.
	 */
	public function test_on_added_option_records_new_option_when_active(): void {
		ChangeLogger::begin( 3, 'add_option' );

		ChangeLogger::on_added_option( 'my_new_setting', 'hello' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'my_new_setting'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( '', $row->before_value );
		$this->assertSame( 'hello', $row->after_value );
	}

	/**
	 * on_added_option() skips when inactive.
	 */
	public function test_on_added_option_skips_when_inactive(): void {
		ChangeLogger::on_added_option( 'my_new_setting', 'hello' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'my_new_setting'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_added_option() redacts sensitive option values.
	 */
	public function test_on_added_option_redacts_sensitive_options(): void {
		ChangeLogger::begin( 1, 'test' );

		ChangeLogger::on_added_option( 'stripe_secret_key', 'sk_live_secret' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'stripe_secret_key'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( '[REDACTED]', $row->after_value );
	}

	// ── on_edited_term ────────────────────────────────────────────────────────

	/**
	 * on_edited_term() skips when inactive.
	 */
	public function test_on_edited_term_skips_when_inactive(): void {
		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );

		ChangeLogger::on_edited_term( $term->term_id, $term->term_taxonomy_id, 'category' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE object_type = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'term'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_edited_term() records a term change when active.
	 */
	public function test_on_edited_term_records_change_when_active(): void {
		ChangeLogger::begin( 7, 'edit_term' );

		$term = self::factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
				'name'     => 'Test Category',
			]
		);

		ChangeLogger::on_edited_term( $term->term_id, $term->term_taxonomy_id, 'category' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE object_type = %s AND object_id = %d',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'term',
				$term->term_id
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( 'Test Category', $row->object_title );
		$this->assertSame( 'category', $row->field_name );
	}

	/**
	 * on_edited_term() skips when term does not exist.
	 */
	public function test_on_edited_term_skips_nonexistent_term(): void {
		ChangeLogger::begin( 1, 'test' );

		ChangeLogger::on_edited_term( 999999, 999999, 'category' );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE object_id = %d',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				999999
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	// ── on_profile_update ─────────────────────────────────────────────────────

	/**
	 * on_profile_update() skips when inactive.
	 */
	public function test_on_profile_update_skips_when_inactive(): void {
		$user_id  = self::factory()->user->create();
		$old_user = get_userdata( $user_id );

		ChangeLogger::on_profile_update( $user_id, $old_user );

		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE object_type = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'user'
			)
		);
		$this->assertSame( '0', (string) $count );
	}

	/**
	 * on_profile_update() records display_name change when active.
	 */
	public function test_on_profile_update_records_display_name_change(): void {
		ChangeLogger::begin( 9, 'update_user' );

		$user_id = self::factory()->user->create( [ 'display_name' => 'Old Name' ] );
		$old_user = get_userdata( $user_id );

		// Update display name.
		wp_update_user( [ 'ID' => $user_id, 'display_name' => 'New Name' ] );
		$new_user = get_userdata( $user_id );

		ChangeLogger::on_profile_update( $user_id, $old_user );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE object_type = %s AND object_id = %d AND field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'user',
				$user_id,
				'display_name'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( 'Old Name', $row->before_value );
	}

	/**
	 * on_profile_update() redacts email changes.
	 */
	public function test_on_profile_update_redacts_email(): void {
		ChangeLogger::begin( 9, 'update_user' );

		$user_id  = self::factory()->user->create( [ 'user_email' => 'old@example.com' ] );
		$old_user = get_userdata( $user_id );

		// Simulate email change by modifying old_user data.
		$old_user->user_email = 'old@example.com';
		wp_update_user( [ 'ID' => $user_id, 'user_email' => 'new@example.com' ] );

		ChangeLogger::on_profile_update( $user_id, $old_user );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE object_type = %s AND object_id = %d AND field_name = %s',
				$wpdb->prefix . 'gratis_ai_agent_changes_log',
				'user',
				$user_id,
				'user_email'
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( '[REDACTED]', $row->before_value );
		$this->assertSame( '[REDACTED]', $row->after_value );
	}
}
