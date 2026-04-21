<?php

/**
 * WP 6.9 polyfill: WP AI Client global functions.
 *
 * Provides polyfills for functions introduced in WordPress 7.0 that are used
 * by Gratis AI Agent. Every definition is guarded by function_exists() so
 * that on WP 7.0+ the core implementation wins.
 *
 * @package GratisAiAgent\Compat
 * @license GPL-2.0-or-later
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Polyfill: wp_supports_ai()
 *
 * Returns whether AI features are supported in the current environment.
 * On WP 7.0 this is defined in wp-includes/ai-client.php.
 *
 * @since 1.8.0 (polyfill)
 * @return bool
 */
if ( ! function_exists( 'wp_supports_ai' ) ) {
	function wp_supports_ai(): bool {
		if ( defined( 'WP_AI_SUPPORT' ) && ! WP_AI_SUPPORT ) {
			return false;
		}

		/**
		 * Filters whether the current request can use AI.
		 *
		 * @since 1.8.0 (polyfill)
		 *
		 * @param bool $is_enabled Whether AI is available. Default true.
		 */
		return (bool) apply_filters( 'wp_supports_ai', true );
	}
}

/**
 * Polyfill: wp_ai_client_prompt()
 *
 * Creates a new AI prompt builder using the default provider registry.
 * On WP 7.0 this is defined in wp-includes/ai-client.php.
 *
 * @since 1.8.0 (polyfill)
 *
 * @param mixed $prompt Optional. Initial prompt content. Default null.
 * @return WP_AI_Client_Prompt_Builder The prompt builder instance.
 */
if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	/**
	 * Polyfill: wp_ai_client_prompt() — matches WP 7.0 signature.
	 *
	 * @param string|\WP_AI_Client_Prompt_Builder|mixed $prompt Optional prompt. Default null.
	 * @return \WP_AI_Client_Prompt_Builder
	 */
	function wp_ai_client_prompt( mixed $prompt = null ): \WP_AI_Client_Prompt_Builder {
		return new \WP_AI_Client_Prompt_Builder(
			\WordPress\AiClient\AiClient::defaultRegistry(),
			$prompt
		);
	}
}
