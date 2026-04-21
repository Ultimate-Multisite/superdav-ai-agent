<?php
/**
 * WP AI Client bridge polyfill: WP_AI_Client_Discovery_Strategy class
 *
 * Provides the WordPress HTTP client discovery strategy for the AI Client SDK
 * on WordPress < 7.0 where this class is not available in core.
 *
 * On WordPress 7.0+, this file is a no-op — core's definition wins.
 *
 * Source: wp-includes/ai-client/adapters/class-wp-ai-client-discovery-strategy.php (WP 7.0)
 *
 * @package GratisAiAgent\Compat
 * @license GPL-2.0-or-later
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WP_AI_Client_Discovery_Strategy' ) ) {
	return;
}

use WordPress\AiClient\Providers\Http\Abstracts\AbstractClientDiscoveryStrategy;
use WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory;
use WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;

/**
 * Discovery strategy for WordPress HTTP client.
 *
 * Registers the WordPress HTTP client adapter with the HTTPlug discovery system
 * so the AI Client SDK can find and use it automatically.
 *
 * @since 7.0.0
 * @internal Intended only to register WordPress's HTTP client so that the PHP AI Client SDK can use it.
 * @access private
 */
class WP_AI_Client_Discovery_Strategy extends AbstractClientDiscoveryStrategy {

	/**
	 * Creates an instance of the WordPress HTTP client.
	 *
	 * @since 7.0.0
	 *
	 * @param Psr17Factory $psr17_factory The PSR-17 factory for creating HTTP messages.
	 * @return ClientInterface The PSR-18 HTTP client.
	 */
	protected static function createClient( Psr17Factory $psr17_factory ): ClientInterface {
		return new WP_AI_Client_HTTP_Client( $psr17_factory, $psr17_factory );
	}
}
