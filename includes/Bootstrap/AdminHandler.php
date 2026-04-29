<?php
/**
 * DI handler for admin-only hooks.
 *
 * Replaces the inline `add_action('admin_menu', ...)` and
 * `add_action('admin_init', ...)` calls in `ai-agent-for-wp.php`.
 *
 * @package SdAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Bootstrap;

use SdAiAgent\Abilities\ToolCapabilities;
use SdAiAgent\Admin\FloatingWidget;
use SdAiAgent\Admin\ModelBenchmarkPage;
use SdAiAgent\Admin\ScreenMetaPanel;
use SdAiAgent\Admin\UnifiedAdminMenu;
use SdAiAgent\Core\Database;
use SdAiAgent\REST\ConnectorsController;
use SdAiAgent\REST\SettingsController;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
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
	container: 'sd-ai-agent',
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
	 * - Connector option update hooks to invalidate the providers cache when
	 *   credentials are changed via the native WP 7.0 Connectors admin page.
	 */
	#[Action( tag: 'admin_init', priority: 10 )]
	public function on_admin_init(): void {
		Database::install();
		ToolCapabilities::register_capabilities( ToolCapabilities::all_ability_ids() );
		UnifiedAdminMenu::handleLegacyRedirects();
		$this->register_connector_cache_hooks();
	}

	/**
	 * Register update_option hooks for WP Connectors API option keys.
	 *
	 * The native WP 7.0 Connectors page (options-connectors.php) writes
	 * connectors_ai_{provider}_api_key options directly.  Hooking into
	 * update_option_{key} ensures the site-wide providers transient cache is
	 * invalidated whenever an external connector change is saved, so admins
	 * see fresh provider data on the next GET /providers request.
	 *
	 * accepted_args=0 is intentional: flush_providers_cache() takes no
	 * parameters, and update_option_{key} passes 3 args we do not need.
	 */
	private function register_connector_cache_hooks(): void {
		foreach ( ConnectorsController::PROVIDERS as $meta ) {
			add_action(
				'update_option_' . $meta['option_key'],
				[ SettingsController::class, 'flush_providers_cache' ],
				10,
				0
			);
		}
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

	/**
	 * Add action links to the plugin listing on plugins.php.
	 *
	 * @param array<string, string> $actions     Plugin action links.
	 * @param string                $plugin_file Path to plugin file relative to plugins directory.
	 * @return array<string, string> Modified action links.
	 */
	#[Filter( tag: 'plugin_action_links', priority: 10 )]
	public function add_plugin_action_links( array $actions, string $plugin_file ): array {
		// Only modify our plugin.
		if ( $plugin_file !== 'ai-agent-for-wp/ai-agent-for-wp.php' ) {
			return $actions;
		}

		$connectors_url = UnifiedAdminMenu::hasNativeConnectorsPage()
			? admin_url( 'options-connectors.php' )
			: admin_url( 'options-general.php?page=options-connectors-wp-admin' );

		$chat_url = admin_url( 'admin.php?page=sd-ai-agent#chat' );

		$actions['sd_chat'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $chat_url ),
			esc_html__( 'Start Chat', 'sd-ai-agent' )
		);

		$actions['sd_connections'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $connectors_url ),
			esc_html__( 'Configure Connections', 'sd-ai-agent' )
		);

		return $actions;
	}
}
