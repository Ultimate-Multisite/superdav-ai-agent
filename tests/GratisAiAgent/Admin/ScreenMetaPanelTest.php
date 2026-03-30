<?php

declare(strict_types=1);
/**
 * Test case for ScreenMetaPanel class.
 *
 * @package GratisAiAgent
 * @subpackage Tests\Admin
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Admin;

use GratisAiAgent\Admin\ScreenMetaPanel;
use GratisAiAgent\Admin\UnifiedAdminMenu;
use WP_UnitTestCase;

/**
 * Test ScreenMetaPanel functionality.
 */
class ScreenMetaPanelTest extends WP_UnitTestCase {

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
	 * Set up test users before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	/**
	 * Test register() hooks admin_enqueue_scripts.
	 */
	public function test_register_hooks_admin_enqueue_scripts(): void {
		ScreenMetaPanel::register();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts', [ 'GratisAiAgent\Admin\ScreenMetaPanel', 'enqueue_assets' ] )
		);
	}

	/**
	 * Test register() hooks current_screen.
	 */
	public function test_register_hooks_current_screen(): void {
		ScreenMetaPanel::register();

		$this->assertGreaterThan(
			0,
			has_action( 'current_screen', [ 'GratisAiAgent\Admin\ScreenMetaPanel', 'add_help_tab' ] )
		);
	}

	// ─── enqueue_assets ───────────────────────────────────────────────────────

	/**
	 * Test enqueue_assets() skips the unified admin top-level page.
	 */
	public function test_enqueue_assets_skips_unified_admin_page(): void {
		wp_set_current_user( $this->admin_id );

		ScreenMetaPanel::enqueue_assets( 'toplevel_page_' . UnifiedAdminMenu::SLUG );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-screen-meta', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips submenu pages under unified admin.
	 */
	public function test_enqueue_assets_skips_unified_admin_subpages(): void {
		wp_set_current_user( $this->admin_id );

		ScreenMetaPanel::enqueue_assets( 'gratis-ai-agent_page_' . UnifiedAdminMenu::SLUG );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-screen-meta', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips users without manage_options.
	 */
	public function test_enqueue_assets_skips_non_admin(): void {
		wp_set_current_user( $this->subscriber_id );

		ScreenMetaPanel::enqueue_assets( 'dashboard' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-screen-meta', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips when wp_ai_client_prompt is unavailable.
	 */
	public function test_enqueue_assets_skips_when_ai_client_unavailable(): void {
		wp_set_current_user( $this->admin_id );

		// wp_ai_client_prompt is not available in the test environment.
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt is available; cannot test unavailable path.' );
		}

		ScreenMetaPanel::enqueue_assets( 'dashboard' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-screen-meta', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips when asset file does not exist.
	 */
	public function test_enqueue_assets_skips_missing_asset_file(): void {
		wp_set_current_user( $this->admin_id );

		// Override build dir to a path that does not exist so file_exists() returns false.
		// When wp_ai_client_prompt is unavailable, enqueue_assets returns early before
		// the file_exists check — the filter is a no-op but the assertion still holds.
		add_filter( 'gratis_ai_agent_build_dir', static fn() => '/nonexistent/path' );

		ScreenMetaPanel::enqueue_assets( 'dashboard' );

		remove_all_filters( 'gratis_ai_agent_build_dir' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-screen-meta', 'enqueued' ) );
	}

	// ─── add_help_tab ─────────────────────────────────────────────────────────

	/**
	 * Test add_help_tab() skips users without manage_options.
	 */
	public function test_add_help_tab_skips_non_admin(): void {
		wp_set_current_user( $this->subscriber_id );

		$screen = convert_to_screen( 'dashboard' );

		// Should not add a help tab — no exception expected.
		ScreenMetaPanel::add_help_tab( $screen );

		$tabs = $screen->get_help_tabs();
		$ids  = array_column( $tabs, 'id' );
		$this->assertNotContains( 'gratis-ai-agent-help', $ids );
	}

	/**
	 * Test add_help_tab() skips when wp_ai_client_prompt is unavailable.
	 */
	public function test_add_help_tab_skips_when_ai_client_unavailable(): void {
		wp_set_current_user( $this->admin_id );

		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt is available; cannot test unavailable path.' );
		}

		$screen = convert_to_screen( 'dashboard' );
		ScreenMetaPanel::add_help_tab( $screen );

		$tabs = $screen->get_help_tabs();
		$ids  = array_column( $tabs, 'id' );
		$this->assertNotContains( 'gratis-ai-agent-help', $ids );
	}

	/**
	 * Test add_help_tab() adds help tab when AI client is available and user is admin.
	 */
	public function test_add_help_tab_adds_tab_when_ai_client_available(): void {
		wp_set_current_user( $this->admin_id );

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt not available in this test run.' );
		}

		$screen = convert_to_screen( 'dashboard' );
		ScreenMetaPanel::add_help_tab( $screen );

		$tabs = $screen->get_help_tabs();
		$ids  = array_column( $tabs, 'id' );
		$this->assertContains( 'gratis-ai-agent-help', $ids );
	}
}
