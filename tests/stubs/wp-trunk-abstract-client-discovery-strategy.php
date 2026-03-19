<?php
/**
 * WP trunk compatibility shim: AbstractClientDiscoveryStrategy.
 *
 * WP trunk's bundled php-ai-client scopes its PSR and Nyholm dependencies
 * under the WordPress\AiClientDependencies\ prefix (via its own autoloader).
 * The Composer-installed wordpress/php-ai-client package uses the global
 * Psr\ and Nyholm\ namespaces instead.
 *
 * When both are present in the same PHP process, Composer's autoloader wins
 * the race for AbstractClientDiscoveryStrategy and registers it with global
 * type hints. WP trunk's concrete adapter class
 * (class-wp-ai-client-discovery-strategy.php) then fails to extend it because
 * it uses WordPress\AiClientDependencies\ type hints — PHP fatal:
 *
 *   Declaration of WP_AI_Client_Discovery_Strategy::createClient(
 *     WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory $psr17_factory):
 *     WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface
 *   must be compatible with
 *     AbstractClientDiscoveryStrategy::createClient(
 *     Nyholm\Psr7\Factory\Psr17Factory $psr17Factory):
 *     Psr\Http\Client\ClientInterface
 *
 * This shim is loaded by a prepended autoloader registered in tests/bootstrap.php
 * when WP trunk is detected. It redefines AbstractClientDiscoveryStrategy using
 * the scoped WordPress\AiClientDependencies\ namespace so that WP trunk's
 * concrete class can extend it without a signature mismatch.
 *
 * @package GratisAiAgent\Tests
 */

namespace WordPress\AiClient\Providers\Http\Abstracts;

use Http\Discovery\Psr18ClientDiscovery;
use Http\Discovery\Strategy\DiscoveryStrategy;
use WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory;
use WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;

/**
 * Abstract discovery strategy for HTTP client implementations.
 *
 * WP trunk shim: mirrors WordPress\AiClient\Providers\Http\Abstracts\AbstractClientDiscoveryStrategy
 * from the Composer-installed wordpress/php-ai-client package, but uses the
 * scoped WordPress\AiClientDependencies\ namespace for PSR and Nyholm types
 * so that WP trunk's concrete adapter class can extend it without a fatal.
 *
 * @since 1.1.0
 */
abstract class AbstractClientDiscoveryStrategy implements DiscoveryStrategy {

	/**
	 * Initializes and registers the discovery strategy.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! class_exists( '\Http\Discovery\Psr18ClientDiscovery' ) ) {
			return;
		}

		Psr18ClientDiscovery::prependStrategy( static::class );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param string $type The type of discovery.
	 * @return array<array<string, mixed>> The discovery candidates.
	 */
	public static function getCandidates( $type ) {
		if ( ClientInterface::class === $type ) {
			return array(
				array(
					'class' => static function () {
						$psr17_factory = new Psr17Factory();
						return static::createClient( $psr17_factory );
					},
				),
			);
		}

		$psr17_factories = array(
			'WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileFactoryInterface',
			'WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface',
		);

		if ( in_array( $type, $psr17_factories, true ) ) {
			return array(
				array(
					'class' => Psr17Factory::class,
				),
			);
		}

		return array();
	}

	/**
	 * Creates an instance of the HTTP client.
	 *
	 * Subclasses must implement this method to return their specific
	 * PSR-18 HTTP client instance. The provided Psr17Factory implements
	 * all PSR-17 interfaces and can be used to satisfy client constructor
	 * dependencies.
	 *
	 * @since 1.1.0
	 *
	 * @param Psr17Factory $psr17_factory The PSR-17 factory for creating HTTP messages.
	 * @return ClientInterface The PSR-18 HTTP client.
	 */
	abstract protected static function createClient( Psr17Factory $psr17_factory ): ClientInterface;
}
