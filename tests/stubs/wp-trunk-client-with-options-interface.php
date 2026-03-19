<?php
/**
 * WP trunk compatibility shim: ClientWithOptionsInterface.
 *
 * WP trunk's bundled php-ai-client scopes its PSR dependencies under the
 * WordPress\AiClientDependencies\ prefix (via its own autoloader). The
 * Composer-installed wordpress/php-ai-client package uses the global Psr\
 * namespace instead. When both are present in the same PHP process the two
 * definitions of ClientWithOptionsInterface are incompatible, causing a fatal:
 *
 *   Declaration of WP_AI_Client_HTTP_Client::sendRequestWithOptions(
 *     WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface …)
 *   must be compatible with
 *     ClientWithOptionsInterface::sendRequestWithOptions(
 *     Psr\Http\Message\RequestInterface …)
 *
 * This shim is loaded by a prepended autoloader registered in tests/bootstrap.php
 * when WP trunk is detected. It defines the interface using the scoped
 * WordPress\AiClientDependencies\Psr\ namespace so that WP trunk's adapter
 * class can implement it without a signature mismatch.
 *
 * @package GratisAiAgent\Tests
 */

namespace WordPress\AiClient\Providers\Http\Contracts;

use WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * Interface for HTTP clients that support per-request transport options.
 *
 * Mirrors WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface
 * from WP trunk's bundled php-ai-client, using the scoped PSR namespace.
 *
 * @since 0.2.0
 */
interface ClientWithOptionsInterface {

	/**
	 * Sends an HTTP request with the given transport options.
	 *
	 * @since 0.2.0
	 *
	 * @param RequestInterface $request The PSR-7 request to send.
	 * @param RequestOptions   $options The request transport options.
	 * @return ResponseInterface The PSR-7 response received.
	 */
	public function sendRequestWithOptions( RequestInterface $request, RequestOptions $options ): ResponseInterface;
}
