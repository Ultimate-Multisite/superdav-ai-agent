<?php

declare(strict_types=1);
/**
 * WP 6.9 Compatibility Loader.
 *
 * Polyfills the WP 7.0 AI APIs (php-ai-client SDK, WP AI Client bridge,
 * Connectors API) for use on WordPress 6.9. On WP 7.0+, all polyfills are
 * guarded by class_exists()/function_exists() and core definitions win.
 *
 * Architecture:
 *   - lib/php-ai-client/   — Copy of WordPress\AiClient\* SDK from WP core
 *   - lib/ai-client/       — Copy of WP AI Client bridge classes (WP 7.0 only)
 *   - includes/Compat/     — Polyfill functions and integration glue
 *
 * @package GratisAiAgent\Compat
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Compat;

/**
 * Loads WP 6.9 compatibility polyfills.
 *
 * Called early in gratis-ai-agent.php, before the DI container is built.
 */
class CompatLoader {

	/** @var string Path to the plugin's lib directory. */
	private string $lib_dir;

	/**
	 * @param string $plugin_dir Absolute path to the plugin root directory.
	 */
	public function __construct( string $plugin_dir ) {
		$this->lib_dir = $plugin_dir . '/lib';
	}

	/**
	 * Load all polyfills. Safe to call on WP 7.0 — every file is guarded.
	 *
	 * @return void
	 */
	public function load(): void {
		$this->load_php_ai_client();
		$this->load_ai_client_bridge();
		$this->load_ai_client_functions();
		$this->load_connectors_polyfill();
	}

	/**
	 * Register the php-ai-client SDK autoloader if the SDK is not already
	 * available (i.e. on WP 6.9 where it is not bundled in core).
	 *
	 * On WP 7.0 the SDK classes already exist; the autoloader below will
	 * simply never be triggered for them because PHP finds the class before
	 * consulting spl_autoload_register handlers added after core loads.
	 *
	 * @return void
	 */
	private function load_php_ai_client(): void {
		// On WP 7.0 the SDK is already loaded by wp-includes/php-ai-client/autoload.php.
		if ( class_exists( 'WordPress\\AiClient\\AiClient', false ) ) {
			return;
		}

		$sdk_dir = $this->lib_dir . '/php-ai-client';

		if ( ! is_dir( $sdk_dir ) ) {
			return;
		}

		// Register a PSR-4-style autoloader for the bundled SDK.
		spl_autoload_register(
			static function ( string $class_name ) use ( $sdk_dir ): void {
				$client_prefix     = 'WordPress\\AiClient\\';
				$client_prefix_len = 19;
				$deps_prefix       = 'WordPress\\AiClientDependencies\\';
				$deps_prefix_len   = 31;

				if ( 0 === strncmp( $class_name, $client_prefix, $client_prefix_len ) ) {
					$relative = substr( $class_name, $client_prefix_len );
					$file     = $sdk_dir . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
					if ( file_exists( $file ) ) {
						require_once $file;
					}
					return;
				}

				if ( 0 === strncmp( $class_name, $deps_prefix, $deps_prefix_len ) ) {
					$relative = substr( $class_name, $deps_prefix_len );
					$file     = $sdk_dir . '/third-party/' . str_replace( '\\', '/', $relative ) . '.php';
					if ( file_exists( $file ) ) {
						require_once $file;
					}
					return;
				}
			},
			true,   // throw
			true    // prepend — ensures our copy loads before any other autoloader
		);
	}

	/**
	 * Load the WP AI Client bridge classes if not already defined by WP 7.0 core.
	 *
	 * WP 7.0 loads these from wp-includes/ai-client/ during bootstrap.
	 * On WP 6.9 we load our bundled copies from lib/ai-client/.
	 *
	 * @return void
	 */
	private function load_ai_client_bridge(): void {
		$bridge_dir = $this->lib_dir . '/ai-client';

		if ( ! is_dir( $bridge_dir ) ) {
			return;
		}

		// Adapters — must be loaded before the bridge classes that depend on them.
		$adapters = [
			'class-wp-ai-client-cache.php',
			'class-wp-ai-client-event-dispatcher.php',
			'class-wp-ai-client-http-client.php',
			'class-wp-ai-client-discovery-strategy.php',
		];

		foreach ( $adapters as $adapter_file ) {
			$file = $bridge_dir . '/adapters/' . $adapter_file;
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		// Main bridge classes.
		if ( ! class_exists( 'WP_AI_Client_Ability_Function_Resolver', false ) ) {
			$file = $bridge_dir . '/class-wp-ai-client-ability-function-resolver.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		if ( ! class_exists( 'WP_AI_Client_Prompt_Builder', false ) ) {
			$file = $bridge_dir . '/class-wp-ai-client-prompt-builder.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Load polyfills for the wp_ai_client_prompt() global function and
	 * related helpers if they are not already defined by WP 7.0.
	 *
	 * @return void
	 */
	private function load_ai_client_functions(): void {
		$polyfill_file = __DIR__ . '/wp-ai-client-functions.php';
		if ( file_exists( $polyfill_file ) ) {
			require_once $polyfill_file;
		}
	}

	/**
	 * Load polyfills for the WP 7.0 Connectors API functions used by the
	 * plugin's ProviderCredentialLoader.
	 *
	 * On WP 7.0 these functions are defined by wp-includes/connectors.php
	 * and win due to the function_exists() guard in the polyfill file.
	 *
	 * @return void
	 */
	private function load_connectors_polyfill(): void {
		$polyfill_file = __DIR__ . '/wp-connectors-polyfill.php';
		if ( file_exists( $polyfill_file ) ) {
			require_once $polyfill_file;
		}
	}
}
