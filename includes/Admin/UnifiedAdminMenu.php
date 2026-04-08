<?php

declare(strict_types=1);
/**
 * Unified admin menu for all AI Agent pages.
 *
 * Creates a top-level menu with submenu items, all using a single React app
 * with client-side routing.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UnifiedAdminMenu {

	const SLUG       = 'gratis-ai-agent';
	const CAPABILITY = 'manage_options';

	/**
	 * Menu configuration for React Router routes.
	 *
	 * @return array
	 */
	public static function getMenuItems(): array {
		return array(
			array(
				'slug'       => 'chat',
				'label'      => __( 'Chat', 'gratis-ai-agent' ),
				'icon'       => 'dashicons-format-chat',
				'position'   => 0,
				'capability' => self::CAPABILITY,
			),
			array(
				'slug'       => 'abilities',
				'label'      => __( 'Abilities', 'gratis-ai-agent' ),
				'icon'       => 'dashicons-admin-tools',
				'position'   => 10,
				'capability' => self::CAPABILITY,
			),
			array(
				'slug'       => 'changes',
				'label'      => __( 'Changes', 'gratis-ai-agent' ),
				'icon'       => 'dashicons-backup',
				'position'   => 20,
				'capability' => self::CAPABILITY,
			),
			array(
				'slug'       => 'settings',
				'label'      => __( 'Settings', 'gratis-ai-agent' ),
				'icon'       => 'dashicons-admin-settings',
				'position'   => 30,
				'capability' => self::CAPABILITY,
			),
		);
	}

	/**
	 * Register the unified admin menu.
	 */
	public static function register(): void {
		// Top-level menu.
		add_menu_page(
			__( 'Gratis AI Agent', 'gratis-ai-agent' ),
			__( 'AI Agent', 'gratis-ai-agent' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' ),
			GRATIS_AI_AGENT_URL . 'assets/menu-icon.svg',
			30 // Position after Dashboard
		);

		// Submenu items — all point to the same render callback.
		// The first submenu uses the parent slug to replace the auto-generated
		// duplicate WordPress creates when add_menu_page has a render callback.
		$menu_items = self::getMenuItems();
		foreach ( $menu_items as $index => $item ) {
			add_submenu_page(
				self::SLUG,
				$item['label'],
				$item['label'],
				$item['capability'],
				0 === $index ? self::SLUG : self::SLUG . '#/' . $item['slug'],
				array( __CLASS__, 'render' )
			);
		}

		// Hook for enqueuing assets.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueueAssets' ) );
	}

	/**
	 * Get the initial route hint for the localized script data.
	 *
	 * PHP never receives URL fragments (browsers strip them before sending HTTP
	 * requests), so deep-link detection must happen client-side. The JS entry
	 * point (index.js) reads window.location.hash directly and uses it as the
	 * authoritative initial route, falling back to this PHP-provided value only
	 * when no hash is present (e.g. a plain admin.php?page=gratis-ai-agent load).
	 *
	 * @return string Always 'chat' — the default route when no hash is present.
	 */
	public static function getCurrentRoute(): string {
		return 'chat';
	}

	/**
	 * Enqueue the unified React app.
	 *
	 * Also enqueues the admin-page bundle which exposes window.gratisAiAgentChat.
	 * ChatRoute (src/unified-admin/routes/chat.js) calls
	 * window.gratisAiAgentChat.mount(container) to embed the full chat UI
	 * (AdminPageApp) inside the #gratis-ai-chat-container div. Without this
	 * bundle the mount API is undefined and the chat panel never renders.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueueAssets( string $hook_suffix ): void {
		// Only enqueue on our pages.
		if ( strpos( $hook_suffix, 'toplevel_page_' . self::SLUG ) !== 0 &&
			strpos( $hook_suffix, '_page_' . self::SLUG ) === false ) {
			return;
		}

		$build_dir  = (string) apply_filters( 'gratis_ai_agent_build_dir', GRATIS_AI_AGENT_DIR . '/build' );
		$asset_file = $build_dir . '/unified-admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// Show admin notice if build is missing.
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Gratis AI Agent build files are missing. Please run npm run build.', 'gratis-ai-agent' );
					echo '</p></div>';
				}
			);
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-unified-admin',
			GRATIS_AI_AGENT_URL . 'build/style-unified-admin.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_style_add_data( 'gratis-ai-agent-unified-admin', 'rtl', 'replace' );

		wp_enqueue_script(
			'gratis-ai-agent-unified-admin',
			GRATIS_AI_AGENT_URL . 'build/unified-admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'gratis-ai-agent-unified-admin', 'gratis-ai-agent' );

		// WP 7.0+: enqueue the `@wordpress/abilities` script module so our
		// client-side ability registry (src/abilities/*) can resolve the
		// bare specifier via the document import map at runtime. Without
		// this, the dynamic import() in registry.js throws a module
		// resolution error and the gratis-ai-agent-js/* abilities are never
		// registered. (t165 — fixes the missing enqueue in #815.)
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( '@wordpress/abilities' );
		}

		// Enqueue the admin-page bundle which sets window.gratisAiAgentChat.
		// ChatRoute calls window.gratisAiAgentChat.mount(container) to render
		// AdminPageApp inside #gratis-ai-chat-container. This bundle must load
		// after unified-admin.js so the container element exists in the DOM.
		$admin_page_asset_file = $build_dir . '/admin-page.asset.php';
		if ( file_exists( $admin_page_asset_file ) ) {
			/** @var array{dependencies: string[], version: string} $admin_page_asset */
			$admin_page_asset = require $admin_page_asset_file;

			wp_enqueue_style(
				'gratis-ai-agent-admin-page',
				GRATIS_AI_AGENT_URL . 'build/style-admin-page.css',
				array( 'wp-components' ),
				$admin_page_asset['version']
			);

			wp_style_add_data( 'gratis-ai-agent-admin-page', 'rtl', 'replace' );

			wp_enqueue_script(
				'gratis-ai-agent-admin-page',
				GRATIS_AI_AGENT_URL . 'build/admin-page.js',
				array_merge( $admin_page_asset['dependencies'], array( 'gratis-ai-agent-unified-admin' ) ),
				$admin_page_asset['version'],
				true
			);

			wp_set_script_translations( 'gratis-ai-agent-admin-page', 'gratis-ai-agent' );
		}

		$current_user = wp_get_current_user();

		wp_localize_script(
			'gratis-ai-agent-unified-admin',
			'gratisAiAgentData',
			array(
				'currentUserId'   => get_current_user_id(),
				'currentUserName' => $current_user->display_name,
				'restNamespace'   => 'gratis-ai-agent/v1',
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'initialRoute'    => self::getCurrentRoute(),
				'menuItems'       => self::getMenuItems(),
				'connectorsUrl'   => is_multisite()
					? network_admin_url( 'options-connectors.php' )
					: admin_url( 'options-connectors.php' ),
			)
		);
	}

	/**
	 * Render the admin page — mount point for React Router app.
	 */
	public static function render(): void {
		?>
		<div class="wrap gratis-ai-agent-wrap">
			<div id="gratis-ai-agent-root" class="gratis-ai-agent-app"></div>
		</div>
		<?php
	}

	/**
	 * Redirect old menu URLs to new unified structure.
	 * Call this on admin_init to handle legacy bookmarks.
	 */
	public static function handleLegacyRedirects(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe redirect, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		$redirect_map = array(
			'gratis-ai-agent-changes'   => admin_url( 'admin.php?page=gratis-ai-agent#/changes' ),
			'gratis-ai-agent-abilities' => admin_url( 'admin.php?page=gratis-ai-agent#/abilities' ),
			'gratis-ai-agent-settings'  => admin_url( 'admin.php?page=gratis-ai-agent#/settings' ),
		);

		if ( isset( $redirect_map[ $page ] ) ) {
			wp_safe_redirect( $redirect_map[ $page ] );
			exit;
		}
	}
}
