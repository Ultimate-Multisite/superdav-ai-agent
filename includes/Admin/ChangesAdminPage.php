<?php

declare(strict_types=1);
/**
 * Admin page for viewing and managing AI-made changes.
 *
 * Renders a React app that shows diffs, allows reverting individual changes,
 * and can export changes as patch files.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChangesAdminPage {

	const SLUG = 'gratis-ai-agent-changes';

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'AI Changes', 'gratis-ai-agent' ),
			__( 'AI Changes', 'gratis-ai-agent' ),
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

		$build_dir  = (string) apply_filters( 'gratis_ai_agent_build_dir', GRATIS_AI_AGENT_DIR . '/build' );
		$asset_file = $build_dir . '/changes-page.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-changes-page',
			GRATIS_AI_AGENT_URL . 'build/style-changes-page.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'gratis-ai-agent-changes-page',
			GRATIS_AI_AGENT_URL . 'build/changes-page.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'gratis-ai-agent-changes-page', 'gratis-ai-agent' );

		wp_localize_script(
			'gratis-ai-agent-changes-page',
			'gratisAiAgentChanges',
			[
				'restUrl' => rest_url( 'gratis-ai-agent/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		?>
		<div class="wrap gratis-ai-agent-changes-wrap">
			<h1><?php esc_html_e( 'AI Changes', 'gratis-ai-agent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'View all changes made by the AI agent, inspect diffs, revert individual changes, and export patches.', 'gratis-ai-agent' ); ?></p>
			<div id="gratis-ai-agent-changes-root"></div>
		</div>
		<?php
	}
}
