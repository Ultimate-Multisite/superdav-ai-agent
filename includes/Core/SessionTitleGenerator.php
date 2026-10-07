<?php

declare(strict_types=1);

namespace SdAiAgent\Core;

use SdAiAgent\Models\Agent;
use SdAiAgent\Repositories\SessionRepository;
use SdAiAgent\REST\RestController;

/** Generate first-message titles in a separate worker alongside the agent. */
final class SessionTitleGenerator {

	private const PENDING_PREFIX = 'sd_ai_agent_title_pending_';
	private const TTL            = 180;

	/**
	 * Publish a provisional title and launch an independently authenticated worker.
	 *
	 * @param int    $session_id Session identifier.
	 * @param string $message First user message.
	 * @param string $provider_id Requested provider.
	 * @param string $model_id Requested model.
	 * @param int    $agent_id Selected agent, if any.
	 * @return string|null Provisional title when this request claimed generation.
	 */
	public static function start( int $session_id, string $message, string $provider_id, string $model_id, int $agent_id = 0 ): ?string {
		$user_id = get_current_user_id();
		$message = trim( $message );
		if ( $session_id <= 0 || $user_id <= 0 || '' === $message ) {
			return null;
		}

		$title = sanitize_text_field( RestController::title_fallback( $message ) );
		if ( '' === $title || ! SessionRepository::replace_title( $session_id, $user_id, '', $title ) ) {
			return null;
		}

		if ( $agent_id > 0 ) {
			$options     = Agent::get_loop_options( $agent_id );
			$provider_id = (string) ( $options['provider_id'] ?? $provider_id );
			$model_id    = (string) ( $options['model_id'] ?? $model_id );
		}

		$job_id = wp_generate_uuid4();
		$token  = wp_generate_password( 40, false );
		set_transient( self::PENDING_PREFIX . $session_id, $job_id, self::TTL );
		set_transient(
			RestController::JOB_PREFIX . $job_id,
			array(
				'title_only' => true,
				'token'      => $token,
				'user_id'    => $user_id,
				'params'     => array(
					'session_id'     => $session_id,
					'message'        => mb_substr( $message, 0, 500 ),
					'expected_title' => $title,
					'provider_id'    => $provider_id,
					'model_id'       => $model_id,
				),
			),
			self::TTL
			);

		// A delayed fallback must not put the title ahead of the main job in the
		// same serial cron batch. The loopback launches a separate PHP worker now.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, BackgroundJobDispatcher::HOOK, array( get_current_blog_id(), $job_id ) );
		wp_remote_post(
			rest_url( RestController::NAMESPACE . '/process' ),
			array(
				'timeout'  => 0.01,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
				array(
					'job_id' => $job_id,
					'token'  => $token,
				)
				),
			)
			);

		return $title;
	}

	/** Whether an independent title worker is still pending. */
	public static function is_pending( int $session_id ): bool {
		return false !== get_transient( self::PENDING_PREFIX . $session_id );
	}

	/**
	 * Run a consumed title job without touching the main job or its conversation.
	 *
	 * @param string               $job_id Consumed job identifier.
	 * @param array<string, mixed> $job Server-created title job.
	 */
	public static function process( string $job_id, array $job ): void {
		$params           = is_array( $job['params'] ?? null ) ? $job['params'] : array();
		$session_id       = (int) ( $params['session_id'] ?? 0 );
		$user_id          = (int) ( $job['user_id'] ?? 0 );
		$original_user_id = get_current_user_id();

		try {
			$session = Database::get_session_maintenance_metadata( $session_id );
			if ( ! $session || (int) $session->user_id !== $user_id || ! get_userdata( $user_id )
				|| 'trash' === $session->status || (string) $session->title !== (string) ( $params['expected_title'] ?? '' )
				|| $job_id !== get_transient( self::PENDING_PREFIX . $session_id ) ) {
				return;
			}

			wp_set_current_user( $user_id );
			if ( ! RolePermissions::current_user_has_chat_access() ) {
				return;
			}
			ProviderCredentialLoader::load();
			$title = RestController::generate_session_title(
				(string) ( $params['message'] ?? '' ),
				'',
				(string) ( $params['provider_id'] ?? '' ),
				(string) ( $params['model_id'] ?? '' )
			);
			if ( $job_id === get_transient( self::PENDING_PREFIX . $session_id ) ) {
				SessionRepository::replace_title( $session_id, $user_id, (string) $params['expected_title'], $title );
			}
		} finally {
			wp_set_current_user( $original_user_id );
			if ( $job_id === get_transient( self::PENDING_PREFIX . $session_id ) ) {
				delete_transient( self::PENDING_PREFIX . $session_id );
			}
			wp_clear_scheduled_hook( BackgroundJobDispatcher::HOOK, array( get_current_blog_id(), $job_id ) );
		}
	}
}
