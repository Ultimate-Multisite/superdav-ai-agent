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
 * WP trunk PSR namespace compatibility shims.
 *
 * WP trunk's bundled php-ai-client scopes its PSR and Nyholm dependencies
 * under WordPress\AiClientDependencies\ (via a custom autoloader in
 * wp-includes/php-ai-client/autoload.php). The Composer-installed
 * wordpress/php-ai-client package uses the global Psr\ and Nyholm\ namespaces.
 *
 * When both are present, Composer's autoloader wins the race for two classes
 * and registers them with global type hints. WP trunk's adapter classes then
 * fail to implement/extend them because they use WordPress\AiClientDependencies\
 * type hints — PHP fatal on every WP trunk test run.
 *
 * Affected classes:
 *
 * 1. WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface
 *    WP trunk's class-wp-ai-client-http-client.php implements this interface
 *    using WordPress\AiClientDependencies\Psr\ type hints.
 *
 * 2. WordPress\AiClient\Providers\Http\Abstracts\AbstractClientDiscoveryStrategy
 *    WP trunk's class-wp-ai-client-discovery-strategy.php extends this abstract
 *    class using WordPress\AiClientDependencies\Nyholm\ and
 *    WordPress\AiClientDependencies\Psr\ type hints.
 *
 * 3. Psr\SimpleCache\CacheInterface (global)
 *    WP trunk's WP_AI_Client_Cache implements the scoped version
 *    (WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface) but
 *    Composer's AiClient::setCache() expects the global Psr\SimpleCache\CacheInterface.
 *    The shim defines the global interface as extending the scoped one so that
 *    WP_AI_Client_Cache satisfies both type hints.
 *
 * Fix: register a prepended autoloader that intercepts both classes and loads
 * shims that use the scoped WordPress\AiClientDependencies\ namespace. Shims
 * are only loaded when WP trunk's scoped PSR namespace is detectable
 * (interface_exists check), so WP 6.9 tests are unaffected.
 *
 * The prepend=true flag ensures this autoloader runs before Composer's,
 * so the shims win the race for the class/interface definitions.
 */
spl_autoload_register(
	static function ( string $class_name ) use ( $plugin_dir ): void {
		$shim_map = array(
			'WordPress\\AiClient\\Providers\\Http\\Contracts\\ClientWithOptionsInterface'      => 'wp-trunk-client-with-options-interface.php',
			'WordPress\\AiClient\\Providers\\Http\\Abstracts\\AbstractClientDiscoveryStrategy' => 'wp-trunk-abstract-client-discovery-strategy.php',
			'Psr\\SimpleCache\\CacheInterface'                                                 => 'wp-trunk-psr-simple-cache-interface.php',
		);

		if ( ! isset( $shim_map[ $class_name ] ) ) {
			return;
		}

		// Only activate shims when WP trunk's scoped PSR autoloader is present.
		// WP trunk registers its autoloader (which handles WordPress\AiClientDependencies\*)
		// in wp-includes/php-ai-client/autoload.php before adapter class files are loaded.
		// On WP 6.9 (no WP trunk), the scoped namespace does not exist and
		// interface_exists() returns false — fall through to Composer's autoloader.
		//
		// Detection is per-shim: each shim checks for the scoped counterpart of the
		// interface/class it is replacing. This avoids a race condition where the
		// generic RequestInterface guard returns false for the CacheInterface shim
		// because WP trunk's scoped RequestInterface hasn't been loaded yet at the
		// point AiClient::setCache() triggers autoloading of Psr\SimpleCache\CacheInterface.
		// The second argument (false) prevents recursive autoloading during the check.
		$scoped_guard_map = array(
			'WordPress\\AiClient\\Providers\\Http\\Contracts\\ClientWithOptionsInterface'      => 'WordPress\\AiClientDependencies\\Psr\\Http\\Message\\RequestInterface',
			'WordPress\\AiClient\\Providers\\Http\\Abstracts\\AbstractClientDiscoveryStrategy' => 'WordPress\\AiClientDependencies\\Psr\\Http\\Message\\RequestInterface',
			'Psr\\SimpleCache\\CacheInterface'                                                 => 'WordPress\\AiClientDependencies\\Psr\\SimpleCache\\CacheInterface',
		);

		$guard_interface = $scoped_guard_map[ $class_name ];
		if ( ! interface_exists( $guard_interface, false ) ) {
			return;
		}

		require_once $plugin_dir . '/tests/stubs/' . $shim_map[ $class_name ];
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
