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
 * @package AiAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_AGENT_COMPAT_DIR', __DIR__ );

/**
 * Load the Abilities API if WordPress core does not provide it.
 *
 * Must run early — before 'init' — so that plugins can register
 * abilities on the `wp_abilities_api_init` hook.
 */
function ai_agent_load_compat_abilities_api(): void {

	if ( class_exists( 'WP_Ability' ) ) {
		return; // Core already provides it.
	}

	// Classes.
	require_once AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-ability-category.php';
	require_once AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-ability-categories-registry.php';
	require_once AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-ability.php';
	require_once AI_AGENT_COMPAT_DIR . '/abilities-api/class-wp-abilities-registry.php';

	// API functions (wp_register_ability, wp_get_abilities, etc.).
	require_once AI_AGENT_COMPAT_DIR . '/abilities-api.php';

	// Core categories & abilities (site, user, environment).
	require_once AI_AGENT_COMPAT_DIR . '/abilities.php';

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
function ai_agent_load_compat_ai_client(): void {

	if ( function_exists( 'wp_ai_client_prompt' ) ) {
		return; // Core already provides it.
	}

	// The adapter classes require WordPress core's prefixed PSR interfaces.
	// These are only available when running within WordPress 7.0+ or with the
	// Jetpack Autoloader (which scopes dependencies). Skip loading if unavailable
	// (e.g., during PHPUnit tests with standard Composer autoloader).
	if ( ! interface_exists( 'WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface' ) ) {
		return;
	}

	// WordPress adapter classes.
	require_once AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-http-client.php';
	require_once AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-cache.php';
	require_once AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-discovery-strategy.php';
	require_once AI_AGENT_COMPAT_DIR . '/ai-client/adapters/class-wp-ai-client-event-dispatcher.php';
	require_once AI_AGENT_COMPAT_DIR . '/ai-client/class-wp-ai-client-ability-function-resolver.php';
	require_once AI_AGENT_COMPAT_DIR . '/ai-client/class-wp-ai-client-prompt-builder.php';

	// The wp_ai_client_prompt() entry point.
	require_once AI_AGENT_COMPAT_DIR . '/ai-client.php';
}

// Load both layers. Order matters: abilities first, then AI client.
ai_agent_load_compat_abilities_api();
ai_agent_load_compat_ai_client();
