<?php

declare(strict_types=1);
/**
 * Screen-meta Help tab integration.
 *
 * Adds a compact AI Agent chat panel to the WordPress admin Help tab
 * (the ? icon in the top-right corner of every admin screen).
 * The panel is context-aware: it receives the current screen ID, base,
 * post type, and taxonomy so the AI can tailor its responses.
 *
 * @package AiAgent
 */

namespace AiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScreenMetaPanel {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'current_screen', [ __CLASS__, 'add_help_tab' ] );
	}

	/**
	 * Enqueue the screen-meta React app on every admin page.
	 *
	 * Skips the dedicated AI Agent page (it has its own full-page UI).
	 * Passes structured screen context to the JS via wp_localize_script.
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

		$asset_file = AI_AGENT_DIR . '/build/screen-meta.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'ai-agent-screen-meta',
			AI_AGENT_URL . 'build/style-screen-meta.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'ai-agent-screen-meta',
			AI_AGENT_URL . 'build/screen-meta.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Pass screen context to JS.
		$screen_context = self::get_screen_context();

		wp_localize_script(
			'ai-agent-screen-meta',
			'aiAgentScreenMeta',
			[
				'screenContext' => $screen_context,
				'mountId'       => 'ai-agent-screen-meta-root',
			]
		);
	}

	/**
	 * Add the AI Agent Help tab to the current screen.
	 *
	 * @param \WP_Screen $screen The current WP_Screen object.
	 */
	public static function add_help_tab( \WP_Screen $screen ): void {
		// Only for users who can access the agent.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Require the AI client.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}

		// Skip the dedicated full-page admin page.
		if ( AdminPage::SLUG === $screen->id ) {
			return;
		}

		$screen->add_help_tab(
			[
				'id'      => 'ai-agent-help',
				'title'   => __( 'AI Agent', 'ai-agent' ),
				'content' => '<div id="ai-agent-screen-meta-root"></div>',
			]
		);
	}

	/**
	 * Build a structured context array from the current WP_Screen.
	 *
	 * Returns an associative array with keys: screen_id, base, post_type,
	 * taxonomy, url, page_title. Safe to pass to wp_localize_script.
	 *
	 * @return array<string, string>
	 */
	private static function get_screen_context(): array {
		$context = [
			'screen_id'  => '',
			'base'       => '',
			'post_type'  => '',
			'taxonomy'   => '',
			'url'        => '',
			'page_title' => '',
		];

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return $context;
		}

		$context['screen_id'] = $screen->id;
		$context['base']      = $screen->base;
		$context['post_type'] = $screen->post_type;
		$context['taxonomy']  = $screen->taxonomy;

		// Sanitize the request URI for context (no auth tokens).
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		$context['url'] = admin_url( ltrim( $request_uri, '/' ) );

		// Page title from the screen's parent_title or a generic fallback.
		$context['page_title'] = $screen->get_option( 'title' ) ?? '';

		return $context;
	}
}
