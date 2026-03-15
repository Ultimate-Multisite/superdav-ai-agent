<?php
/**
 * Compatibility layer for WordPress < 7.0.
 *
 * Loads the Abilities API and AI Client SDK from bundled copies
 * when they are not provided by WordPress core. This allows the
 * plugin to run on WordPress 6.9 (which lacks these APIs).
 *
 * On WordPress 7.0+ these functions already exist and nothing is loaded.
 *
 * @package GratisAiAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRATIS_AI_AGENT_COMPAT_DIR', __DIR__ );

/**
 * Load the Abilities API if WordPress core does not provide it.
 *
 * Must run early — before 'init' — so that plugins can register
 * abilities on the `wp_abilities_api_init` hook.
 */
function gratis_ai_agent_load_compat_abilities_api(): void {

	if ( class_exists( 'WP_Ability' ) ) {
		return; // Core already provides it.
	}

	// Classes.
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-ability-category.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-ability-categories-registry.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-ability.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-abilities-registry.php';

	// API functions (wp_register_ability, wp_get_abilities, etc.).
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/abilities-api.php';

	// Core categories & abilities (site, user, environment).
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/abilities.php';

	// Register the default hooks that wp-includes/default-filters.php would add.
	add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
	add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
}

/**
 * Load the AI Client SDK if WordPress core does not provide it.
 *
 * The php-ai-client library is provided by Composer (via Jetpack Autoloader).
 * This function loads the WordPress adapter classes and wp_ai_client_prompt().
 */
function gratis_ai_agent_load_compat_ai_client(): void {

	if ( function_exists( 'wp_ai_client_prompt' ) ) {
		return; // Core already provides it.
	}

	// The adapter classes require PSR HTTP interfaces.
	// WordPress 7.0+ ships these under a prefixed namespace; our Composer install
	// provides the standard Psr\* namespace. Accept either.
	if (
		! interface_exists( 'Psr\Http\Client\ClientInterface' )
		&& ! interface_exists( 'WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface' )
	) {
		return;
	}

	// WordPress adapter classes.
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-http-client.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-cache.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-discovery-strategy.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-event-dispatcher.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client/class-wp-ai-client-ability-function-resolver.php';
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client/class-wp-ai-client-prompt-builder.php';

	// The wp_ai_client_prompt() entry point.
	require_once GRATIS_AI_AGENT_COMPAT_DIR . '/ai-client.php';
}

// Load both layers. Order matters: abilities first, then AI client.
gratis_ai_agent_load_compat_abilities_api();
gratis_ai_agent_load_compat_ai_client();
