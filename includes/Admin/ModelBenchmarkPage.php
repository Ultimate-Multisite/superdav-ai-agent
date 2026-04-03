<?php

declare(strict_types=1);
/**
 * Admin page for Model Benchmarking.
 *
 * Allows advanced users to benchmark AI models against WordPress knowledge tests.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ModelBenchmarkPage {

	const SLUG = 'gratis-ai-agent-benchmark';

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'Model Benchmark', 'gratis-ai-agent' ),
			__( 'Model Benchmark', 'gratis-ai-agent' ),
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
		$asset_file = $build_dir . '/benchmark-page.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
		$asset = require $asset_file;

		wp_enqueue_style(
			'gratis-ai-agent-benchmark-page',
			GRATIS_AI_AGENT_URL . 'build/style-benchmark-page.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'gratis-ai-agent-benchmark-page',
			GRATIS_AI_AGENT_URL . 'build/benchmark-page.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'gratis-ai-agent-benchmark-page', 'gratis-ai-agent' );

		wp_localize_script(
			'gratis-ai-agent-benchmark-page',
			'gratisAiBenchmarkData',
			[
				'currentUserId' => get_current_user_id(),
				'restNamespace' => 'gratis-ai-agent/v1',
			]
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Model Benchmark', 'gratis-ai-agent' ) . '</h1>';
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'The WordPress AI Client SDK is not available. Please check the compatibility layer.', 'gratis-ai-agent' );
			echo '</p></div></div>';
			return;
		}

		?>
		<div class="wrap gratis-ai-agent-benchmark-wrap">
			<h1><?php esc_html_e( 'Model Benchmark', 'gratis-ai-agent' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Benchmark AI models against WordPress knowledge and coding tasks. Compare performance, accuracy, and cost across different providers.', 'gratis-ai-agent' ); ?>
			</p>
			<div id="gratis-ai-agent-benchmark-root"></div>
		</div>
		<?php
	}
}
