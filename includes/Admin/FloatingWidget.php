<?php

declare(strict_types=1);
/**
 * Floating chat widget available on all admin pages and optionally on the frontend.
 *
 * Enqueues a lightweight React app that renders a FAB button
 * and expandable chat panel in the bottom-right corner.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Admin;

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
		if ( empty( $settings['show_on_frontend'] ) ) {
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

		self::enqueue_widget_assets();
	}

	/**
	 * Shared asset enqueueing logic for both admin and frontend contexts.
	 */
	private static function enqueue_widget_assets(): void {
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

		// Pass site builder mode flag to the widget.
		$settings = Settings::get();
		wp_localize_script(
			'gratis-ai-agent-floating-widget',
			'gratisAiAgentSiteBuilder',
			[
				'siteBuilderMode'    => ! empty( $settings['site_builder_mode'] ),
				'onboardingComplete' => ! empty( $settings['onboarding_complete'] ),
				'startEndpoint'      => rest_url( 'gratis-ai-agent/v1/site-builder/start' ),
				'statusEndpoint'     => rest_url( 'gratis-ai-agent/v1/site-builder/status' ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}
