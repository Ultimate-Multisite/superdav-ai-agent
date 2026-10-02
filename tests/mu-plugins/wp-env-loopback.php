<?php
/**
 * Route wp-env self-requests to Apache's container port.
 *
 * Browser URLs use Docker's published port, which is not listening inside the
 * WordPress container. Preserve the public Host header for WordPress routing.
 * This helper is mounted only in wp-env and never ships in the plugin.
 *
 * @package SdAiAgent
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_http_request',
	static function ( false|array|WP_Error $response, array $args, string $url ): false|array|WP_Error {
		if ( false !== $response || ! defined( 'SD_AI_AGENT_WP_ENV_LOOPBACK_HOST' ) || 'development' !== wp_get_environment_type() ) {
			return $response;
		}

		$site    = wp_parse_url( site_url() );
		$request = wp_parse_url( $url );
		if ( ! is_array( $site ) || ! is_array( $request )
			|| ! in_array( $site['host'] ?? '', array( 'localhost', '127.0.0.1' ), true )
			|| 'http' !== ( $site['scheme'] ?? '' )
			|| ( $request['scheme'] ?? '' ) !== $site['scheme']
			|| ( $request['host'] ?? '' ) !== $site['host']
			|| ( $request['port'] ?? 80 ) !== ( $site['port'] ?? 80 )
			|| 80 === ( $site['port'] ?? 80 )
			|| isset( $request['user'] ) || isset( $request['pass'] )
		) {
			return $response;
		}

		$internal_url = 'http://' . SD_AI_AGENT_WP_ENV_LOOPBACK_HOST . ( $request['path'] ?? '/' );
		if ( isset( $request['query'] ) ) {
			$internal_url .= '?' . $request['query'];
		}

		if ( is_string( $args['headers'] ?? null ) ) {
			$headers = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $args['headers'] ) ?: array() as $header ) {
				$separator = strpos( $header, ':' );
				if ( false !== $separator ) {
					$header_name              = trim( substr( $header, 0, $separator ) );
					$headers[ $header_name ] = trim( substr( $header, $separator + 1 ) );
				}
			}

			$args['headers'] = $headers;
		}

		$args['headers']['Host'] = $site['host'] . ':' . $site['port'];

		return wp_remote_request( $internal_url, $args );
	},
	5,
	3
);
