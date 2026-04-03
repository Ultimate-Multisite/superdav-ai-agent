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
 * WordPress 7.0+ provides wp_ai_client_prompt() natively. This stub
 * is a last-resort fallback for test environments where core may not
 * be fully loaded — it provides a no-op so E2E tests don't fatal.
 *
 * Runs at plugins_loaded priority 999 to avoid shadowing the real
 * implementation.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			return; // Real function available — nothing to do.
		}
		// Core did not provide it — define a no-op stub for tests.
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
