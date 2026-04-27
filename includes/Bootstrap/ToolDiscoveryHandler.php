<?php
/**
 * DI handler for tool-discovery and custom-tool-executor abilities.
 *
 * Replaces the `ToolDiscovery::register()` and `CustomToolExecutor::register()`
 * calls in CoreServicesHandler by wiring the `wp_abilities_api_init` hook
 * directly via `#[Action]`.
 *
 * Both classes register abilities on the same hook; combining them here avoids
 * two separate handler classes for essentially the same lifecycle step.
 *
 * @package GratisAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\ToolDiscovery;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers auto-discovered and user-defined custom tools as WordPress Abilities.
 *
 * `INIT_IMMEDIATELY` ensures the `wp_abilities_api_init` callback is
 * queued during `plugins_loaded` — before `init` fires the hook.
 */
#[Handler(
	container: 'gratis-ai-agent',
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class ToolDiscoveryHandler {

	/**
	 * Register the gratis-ai-agent-js ability category server-side.
	 *
	 * Must run on wp_abilities_api_categories_init (which fires inside
	 * WP_Ability_Categories_Registry::get_instance(), before
	 * wp_abilities_api_init) so the category exists when
	 * register_tool_abilities() tries to register JS ability stubs.
	 */
	#[Action( tag: 'wp_abilities_api_categories_init', priority: 10 )]
	public function register_js_category(): void {
		ToolDiscovery::register_js_category();
	}

	/**
	 * Register auto-discovered tool abilities and custom tool executor abilities.
	 *
	 * Called on `wp_abilities_api_init` (fires during `init`).
	 */
	#[Action( tag: 'wp_abilities_api_init', priority: 10 )]
	public function register_tool_abilities(): void {
		ToolDiscovery::register_abilities();
		CustomToolExecutor::register_abilities();
	}
}
