<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

// Load standard Composer autoloader (not Jetpack).
// Jetpack Autoloader requires WordPress functions, so we use the standard autoloader for tests.
$plugin_dir = dirname( __DIR__ );
require_once $plugin_dir . '/vendor/autoload.php';

/*
 * Force XWP_Context to CTX_REST so the x-wp/di container loads REST handlers.
 *
 * Problem: the WP test bootstrap defines WP_ADMIN=true before loading
 * wp-settings.php. XWP_Context::get() uses a match(true) that checks
 * admin() BEFORE rest(), so the context resolves to Admin (2) — silently
 * skipping every CTX_REST (16) handler and producing 404 on all REST route
 * tests. Setting $_SERVER['REQUEST_URI'] to /wp-json/... doesn't help because
 * the admin() branch short-circuits before rest() is evaluated.
 *
 * Fix: pre-set the private static XWP_Context::$current via reflection BEFORE
 * WordPress boots. The ??= assignment in get() preserves our value, so the
 * match expression is never reached. This runs at file-scope before the WP
 * test bootstrap is even loaded — no hook ordering issues.
 */
( static function (): void {
	$refl = new ReflectionProperty( XWP_Context::class, 'current' );
	$refl->setValue( null, XWP_Context::REST );

	// Debug: verify the override sticks.
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug.
	error_log( 'BOOTSTRAP: XWP_Context pre-set to ' . XWP_Context::get() . ' (expected ' . XWP_Context::REST . ')' );
} )();

$_tests_dir = getenv('WP_TESTS_DIR');
if ( ! $_tests_dir ) {
	// wp-env places the test suite at /wordpress-phpunit.
	if ( file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
		$_tests_dir = '/wordpress-phpunit';
	} else {
		$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
	}
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
// Auto-detect from Composer vendor directory if not set via env var.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if ( false === $_phpunit_polyfills_path ) {
	$_phpunit_polyfills_path = $plugin_dir . '/vendor/yoast/phpunit-polyfills';
}
if ( is_dir( $_phpunit_polyfills_path ) ) {
	define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if ( ! file_exists("{$_tests_dir}/includes/functions.php") ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit(1);
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname(__DIR__) . '/gratis-ai-agent.php';

	// Install database tables (normally done on activation).
	// Database::install() includes KnowledgeDatabase schema.
	GratisAiAgent\Core\Database::install();
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Debug: verify context survived WP bootstrap.
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug.
error_log( 'POST-BOOT: XWP_Context::get() = ' . XWP_Context::get() . ', validate(REST) = ' . ( XWP_Context::validate( XWP_Context::REST ) ? 'true' : 'false' ) . ', is_admin() = ' . ( is_admin() ? 'true' : 'false' ) );
