<?php

declare(strict_types=1);
/**
 * Immediate, parallel dispatch for durable customer-agent runtime jobs.
 *
 * @package SdAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

use SdAiAgent\REST\RestController;
use SdAiAgent\Services\CustomerAgentRuntimeService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts each queued customer turn in its own PHP worker right away.
 *
 * WP-Cron alone made customer replies slow and serial: spawn_cron() refuses to
 * start while another cron run holds the lock, and one cron run executes due
 * events one after another. Each job now gets a signed, non-blocking loopback
 * to its own worker. The scheduled WP-Cron event stays as the fallback; the
 * runtime's atomic queued-to-processing claim makes every extra start a no-op.
 */
final class CustomerAgentJobDispatcher {

	/** REST route, relative to RestController::NAMESPACE. */
	public const ROUTE = '/customer-agent/process';

	/** Maximum age of a loopback signature, in seconds. */
	private const SIGNATURE_TTL = 60;

	/**
	 * Queue the fallback event and start the job's worker now.
	 *
	 * @param string $job_id Runtime job ID.
	 * @return bool Whether the fallback event is scheduled.
	 */
	public static function dispatch( string $job_id ): bool {
		$scheduled = wp_schedule_single_event( time(), CustomerAgentRuntimeService::PROCESS_HOOK, array( $job_id ), true );
		if ( true !== $scheduled ) {
			// The caller fails the job; never start a worker for it.
			return false;
		}

		/**
		 * Whether to start customer-agent jobs immediately with a loopback request.
		 *
		 * Hosts that block loopback requests, or run a persistent queue worker,
		 * may return false to rely on the scheduled WP-Cron event alone.
		 *
		 * @param bool   $use_loopback Default true.
		 * @param string $job_id       Runtime job ID.
		 */
		if ( (bool) apply_filters( 'sd_ai_agent_customer_agent_loopback', true, $job_id ) ) {
			self::send_loopback( $job_id );
		} elseif ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		return true;
	}

	/**
	 * Accept only fresh requests signed with this install's salt.
	 *
	 * @param string $job_id    Runtime job ID.
	 * @param int    $timestamp Unix time the request was signed.
	 * @param string $signature Hex HMAC.
	 */
	public static function verify( string $job_id, int $timestamp, string $signature ): bool {
		if ( '' === $job_id || abs( time() - $timestamp ) > self::SIGNATURE_TTL ) {
			return false;
		}

		return hash_equals( self::sign( $job_id, $timestamp ), $signature );
	}

	/**
	 * Fire one POST to this site's process route without waiting for it.
	 *
	 * WordPress' cURL transport enforces a one-second minimum timeout, so even a
	 * non-blocking request stalls the caller for about a second. The bundled
	 * fsockopen transport returns as soon as the request is written. Callers
	 * are often long-running background workers, so the request is not deferred
	 * to shutdown.
	 *
	 * @param string $job_id Runtime job ID.
	 */
	private static function send_loopback( string $job_id ): void {
		$url       = get_rest_url( get_current_blog_id(), RestController::NAMESPACE . self::ROUTE );
		$timestamp = time();
		$body      = array(
			'job_id'    => $job_id,
			'timestamp' => $timestamp,
			'signature' => self::sign( $job_id, $timestamp ),
		);
		/** This filter is documented in wp-includes/cron.php */
		$sslverify = (bool) apply_filters( 'https_local_ssl_verify', false );

		$is_ssl = str_starts_with( $url, 'https://' );
		if ( class_exists( '\WpOrg\Requests\Requests' ) && class_exists( '\WpOrg\Requests\Transport\Fsockopen' ) && \WpOrg\Requests\Transport\Fsockopen::test( array( 'ssl' => $is_ssl ) ) ) {
			try {
				\WpOrg\Requests\Requests::post(
					$url,
					array(),
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

		wp_remote_post(
			$url,
			array(
				'timeout'     => 0.01,
				'blocking'    => false,
				'sslverify'   => $sslverify,
				'redirection' => 0,
				'body'        => $body,
			)
		);
	}

	/**
	 * Sign one loopback request.
	 *
	 * @param string $job_id    Runtime job ID.
	 * @param int    $timestamp Unix time.
	 */
	private static function sign( string $job_id, int $timestamp ): string {
		return hash_hmac( 'sha256', 'sd_ai_agent_customer_agent_process|' . $job_id . '|' . $timestamp, wp_salt( 'nonce' ) );
	}
}
