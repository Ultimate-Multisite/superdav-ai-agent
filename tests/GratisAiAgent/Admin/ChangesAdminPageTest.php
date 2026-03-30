<?php

declare(strict_types=1);
/**
 * Test case for ChangesAdminPage class.
 *
 * @package GratisAiAgent
 * @subpackage Tests\Admin
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Admin;

use GratisAiAgent\Admin\ChangesAdminPage;
use WP_UnitTestCase;

/**
 * Test ChangesAdminPage functionality.
 */
class ChangesAdminPageTest extends WP_UnitTestCase {

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
		$this->assertSame( 'gratis-ai-agent-changes', ChangesAdminPage::SLUG );
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	/**
	 * Test register() adds a management page under tools.php.
	 */
	public function test_register_adds_management_page(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		ChangesAdminPage::register();

		global $submenu;
		/** @var array<string, array<int, array<int, string>>> $submenu */
		$this->assertArrayHasKey( 'tools.php', $submenu );

		$slugs = array_column( $submenu['tools.php'], 2 );
		$this->assertContains( ChangesAdminPage::SLUG, $slugs );
	}

	/**
	 * Test register() hooks admin_enqueue_scripts when page is registered.
	 */
	public function test_register_hooks_enqueue_scripts(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		ChangesAdminPage::register();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts', [ 'GratisAiAgent\Admin\ChangesAdminPage', 'enqueue_assets' ] )
		);
	}

	// ─── enqueue_assets ───────────────────────────────────────────────────────

	/**
	 * Test enqueue_assets() skips non-matching hook suffix.
	 */
	public function test_enqueue_assets_skips_wrong_hook(): void {
		wp_set_current_user( $this->admin_id );

		ChangesAdminPage::enqueue_assets( 'dashboard' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-changes-page', 'enqueued' ) );
	}

	/**
	 * Test enqueue_assets() skips when asset file does not exist.
	 */
	public function test_enqueue_assets_skips_missing_asset_file(): void {
		wp_set_current_user( $this->admin_id );

		// Override build dir to a path that does not exist so file_exists() returns false.
		add_filter( 'gratis_ai_agent_build_dir', static fn() => '/nonexistent/path' );

		ChangesAdminPage::enqueue_assets( 'tools_page_' . ChangesAdminPage::SLUG );

		remove_all_filters( 'gratis_ai_agent_build_dir' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-changes-page', 'enqueued' ) );
	}

	// ─── render ───────────────────────────────────────────────────────────────

	/**
	 * Test render() outputs the changes wrap div.
	 */
	public function test_render_outputs_wrap_div(): void {
		ob_start();
		ChangesAdminPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gratis-ai-agent-changes-wrap', $output );
	}

	/**
	 * Test render() outputs the React mount point.
	 */
	public function test_render_outputs_mount_point(): void {
		ob_start();
		ChangesAdminPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gratis-ai-agent-changes-root', $output );
	}

	/**
	 * Test render() outputs the page heading.
	 */
	public function test_render_outputs_heading(): void {
		ob_start();
		ChangesAdminPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'AI Changes', $output );
	}

	/**
	 * Test render() outputs the page description.
	 */
	public function test_render_outputs_description(): void {
		ob_start();
		ChangesAdminPage::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'diffs', $output );
	}
}
