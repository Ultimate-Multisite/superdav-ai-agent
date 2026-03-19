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
 *    Fix: use class_alias() to make the global name an alias for the scoped interface.
 *    This makes WP_AI_Client_Cache satisfy the global type hint because PHP's type
 *    system treats the two names as the same interface.
 *    IMPORTANT: PHP does NOT call autoloaders for parameter type checks. The alias must
 *    be set up eagerly (before wp-settings.php runs), not via an autoloader callback.
 *    See the eager class_alias block after $_tests_dir detection.
 *
 * Fix: register a prepended autoloader that intercepts the affected classes and loads
 * shims that use the scoped WordPress\AiClientDependencies\ namespace (strategy A).
 * Shims are only loaded when WP trunk's scoped PSR namespace is detectable
 * (interface_exists check), so WP 6.9 tests are unaffected.
 *
 * The prepend=true flag ensures this autoloader runs before Composer's,
 * so the shims win the race for the class/interface definitions.
 *
 * Strategy B (CacheInterface alias) is handled separately — see the eager
 * class_alias block after $_tests_dir detection below.
 */
spl_autoload_register(
	static function ( string $class_name ) use ( $plugin_dir ): void {
		// Only activate shims when WP trunk's scoped PSR autoloader is present.
		// WP trunk registers its autoloader (which handles WordPress\AiClientDependencies\*)
		// in wp-includes/php-ai-client/autoload.php before adapter class files are loaded.
		// On WP 6.9 (no WP trunk), the scoped namespace does not exist and
		// interface_exists() returns false — fall through to Composer's autoloader.
		//
		// These shims handle interface redefinition (strategy A): WP trunk's adapter classes
		// implement/extend the Composer-defined interfaces but use scoped PSR type hints in
		// their method signatures. Fix: redefine the interfaces using scoped type hints so
		// WP trunk's classes can implement them without a signature mismatch.
		//
		// Note: Psr\SimpleCache\CacheInterface (strategy B) is handled differently — see the
		// eager class_alias block below. PHP does not call autoloaders for parameter type
		// checks, so the alias must be set up before wp-settings.php runs.
		$shim_map = array(
			'WordPress\\AiClient\\Providers\\Http\\Contracts\\ClientWithOptionsInterface'      => 'wp-trunk-client-with-options-interface.php',
			'WordPress\\AiClient\\Providers\\Http\\Abstracts\\AbstractClientDiscoveryStrategy' => 'wp-trunk-abstract-client-discovery-strategy.php',
		);

		if ( ! isset( $shim_map[ $class_name ] ) ) {
			return;
		}

		if ( ! interface_exists( 'WordPress\\AiClientDependencies\\Psr\\Http\\Message\\RequestInterface' ) ) {
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

/**
 * WP trunk CacheInterface alias — must be set up eagerly before wp-settings.php runs.
 *
 * PHP does NOT call autoloaders for parameter type checks. When wp-settings.php calls
 * AiClient::setCache(new WP_AI_Client_Cache()), PHP checks the type hint at call time
 * without triggering autoloading. The alias must therefore be created before that call.
 *
 * Strategy: load WP trunk's scoped autoloader (if present), then alias the global
 * Psr\SimpleCache\CacheInterface to the scoped one. WP trunk's autoloader is at
 * wp-includes/php-ai-client/autoload.php relative to the WP core directory.
 *
 * The WP core directory is derived from the tests directory using the standard
 * install-wp-tests.sh convention: tests dir = {tmpdir}/wordpress-tests-lib,
 * WP core = {tmpdir}/wordpress. For wp-env, WP core is at /wordpress.
 *
 * This is a no-op on WP 6.9 (no WP trunk): the autoloader file won't exist.
 */
$_wp_core_dir = '';
if ( '/wordpress-phpunit' === $_tests_dir ) {
	// wp-env: WP core is at /wordpress.
	$_wp_core_dir = '/wordpress';
} else {
	// Standard install-wp-tests.sh convention: strip '-tests-lib' suffix.
	$_wp_core_dir = preg_replace( '/-tests-lib$/', '', $_tests_dir );
}

$_wp_trunk_autoloader = $_wp_core_dir . '/wp-includes/php-ai-client/autoload.php';
if ( file_exists( $_wp_trunk_autoloader ) ) {
	require_once $_wp_trunk_autoloader;
	// Load the scoped CacheInterface so we can alias it.
	if ( ! interface_exists( 'WordPress\\AiClientDependencies\\Psr\\SimpleCache\\CacheInterface' ) ) {
		$_scoped_cache_file = $_wp_core_dir . '/wp-includes/php-ai-client/third-party/Psr/SimpleCache/CacheInterface.php';
		if ( file_exists( $_scoped_cache_file ) ) {
			require_once $_scoped_cache_file;
		}
	}
	if ( interface_exists( 'WordPress\\AiClientDependencies\\Psr\\SimpleCache\\CacheInterface' ) ) {
		class_alias(
			'WordPress\\AiClientDependencies\\Psr\\SimpleCache\\CacheInterface',
			'Psr\\SimpleCache\\CacheInterface'
		);
	}
}
unset( $_wp_core_dir, $_wp_trunk_autoloader );
if ( isset( $_scoped_cache_file ) ) {
	unset( $_scoped_cache_file );
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
