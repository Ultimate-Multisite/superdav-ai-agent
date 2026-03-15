<?php

declare(strict_types=1);
/**
 * Floating chat widget available on all admin pages.
 *
 * Enqueues a lightweight React app that renders a FAB button
 * and expandable chat panel in the bottom-right corner.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FloatingWidget {

	/**
	 * Register the admin_enqueue_scripts hook.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue the floating widget on every admin page.
	 *
	 * Skips the dedicated AI Agent page (it has its own full-page UI).
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		// Skip the dedicated full-page admin page.
		if ( 'tools_page_' . AdminPage::SLUG === $hook_suffix ) {
			return;
		}

		// Only for users who can access the agent.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Require the AI client.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}

		$asset_file = GRATIS_AI_AGENT_DIR . '/build/floating-widget.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-floating-widget',
			GRATIS_AI_AGENT_URL . 'build/style-floating-widget.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'gratis-ai-agent-floating-widget',
			GRATIS_AI_AGENT_URL . 'build/floating-widget.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}
}
