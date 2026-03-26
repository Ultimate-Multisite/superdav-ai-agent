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
	public static function get_menu_items(): array {
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
			[ __CLASS__, 'render' ],
			'dashicons-robot',
			30 // Position after Dashboard
		);

		// Submenu items — all point to the same render callback.
		$menu_items = self::get_menu_items();
		foreach ( $menu_items as $item ) {
			add_submenu_page(
				self::SLUG,
				$item['label'],
				$item['label'],
				$item['capability'],
				self::SLUG . '#/' . $item['slug'],
				[ __CLASS__, 'render' ]
			);
		}

		// Hook for enqueuing assets.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Get the current route from the page parameter.
	 *
	 * @return string
	 */
	public static function get_current_route(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce required, reading only.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : self::SLUG;

		// Extract route from page parameter (e.g., "gratis-ai-agent#/chat" -> "chat").
		if ( strpos( $page, '#/' ) !== false ) {
			$parts = explode( '#/', $page );
			return $parts[1] ?? 'chat';
		}

		return 'chat';
	}

	/**
	 * Enqueue the unified React app.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		// Only enqueue on our pages.
		if ( strpos( $hook_suffix, 'toplevel_page_' . self::SLUG ) !== 0 &&
			strpos( $hook_suffix, '_page_' . self::SLUG ) === false ) {
			return;
		}

		$asset_file = GRATIS_AI_AGENT_DIR . '/build/unified-admin.asset.php';

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
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'gratis-ai-agent-unified-admin',
			GRATIS_AI_AGENT_URL . 'build/unified-admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$current_user = wp_get_current_user();

		wp_localize_script(
			'gratis-ai-agent-unified-admin',
			'gratisAiAgentData',
			[
				'currentUserId'     => get_current_user_id(),
				'currentUserName'   => $current_user->display_name,
				'restNamespace'     => 'gratis-ai-agent/v1',
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'initialRoute'      => self::get_current_route(),
				'menuItems'         => self::get_menu_items(),
				'aiClientAvailable' => function_exists( 'wp_ai_client_prompt' ),
			]
		);
	}

	/**
	 * Render the admin page — mount point for React Router app.
	 */
	public static function render(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			self::render_compatibility_notice();
			return;
		}

		?>
		<div class="wrap gratis-ai-agent-wrap">
			<div id="gratis-ai-agent-root" class="gratis-ai-agent-app"></div>
		</div>
		<?php
	}

	/**
	 * Render compatibility notice when AI Client is not available.
	 */
	private static function render_compatibility_notice(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'The WordPress AI Client SDK is not available. Please check that WordPress 6.9+ is installed or the compatibility layer is loaded.',
						'gratis-ai-agent'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Redirect old menu URLs to new unified structure.
	 * Call this on admin_init to handle legacy bookmarks.
	 */
	public static function handle_legacy_redirects(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe redirect, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		$redirect_map = array(
			'gratis-ai-agent-changes'   => admin_url( 'admin.php?page=gratis-ai-agent#/changes' ),
			'gratis-ai-agent-abilities' => admin_url( 'admin.php?page=gratis-ai-agent#/abilities' ),
			'gratis-ai-agent-settings'  => admin_url( 'admin.php?page=gratis-ai-agent#/settings' ),
			'gratis-ai-agent-benchmark' => admin_url( 'admin.php?page=gratis-ai-agent#/settings' ),
		);

		if ( isset( $redirect_map[ $page ] ) ) {
			wp_safe_redirect( $redirect_map[ $page ] );
			exit;
		}
	}
}
