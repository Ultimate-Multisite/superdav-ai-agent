<?php

declare(strict_types=1);
/**
 * Database layer for the Webhook API.
 *
 * Manages two tables:
 *   - {prefix}gratis_ai_agent_webhooks      — webhook configuration records
 *   - {prefix}gratis_ai_agent_webhook_logs  — per-execution audit log
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\REST;

class WebhookDatabase {

	/**
	 * Get the webhooks table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_webhooks';
	}

	/**
	 * Get the webhook logs table name.
	 */
	public static function logs_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_webhook_logs';
	}

	/**
	 * Return the CREATE TABLE SQL for both webhook tables.
	 *
	 * Called by Database::install() so the tables are created alongside
	 * the rest of the plugin's schema.
	 *
	 * @param string $charset The charset collation string from $wpdb.
	 * @return string SQL fragment (no trailing semicolon — dbDelta handles it).
	 */
	public static function get_schema( string $charset ): string {
		$webhooks_table = self::table_name();
		$logs_table     = self::logs_table_name();

		return "
CREATE TABLE {$webhooks_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	name varchar(255) NOT NULL,
	description text NOT NULL DEFAULT '',
	secret varchar(64) NOT NULL,
	prompt_template longtext NOT NULL DEFAULT '',
	system_instruction longtext NOT NULL DEFAULT '',
	provider_id varchar(100) NOT NULL DEFAULT '',
	model_id varchar(100) NOT NULL DEFAULT '',
	max_iterations int(11) NOT NULL DEFAULT 10,
	enabled tinyint(1) NOT NULL DEFAULT 1,
	run_count int(11) NOT NULL DEFAULT 0,
	last_run_at datetime DEFAULT NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY enabled (enabled)
) {$charset};

CREATE TABLE {$logs_table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	webhook_id bigint(20) unsigned NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'success',
	reply longtext NOT NULL DEFAULT '',
	tool_calls longtext NOT NULL DEFAULT '',
	prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
	completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
	duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
	error_message text NOT NULL DEFAULT '',
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY webhook_id (webhook_id),
	KEY created_at (created_at)
) {$charset};";
	}

	// ─── Webhook CRUD ────────────────────────────────────────────────

	/**
	 * List all webhooks ordered by name.
	 *
	 * @return object[]
	 */
	public static function list_webhooks(): array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ) ?: [];
	}

	/**
	 * Get a single webhook by ID.
	 *
	 * @param int $id Webhook ID.
	 * @return object|null
	 */
	public static function get_webhook( int $id ): ?object {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ?: null;
	}

	/**
	 * Create a new webhook.
	 *
	 * @param array<string, mixed> $data Webhook data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create_webhook( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$insert = [
			'name'               => $data['name'] ?? '',
			'description'        => $data['description'] ?? '',
			'secret'             => $data['secret'] ?? '',
			'prompt_template'    => $data['prompt_template'] ?? '',
			'system_instruction' => $data['system_instruction'] ?? '',
			'provider_id'        => $data['provider_id'] ?? '',
			'model_id'           => $data['model_id'] ?? '',
			'max_iterations'     => (int) ( $data['max_iterations'] ?? 10 ),
			'enabled'            => (int) ( $data['enabled'] ?? 1 ),
			'run_count'          => 0,
			'last_run_at'        => null,
			'created_at'         => $now,
			'updated_at'         => $now,
		];

		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert( self::table_name(), $insert, $formats );

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a webhook.
	 *
	 * @param int                  $id   Webhook ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public static function update_webhook( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( 'updated_at' === $key || 'last_run_at' === $key ) {
				$formats[] = '%s';
			} elseif ( in_array( $key, [ 'max_iterations', 'enabled', 'run_count' ], true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$result = $wpdb->update( self::table_name(), $data, [ 'id' => $id ], $formats, [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Delete a webhook and its logs.
	 *
	 * @param int $id Webhook ID.
	 * @return bool
	 */
	public static function delete_webhook( int $id ): bool {
		global $wpdb;

		// Delete logs first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( self::logs_table_name(), [ 'webhook_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	// ─── Execution logging ───────────────────────────────────────────

	/**
	 * Log a webhook execution.
	 *
	 * Also increments the run_count and updates last_run_at on the webhook.
	 *
	 * @param int                        $webhook_id        Webhook ID.
	 * @param string                     $status            'success' or 'error'.
	 * @param string                     $reply             AI reply text.
	 * @param list<array<string, mixed>> $tool_calls   Tool call log.
	 * @param int                        $prompt_tokens     Prompt token count.
	 * @param int                        $completion_tokens Completion token count.
	 * @param int                        $duration_ms       Execution duration in milliseconds.
	 * @param string                     $error_message     Error message (empty on success).
	 * @return int|false Inserted log row ID or false on failure.
	 */
	public static function log_execution(
		int $webhook_id,
		string $status,
		string $reply,
		array $tool_calls,
		int $prompt_tokens,
		int $completion_tokens,
		int $duration_ms,
		string $error_message
	) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$insert = [
			'webhook_id'        => $webhook_id,
			'status'            => $status,
			'reply'             => $reply,
			'tool_calls'        => wp_json_encode( $tool_calls ),
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'duration_ms'       => $duration_ms,
			'error_message'     => $error_message,
			'created_at'        => $now,
		];

		$formats = [ '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$result = $wpdb->insert( self::logs_table_name(), $insert, $formats );

		if ( false !== $result ) {
			// Update run_count and last_run_at on the webhook.
			$webhooks_table = self::table_name();
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method, not user input.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$webhooks_table} SET run_count = run_count + 1, last_run_at = %s, updated_at = %s WHERE id = %d",
					$now,
					$now,
					$webhook_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

			return (int) $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get execution logs for a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @param int $limit      Maximum rows to return.
	 * @param int $offset     Row offset for pagination.
	 * @return object[]
	 */
	public static function get_logs( int $webhook_id, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;
		$table = self::logs_table_name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE webhook_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$webhook_id,
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $rows ?: [];
	}

	/**
	 * Count total execution logs for a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return int
	 */
	public static function count_logs( int $webhook_id ): int {
		global $wpdb;
		$table = self::logs_table_name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE webhook_id = %d", $webhook_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $count;
	}
}
