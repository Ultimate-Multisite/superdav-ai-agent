<?php

declare(strict_types=1);
/**
 * DI wiring for the durable customer-agent runtime worker.
 *
 * @package SdAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Bootstrap;

use SdAiAgent\Core\CustomerAgentJobDispatcher;
use SdAiAgent\Core\Database;
use SdAiAgent\REST\RestController;
use SdAiAgent\Services\CustomerAgentRuntimeService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Wires the private WP-Cron processing hook in every WordPress context. */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class CustomerAgentRuntimeHandler {

	/** Upgrade stale schemas before frontend, REST, CLI, or cron runtime use. */
	#[Action( tag: 'init', priority: 1 )]
	public function ensure_database_schema(): void {
		Database::install();
	}

	/** Execute one opaque queued customer-agent job. */
	#[Action( tag: CustomerAgentRuntimeService::PROCESS_HOOK, priority: 10 )]
	public function process_job( string $job_id ): void {
		CustomerAgentRuntimeService::instance()->process_job( $job_id );
	}

	/** Register the signed loopback that starts one job in its own worker. */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_process_route(): void {
		register_rest_route(
			RestController::NAMESPACE,
			CustomerAgentJobDispatcher::ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_process_request' ),
				'permission_callback' => array( $this, 'verify_process_request' ),
				'args'                => array(
					'job_id'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'timestamp' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'signature' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Accept only fresh loopbacks signed by this install.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string,mixed>> $request
	 */
	public function verify_process_request( WP_REST_Request $request ): bool {
		return CustomerAgentJobDispatcher::verify(
			(string) $request->get_param( 'job_id' ),
			(int) $request->get_param( 'timestamp' ),
			(string) $request->get_param( 'signature' )
		);
	}

	/**
	 * Run the job in this request. The sender has already disconnected.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param WP_REST_Request<array<string,mixed>> $request
	 */
	public function handle_process_request( WP_REST_Request $request ): WP_REST_Response {
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- The runtime enforces its own per-job deadline.
		}

		CustomerAgentRuntimeService::instance()->process_job( (string) $request->get_param( 'job_id' ) );

		return new WP_REST_Response( array( 'processed' => true ), 200 );
	}
}
