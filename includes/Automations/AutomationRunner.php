<?php

declare(strict_types=1);
/**
 * Automation Runner — cron handler that fires Agent_Loop for scheduled automations.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Automations;

use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\BudgetManager;
use GratisAiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutomationRunner {

	const CRON_HOOK = 'gratis_ai_agent_run_automation';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );

		// Register custom weekly schedule if not already available.
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array<string, mixed> $schedules Existing schedules.
	 * @return array<string, mixed>
	 */
	public static function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'gratis-ai-agent' ),
			];
		}
		return $schedules;
	}

	/**
	 * Schedule a cron event for an automation.
	 *
	 * @param int    $automation_id Automation ID.
	 * @param string $schedule      WordPress cron schedule name.
	 */
	public static function schedule( int $automation_id, string $schedule ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK, [ $automation_id ] ) ) {
			wp_schedule_event( time(), $schedule, self::CRON_HOOK, [ $automation_id ] );
		}
	}

	/**
	 * Unschedule a cron event for an automation.
	 *
	 * @param int $automation_id Automation ID.
	 */
	public static function unschedule( int $automation_id ): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK, [ $automation_id ] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK, [ $automation_id ] );
		}
		// Also clear any recurring schedules.
		wp_clear_scheduled_hook( self::CRON_HOOK, [ $automation_id ] );
	}

	/**
	 * Run an automation (fired by WP Cron or manually).
	 *
	 * @param int $automation_id Automation ID.
	 * @return array<string, mixed>|null Run result or null if automation not found/disabled.
	 */
	public static function run( int $automation_id ): ?array {
		$automation = Automations::get( $automation_id );
		if ( ! $automation ) {
			return null;
		}

		// Skip automation execution when the budget is exceeded.
		if ( BudgetManager::is_exceeded() ) {
			return null;
		}

		$start_time = microtime( true );
		$now        = current_time( 'mysql', true );

		// Ensure provider credentials are available.
		AgentLoop::ensure_provider_credentials_static();

		// Build agent loop options.
		$settings = Settings::get();
		$options  = [
			// @phpstan-ignore-next-line
			'max_iterations' => $automation['max_iterations'] ?: ( $settings['max_iterations'] ?: 10 ),
			// @phpstan-ignore-next-line
			'provider_id'    => $settings['default_provider'] ?? '',
			// @phpstan-ignore-next-line
			'model_id'       => $settings['default_model'] ?? '',
		];

		// @phpstan-ignore-next-line
		$loop   = new AgentLoop( $automation['prompt'], [], [], $options );
		$result = $loop->run();

		$duration = round( ( microtime( true ) - $start_time ) * 1000 );

		// Determine success.
		$is_error = is_wp_error( $result );
		$status   = $is_error ? 'error' : 'success';

		// Extract data from result.
		$reply       = $is_error ? $result->get_error_message() : ( $result['reply'] ?? '' );
		$tool_calls  = $is_error ? [] : ( $result['tool_calls'] ?? [] );
		$token_usage = $is_error ? [] : ( $result['token_usage'] ?? [] );

		// Log the run.
		$log_data = [
			'automation_id'     => $automation_id,
			'status'            => $status,
			'reply'             => $reply,
			'tool_calls'        => $tool_calls,
			// @phpstan-ignore-next-line
			'prompt_tokens'     => $token_usage['prompt'] ?? 0,
			// @phpstan-ignore-next-line
			'completion_tokens' => $token_usage['completion'] ?? 0,
			'duration_ms'       => $duration,
			'error_message'     => $is_error ? $result->get_error_message() : '',
		];

		AutomationLogs::create( $log_data );

		// Update automation metadata.
		Automations::record_run( $automation_id, $now );

		// Dispatch Slack/Discord notifications (non-blocking; failures are logged, not thrown).
		NotificationDispatcher::dispatch( $automation, $log_data );

		/**
		 * Fires after a scheduled automation completes.
		 *
		 * @param int   $automation_id The automation ID.
		 * @param array $log_data      The log data for this run.
		 * @param array $automation    The automation definition.
		 */
		do_action( 'gratis_ai_agent_automation_complete', $automation_id, $log_data, $automation );

		return $log_data;
	}

	/**
	 * Reschedule all enabled automations (called on activation).
	 */
	public static function reschedule_all(): void {
		$automations = Automations::list( true );

		foreach ( $automations as $automation ) {
			// @phpstan-ignore-next-line
			self::unschedule( $automation['id'] );
			// @phpstan-ignore-next-line
			self::schedule( $automation['id'], $automation['schedule'] );
		}
	}

	/**
	 * Unschedule all automations (called on deactivation).
	 */
	public static function unschedule_all(): void {
		$automations = Automations::list();

		foreach ( $automations as $automation ) {
			// @phpstan-ignore-next-line
			self::unschedule( $automation['id'] );
		}
	}
}
