<?php
/**
 * WP AI Client bridge polyfill: global functions
 *
 * Provides wp_ai_client_prompt() and wp_supports_ai() on WordPress < 7.0
 * where these functions are not available in core.
 *
 * On WordPress 7.0+, this file is a no-op — core's definitions win.
 *
 * Source: wp-includes/ai-client.php (WP 7.0)
 *
 * @package GratisAiAgent\Compat
 * @license GPL-2.0-or-later
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;

if ( ! function_exists( 'wp_supports_ai' ) ) {
	/**
	 * Returns whether AI features are supported in the current environment.
	 *
	 * @since 7.0.0
	 *
	 * @return bool Whether AI features are supported.
	 */
	function wp_supports_ai(): bool {
		// Return early if AI is disabled by the current environment.
		if ( defined( 'WP_AI_SUPPORT' ) && ! WP_AI_SUPPORT ) {
			return false;
		}

		/**
		 * Filters whether the current request can use AI.
		 *
		 * This allows plugins and 3rd-party code to disable AI features on a per-request basis, or to even override
		 * explicit preferences defined by the site owner.
		 *
		 * @since 7.0.0
		 *
		 * @param bool $is_enabled Whether AI is available. Default to true.
		 */
		return (bool) apply_filters( 'wp_supports_ai', true );
	}
}

if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	/**
	 * Creates a new AI prompt builder using the default provider registry.
	 *
	 * This is the main entry point for generating AI content in WordPress. It returns
	 * a fluent builder that can be used to configure and execute AI prompts.
	 *
	 * The prompt can be provided as a simple string for basic text prompts, or as more
	 * complex types for advanced use cases like multi-modal content or conversation history.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed $prompt Optional. Initial prompt content.
	 *                      A string for simple text prompts,
	 *                      a MessagePart or Message object for
	 *                      structured content, an array for a
	 *                      message array shape, or a list of
	 *                      parts or messages for multi-turn
	 *                      conversations. Default null.
	 * @return WP_AI_Client_Prompt_Builder The prompt builder instance.
	 */
	function wp_ai_client_prompt( $prompt = null ): WP_AI_Client_Prompt_Builder {
		return new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), $prompt );
	}
}
