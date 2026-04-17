<?php

declare(strict_types=1);
/**
 * REST API controller for feedback report sending.
 *
 * Provides two endpoints:
 *   GET  /feedback/preview — returns sanitized payload for the consent modal.
 *   POST /feedback/send    — builds, sanitizes, and forwards the report.
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
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages feedback report preview and submission.
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
final class FeedbackController {

	use PermissionTrait;

	/**
	 * Register REST routes for feedback endpoints.
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/feedback/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_preview' ),
				'permission_callback' => array( $this, 'check_chat_permission' ),
				'args'                => $this->get_preview_args(),
			)
		);
		register_rest_route(
			RestController::NAMESPACE,
			'/feedback/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_send' ),
				'permission_callback' => array( $this, 'check_chat_permission' ),
				'args'                => $this->get_send_args(),
			)
		);
	}

	/**
	 * Handle GET /feedback/preview — return the sanitized payload for modal preview.
	 *
	 * When message_index >= 0, only the targeted message +/- 2 surrounding messages
	 * are included (thumbs-down scoped context, t186).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session_id         = (int) $request->get_param( 'session_id' );
		$strip_tool_results = (bool) $request->get_param( 'strip_tool_results' );
		$message_index      = (int) $request->get_param( 'message_index' );

		$summary = ReportBuilder::build_summary( $session_id, $strip_tool_results, $message_index );

		if ( null === $summary ) {
			return new WP_Error(
				'feedback_session_not_found',
				__( 'Session not found or access denied.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		$raw_payload = ReportBuilder::build(
			$session_id,
			'preview',
			'',
			$strip_tool_results,
			$message_index
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
	 * When message_index >= 0, only the targeted message +/- 2 surrounding messages
	 * are included in the report (thumbs-down scoped context, t186).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_send( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$report_type        = (string) $request->get_param( 'report_type' );
		$user_description   = (string) $request->get_param( 'user_description' );
		$session_id         = (int) $request->get_param( 'session_id' );
		$strip_tool_results = (bool) $request->get_param( 'strip_tool_results' );
		$message_index      = (int) $request->get_param( 'message_index' );

		if ( $session_id > 0 ) {
			$payload = ReportBuilder::build(
				$session_id,
				$report_type,
				$user_description,
				$strip_tool_results,
				$message_index
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

	/**
	 * Schema arguments for GET /feedback/preview.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_preview_args(): array {
		return array(
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
			'message_index'      => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => -1,
			),
		);
	}

	/**
	 * Schema arguments for POST /feedback/send.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_send_args(): array {
		return array(
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
			'message_index'      => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => -1,
			),
		);
	}
}
