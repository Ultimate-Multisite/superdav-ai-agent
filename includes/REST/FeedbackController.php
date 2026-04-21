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
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;
use XWP_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages feedback report preview and submission.
 */
#[REST_Handler(
	namespace: RestController::NAMESPACE,
	basename: 'feedback',
	container: 'gratis-ai-agent',
)]
final class FeedbackController extends XWP_REST_Controller {

	use PermissionTrait;

	/**
	 * Handle GET /feedback/preview — return the sanitized payload for modal preview.
	 *
	 * When message_index >= 0, only the targeted message +/- 2 surrounding messages
	 * are included (thumbs-down scoped context, t186).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: 'preview',
		methods: WP_REST_Server::READABLE,
		vars: 'get_preview_args',
		guard: 'check_chat_permission',
	)]
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
	#[REST_Route(
		route: 'send',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_send_args',
		guard: 'check_chat_permission',
	)]
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
				'session_data'    => null,
				'environment'      => array(),
				'generated_at'     => gmdate( 'c' ),
			);
		}

		$sanitized = ReportSanitizer::sanitize( $payload );
		$result    = ReportSender::send( $sanitized, true ); // Force send for manual user submissions.

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
