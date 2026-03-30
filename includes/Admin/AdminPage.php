<?php

declare(strict_types=1);
/**
 * Admin page for the AI Agent chat UI.
 *
 * Renders a full-page React app (two-column layout with session sidebar).
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPage {

	const SLUG = 'gratis-ai-agent';

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'Gratis AI Agent', 'gratis-ai-agent' ),
			__( 'Gratis AI Agent', 'gratis-ai-agent' ),
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
		$asset_file = $build_dir . '/admin-page.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-admin-page',
			GRATIS_AI_AGENT_URL . 'build/style-admin-page.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'gratis-ai-agent-admin-page',
			GRATIS_AI_AGENT_URL . 'build/admin-page.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'gratis-ai-agent-admin-page',
			'gratisAiAgentData',
			[
				'currentUserId' => get_current_user_id(),
			]
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Gratis AI Agent', 'gratis-ai-agent' ) . '</h1>';
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'The WordPress AI Client SDK is not available. Please check the compatibility layer.', 'gratis-ai-agent' );
			echo '</p></div></div>';
			return;
		}

		?>
		<div class="wrap gratis-ai-agent-admin-wrap">
			<h1><?php esc_html_e( 'Gratis AI Agent', 'gratis-ai-agent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Chat with an AI assistant that can interact with your WordPress site using registered abilities.', 'gratis-ai-agent' ); ?></p>
			<div id="gratis-ai-agent-root"></div>
		</div>
		<?php
	}
}
