<?php

declare(strict_types=1);

namespace SdAiAgent\Core;

use SdAiAgent\REST\RestController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dispatch long-running agent jobs outside the browser-facing PHP request. */
final class BackgroundJobDispatcher {

	public const HOOK   = 'sd_ai_agent_process_background_job';
	private const GROUP = 'sd-ai-agent';

	/**
	 * Queue a job on its own site's out-of-process worker.
	 *
	 * The worker bootstraps the site from the queued action's URL. Switching
	 * from the main site only after bootstrap cannot access a tenant's job
	 * transient when its object-cache prefix is isolated.
	 */
	public static function dispatch( string $job_id, string $token ): void {
		$target_blog_id = get_current_blog_id();
		$args           = array( $target_blog_id, $job_id );
		$scheduled      = self::schedule_action( $args );
		if ( ! $scheduled ) {
			if ( false === wp_next_scheduled( self::HOOK, $args ) ) {
				wp_schedule_single_event( time(), self::HOOK, $args, true );
			}
		}

		// Hosts with a persistent queue worker (for example The Perfect WP Cron)
		// run the queued action in a CLI subprocess with no PHP-FPM request
		// time limit. A loopback would win the claim and run the whole loop
		// inside an FPM request that request_terminate_timeout can kill, so
		// such hosts return false from this filter to leave the job to the queue.
		/**
		 * Whether to also start the job immediately with a loopback request.
		 *
		 * @param bool   $use_loopback Default true.
		 * @param string $job_id       Job ID.
		 */
		$use_loopback = (bool) apply_filters( 'sd_ai_agent_background_loopback', true, $job_id );
		if ( ! $use_loopback && $scheduled ) {
			return;
		}

		// Start immediately, even from REST requests that do not wake the queue.
		// Keep the scheduled action as a safety net when loopbacks are blocked;
		// every starter shares the worker's atomic queued-to-processing claim.
		self::start_worker( $job_id, $token );
	}

	/** @var list<array{0:string,1:string}> Loopbacks deferred until the browser response is sent. */
	private static array $deferred = array();

	/**
	 * Launch the out-of-process worker for a job.
	 *
	 * WordPress' cURL transport enforces a one-second minimum timeout, so even a
	 * non-blocking loopback holds the browser-facing request for about a second.
	 * Under FastCGI/LiteSpeed, send the response first and fire the loopback from
	 * a shutdown function; elsewhere (CLI, tests) send it inline as before.
	 *
	 * @param string $job_id Job ID.
	 * @param string $token  Job token proving the loopback was issued by this site.
	 */
	public static function start_worker( string $job_id, string $token ): void {
		$url  = rest_url( RestController::NAMESPACE . '/process' );
		$body = (string) wp_json_encode(
			array(
				'job_id' => $job_id,
				'token'  => $token,
			)
		);

		if ( ! self::can_finish_response_early() ) {
			self::send_loopback( $url, $body );
			return;
		}

		if ( empty( self::$deferred ) ) {
			register_shutdown_function( array( self::class, 'flush_deferred' ) );
		}
		self::$deferred[] = array( $url, $body );
	}

	/** Send deferred loopbacks after the client response has been completed. */
	public static function flush_deferred(): void {
		$pending        = self::$deferred;
		self::$deferred = array();
		if ( empty( $pending ) ) {
			return;
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		foreach ( $pending as $loopback ) {
			self::send_loopback( $loopback[0], $loopback[1] );
		}
	}

	/** Whether this SAPI can complete the browser response before shutdown work. */
	private static function can_finish_response_early(): bool {
		return ! ( defined( 'WP_CLI' ) && WP_CLI )
			&& 'cli' !== PHP_SAPI
			&& ( function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' ) );
	}

	/**
	 * Fire one non-blocking worker loopback.
	 *
	 * @param string $url  Process endpoint on the originating site.
	 * @param string $body JSON body with the job ID and token.
	 */
	private static function send_loopback( string $url, string $body ): void {
		wp_remote_post(
			$url,
			array(
				'timeout'  => 0.01,
				'blocking' => false,
				'body'     => $body,
				'headers'  => array( 'Content-Type' => 'application/json' ),
			)
		);
	}

	/**
	 * Prefer Action Scheduler because the queue worker gives it an independent
	 * execution lane that cannot be starved by an unrelated WP-Cron backlog.
	 *
	 * @param array{0:int,1:string} $args Callback arguments.
	 */
	private static function schedule_action( array $args ): bool {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		if ( as_has_scheduled_action( self::HOOK, $args, self::GROUP ) ) {
			return true;
		}

		return as_enqueue_async_action( self::HOOK, $args, self::GROUP, false, 0 ) > 0;
	}
}
