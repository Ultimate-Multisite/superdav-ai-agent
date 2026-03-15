<?php

declare(strict_types=1);
/**
 * Abilities Explorer admin page.
 *
 * Read-only reference page listing all registered WordPress abilities with
 * their full metadata: name, description, annotations (readonly/destructive/
 * idempotent), output_schema, and show_in_rest status.
 *
 * @package AiAgent
 */

namespace AiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbilitiesExplorer {

	const SLUG = 'ai-agent-abilities';

	/**
	 * Register the admin submenu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'Abilities Explorer', 'ai-agent' ),
			__( 'Abilities Explorer', 'ai-agent' ),
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

		$asset_file = AI_AGENT_DIR . '/build/abilities-explorer.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'ai-agent-abilities-explorer',
			AI_AGENT_URL . 'build/style-abilities-explorer.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'ai-agent-abilities-explorer',
			AI_AGENT_URL . 'build/abilities-explorer.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		?>
		<div class="wrap ai-agent-abilities-explorer-wrap">
			<h1><?php esc_html_e( 'Abilities Explorer', 'ai-agent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'All registered WordPress abilities and their metadata. This is a read-only reference page.', 'ai-agent' ); ?></p>
			<div id="ai-agent-abilities-explorer-root"></div>
		</div>
		<?php
	}
}
