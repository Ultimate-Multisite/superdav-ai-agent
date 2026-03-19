<?php

declare(strict_types=1);
/**
 * Database layer for the Resale API.
 *
 * Manages two tables:
 *   - {prefix}gratis_ai_agent_resale_clients  — API client credentials and quotas
 *   - {prefix}gratis_ai_agent_resale_usage    — per-request usage audit log
 *
 * Each resale client gets a unique API key and an optional monthly token quota.
 * Every proxied request is logged with token counts, cost, model, and status so
 * the site owner can track consumption per client.
 *
 * @package GratisAiAgent\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

class ResaleApiDatabase {

	/**
	 * Get the resale clients table name.
	 */
	public static function clients_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_resale_clients';
	}

	/**
	 * Get the resale usage log table name.
	 */
	public static function usage_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_resale_usage';
	}

	/**
	 * Return the CREATE TABLE SQL for both resale tables.
	 *
	 * Called by Database::install() so the tables are created alongside
	 * the rest of the plugin's schema.
	 *
	 * @param string $charset The charset collation string from $wpdb.
	 * @return string SQL fragment (no trailing semicolon — dbDelta handles it).
	 */
	public static function get_schema( string $charset ): string {
		$clients_table = self::clients_table_name();
		$usage_table   = self::usage_table_name();

		return "
CREATE TABLE {$clients_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(255) NOT NULL,
	description text NOT NULL DEFAULT '',
	api_key varchar(64) NOT NULL,
	monthly_token_quota bigint(20) unsigned NOT NULL DEFAULT 0,
	tokens_used_this_month bigint(20) unsigned NOT NULL DEFAULT 0,
	quota_reset_at datetime DEFAULT NULL,
	allowed_models longtext NOT NULL DEFAULT '',
	markup_percent decimal(5,2) NOT NULL DEFAULT 0.00,
	enabled tinyint(1) NOT NULL DEFAULT 1,
	request_count int(11) NOT NULL DEFAULT 0,
	last_used_at datetime DEFAULT NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY api_key (api_key),
	KEY enabled (enabled)
) {$charset};

CREATE TABLE {$usage_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	client_id bigint(20) unsigned NOT NULL,
	provider_id varchar(100) NOT NULL DEFAULT '',
	model_id varchar(100) NOT NULL DEFAULT '',
	prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
	completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
	cost_usd decimal(10,6) NOT NULL DEFAULT 0,
	status varchar(20) NOT NULL DEFAULT 'success',
	error_message text NOT NULL DEFAULT '',
	duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY client_id (client_id),
	KEY created_at (created_at),
	KEY model_id (model_id),
	KEY status (status)
) {$charset};";
	}

	// ─── Client CRUD ─────────────────────────────────────────────────

	/**
	 * List all resale clients ordered by name.
	 *
	 * @return object[]
	 */
	public static function list_clients(): array {
		global $wpdb;
		$table = self::clients_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ) ?: [];
	}

	/**
	 * Get a single client by ID.
	 *
	 * @param int $id Client ID.
	 * @return object|null
	 */
	public static function get_client( int $id ): ?object {
		global $wpdb;
		$table = self::clients_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ?: null;
	}

	/**
	 * Get a client by API key (used for authentication on the proxy endpoint).
	 *
	 * @param string $api_key The client's API key.
	 * @return object|null
	 */
	public static function get_client_by_key( string $api_key ): ?object {
		global $wpdb;
		$table = self::clients_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE api_key = %s", $api_key ) );
		return $row ?: null;
	}

	/**
	 * Create a new resale client.
	 *
	 * @param array<string, mixed> $data Client data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create_client( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$insert = [
			'name'                   => $data['name'] ?? '',
			'description'            => $data['description'] ?? '',
			'api_key'                => $data['api_key'] ?? '',
			// @phpstan-ignore-next-line
			'monthly_token_quota'    => (int) ( $data['monthly_token_quota'] ?? 0 ),
			'tokens_used_this_month' => 0,
			'quota_reset_at'         => $data['quota_reset_at'] ?? null,
			'allowed_models'         => wp_json_encode( $data['allowed_models'] ?? [] ),
			// @phpstan-ignore-next-line
			'markup_percent'         => (float) ( $data['markup_percent'] ?? 0.0 ),
			// @phpstan-ignore-next-line
			'enabled'                => (int) ( $data['enabled'] ?? 1 ),
			'request_count'          => 0,
			'last_used_at'           => null,
			'created_at'             => $now,
			'updated_at'             => $now,
		];

		$formats = [ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert( self::clients_table_name(), $insert, $formats );

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a resale client.
	 *
	 * @param int                  $id   Client ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public static function update_client( int $id, array $data ): bool {
		global $wpdb;

		// Encode allowed_models array to JSON if provided.
		if ( isset( $data['allowed_models'] ) && is_array( $data['allowed_models'] ) ) {
			$data['allowed_models'] = wp_json_encode( $data['allowed_models'] );
		}

		$data['updated_at'] = current_time( 'mysql' );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, [ 'monthly_token_quota', 'tokens_used_this_month', 'enabled', 'request_count' ], true ) ) {
				$formats[] = '%d';
			} elseif ( 'markup_percent' === $key ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$result = $wpdb->update( self::clients_table_name(), $data, [ 'id' => $id ], $formats, [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Delete a resale client and its usage logs.
	 *
	 * @param int $id Client ID.
	 * @return bool
	 */
	public static function delete_client( int $id ): bool {
		global $wpdb;

		// Delete usage logs first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( self::usage_table_name(), [ 'client_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete( self::clients_table_name(), [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	// ─── Usage logging ───────────────────────────────────────────────

	/**
	 * Log a proxied request and update client counters.
	 *
	 * Also increments request_count, updates last_used_at, and accumulates
	 * tokens_used_this_month on the client record.
	 *
	 * @param int    $client_id         Client ID.
	 * @param string $provider_id       Provider identifier.
	 * @param string $model_id          Model identifier.
	 * @param int    $prompt_tokens     Input token count.
	 * @param int    $completion_tokens Output token count.
	 * @param float  $cost_usd          Calculated cost in USD.
	 * @param string $status            'success' or 'error'.
	 * @param string $error_message     Error message (empty on success).
	 * @param int    $duration_ms       Request duration in milliseconds.
	 * @return int|false Inserted log row ID or false on failure.
	 */
	public static function log_usage(
		int $client_id,
		string $provider_id,
		string $model_id,
		int $prompt_tokens,
		int $completion_tokens,
		float $cost_usd,
		string $status,
		string $error_message,
		int $duration_ms
	) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$insert = [
			'client_id'         => $client_id,
			'provider_id'       => $provider_id,
			'model_id'          => $model_id,
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'cost_usd'          => $cost_usd,
			'status'            => $status,
			'error_message'     => $error_message,
			'duration_ms'       => $duration_ms,
			'created_at'        => $now,
		];

		$formats = [ '%d', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%d', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert( self::usage_table_name(), $insert, $formats );

		if ( false !== $result ) {
			$total_tokens  = $prompt_tokens + $completion_tokens;
			$clients_table = self::clients_table_name();
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$clients_table} SET request_count = request_count + 1, tokens_used_this_month = tokens_used_this_month + %d, last_used_at = %s, updated_at = %s WHERE id = %d",
					$total_tokens,
					$now,
					$now,
					$client_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

			return (int) $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get usage logs for a client.
	 *
	 * @param int $client_id Client ID.
	 * @param int $limit     Maximum rows to return.
	 * @param int $offset    Row offset for pagination.
	 * @return object[]
	 */
	public static function get_usage( int $client_id, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;
		$table = self::usage_table_name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE client_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$client_id,
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $rows ?: [];
	}

	/**
	 * Count total usage log rows for a client.
	 *
	 * @param int $client_id Client ID.
	 * @return int
	 */
	public static function count_usage( int $client_id ): int {
		global $wpdb;
		$table = self::usage_table_name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE client_id = %d", $client_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $count;
	}

	/**
	 * Get aggregated usage summary for a client.
	 *
	 * Returns totals for prompt tokens, completion tokens, cost, and request
	 * count, optionally filtered by date range.
	 *
	 * @param int         $client_id  Client ID.
	 * @param string|null $start_date ISO date string (inclusive), e.g. '2025-01-01'.
	 * @param string|null $end_date   ISO date string (inclusive), e.g. '2025-01-31'.
	 * @return array<string, mixed>
	 */
	public static function get_usage_summary( int $client_id, ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;
		$table = self::usage_table_name();

		$where  = 'WHERE client_id = %d';
		$params = [ $client_id ];

		if ( $start_date ) {
			$where   .= ' AND created_at >= %s';
			$params[] = $start_date . ' 00:00:00';
		}
		if ( $end_date ) {
			$where   .= ' AND created_at <= %s';
			$params[] = $end_date . ' 23:59:59';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method. Placeholders are in $where via variadic spread.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS request_count,
					COALESCE(SUM(prompt_tokens), 0) AS total_prompt_tokens,
					COALESCE(SUM(completion_tokens), 0) AS total_completion_tokens,
					COALESCE(SUM(cost_usd), 0) AS total_cost_usd
				FROM {$table} {$where}",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $row ?? [
			'request_count'           => 0,
			'total_prompt_tokens'     => 0,
			'total_completion_tokens' => 0,
			'total_cost_usd'          => 0,
		];
	}

	/**
	 * Reset the monthly token counter for a client.
	 *
	 * Called when a new billing month begins (quota_reset_at passes).
	 *
	 * @param int $client_id Client ID.
	 * @return bool
	 */
	public static function reset_monthly_quota( int $client_id ): bool {
		global $wpdb;

		$now           = current_time( 'mysql' );
		$next_reset    = gmdate( 'Y-m-d H:i:s', (int) strtotime( '+1 month', (int) strtotime( $now ) ) );
		$clients_table = self::clients_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$clients_table} SET tokens_used_this_month = 0, quota_reset_at = %s, updated_at = %s WHERE id = %d",
				$next_reset,
				$now,
				$client_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return false !== $result;
	}
}
