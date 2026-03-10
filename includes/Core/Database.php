<?php

declare(strict_types=1);
/**
 * Database table management for AI Agent sessions.
 *
 * @package AiAgent
 */

namespace AiAgent\Core;

use AiAgent\Knowledge\KnowledgeDatabase;
use AiAgent\Models\Skill;
use AiAgent\Tools\CustomTools;

class Database {

	const DB_VERSION_OPTION = 'ai_agent_db_version';
	const DB_VERSION        = '8.0.0';

	/**
	 * Get the sessions table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_sessions';
	}

	/**
	 * Get the usage table name.
	 */
	public static function usage_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_usage';
	}

	/**
	 * Get the memories table name.
	 */
	public static function memories_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_memories';
	}

	/**
	 * Get the skills table name.
	 */
	public static function skills_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_skills';
	}

	/**
	 * Install or upgrade the database table.
	 */
	/**
	 * Get the custom tools table name.
	 */
	public static function custom_tools_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_custom_tools';
	}

	/**
	 * Get the automations table name.
	 */
	public static function automations_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_automations';
	}

	/**
	 * Get the automation logs table name.
	 */
	public static function automation_logs_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_automation_logs';
	}

	/**
	 * Get the event automations table name.
	 */
	public static function event_automations_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_event_automations';
	}

	/**
	 * Install or upgrade the database table.
	 */
	public static function install(): void {
		global $wpdb;

		$installed_version = get_option( self::DB_VERSION_OPTION );

		if ( $installed_version === self::DB_VERSION ) {
			return;
		}

		$table                   = self::table_name();
		$usage_table             = self::usage_table_name();
		$memories_table          = self::memories_table_name();
		$skills_table            = self::skills_table_name();
		$custom_tools_table      = self::custom_tools_table_name();
		$automations_table       = self::automations_table_name();
		$automation_logs_table   = self::automation_logs_table_name();
		$event_automations_table = self::event_automations_table_name();
		$charset                 = $wpdb->get_charset_collate();

		// Knowledge tables.
		$sql = KnowledgeDatabase::get_schema( $charset );

		$sql .= "\n\nCREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			provider_id varchar(100) NOT NULL DEFAULT '',
			model_id varchar(100) NOT NULL DEFAULT '',
			messages longtext NOT NULL,
			tool_calls longtext NOT NULL,
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			pinned tinyint(1) NOT NULL DEFAULT 0,
			folder varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY updated_at (updated_at),
			KEY status_user (user_id, status, updated_at)
		) {$charset};

		CREATE TABLE {$usage_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			session_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider_id varchar(100) NOT NULL DEFAULT '',
			model_id varchar(100) NOT NULL DEFAULT '',
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			cost_usd decimal(10,6) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_session (user_id, session_id),
			KEY created_at (created_at),
			KEY model_id (model_id)
		) {$charset};

		CREATE TABLE {$memories_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			category varchar(50) NOT NULL DEFAULT 'general',
			content text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY category (category)
		) {$charset};

		CREATE TABLE {$skills_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text NOT NULL,
			content longtext NOT NULL,
			is_builtin tinyint(1) NOT NULL DEFAULT 0,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY enabled (enabled)
		) {$charset};

		CREATE TABLE {$custom_tools_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text NOT NULL DEFAULT '',
			type varchar(20) NOT NULL DEFAULT 'http',
			config longtext NOT NULL,
			input_schema longtext NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY type (type),
			KEY enabled (enabled)
		) {$charset};

		CREATE TABLE {$automations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text NOT NULL DEFAULT '',
			prompt longtext NOT NULL,
			schedule varchar(50) NOT NULL DEFAULT 'daily',
			cron_expression varchar(100) NOT NULL DEFAULT '',
			tool_profile varchar(100) NOT NULL DEFAULT '',
			max_iterations int(11) NOT NULL DEFAULT 10,
			enabled tinyint(1) NOT NULL DEFAULT 0,
			last_run_at datetime DEFAULT NULL,
			next_run_at datetime DEFAULT NULL,
			run_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY enabled (enabled),
			KEY schedule (schedule)
		) {$charset};

		CREATE TABLE {$automation_logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			trigger_type varchar(20) NOT NULL DEFAULT 'scheduled',
			trigger_name varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'success',
			reply longtext NOT NULL,
			tool_calls longtext NOT NULL,
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			error_message text NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY automation_id (automation_id),
			KEY trigger_type (trigger_type),
			KEY created_at (created_at)
		) {$charset};

		CREATE TABLE {$event_automations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text NOT NULL DEFAULT '',
			hook_name varchar(255) NOT NULL,
			prompt_template longtext NOT NULL,
			conditions longtext NOT NULL,
			tool_profile varchar(100) NOT NULL DEFAULT '',
			max_iterations int(11) NOT NULL DEFAULT 5,
			enabled tinyint(1) NOT NULL DEFAULT 0,
			run_count int(11) NOT NULL DEFAULT 0,
			last_run_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY hook_name (hook_name),
			KEY enabled (enabled)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Add FULLTEXT index on memories table if not present.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query; table name from internal method.
		$ft_exists = $wpdb->get_var( "SHOW INDEX FROM {$memories_table} WHERE Key_name = 'ft_content'" );
		if ( ! $ft_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Required for fulltext index creation during install.
			$wpdb->query( "ALTER TABLE {$memories_table} ADD FULLTEXT KEY ft_content (content)" );
		}

		// Seed built-in skills.
		Skill::seed_builtins();

		// Seed example custom tools.
		CustomTools::seed_examples();

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create a new session.
	 *
	 * @param array $data Session data: user_id, title, provider_id, model_id.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create_session( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				'user_id'     => $data['user_id'],
				'title'       => $data['title'] ?? '',
				'provider_id' => $data['provider_id'] ?? '',
				'model_id'    => $data['model_id'] ?? '',
				'messages'    => '[]',
				'tool_calls'  => '[]',
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a single session by ID.
	 *
	 * @param int $session_id Session ID.
	 * @return object|null Session row or null.
	 */
	public static function get_session( int $session_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::table_name(),
				$session_id
			)
		);
	}

	/**
	 * List sessions for a user (lightweight — no messages/tool_calls).
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $filters Optional filters: status, folder, search, pinned.
	 * @return array Array of session summary objects.
	 */
	public static function list_sessions( int $user_id, array $filters = [] ): array {
		global $wpdb;

		$table = self::table_name();

		$where = [ $wpdb->prepare( 'user_id = %d', $user_id ) ];

		$status  = $filters['status'] ?? 'active';
		$where[] = $wpdb->prepare( 'status = %s', $status );

		if ( ! empty( $filters['folder'] ) ) {
			$where[] = $wpdb->prepare( 'folder = %s', $filters['folder'] );
		}

		if ( isset( $filters['pinned'] ) ) {
			$where[] = $wpdb->prepare( 'pinned = %d', $filters['pinned'] ? 1 : 0 );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[] = $wpdb->prepare( '(title LIKE %s OR messages LIKE %s)', $like, $like );
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query; built from prepared fragments.
		return $wpdb->get_results(
			"SELECT id, user_id, title, provider_id, model_id, status, pinned, folder, created_at, updated_at,
				JSON_LENGTH(messages) AS message_count
			FROM {$table}
			WHERE {$where_sql}
			ORDER BY pinned DESC, updated_at DESC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * List distinct folders for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Array of folder name strings.
	 */
	public static function list_folders( int $user_id ): array {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT folder FROM %i WHERE user_id = %d AND folder != '' ORDER BY folder ASC",
				$table,
				$user_id
			)
		);

		return $results ?: [];
	}

	/**
	 * Bulk update sessions.
	 *
	 * @param array $session_ids Array of session IDs.
	 * @param int   $user_id     User ID for ownership check.
	 * @param array $data        Fields to update (status, pinned, folder).
	 * @return int Number of rows affected.
	 */
	public static function bulk_update_sessions( array $session_ids, int $user_id, array $data ): int {
		global $wpdb;

		if ( empty( $session_ids ) || empty( $data ) ) {
			return 0;
		}

		$table              = self::table_name();
		$data['updated_at'] = current_time( 'mysql', true );

		$set_parts = [];
		$values    = [];

		foreach ( $data as $key => $value ) {
			$set_parts[] = "{$key} = %s";
			$values[]    = $value;
		}

		$set_sql      = implode( ', ', $set_parts );
		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
		$values       = array_merge( $values, $session_ids, [ $user_id ] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query; dynamic columns from internal method, not user input.
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET {$set_sql} WHERE id IN ({$placeholders}) AND user_id = %d",
				...$values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $result !== false ? (int) $result : 0;
	}

	/**
	 * Permanently delete sessions in trash for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of rows deleted.
	 */
	public static function empty_trash( int $user_id ): int {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE user_id = %d AND status = 'trash'",
				$table,
				$user_id
			)
		);

		return $result !== false ? (int) $result : 0;
	}

	/**
	 * Log a usage record.
	 *
	 * @param array $data Usage data: user_id, session_id, provider_id, model_id, prompt_tokens, completion_tokens, cost_usd.
	 * @return int|false Inserted row ID or false.
	 */
	public static function log_usage( array $data ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::usage_table_name(),
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
	 * @param array $filters Optional: user_id, period (7d, 30d, all), start_date, end_date.
	 * @return array Summary with totals and per-model breakdown.
	 */
	public static function get_usage_summary( array $filters = [] ): array {
		global $wpdb;

		$table = self::usage_table_name();
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

	/**
	 * Update session fields.
	 *
	 * @param int   $session_id Session ID.
	 * @param array $data       Fields to update.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_session( int $session_id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, [ 'user_id', 'id' ], true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $session_id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a session.
	 *
	 * @param int $session_id Session ID.
	 * @return bool Whether the delete succeeded.
	 */
	public static function delete_session( int $session_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $session_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Update token usage for a session (accumulates).
	 *
	 * @param int $session_id       Session ID.
	 * @param int $prompt_tokens    Prompt tokens to add.
	 * @param int $completion_tokens Completion tokens to add.
	 * @return bool
	 */
	public static function update_session_tokens( int $session_id, int $prompt_tokens, int $completion_tokens ): bool {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET prompt_tokens = prompt_tokens + %d, completion_tokens = completion_tokens + %d, updated_at = %s WHERE id = %d',
				$table,
				$prompt_tokens,
				$completion_tokens,
				current_time( 'mysql', true ),
				$session_id
			)
		);

		return $result !== false;
	}

	/**
	 * Append messages and tool calls to a session.
	 *
	 * Loads current data, merges new entries, and saves back.
	 *
	 * @param int   $session_id Session ID.
	 * @param array $messages   New message arrays to append.
	 * @param array $tool_calls New tool call log entries to append.
	 * @return bool Whether the update succeeded.
	 */
	public static function append_to_session( int $session_id, array $messages, array $tool_calls = [] ): bool {
		$session = self::get_session( $session_id );

		if ( ! $session ) {
			return false;
		}

		$existing_messages   = json_decode( $session->messages, true ) ?: [];
		$existing_tool_calls = json_decode( $session->tool_calls, true ) ?: [];

		$merged_messages   = array_merge( $existing_messages, $messages );
		$merged_tool_calls = array_merge( $existing_tool_calls, $tool_calls );

		return self::update_session(
			$session_id,
			[
				'messages'   => wp_json_encode( $merged_messages ),
				'tool_calls' => wp_json_encode( $merged_tool_calls ),
			]
		);
	}
}
