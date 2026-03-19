<?php
/**
 * WP trunk compatibility shim: Psr\SimpleCache\CacheInterface.
 *
 * WP trunk's bundled php-ai-client scopes its PSR-16 dependency under the
 * WordPress\AiClientDependencies\ prefix (via its own autoloader). The
 * Composer-installed wordpress/php-ai-client package uses the global Psr\
 * namespace instead.
 *
 * When both are present in the same PHP process, WP trunk's cache adapter
 * class (WP_AI_Client_Cache) implements the scoped interface:
 *
 *   class WP_AI_Client_Cache implements
 *     WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface
 *
 * But Composer's AiClient::setCache() declares the global interface:
 *
 *   public static function setCache(?Psr\SimpleCache\CacheInterface $cache): void
 *
 * PHP sees these as two unrelated interfaces, so passing WP_AI_Client_Cache
 * to setCache() throws a TypeError:
 *
 *   TypeError: WordPress\AiClient\AiClient::setCache(): Argument #1 must be
 *   of type ?Psr\SimpleCache\CacheInterface, WP_AI_Client_Cache given,
 *   called in /tmp/wordpress/wp-settings.php on line 480
 *
 * Fix: intercept Psr\SimpleCache\CacheInterface in the prepended autoloader
 * (tests/bootstrap.php) and define it as extending the scoped interface.
 * Because the global interface extends the scoped one, any class that
 * implements the scoped interface (including WP_AI_Client_Cache) also
 * satisfies the global interface type hint — PHP's type system is satisfied.
 *
 * This shim is only loaded when WP trunk's scoped PSR namespace is detectable,
 * so WP 6.9 tests are unaffected (Composer's psr/simple-cache package is used
 * as normal).
 *
 * @package GratisAiAgent\Tests
 * @license GPL-2.0-or-later
 */

namespace Psr\SimpleCache;

/**
 * PSR-16 CacheInterface shim for WP trunk compatibility.
 *
 * Extends WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface so
 * that WP trunk's WP_AI_Client_Cache (which implements the scoped version)
 * also satisfies Psr\SimpleCache\CacheInterface type hints in Composer's
 * wordpress/php-ai-client package.
 *
 * @since 0.2.0
 */
interface CacheInterface extends \WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface {
	// Inherits all methods from the scoped interface — no additional declarations needed.
	// PHP's interface inheritance ensures any implementor of the scoped interface
	// also satisfies this global interface.
}
