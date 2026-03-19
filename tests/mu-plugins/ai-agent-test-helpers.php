<?php
/**
 * MU-Plugin: Test helpers for AI Agent development.
 *
 * Loaded automatically by wp-env in the development environment.
 * Provides debugging aids and test fixtures.
 *
 * @package AiAgent
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enable error display in development.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
}

/**
 * Late-loaded stub for wp_ai_client_prompt().
 *
 * The plugin's compat layer (compat/load.php) provides the real
 * wp_ai_client_prompt() and its supporting classes on WP < 7.0.
 * On WP 7.0+ core provides them natively.
 *
 * This stub is a last-resort fallback that only activates AFTER
 * plugins_loaded — if neither core nor the compat layer defined
 * the function, it provides a no-op so E2E tests don't fatal.
 *
 * Runs at plugins_loaded priority 999 (well after the plugin's
 * compat layer at default priority) to avoid shadowing the real
 * implementation.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			return; // Real function available — nothing to do.
		}
		// Neither core nor compat provided it — define a no-op stub.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		function wp_ai_client_prompt( $prompt = null ) {
			return new class() {
				/** @return static */
				public function __call( string $name, array $args ): static {
					return $this;
				}
			};
		}
	},
	999
);
