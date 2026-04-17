<?php
/**
 * DI handler for admin-only hooks.
 *
 * Replaces the inline `add_action('admin_menu', ...)` and
 * `add_action('admin_init', ...)` calls in `gratis-ai-agent.php`.
 *
 * @package GratisAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\Abilities\ToolCapabilities;
use GratisAiAgent\Admin\FloatingWidget;
use GratisAiAgent\Admin\ModelBenchmarkPage;
use GratisAiAgent\Admin\ScreenMetaPanel;
use GratisAiAgent\Admin\UnifiedAdminMenu;
use GratisAiAgent\Core\Database;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin menus, capabilities, DB safety-net, and admin assets.
 *
 * Context CTX_ADMIN ensures this handler only loads on admin pages —
 * saving hook registration overhead on frontend/REST/CLI requests.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_ADMIN,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class AdminHandler {

	/**
	 * Admin menu registration.
	 *
	 * - Unified top-level menu (hash-based React routing).
	 * - Benchmark page under Tools.
	 */
	#[Action( tag: 'admin_menu', priority: 10 )]
	public function register_menus(): void {
		UnifiedAdminMenu::register();
		ModelBenchmarkPage::register();
	}

	/**
	 * Admin init hooks.
	 *
	 * - DB schema safety-net (dbDelta is no-op when schema is current).
	 * - Per-tool capabilities for role-management plugins.
	 * - Legacy URL redirects to unified menu.
	 */
	#[Action( tag: 'admin_init', priority: 10 )]
	public function on_admin_init(): void {
		Database::install();
		ToolCapabilities::register_capabilities( ToolCapabilities::all_ability_ids() );
		UnifiedAdminMenu::handleLegacyRedirects();
	}

	/**
	 * Enqueue admin-only assets for the floating widget and screen-meta panel.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	#[Action( tag: 'admin_enqueue_scripts', priority: 10 )]
	public function enqueue_admin_assets( string $hook_suffix ): void {
		FloatingWidget::enqueue_assets_admin( $hook_suffix );
		ScreenMetaPanel::enqueue_assets( $hook_suffix );
	}

	/**
	 * Add the Help tab chat panel to every admin screen.
	 *
	 * @param \WP_Screen $screen Current screen object.
	 */
	#[Action( tag: 'current_screen', priority: 10 )]
	public function add_help_tab( \WP_Screen $screen ): void {
		ScreenMetaPanel::add_help_tab( $screen );
	}
}
