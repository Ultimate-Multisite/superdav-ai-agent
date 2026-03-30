<?php

declare(strict_types=1);
/**
 * Test case for AdminPage class.
 *
 * @package GratisAiAgent
 * @subpackage Tests\Admin
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Admin;

use GratisAiAgent\Admin\AdminPage;
use WP_UnitTestCase;

/**
 * Test AdminPage functionality.
 */
class AdminPageTest extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected int $admin_id;

	/**
	 * Set up test user before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	// ─── Constants ────────────────────────────────────────────────────────────

	/**
	 * Test SLUG constant value.
	 */
	public function test_slug_constant(): void {
		$this->assertSame( 'gratis-ai-agent', AdminPage::SLUG );
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	/**
	 * Test register() adds a management page.
	 */
	public function test_register_adds_management_page(): void {
		wp_set_current_user( $this->admin_id );

		// Trigger admin_menu to allow add_management_page to work.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		AdminPage::register();

		global $submenu;
		// add_management_page registers under 'tools.php'.
		/** @var array<string, array<int, array<int, string>>> $submenu */
		$this->assertArrayHasKey( 'tools.php', $submenu );

		$slugs = array_column( $submenu['tools.php'], 2 );
		$this->assertContains( AdminPage::SLUG, $slugs );
	}

	/**
	 * Test register() hooks admin_enqueue_scripts when page is registered.
	 */
	public function test_register_hooks_enqueue_scripts(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		AdminPage::register();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts', [ 'GratisAiAgent\Admin\AdminPage', 'enqueue_assets' ] )
		);
	}

	// ─── enqueue_assets ───────────────────────────────────────────────────────

	/**
	 * Test enqueue_assets() skips non-matching hook suffix.
	 */
	public function test_enqueue_assets_skips_wrong_hook(): void {
		wp_set_current_user( $this->admin_id );

		// Call with a different hook suffix — should not enqueue anything.
		AdminPage::enqueue_assets( 'dashboard' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-admin-page', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips when asset file does not exist.
	 */
	public function test_enqueue_assets_skips_missing_asset_file(): void {
		wp_set_current_user( $this->admin_id );

		// Override build dir to a path that does not exist so file_exists() returns false.
		add_filter( 'gratis_ai_agent_build_dir', static fn() => '/nonexistent/path' );

		AdminPage::enqueue_assets( 'tools_page_' . AdminPage::SLUG );

		remove_all_filters( 'gratis_ai_agent_build_dir' );

		// Script should not be enqueued since asset file is missing.
		$this->assertFalse( wp_script_is( 'gratis-ai-agent-admin-page', 'enqueued' ) );
	}

	// ─── render ───────────────────────────────────────────────────────────────

	/**
	 * Test render() outputs compatibility notice when wp_ai_client_prompt is unavailable.
	 */
	public function test_render_outputs_notice_when_ai_client_unavailable(): void {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is available; cannot test unavailable path.' );
		}

		ob_start();
		AdminPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'AI Client SDK', $output );
	}

	/**
	 * Test render() outputs wrap div when wp_ai_client_prompt is available.
	 */
	public function test_render_outputs_wrap_when_ai_client_available(): void {
		// Temporarily define the function if not already defined.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Test stub for WP core function.
			function wp_ai_client_prompt() {
				return null;
			}
		}

		ob_start();
		AdminPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gratis-ai-agent-admin-wrap', $output );
		$this->assertStringContainsString( 'gratis-ai-agent-root', $output );
	}

	/**
	 * Test render() outputs the page heading.
	 */
	public function test_render_outputs_heading(): void {
		ob_start();
		AdminPage::render();
		$output = ob_get_clean();

		// Either the notice or the full page — both should mention the plugin name.
		$this->assertStringContainsString( 'Gratis AI Agent', $output );
	}
}
