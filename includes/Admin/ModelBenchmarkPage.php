<?php

declare(strict_types=1);
/**
 * Admin page for Model Benchmarking.
 *
 * Allows advanced users to benchmark AI models against WordPress knowledge tests.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ModelBenchmarkPage {

	const SLUG = 'sd-ai-agent-benchmark';

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'Model Benchmark', 'sd-ai-agent' ),
			__( 'Model Benchmark', 'sd-ai-agent' ),
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

		$build_dir  = (string) apply_filters( 'sd_ai_agent_build_dir', SD_AI_AGENT_DIR . '/build' );
		$asset_file = $build_dir . '/benchmark-page.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies: string[], version: string} $asset */
		$asset = require $asset_file;

		wp_enqueue_style(
			'sd-ai-agent-benchmark-page',
			SD_AI_AGENT_URL . 'build/style-benchmark-page.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'sd-ai-agent-benchmark-page',
			SD_AI_AGENT_URL . 'build/benchmark-page.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'sd-ai-agent-benchmark-page', 'sd-ai-agent' );

		wp_localize_script(
			'sd-ai-agent-benchmark-page',
			'sdAiBenchmarkData',
			[
				'currentUserId' => get_current_user_id(),
				'restNamespace' => 'sd-ai-agent/v1',
			]
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		?>
		<div class="wrap sd-ai-agent-benchmark-wrap">
			<h1><?php esc_html_e( 'Model Benchmark', 'sd-ai-agent' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Benchmark AI models against WordPress knowledge and coding tasks. Compare performance, accuracy, and cost across different providers.', 'sd-ai-agent' ); ?>
			</p>
			<div id="sd-ai-agent-benchmark-root"></div>
		</div>
		<?php
	}
}
