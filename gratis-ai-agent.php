<?php
/**
 * Plugin Name: AI Agent for WP
 * Plugin URI:  https://github.com/Ultimate-Multisite/gratis-ai-agent
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.7.0
 * Author:      superdav42
 * Author URI:  https://github.com/superdav42
 * License:     GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Text Domain: ai-agent-for-wp
 *
 * @package GratisAiAgent
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRATIS_AI_AGENT_VERSION', '1.7.0' );
define( 'GRATIS_AI_AGENT_DIR', __DIR__ );
define( 'GRATIS_AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Built-in fallback model ID used when no model is configured in settings
 * and no connector default is available.
 *
 * Developers can override the effective default at runtime via the
 * `gratis_ai_agent_default_model` filter rather than changing this constant.
 */
define( 'GRATIS_AI_AGENT_DEFAULT_MODEL', 'claude-sonnet-4' );

// Load Jetpack Autoloader for PSR-4 autoloading with version conflict resolution.
// Jetpack Autoloader ensures the newest version of shared packages (like php-ai-client) is used.
if ( file_exists( GRATIS_AI_AGENT_DIR . '/vendor/autoload_packages.php' ) ) {
	require_once GRATIS_AI_AGENT_DIR . '/vendor/autoload_packages.php';
} elseif ( file_exists( GRATIS_AI_AGENT_DIR . '/vendor/autoload.php' ) ) {
	require_once GRATIS_AI_AGENT_DIR . '/vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'Gratis AI Agent is missing its vendor dependencies. Please run "composer install" in the plugin directory.',
					'gratis-ai-agent',
				),
			);
		},
	);
	return;
}

use GratisAiAgent\Bootstrap\LifecycleHandler;
use GratisAiAgent\Compat\AiBridgeLoader;
use GratisAiAgent\Plugin;

// Load the WP AI Client bridge polyfill on WordPress < 7.0.
// On WP 7.0+ this is a no-op — core's definitions take precedence.
// Requires the wordpress/php-ai-client SDK to be available via Composer
// (added in Phase 1 — t227). Safe to call before WordPress has loaded
// all plugins because it only require_once's files and calls SDK static
// methods that are independent of WordPress hooks.
AiBridgeLoader::maybe_load();

// Activation / deactivation hooks fire *before* `plugins_loaded`, so they
// cannot be wired through the DI container. `LifecycleHandler` consolidates
// the handful of static calls that used to live inline here.
register_activation_hook( __FILE__, [ LifecycleHandler::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ LifecycleHandler::class, 'deactivate' ] );

// Normalize REQUEST_URI for plain-permalink REST requests.
//
// `XWP_Context::rest()` detects REST context by checking if REQUEST_URI
// contains the wp-json prefix (e.g. `/wp-json/`). WordPress also routes REST
// requests via `?rest_route=/...` when pretty permalinks are disabled (the
// default in fresh wp-env installs and many CI environments).
//
// Without this normalisation, `XWP_Context::rest()` returns false for
// `?rest_route=` requests, so all DI handlers with `context: CTX_REST` are
// never initialised and every REST endpoint returns 404.
//
// The normalisation runs before `xwp_load_app()` queues the container build
// at `plugins_loaded:PHP_INT_MIN`, so XWP_Context::get() caches the correct
// REST context when the container actually initialises.
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
if ( ! empty( $_GET['rest_route'] ) ) {
	$gratis_rest_prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
	$gratis_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( false === strpos( (string) $gratis_request_uri, $gratis_rest_prefix ) ) {
		$_SERVER['REQUEST_URI'] = '/' . $gratis_rest_prefix . wp_unslash( $_GET['rest_route'] );
	}
	unset( $gratis_rest_prefix, $gratis_request_uri );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated

// Bootstrap the DI container.
//
// `xwp_load_app()` schedules the container build at its default
// `plugins_loaded:PHP_INT_MIN` so it runs *before* the `Plugin` module's
// own `#[Module(hook: 'plugins_loaded', priority: 1)]` registration fires.
//
// All hook wiring — REST controllers, abilities, admin menus, core services,
// frontend assets — is managed by `#[Handler]` classes registered in
// `GratisAiAgent\Plugin::$handlers`. Nothing else needs to live in this file.
xwp_load_app(
	[
		'id'            => 'gratis-ai-agent',
		'module'        => Plugin::class,
		'autowiring'    => true,
		'compile'       => 'production' === wp_get_environment_type(),
		// The default `compile_class` is `CompiledContainer` + uppercased ID,
		// which produces invalid PHP class names when the ID contains hyphens.
		'compile_class' => 'CompiledContainerGratisAiAgent',
		'compile_dir'   => GRATIS_AI_AGENT_DIR . '/build/di-cache/' . GRATIS_AI_AGENT_VERSION,
	],
);
