<?php

declare(strict_types=1);
/**
 * Repository for AI Agent usage-log persistence.
 *
 * Extracted from GratisAiAgent\Core\Database to keep domain logic focused.
 * Database::* methods delegate here for backward compatibility.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Repositories;

use GratisAiAgent\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles persistence for per-request token/cost usage records.
 */
class UsageRepository {

	/**
	 * Log a usage record.
	 *
	 * @param array<string, mixed> $data Usage data: user_id, session_id, provider_id, model_id, prompt_tokens, completion_tokens, cost_usd.
	 * @return int|false Inserted row ID or false.
	 */
	public static function log( array $data ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			Database::usage_table_name(),
			[
				'user_id'           => $data['user_id'] ?? 0,
				'session_id'        => $data['session_id'] ?? 0,
				'provider_id'       => $data['provider_id'] ?? '',
				'model_id'          => $data['model_id'] ?? '',
				'prompt_tokens'     => $data['prompt_tokens'] ?? 0,
				'completion_tokens' => $data['completion_tokens'] ?? 0,
				'cost_usd'          => $data['cost_usd'] ?? 0,
				'created_at'        => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get usage summary with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: user_id, period (7d, 30d, all), start_date, end_date.
	 * @return array<string, mixed> Summary with totals and per-model breakdown.
	 */
	public static function get_summary( array $filters = [] ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::usage_table_name();
		$where = [];

		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $filters['user_id'] );
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $filters['start_date'] );
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $filters['end_date'] );
		}

		if ( ! empty( $filters['period'] ) ) {
			switch ( $filters['period'] ) {
				case '7d':
					$where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
					break;
				case '30d':
					$where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
					break;
				case '90d':
					$where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
					break;
			}
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query; table name from internal method.

		// Totals.
		$totals = $wpdb->get_row(
			"SELECT
				COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
				COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
				COALESCE(SUM(cost_usd), 0) AS cost_usd,
				COUNT(*) AS request_count
			FROM {$table} {$where_sql}"
		);

		// Per-model breakdown.
		$by_model = $wpdb->get_results(
			"SELECT
				model_id,
				provider_id,
				COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
				COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
				COALESCE(SUM(cost_usd), 0) AS cost_usd,
				COUNT(*) AS request_count
			FROM {$table} {$where_sql}
			GROUP BY model_id, provider_id
			ORDER BY cost_usd DESC"
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return [
			'totals'   => $totals,
			'by_model' => $by_model,
		];
	}
}
