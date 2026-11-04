<?php

declare(strict_types=1);
/**
 * Non-blocking same-site loopback requests.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

/**
 * Sends fire-and-forget POST requests to this site.
 *
 * WordPress' cURL transport enforces a one-second minimum timeout, so even a
 * `blocking => false` request holds the caller for about a second. The bundled
 * fsockopen transport returns as soon as the request is written.
 */
final class LoopbackRequest {

	/**
	 * Send a non-blocking POST to a same-site URL.
	 *
	 * @param string               $url     Target URL.
	 * @param string|array<mixed>  $body    Request body.
	 * @param array<string,string> $headers Request headers.
	 */
	public static function send( string $url, string|array $body, array $headers = array() ): void {
		/** This filter is documented in wp-includes/cron.php */
		$sslverify = (bool) apply_filters( 'https_local_ssl_verify', false );

		/**
		 * Filters whether loopbacks may use the fsockopen transport.
		 *
		 * @param bool $enabled Whether the fast path is allowed. Default true.
		 */
		$fast = (bool) apply_filters( 'sd_ai_agent_fast_loopback', true );

		if ( $fast && class_exists( '\WpOrg\Requests\Requests' ) && class_exists( '\WpOrg\Requests\Transport\Fsockopen' ) ) {
			$is_ssl = str_starts_with( $url, 'https://' );
			if ( \WpOrg\Requests\Transport\Fsockopen::test( array( 'ssl' => $is_ssl ) ) ) {
				try {
					\WpOrg\Requests\Requests::post(
						$url,
						$headers,
						$body,
						array(
							'transport'        => \WpOrg\Requests\Transport\Fsockopen::class,
							'blocking'         => false,
							'timeout'          => 1,
							'connect_timeout'  => 1,
							'verify'           => $sslverify,
							'follow_redirects' => false,
						)
					);
					return;
				} catch ( \Exception $exception ) {
					// Fall through to the WordPress HTTP API.
					unset( $exception );
				}
			}
		}

		wp_remote_post(
			$url,
			array(
				'timeout'     => 0.01,
				'blocking'    => false,
				'sslverify'   => $sslverify,
				'redirection' => 0,
				'body'        => $body,
				'headers'     => $headers,
			)
		);
	}
}
