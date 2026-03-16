<?php
/**
 * MU-Plugin: Test helpers for AI Agent development.
 *
 * Loaded automatically by wp-env in the development environment.
 * Provides debugging aids and test fixtures.
 *
 * @package AiAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enable error display in development.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
}

/**
 * Stub for wp_ai_client_prompt() — available in WordPress 6.9+ core.
 *
 * Provides a no-op implementation so the floating widget and other plugin
 * features that guard on function_exists( 'wp_ai_client_prompt' ) load
 * correctly in wp-env E2E test environments where the function may not
 * yet be present.
 *
 * The real function signature is wp_ai_client_prompt( $prompt = null )
 * and returns a WP_AI_Client_Prompt_Builder. This stub matches that
 * signature so it does not cause fatal errors if called (e.g. from
 * AgentLoop which calls wp_ai_client_prompt() with no arguments).
 *
 * @param mixed $prompt Optional prompt content (string, array, or null).
 * @return object Minimal stub object — no AI call is made.
 */
if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	function wp_ai_client_prompt( $prompt = null ) { // phpcs:ignore
		// Return a minimal stub object so callers that chain methods
		// (e.g. ->withModel()->run()) do not throw fatal errors.
		return new class() {
			public function __call( string $name, array $args ): static {
				return $this;
			}
		};
	}
}
