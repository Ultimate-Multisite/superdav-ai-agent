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
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Admin;

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
	 * Skips the dedicated Gratis AI Agent page (it has its own full-page UI).
	 * Passes structured screen context to the JS via wp_localize_script.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		// Skip the dedicated full-page admin page (UnifiedAdminMenu registers as
		// a top-level menu page, so the hook suffix is toplevel_page_<slug>).
		// Submenu pages under the same slug use <parent>_page_<slug> suffixes.
		if ( 'toplevel_page_' . UnifiedAdminMenu::SLUG === $hook_suffix ) {
			return;
		}
		// Also skip any submenu page under the unified admin.
		if ( strpos( $hook_suffix, '_page_' . UnifiedAdminMenu::SLUG ) !== false ) {
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

		$asset_file = GRATIS_AI_AGENT_DIR . '/build/screen-meta.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-screen-meta',
			GRATIS_AI_AGENT_URL . 'build/style-screen-meta.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'gratis-ai-agent-screen-meta',
			GRATIS_AI_AGENT_URL . 'build/screen-meta.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Pass screen context to JS.
		$screen_context = self::get_screen_context();

		wp_localize_script(
			'gratis-ai-agent-screen-meta',
			'gratisAiAgentScreenMeta',
			[
				'screenContext' => $screen_context,
				'mountId'       => 'gratis-ai-agent-screen-meta-root',
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
				'id'      => 'gratis-ai-agent-help',
				'title'   => __( 'AI Agent', 'gratis-ai-agent' ),
				'content' => '<div id="gratis-ai-agent-screen-meta-root"></div>',
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
		// Use site_url() + REQUEST_URI to avoid double /wp-admin/ from admin_url().
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			// @phpstan-ignore-next-line
			? sanitize_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		$context['url'] = $request_uri ? site_url( $request_uri ) : '';

		// Page title from the screen's parent_title or a generic fallback.
		$context['page_title'] = $screen->get_option( 'title' ) ?? '';

		return $context;
	}
}
