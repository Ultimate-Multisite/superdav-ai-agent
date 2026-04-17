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
 *
 * Sets $_SERVER['REQUEST_URI'] to a REST URL so the x-wp/di context detector
 * (XWP_Context) recognizes the PHPUnit environment as CTX_REST. Without this,
 * handlers decorated with `context: CTX_REST` (including all REST_Handler and
 * Handler-based REST controllers) are silently skipped during plugins_loaded,
 * and routes return 404 in tests.
 *
 * The WP test bootstrap hardcodes REQUEST_URI = '/' before muplugins_loaded,
 * which makes XWP_Context::rest() return false. We override it here — before
 * the plugin calls xwp_load_app() — so the context is correct when the DI
 * Module processes handlers on plugins_loaded.
 */
function _manually_load_plugin() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Test bootstrap only; not user input.
	$_SERVER['REQUEST_URI'] = '/wp-json/gratis-ai-agent/v1/';

	require dirname(__DIR__) . '/gratis-ai-agent.php';

	// Install database tables (normally done on activation).
	// Database::install() includes KnowledgeDatabase schema.
	GratisAiAgent\Core\Database::install();
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
