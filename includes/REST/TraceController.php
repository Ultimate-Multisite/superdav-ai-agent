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
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;
use XWP_REST_Controller;

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
 */
#[REST_Handler(
	namespace: RestController::NAMESPACE,
	basename: 'trace',
	container: 'gratis-ai-agent',
)]
final class TraceController extends XWP_REST_Controller {

	/**
	 * Permission check — admin only, and only when WP_DEBUG is active.
	 *
	 * Provider tracing is a debug-only feature. The REST endpoints are
	 * unavailable (return 403) on any site where WP_DEBUG is not defined
	 * or is false, regardless of the user's role.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		if ( ! ProviderTrace::is_debug_mode() ) {
			return false;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle GET /trace — list trace records.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	#[REST_Route(
		route: '',
		methods: WP_REST_Server::READABLE,
		vars: 'get_list_args',
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: '(?P<id>\d+)',
		methods: WP_REST_Server::READABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: '(?P<id>\d+)/curl',
		methods: WP_REST_Server::READABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: '',
		methods: WP_REST_Server::DELETABLE,
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: 'settings',
		methods: WP_REST_Server::READABLE,
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: 'settings',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_settings_args',
		guard: 'check_permission',
	)]
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
