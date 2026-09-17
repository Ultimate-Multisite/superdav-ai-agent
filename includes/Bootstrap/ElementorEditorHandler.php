<?php

declare(strict_types=1);

namespace SdAiAgent\Bootstrap;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the optional Superdav adapter through Elementor's public V2 editor hooks.
 *
 * Elementor resolves its own package assets from its installation directory, so
 * this external package uses the documented V2 script hooks. Its bundle depends
 * on the public `elementor-v2-editor-mcp` package and publishes its own `init()`
 * contract after that dependency has loaded.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_ADMIN,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class ElementorEditorHandler {

	private const SCRIPT_HANDLE        = 'elementor-v2-sd-ai-agent-elementor-mcp';
	private const ELEMENTOR_MCP_HANDLE = 'elementor-v2-editor-mcp';

	/**
	 * Register the bridge after Elementor has registered its public editor-mcp handle.
	 *
	 * @return void
	 */
	#[Action( tag: 'elementor/editor/v2/scripts/register', priority: 10 )]
	public function register_editor_package(): void {
		if ( ! $this->current_user_can_use_bridge() || ! wp_script_is( self::ELEMENTOR_MCP_HANDLE, 'registered' ) ) {
			return;
		}

		$build_dir  = (string) apply_filters( 'sd_ai_agent_build_dir', SD_AI_AGENT_DIR . '/build' );
		$asset_path = $build_dir . '/elementor-editor-mcp.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;
		if ( ! is_array( $asset ) ) {
			return;
		}

		$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		if ( ! in_array( self::ELEMENTOR_MCP_HANDLE, $dependencies, true ) ) {
			$dependencies[] = self::ELEMENTOR_MCP_HANDLE;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			SD_AI_AGENT_URL . 'build/elementor-editor-mcp.js',
			array_values( array_unique( $dependencies ) ),
			isset( $asset['version'] ) ? (string) $asset['version'] : SD_AI_AGENT_VERSION,
			true
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'superdav-ai-agent' );
	}

	/**
	 * Enqueue the bridge before Elementor's final V2 editor loader runs init().
	 *
	 * @return void
	 */
	#[Action( tag: 'elementor/editor/v2/scripts/enqueue', priority: 10 )]
	public function enqueue_editor_package(): void {
		if ( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	/**
	 * Mirror the existing floating-chat admin gate; registration grants no API
	 * authority because normal Superdav and Elementor capability checks remain.
	 *
	 * @return bool Whether the current user can receive the browser bridge.
	 */
	private function current_user_can_use_bridge(): bool {
		return current_user_can( 'manage_options' );
	}
}
