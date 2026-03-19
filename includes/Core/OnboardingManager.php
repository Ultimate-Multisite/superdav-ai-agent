<?php

declare(strict_types=1);
/**
 * Onboarding Manager — orchestrates the first-activation site scan.
 *
 * Detects whether this is a fresh installation (no memories, no scan record)
 * and schedules the background SiteScanner job. Also exposes a REST endpoint
 * so the admin UI can poll scan status.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Models\Memory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnboardingManager {

	/**
	 * Option key that records whether onboarding has been triggered.
	 * Separate from SiteScanner::STATUS_OPTION so we can distinguish
	 * "never triggered" from "triggered but pending".
	 */
	const TRIGGERED_OPTION = 'gratis_ai_agent_onboarding_triggered';

	// ── Bootstrap ─────────────────────────────────────────────────────────

	/**
	 * Register all hooks.
	 */
	public static function register(): void {
		// Register the cron handler.
		SiteScanner::register();

		// On every admin_init, check whether we should trigger onboarding.
		add_action( 'admin_init', [ __CLASS__, 'maybe_trigger' ] );

		// REST endpoint for status polling.
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	/**
	 * Called on plugin activation — schedule the scan immediately.
	 */
	public static function on_activation(): void {
		self::trigger();
	}

	// ── Trigger logic ─────────────────────────────────────────────────────

	/**
	 * Trigger the onboarding scan if conditions are met.
	 *
	 * Conditions (all must be true):
	 *  1. Scan has never been triggered before.
	 *  2. No existing memories (fresh install).
	 *  3. Scan is not already complete or running.
	 */
	public static function maybe_trigger(): void {
		// Already triggered — nothing to do.
		if ( get_option( self::TRIGGERED_OPTION ) ) {
			return;
		}

		// Scan already complete or running.
		if ( SiteScanner::is_complete() || SiteScanner::is_pending() ) {
			return;
		}

		// If there are existing memories, this is not a fresh install.
		$existing_memories = Memory::get_all();
		if ( ! empty( $existing_memories ) ) {
			// Mark as triggered so we don't keep checking.
			update_option( self::TRIGGERED_OPTION, true, false );
			return;
		}

		self::trigger();
	}

	/**
	 * Schedule the background scan and mark as triggered.
	 */
	public static function trigger(): void {
		update_option( self::TRIGGERED_OPTION, true, false );
		SiteScanner::schedule();
	}

	/**
	 * Reset onboarding state (allows re-running the scan).
	 *
	 * Clears the triggered flag and scan status so the next admin_init
	 * will re-evaluate and schedule a new scan.
	 */
	public static function reset(): void {
		delete_option( self::TRIGGERED_OPTION );
		delete_option( SiteScanner::STATUS_OPTION );
		SiteScanner::unschedule();
	}

	// ── REST API ──────────────────────────────────────────────────────────

	/**
	 * Register the onboarding status REST route.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'gratis-ai-agent/v1',
			'/onboarding/status',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'rest_get_status' ],
				'permission_callback' => [ __CLASS__, 'rest_permission' ],
			]
		);

		register_rest_route(
			'gratis-ai-agent/v1',
			'/onboarding/rescan',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_rescan' ],
				'permission_callback' => [ __CLASS__, 'rest_permission' ],
			]
		);

		// Interview endpoints (t064).
		register_rest_route(
			'gratis-ai-agent/v1',
			'/onboarding/interview',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'rest_get_interview' ],
					'permission_callback' => [ __CLASS__, 'rest_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'rest_save_interview' ],
					'permission_callback' => [ __CLASS__, 'rest_permission' ],
					'args'                => [
						'answers' => [
							'required' => false,
							'type'     => 'object',
							'default'  => [],
						],
						'skipped' => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						],
					],
				],
			]
		);
	}

	/**
	 * Permission callback — require manage_options capability.
	 *
	 * @return bool|\WP_Error
	 */
	public static function rest_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'gratis-ai-agent' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * GET /gratis-ai-agent/v1/onboarding/status
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_get_status(): \WP_REST_Response {
		$scan_status = SiteScanner::get_status();

		return new \WP_REST_Response(
			[
				'triggered'       => (bool) get_option( self::TRIGGERED_OPTION ),
				'scan'            => $scan_status,
				'scheduled'       => (bool) wp_next_scheduled( SiteScanner::CRON_HOOK ),
				'interview_ready' => OnboardingInterview::is_ready(),
				'interview_done'  => OnboardingInterview::is_done(),
			],
			200
		);
	}

	/**
	 * POST /gratis-ai-agent/v1/onboarding/rescan
	 *
	 * Resets onboarding state and schedules a fresh scan.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_rescan(): \WP_REST_Response {
		self::reset();
		OnboardingInterview::reset();
		self::trigger();

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Site scan scheduled. Results will be available shortly.', 'gratis-ai-agent' ),
			],
			200
		);
	}

	// ── Interview REST handlers (t064) ────────────────────────────────────

	/**
	 * GET /gratis-ai-agent/v1/onboarding/interview
	 *
	 * Returns the interview questions and current status.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_get_interview(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'ready'     => OnboardingInterview::is_ready(),
				'done'      => OnboardingInterview::is_done(),
				'questions' => OnboardingInterview::is_ready()
					? OnboardingInterview::get_questions()
					: [],
			],
			200
		);
	}

	/**
	 * POST /gratis-ai-agent/v1/onboarding/interview
	 *
	 * Saves interview answers (or marks as skipped).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_save_interview( \WP_REST_Request $request ) {
		$skipped = (bool) $request->get_param( 'skipped' );

		if ( $skipped ) {
			OnboardingInterview::mark_skipped();

			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Interview skipped.', 'gratis-ai-agent' ),
				],
				200
			);
		}

		$answers = $request->get_param( 'answers' );

		if ( ! is_array( $answers ) ) {
			return new \WP_Error(
				'invalid_answers',
				__( 'Answers must be an object mapping question IDs to answer strings.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		// Sanitize each answer.
		$sanitized = [];
		foreach ( $answers as $id => $value ) {
			// @phpstan-ignore-next-line
			$sanitized[ sanitize_key( $id ) ] = sanitize_textarea_field( (string) $value );
		}

		$saved = OnboardingInterview::save_answers( $sanitized );

		if ( ! $saved ) {
			return new \WP_Error(
				'save_failed',
				__( 'No answers were provided.', 'gratis-ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Interview answers saved. The AI now has context about your site goals.', 'gratis-ai-agent' ),
			],
			200
		);
	}
}
