<?php

declare(strict_types=1);
/**
 * Test case for UnifiedAdminMenu class.
 *
 * @package GratisAiAgent
 * @subpackage Tests\Admin
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Admin;

use GratisAiAgent\Admin\UnifiedAdminMenu;
use WP_UnitTestCase;

/**
 * Test UnifiedAdminMenu functionality.
 */
class UnifiedAdminMenuTest extends WP_UnitTestCase {

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
		$this->assertSame( 'gratis-ai-agent', UnifiedAdminMenu::SLUG );
	}

	/**
	 * Test CAPABILITY constant value.
	 */
	public function test_capability_constant(): void {
		$this->assertSame( 'manage_options', UnifiedAdminMenu::CAPABILITY );
	}

	// ─── getMenuItems ─────────────────────────────────────────────────────────

	/**
	 * Test getMenuItems() returns an array.
	 */
	public function test_get_menu_items_returns_array(): void {
		$items = UnifiedAdminMenu::getMenuItems();

		$this->assertIsArray( $items );
	}

	/**
	 * Test getMenuItems() returns at least 4 items.
	 *
	 * On WP 6.9 (no native Connectors page) a 5th Connectors item is added;
	 * on WP 7.0+ the native page handles connectors so only 4 items appear.
	 */
	public function test_get_menu_items_returns_four_items(): void {
		$items = UnifiedAdminMenu::getMenuItems();

		$this->assertGreaterThanOrEqual( 4, count( $items ) );
	}

	/**
	 * Test getMenuItems() includes chat item.
	 */
	public function test_get_menu_items_includes_chat(): void {
		$items = UnifiedAdminMenu::getMenuItems();
		$slugs = array_column( $items, 'slug' );

		$this->assertContains( 'chat', $slugs );
	}

	/**
	 * Test getMenuItems() includes abilities item.
	 */
	public function test_get_menu_items_includes_abilities(): void {
		$items = UnifiedAdminMenu::getMenuItems();
		$slugs = array_column( $items, 'slug' );

		$this->assertContains( 'abilities', $slugs );
	}

	/**
	 * Test getMenuItems() includes changes item.
	 */
	public function test_get_menu_items_includes_changes(): void {
		$items = UnifiedAdminMenu::getMenuItems();
		$slugs = array_column( $items, 'slug' );

		$this->assertContains( 'changes', $slugs );
	}

	/**
	 * Test getMenuItems() includes settings item.
	 */
	public function test_get_menu_items_includes_settings(): void {
		$items = UnifiedAdminMenu::getMenuItems();
		$slugs = array_column( $items, 'slug' );

		$this->assertContains( 'settings', $slugs );
	}

	/**
	 * Test each menu item has required keys.
	 */
	public function test_get_menu_items_have_required_keys(): void {
		$items = UnifiedAdminMenu::getMenuItems();

		foreach ( $items as $item ) {
			$this->assertArrayHasKey( 'slug', $item );
			$this->assertArrayHasKey( 'label', $item );
			$this->assertArrayHasKey( 'icon', $item );
			$this->assertArrayHasKey( 'position', $item );
			$this->assertArrayHasKey( 'capability', $item );
		}
	}

	/**
	 * Test each menu item capability is manage_options.
	 */
	public function test_get_menu_items_capability_is_manage_options(): void {
		$items = UnifiedAdminMenu::getMenuItems();

		foreach ( $items as $item ) {
			$this->assertSame( 'manage_options', $item['capability'] );
		}
	}

	/**
	 * Test menu items are ordered by position.
	 */
	public function test_get_menu_items_ordered_by_position(): void {
		$items     = UnifiedAdminMenu::getMenuItems();
		$positions = array_column( $items, 'position' );
		$sorted    = $positions;
		sort( $sorted );

		$this->assertSame( $sorted, $positions );
	}

	/**
	 * Test getMenuItems() always includes chat, abilities, changes, settings.
	 */
	public function test_get_menu_items_includes_core_items(): void {
		$items = UnifiedAdminMenu::getMenuItems();
		$slugs = array_column( $items, 'slug' );

		$this->assertContains( 'chat', $slugs );
		$this->assertContains( 'abilities', $slugs );
		$this->assertContains( 'changes', $slugs );
		$this->assertContains( 'settings', $slugs );
	}

	/**
	 * Test hasNativeConnectorsPage() returns a boolean.
	 */
	public function test_has_native_connectors_page_returns_bool(): void {
		$result = UnifiedAdminMenu::hasNativeConnectorsPage();
		$this->assertIsBool( $result );
	}

	/**
	 * Test getConnectorsUrl() returns a non-empty string.
	 */
	public function test_get_connectors_url_returns_string(): void {
		$url = UnifiedAdminMenu::getConnectorsUrl();
		$this->assertIsString( $url );
		$this->assertNotEmpty( $url );
	}

	/**
	 * Test getMenuItems() conditionally includes Connectors item.
	 *
	 * When there is no native WP 7.0+ Connectors page, a Connectors item is
	 * added to the unified admin menu. When the native page exists, it is
	 * omitted so users go to the core page instead.
	 */
	public function test_get_menu_items_connectors_conditional(): void {
		$items = UnifiedAdminMenu::getMenuItems();
		$slugs = array_column( $items, 'slug' );

		if ( UnifiedAdminMenu::hasNativeConnectorsPage() ) {
			$this->assertNotContains( 'connectors', $slugs );
		} else {
			$this->assertContains( 'connectors', $slugs );
		}
	}

	// ─── getCurrentRoute ──────────────────────────────────────────────────────

	/**
	 * Test getCurrentRoute() returns 'chat' as default.
	 */
	public function test_get_current_route_returns_chat(): void {
		$route = UnifiedAdminMenu::getCurrentRoute();

		$this->assertSame( 'chat', $route );
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	/**
	 * Test register() adds a top-level menu page.
	 */
	public function test_register_adds_top_level_menu(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		UnifiedAdminMenu::register();

		global $menu;
		/** @var array<int, array<int, string>> $menu */
		$slugs = array_column( $menu, 2 );
		$this->assertContains( UnifiedAdminMenu::SLUG, $slugs );
	}

	/**
	 * Test register() hooks admin_enqueue_scripts.
	 */
	public function test_register_hooks_enqueue_scripts(): void {
		wp_set_current_user( $this->admin_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress core hook.
		do_action( 'admin_menu' );

		UnifiedAdminMenu::register();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts', [ 'GratisAiAgent\Admin\UnifiedAdminMenu', 'enqueueAssets' ] )
		);
	}

	// ─── enqueueAssets ────────────────────────────────────────────────────────

	/**
	 * Test enqueueAssets() skips non-matching hook suffix.
	 */
	public function test_enqueue_assets_skips_wrong_hook(): void {
		wp_set_current_user( $this->admin_id );

		UnifiedAdminMenu::enqueueAssets( 'dashboard' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-unified-admin', 'enqueued' ) );
	}

	/**
	 * Test enqueueAssets() skips when asset file does not exist.
	 */
	public function test_enqueue_assets_skips_missing_asset_file(): void {
		wp_set_current_user( $this->admin_id );

		// Override build dir to a path that does not exist so file_exists() returns false.
		add_filter( 'gratis_ai_agent_build_dir', static fn() => '/nonexistent/path' );

		UnifiedAdminMenu::enqueueAssets( 'toplevel_page_' . UnifiedAdminMenu::SLUG );

		remove_all_filters( 'gratis_ai_agent_build_dir' );

		$this->assertFalse( wp_script_is( 'gratis-ai-agent-unified-admin', 'enqueued' ) );
	}

	// ─── render ───────────────────────────────────────────────────────────────

	/**
	 * Test render() outputs the React mount point.
	 */
	public function test_render_outputs_mount_point(): void {
		ob_start();
		UnifiedAdminMenu::render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'gratis-ai-agent-root', $output );
		$this->assertStringContainsString( 'gratis-ai-agent-wrap', $output );
	}

	// ─── handleLegacyRedirects ────────────────────────────────────────────────

	/**
	 * Test handleLegacyRedirects() does nothing when no page param is set.
	 */
	public function test_handle_legacy_redirects_no_page_param(): void {
		// Ensure $_GET['page'] is not set.
		unset( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Should not throw or redirect.
		UnifiedAdminMenu::handleLegacyRedirects();

		// If we reach here without a redirect, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test handleLegacyRedirects() does nothing for non-legacy page slugs.
	 */
	public function test_handle_legacy_redirects_ignores_unknown_page(): void {
		$_GET['page'] = 'some-other-plugin'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Should not throw or redirect.
		UnifiedAdminMenu::handleLegacyRedirects();

		// If we reach here without a redirect, the test passes.
		$this->assertTrue( true );

		unset( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
