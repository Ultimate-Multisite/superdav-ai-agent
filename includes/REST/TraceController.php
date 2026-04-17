<?php

declare(strict_types=1);
/**
 * REST API controller for Provider Trace (debug HTTP traffic logging).
 *
 * Provides endpoints for listing, viewing, and clearing provider trace records,
 * as well as toggling the trace setting.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Models\ProviderTrace;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages provider trace records via REST.
 *
 * Endpoints:
 *  GET    /trace             — list trace records
 *  GET    /trace/{id}        — get a single trace record
 *  GET    /trace/{id}/curl   — get curl command for a trace
 *  DELETE /trace             — clear all traces
 *  GET    /trace/settings    — get trace settings
 *  POST   /trace/settings    — update trace settings
 *
 * Uses #[Handler] + INIT_IMMEDIATELY so register_routes() is called directly
 * on rest_api_init, which is the only strategy that works in the PHPUnit test
 * environment (the #[REST_Handler] INIT_DEFFERED path fails to fire its
 * do_action chain when rest_api_init is manually triggered by test setUp()).
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class TraceController {

	/**
	 * Permission check — admin only.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST routes for provider trace endpoints.
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/trace',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_list_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_clear' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/trace/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/trace/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_id_args(),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/trace/(?P<id>\d+)/curl',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_curl' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_id_args(),
			)
		);
	}

	/**
	 * Handle GET /trace — list trace records.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list( WP_REST_Request $request ): WP_REST_Response {
		$filters = [];

		$provider = $request->get_param( 'provider' );
		if ( ! empty( $provider ) ) {
			$filters['provider_id'] = $provider;
		}

		$status_code = $request->get_param( 'status_code' );
		if ( ! empty( $status_code ) ) {
			$filters['status_code'] = (int) $status_code;
		}

		if ( $request->get_param( 'errors_only' ) ) {
			$filters['errors_only'] = true;
		}

		$filters['limit']  = (int) $request->get_param( 'limit' );
		$filters['offset'] = (int) $request->get_param( 'offset' );

		$traces = ProviderTrace::list( $filters );
		$total  = ProviderTrace::count( $filters );

		return new WP_REST_Response(
			array(
				'traces' => $traces,
				'total'  => $total,
			)
		);
	}

	/**
	 * Handle GET /trace/{id} — get a single trace record with full bodies.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get( WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$trace = ProviderTrace::get( $id );

		if ( ! $trace ) {
			return new WP_Error(
				'trace_not_found',
				__( 'Trace record not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $trace );
	}

	/**
	 * Handle GET /trace/{id}/curl — get curl command for a trace.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_curl( WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$trace = ProviderTrace::get( $id );

		if ( ! $trace ) {
			return new WP_Error(
				'trace_not_found',
				__( 'Trace record not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response(
			array(
				'curl' => ProviderTrace::to_curl( $trace ),
			)
		);
	}

	/**
	 * Handle DELETE /trace — clear all trace records.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_clear(): WP_REST_Response {
		ProviderTrace::clear();

		return new WP_REST_Response(
			array( 'cleared' => true )
		);
	}

	/**
	 * Handle GET /trace/settings — get trace settings.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'enabled'  => ProviderTrace::is_enabled(),
				'max_rows' => ProviderTrace::get_max_rows(),
				'count'    => ProviderTrace::count(),
				'warning'  => ProviderTrace::is_enabled()
					? __( 'Provider tracing is enabled. Logs may contain prompt content. Disable on shared environments.', 'gratis-ai-agent' )
					: '',
			)
		);
	}

	/**
	 * Handle POST /trace/settings — update trace settings.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$enabled  = $request->get_param( 'enabled' );
		$max_rows = $request->get_param( 'max_rows' );

		if ( null !== $enabled ) {
			ProviderTrace::set_enabled( (bool) $enabled );
		}

		if ( null !== $max_rows ) {
			ProviderTrace::set_max_rows( (int) $max_rows );
		}

		return new WP_REST_Response(
			array(
				'enabled'  => ProviderTrace::is_enabled(),
				'max_rows' => ProviderTrace::get_max_rows(),
				'count'    => ProviderTrace::count(),
				'warning'  => ProviderTrace::is_enabled()
					? __( 'Provider tracing is enabled. Logs may contain prompt content. Disable on shared environments.', 'gratis-ai-agent' )
					: '',
			)
		);
	}

	/**
	 * Schema arguments for GET /trace (list).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_list_args(): array {
		return array(
			'limit'       => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 50,
				'sanitize_callback' => 'absint',
			),
			'offset'      => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'provider'    => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status_code' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'errors_only' => array(
				'required' => false,
				'type'     => 'boolean',
				'default'  => false,
			),
		);
	}

	/**
	 * Schema arguments for routes that only need an ID parameter.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_id_args(): array {
		return array(
			'id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Schema arguments for POST /trace/settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_settings_args(): array {
		return array(
			'enabled'  => array(
				'required' => false,
				'type'     => 'boolean',
			),
			'max_rows' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}
}
