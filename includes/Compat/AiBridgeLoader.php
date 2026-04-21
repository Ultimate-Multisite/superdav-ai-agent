<?php

declare(strict_types=1);
/**
 * WP AI Client bridge polyfill loader.
 *
 * On WordPress < 7.0, WordPress core does not ship the AI Client bridge
 * (WP_AI_Client_Prompt_Builder, WP_AI_Client_Ability_Function_Resolver,
 * and the wp_ai_client_prompt() / wp_supports_ai() functions).
 *
 * This loader detects whether the bridge is already available (WP 7.0+) and,
 * if not, loads our bundled copies from includes/Compat/ai-client/.
 *
 * It also calls the one-time initialization sequence that wp-settings.php
 * runs on WP 7.0 (registering the WordPress HTTP client with HTTPlug and
 * wiring the WordPress object cache and hook system into the SDK).
 *
 * Activation requirement: the wordpress/php-ai-client SDK must be resolvable
 * via the Composer autoloader (added in Phase 1 — t227). If the SDK is not
 * available, the loader skips silently and the plugin falls back to requiring
 * WordPress 7.0 as before.
 *
 * Usage — call once, right after the Composer autoloader:
 *
 *   \GratisAiAgent\Compat\AiBridgeLoader::maybe_load();
 *
 * @package GratisAiAgent\Compat
 * @license GPL-2.0-or-later
 * @since   1.8.0
 */

namespace GratisAiAgent\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the WP AI Client bridge polyfill when running on WordPress < 7.0.
 *
 * All polyfill files are guarded with class_exists() / function_exists()
 * checks, so they are safe to load on WP 7.0+ too — the core definitions
 * simply take precedence and nothing is re-declared.
 *
 * @since 1.8.0
 */
final class AiBridgeLoader {

	/**
	 * The directory containing the polyfill class files.
	 *
	 * @since 1.8.0
	 * @var string
	 */
	private const COMPAT_DIR = __DIR__ . '/ai-client';

	/**
	 * Whether the polyfill has already been loaded in this request.
	 *
	 * Prevents double-loading when the plugin is activated alongside
	 * a mu-plugin that calls maybe_load() independently.
	 *
	 * @since 1.8.0
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Loads the AI Client bridge polyfill if necessary.
	 *
	 * Called once from gratis-ai-agent.php after the Composer autoloader.
	 *
	 * Safe to call multiple times — subsequent calls are no-ops.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public static function maybe_load(): void {
		// Only run once per request.
		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;

		// Nothing to do if WP 7.0 core already provides the bridge.
		if ( class_exists( 'WP_AI_Client_Prompt_Builder' ) ) {
			return;
		}

		// The SDK must be available (installed by Phase 1 — t227).
		// If it's not, we cannot provide the polyfill.
		if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
			return;
		}

		self::load_adapter_classes();
		self::load_bridge_classes();
		self::load_global_functions();
		self::initialize_sdk_adapters();
	}

	/**
	 * Loads the WordPress HTTP/cache/event adapter classes.
	 *
	 * These must be loaded before the bridge classes because
	 * WP_AI_Client_Discovery_Strategy extends AbstractClientDiscoveryStrategy
	 * which is in the SDK namespace (resolved via autoloader).
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	private static function load_adapter_classes(): void {
		$adapter_dir = self::COMPAT_DIR . '/adapters';

		require_once $adapter_dir . '/class-wp-ai-client-http-client.php';
		require_once $adapter_dir . '/class-wp-ai-client-cache.php';
		require_once $adapter_dir . '/class-wp-ai-client-discovery-strategy.php';
		require_once $adapter_dir . '/class-wp-ai-client-event-dispatcher.php';
	}

	/**
	 * Loads the core bridge class files.
	 *
	 * WP_AI_Client_Ability_Function_Resolver is loaded before
	 * WP_AI_Client_Prompt_Builder because the prompt builder references it
	 * in using_abilities().
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	private static function load_bridge_classes(): void {
		require_once self::COMPAT_DIR . '/class-wp-ai-client-ability-function-resolver.php';
		require_once self::COMPAT_DIR . '/class-wp-ai-client-prompt-builder.php';
	}

	/**
	 * Loads the global function definitions (wp_ai_client_prompt, wp_supports_ai).
	 *
	 * Must be called after the bridge classes are loaded since wp_ai_client_prompt()
	 * instantiates WP_AI_Client_Prompt_Builder.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	private static function load_global_functions(): void {
		require_once __DIR__ . '/ai-client.php';
	}

	/**
	 * Runs the SDK adapter initialization sequence.
	 *
	 * Mirrors what wp-settings.php does on WP 7.0:
	 *
	 *   WP_AI_Client_Discovery_Strategy::init();
	 *   WordPress\AiClient\AiClient::setCache( new WP_AI_Client_Cache() );
	 *   WordPress\AiClient\AiClient::setEventDispatcher( new WP_AI_Client_Event_Dispatcher() );
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	private static function initialize_sdk_adapters(): void {
		\WP_AI_Client_Discovery_Strategy::init();
		\WordPress\AiClient\AiClient::setCache( new \WP_AI_Client_Cache() );
		\WordPress\AiClient\AiClient::setEventDispatcher( new \WP_AI_Client_Event_Dispatcher() );
	}
}
