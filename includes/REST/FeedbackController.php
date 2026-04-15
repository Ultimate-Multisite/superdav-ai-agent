<?php

declare(strict_types=1);
/**
 * REST API controller for feedback report sending.
 *
 * Provides two endpoints:
 *   GET  /gratis-ai-agent/v1/feedback/preview — returns sanitized payload for
 *        the consent modal's collapsible "View full payload" section.
 *   POST /gratis-ai-agent/v1/feedback/send    — builds, sanitizes, and forwards
 *        the report to the configured feedback receiver endpoint.
 *
 * The API key is held server-side and never exposed to the browser.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Feedback\ReportBuilder;
use GratisAiAgent\Feedback\ReportSanitizer;
use GratisAiAgent\Feedback\ReportSender;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class FeedbackController {

	use PermissionTrait;

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		$instance = new self();

		register_rest_route(
			self::NAMESPACE,
			'/feedback/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_preview' ),
				'permission_callback' => array( $instance, 'check_chat_permission' ),
				'args'                => array(
					'session_id'         => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'strip_tool_results' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/feedback/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_send' ),
				'permission_callback' => array( $instance, 'check_chat_permission' ),
				'args'                => array(
					'report_type'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'user_description'   => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'session_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'strip_tool_results' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);
	}

	/**
	 * Handle GET /feedback/preview — return the sanitized payload for modal preview.
	 *
	 * Returns:
	 *   - summary  (message_count, tool_call_count, environment_keys, model_id, …)
	 *   - payload  (full sanitized report, suitable for JSON display)
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session_id         = (int) $request->get_param( 'session_id' );
		$strip_tool_results = (bool) $request->get_param( 'strip_tool_results' );

		$summary = ReportBuilder::build_summary( $session_id, $strip_tool_results );

		if ( null === $summary ) {
			return new WP_Error(
				'feedback_session_not_found',
				__( 'Session not found or access denied.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		// Build the full sanitized payload for the collapsible preview section.
		$raw_payload = ReportBuilder::build(
			$session_id,
			'preview',
			'',
			$strip_tool_results
		);

		if ( null === $raw_payload ) {
			return new WP_Error(
				'feedback_build_error',
				__( 'Could not build report payload.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$sanitized = ReportSanitizer::sanitize( $raw_payload );

		return new WP_REST_Response(
			array(
				'summary' => $summary,
				'payload' => $sanitized,
			),
			200
		);
	}

	/**
	 * Handle POST /feedback/send — build, sanitize, and forward the report.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_send( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$report_type        = (string) $request->get_param( 'report_type' );
		$user_description   = (string) $request->get_param( 'user_description' );
		$session_id         = (int) $request->get_param( 'session_id' );
		$strip_tool_results = (bool) $request->get_param( 'strip_tool_results' );

		// When a session_id is provided, build a rich payload; otherwise send a
		// minimal report (for cases where no session is active yet, e.g. onboarding).
		if ( $session_id > 0 ) {
			$payload = ReportBuilder::build(
				$session_id,
				$report_type,
				$user_description,
				$strip_tool_results
			);

			if ( null === $payload ) {
				return new WP_Error(
					'feedback_session_not_found',
					__( 'Session not found or access denied.', 'gratis-ai-agent' ),
					array( 'status' => 404 )
				);
			}
		} else {
			$payload = array(
				'report_type'      => $report_type,
				'user_description' => $user_description,
				'session'          => null,
				'environment'      => array(),
				'generated_at'     => gmdate( 'c' ),
			);
		}

		$sanitized = ReportSanitizer::sanitize( $payload );
		$result    = ReportSender::send( $sanitized );

		if ( is_wp_error( $result ) ) {
			// Map well-known error codes to appropriate HTTP status codes.
			$http_status = 500;
			$error_code  = $result->get_error_code();

			if ( in_array( $error_code, array( 'feedback_disabled', 'feedback_no_endpoint', 'feedback_invalid_url' ), true ) ) {
				$http_status = 422;
			}

			return new WP_Error(
				$error_code,
				$result->get_error_message(),
				array( 'status' => $http_status )
			);
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
