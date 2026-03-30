<?php

declare(strict_types=1);
/**
 * Floating chat widget available on all admin pages and optionally on the frontend.
 *
 * Enqueues a lightweight React app that renders a FAB button
 * and expandable chat panel in the bottom-right corner.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Admin;

use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FloatingWidget {

	/**
	 * Register the admin_enqueue_scripts and (optionally) wp_enqueue_scripts hooks.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets_admin' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets_frontend' ] );
	}

	/**
	 * Enqueue the floating widget on every admin page.
	 *
	 * Skips the dedicated AI Agent page (it has its own full-page UI).
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_assets_admin( string $hook_suffix ): void {
		// Skip the dedicated full-page admin page (UnifiedAdminMenu registers as
		// a top-level menu page, so the hook suffix is toplevel_page_<slug>).
		// Submenu pages under the same slug use <parent>_page_<slug> suffixes.
		if ( 'toplevel_page_' . UnifiedAdminMenu::SLUG === $hook_suffix ) {
			return;
		}
		// Also skip any submenu page under the unified admin (e.g. abilities, settings).
		if ( strpos( $hook_suffix, '_page_' . UnifiedAdminMenu::SLUG ) !== false ) {
			return;
		}

		// Only for users who can access the agent.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Note: wp_ai_client_prompt() availability is NOT checked here.
		// The floating widget UI (FAB + panel) renders independently of the AI
		// client. The REST API handles provider availability at message-send time.

		self::enqueue_widget_assets();
	}

	/**
	 * Enqueue the floating widget on frontend pages when enabled in settings.
	 *
	 * Only loads for logged-in users with manage_options capability.
	 */
	public static function enqueue_assets_frontend(): void {
		$settings = Settings::get();

		// Only when the frontend display setting is enabled.
		// @phpstan-ignore-next-line
		if ( empty( $settings['show_on_frontend'] ) ) {
			return;
		}

		// Only for users who can access the agent.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Note: wp_ai_client_prompt() availability is NOT checked here.
		// The floating widget UI (FAB + panel) renders independently of the AI
		// client. The REST API handles provider availability at message-send time.

		self::enqueue_widget_assets();
	}

	/**
	 * Shared asset enqueueing logic for both admin and frontend contexts.
	 */
	private static function enqueue_widget_assets(): void {
		$build_dir  = (string) apply_filters( 'gratis_ai_agent_build_dir', GRATIS_AI_AGENT_DIR . '/build' );
		$asset_file = $build_dir . '/floating-widget.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
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

		// Detect fresh install and pass site-builder context to the widget.
		$is_fresh     = FreshInstallDetector::isFreshInstall();
		$site_builder = (bool) Settings::get( 'site_builder_mode' );

		// Auto-enable site_builder_mode on first detection of a fresh install.
		if ( $is_fresh && ! $site_builder ) {
			Settings::update( [ 'site_builder_mode' => true ] );
			$site_builder = true;
		}

		wp_localize_script(
			'gratis-ai-agent-floating-widget',
			'gratisAiAgentSiteBuilder',
			[
				'isFreshInstall'  => $is_fresh,
				'siteBuilderMode' => $site_builder,
			]
		);

		// Pass white-label branding values to the widget (t075).
		$branding = Settings::get();
		wp_localize_script(
			'gratis-ai-agent-floating-widget',
			'gratisAiAgentBranding',
			array(
				// @phpstan-ignore-next-line
				'agentName'       => (string) ( $branding['agent_name'] ?? '' ),
				// @phpstan-ignore-next-line
				'primaryColor'    => (string) ( $branding['brand_primary_color'] ?? '' ),
				// @phpstan-ignore-next-line
				'textColor'       => (string) ( $branding['brand_text_color'] ?? '' ),
				// @phpstan-ignore-next-line
				'logoUrl'         => (string) ( $branding['brand_logo_url'] ?? '' ),
				// @phpstan-ignore-next-line
				'greetingMessage' => (string) ( $branding['greeting_message'] ?? '' ),
			)
		);

		wp_localize_script(
			'gratis-ai-agent-floating-widget',
			'gratisAiAgentData',
			[
				'currentUserId' => get_current_user_id(),
			]
		);
	}
}
