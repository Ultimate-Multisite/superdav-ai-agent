<?php

declare(strict_types=1);
/**
 * Admin page for exploring all registered WordPress abilities.
 *
 * Lists every registered ability with its name, description, configuration
 * status, required API keys, and meta flags (readonly, destructive).
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbilitiesExplorerAdminPage {

	const SLUG = 'gratis-ai-agent-abilities';

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'AI Abilities Explorer', 'gratis-ai-agent' ),
			__( 'AI Abilities', 'gratis-ai-agent' ),
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render' ]
		);

		if ( $hook ) {
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		}
	}

	/**
	 * Enqueue the built React app only on our page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = GRATIS_AI_AGENT_DIR . '/build/abilities-explorer.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-abilities-explorer',
			GRATIS_AI_AGENT_URL . 'build/style-abilities-explorer.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_style_add_data( 'gratis-ai-agent-abilities-explorer', 'rtl', 'replace' );

		wp_enqueue_script(
			'gratis-ai-agent-abilities-explorer',
			GRATIS_AI_AGENT_URL . 'build/abilities-explorer.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'gratis-ai-agent-abilities-explorer', 'gratis-ai-agent' );

		wp_localize_script(
			'gratis-ai-agent-abilities-explorer',
			'gratisAiAgentAbilities',
			[
				'restUrl'     => rest_url( 'gratis-ai-agent/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'settingsUrl' => admin_url( 'tools.php?page=gratis-ai-agent-settings' ),
			]
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		?>
		<div class="wrap gratis-ai-agent-abilities-wrap">
			<h1><?php esc_html_e( 'AI Abilities Explorer', 'gratis-ai-agent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Browse all registered WordPress abilities available to the AI agent. Configure API keys in Settings to enable provider-specific abilities.', 'gratis-ai-agent' ); ?></p>
			<div id="gratis-ai-agent-abilities-root"></div>
		</div>
		<?php
	}
}
