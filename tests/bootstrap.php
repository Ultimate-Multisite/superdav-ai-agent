<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GratisAiAgent
 */

// Load standard Composer autoloader (not Jetpack) - required for PSR interfaces used by compat layer.
// Jetpack Autoloader requires WordPress functions, so we use the standard autoloader for tests.
$plugin_dir = dirname( __DIR__ );
require_once $plugin_dir . '/vendor/autoload.php';

/**
 * WP trunk PSR namespace compatibility shim.
 *
 * WP trunk's bundled php-ai-client scopes its PSR dependencies under
 * WordPress\AiClientDependencies\Psr\ (via a custom autoloader in
 * wp-includes/php-ai-client/autoload.php). The Composer-installed
 * wordpress/php-ai-client package uses the global Psr\ namespace.
 *
 * When both are present, Composer's autoloader wins the race for
 * WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface
 * and registers it with global Psr\ type hints. WP trunk's adapter class
 * (class-wp-ai-client-http-client.php) then fails to implement it because
 * it uses WordPress\AiClientDependencies\Psr\ type hints — PHP fatal.
 *
 * Fix: register a prepended autoloader that intercepts
 * ClientWithOptionsInterface and loads a shim that uses the scoped
 * WordPress\AiClientDependencies\Psr\ namespace. The shim is only loaded
 * when WP trunk's autoloader is already registered (i.e. when WP trunk is
 * the active WordPress install), so the scoped PSR types can be resolved.
 *
 * The prepend=true flag ensures this autoloader runs before Composer's,
 * so the shim wins the race for the interface definition.
 */
spl_autoload_register(
	static function ( string $class_name ) use ( $plugin_dir ): void {
		if ( 'WordPress\\AiClient\\Providers\\Http\\Contracts\\ClientWithOptionsInterface' !== $class_name ) {
			return;
		}

		// Only activate the shim when WP trunk's scoped PSR autoloader is
		// present. WP trunk registers its autoloader (which handles
		// WordPress\AiClientDependencies\*) in wp-includes/php-ai-client/autoload.php,
		// which runs before the adapter class files are loaded. By the time
		// PHP resolves ClientWithOptionsInterface (triggered by the adapter
		// class declaration), WP trunk's autoloader is already registered and
		// can resolve WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface.
		//
		// Detection: attempt to trigger WP trunk's autoloader for the scoped
		// PSR RequestInterface. If it resolves, WP trunk is present and the
		// shim is needed. On WP 6.9 (no WP trunk), the scoped namespace does
		// not exist and interface_exists() returns false — fall through to Composer.
		if ( ! interface_exists( 'WordPress\\AiClientDependencies\\Psr\\Http\\Message\\RequestInterface' ) ) {
			return;
		}

		require_once $plugin_dir . '/tests/stubs/wp-trunk-client-with-options-interface.php';
	},
	true,  // throw on error
	true   // prepend — run before Composer's autoloader
);

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
