<?php

declare(strict_types=1);
/**
 * Budget Manager — spending limits and budget caps for AI API usage.
 *
 * Checks daily and monthly spend against configured caps before each API call.
 * Uses the existing usage table and caches aggregations via transients.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BudgetManager {

	/**
	 * Transient key for cached daily spend.
	 */
	const TRANSIENT_DAILY = 'gratis_ai_agent_budget_daily';

	/**
	 * Transient key for cached monthly spend.
	 */
	const TRANSIENT_MONTHLY = 'gratis_ai_agent_budget_monthly';

	/**
	 * Cache TTL in seconds (5 minutes).
	 */
	const CACHE_TTL = 300;

	/**
	 * Check whether the current spend is within budget.
	 *
	 * Returns true when the request may proceed, or a WP_Error when the budget
	 * is exceeded and the configured action is "pause".
	 *
	 * @return true|WP_Error
	 */
	public static function check_budget(): true|WP_Error {
		$settings = Settings::get();

		// @phpstan-ignore-next-line
		$daily_cap = (float) ( $settings['budget_daily_cap'] ?? 0 );
		// @phpstan-ignore-next-line
		$monthly_cap = (float) ( $settings['budget_monthly_cap'] ?? 0 );
		// @phpstan-ignore-next-line
		$action = (string) ( $settings['budget_exceeded_action'] ?? 'pause' );

		// No caps configured — always allow.
		if ( $daily_cap <= 0 && $monthly_cap <= 0 ) {
			return true;
		}

		if ( 'pause' !== $action ) {
			// Warn-only mode: never block, just let the caller show a warning.
			return true;
		}

		if ( $daily_cap > 0 ) {
			$daily_spend = self::get_daily_spend();
			if ( $daily_spend >= $daily_cap ) {
				return new WP_Error(
					'gratis_ai_agent_budget_daily_exceeded',
					sprintf(
						/* translators: 1: formatted spend, 2: formatted cap */
						__( 'Daily budget of %2$s reached (spent %1$s). Resets at midnight UTC.', 'gratis-ai-agent' ),
						self::format_cost( $daily_spend ),
						self::format_cost( $daily_cap )
					)
				);
			}
		}

		if ( $monthly_cap > 0 ) {
			$monthly_spend = self::get_monthly_spend();
			if ( $monthly_spend >= $monthly_cap ) {
				return new WP_Error(
					'gratis_ai_agent_budget_monthly_exceeded',
					sprintf(
						/* translators: 1: formatted spend, 2: formatted cap */
						__( 'Monthly budget of %2$s reached (spent %1$s). Resets on the 1st of next month UTC.', 'gratis-ai-agent' ),
						self::format_cost( $monthly_spend ),
						self::format_cost( $monthly_cap )
					)
				);
			}
		}

		return true;
	}

	/**
	 * Get the total estimated spend for the current UTC day.
	 *
	 * Result is cached for CACHE_TTL seconds to avoid expensive queries on
	 * every message.
	 *
	 * @return float Spend in USD.
	 */
	public static function get_daily_spend(): float {
		$cached = get_transient( self::TRANSIENT_DAILY );
		if ( false !== $cached ) {
			// @phpstan-ignore-next-line
			return (float) $cached;
		}

		global $wpdb;
		$table = Database::usage_table_name();

		// Current UTC date.
		$today = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$spend = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- %i is the identifier placeholder (WP 6.2+); table name is generated internally, not user input.
			$wpdb->prepare(
				'SELECT COALESCE(SUM(cost_usd), 0) FROM %i WHERE DATE(created_at) = %s',
				$table,
				$today
			)
		);

		$spend = (float) $spend;
		set_transient( self::TRANSIENT_DAILY, $spend, self::CACHE_TTL );

		return $spend;
	}

	/**
	 * Get the total estimated spend for the current UTC month.
	 *
	 * Result is cached for CACHE_TTL seconds.
	 *
	 * @return float Spend in USD.
	 */
	public static function get_monthly_spend(): float {
		$cached = get_transient( self::TRANSIENT_MONTHLY );
		if ( false !== $cached ) {
			// @phpstan-ignore-next-line
			return (float) $cached;
		}

		global $wpdb;
		$table = Database::usage_table_name();

		// Current UTC year-month.
		$year_month = gmdate( 'Y-m' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$spend = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- %i is the identifier placeholder (WP 6.2+); table name is generated internally, not user input.
			$wpdb->prepare(
				"SELECT COALESCE(SUM(cost_usd), 0) FROM %i WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s",
				$table,
				$year_month
			)
		);

		$spend = (float) $spend;
		set_transient( self::TRANSIENT_MONTHLY, $spend, self::CACHE_TTL );

		return $spend;
	}

	/**
	 * Whether the budget is currently exceeded (either daily or monthly).
	 *
	 * @return bool
	 */
	public static function is_exceeded(): bool {
		$result = self::check_budget();
		return is_wp_error( $result );
	}

	/**
	 * Get the current warning level based on spend vs caps.
	 *
	 * @return string 'ok' | 'warning' | 'exceeded'
	 */
	public static function get_warning_level(): string {
		$settings = Settings::get();

		// @phpstan-ignore-next-line
		$daily_cap = (float) ( $settings['budget_daily_cap'] ?? 0 );
		// @phpstan-ignore-next-line
		$monthly_cap = (float) ( $settings['budget_monthly_cap'] ?? 0 );
		// @phpstan-ignore-next-line
		$warning_pct      = (float) ( $settings['budget_warning_threshold'] ?? 80 );
		$warning_fraction = $warning_pct / 100;

		if ( $daily_cap <= 0 && $monthly_cap <= 0 ) {
			return 'ok';
		}

		$daily_spend   = $daily_cap > 0 ? self::get_daily_spend() : 0.0;
		$monthly_spend = $monthly_cap > 0 ? self::get_monthly_spend() : 0.0;

		// Check exceeded first.
		if ( $daily_cap > 0 && $daily_spend >= $daily_cap ) {
			return 'exceeded';
		}
		if ( $monthly_cap > 0 && $monthly_spend >= $monthly_cap ) {
			return 'exceeded';
		}

		// Check warning threshold.
		if ( $daily_cap > 0 && $daily_spend >= ( $daily_cap * $warning_fraction ) ) {
			return 'warning';
		}
		if ( $monthly_cap > 0 && $monthly_spend >= ( $monthly_cap * $warning_fraction ) ) {
			return 'warning';
		}

		return 'ok';
	}

	/**
	 * Get a summary of current budget status for the REST API / frontend.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status(): array {
		$settings = Settings::get();

		// @phpstan-ignore-next-line
		$daily_cap = (float) ( $settings['budget_daily_cap'] ?? 0 );
		// @phpstan-ignore-next-line
		$monthly_cap = (float) ( $settings['budget_monthly_cap'] ?? 0 );

		$daily_spend   = $daily_cap > 0 ? self::get_daily_spend() : 0.0;
		$monthly_spend = $monthly_cap > 0 ? self::get_monthly_spend() : 0.0;

		return [
			'daily_spend'   => $daily_spend,
			'monthly_spend' => $monthly_spend,
			'daily_cap'     => $daily_cap,
			'monthly_cap'   => $monthly_cap,
			'warning_level' => self::get_warning_level(),
			'is_exceeded'   => self::is_exceeded(),
		];
	}

	/**
	 * Invalidate the spend caches (call after recording a new usage row).
	 */
	public static function invalidate_cache(): void {
		delete_transient( self::TRANSIENT_DAILY );
		delete_transient( self::TRANSIENT_MONTHLY );
	}

	/**
	 * Format a cost value as a human-readable USD string.
	 *
	 * @param float $cost Cost in USD.
	 * @return string Formatted string, e.g. "$2.34" or "$0.0012".
	 */
	public static function format_cost( float $cost ): string {
		if ( 0.0 === $cost ) {
			return '$' . number_format( $cost, 2 );
		}
		if ( $cost < 0.01 ) {
			return '$' . number_format( $cost, 4 );
		}
		return '$' . number_format( $cost, 2 );
	}
}
