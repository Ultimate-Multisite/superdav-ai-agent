<?php

declare(strict_types=1);
/**
 * Database table management for AI Agent sessions.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Knowledge\KnowledgeDatabase;
use GratisAiAgent\Models\ConversationTemplate;
use GratisAiAgent\Models\Skill;
use GratisAiAgent\REST\ResaleApiDatabase;
use GratisAiAgent\REST\WebhookDatabase;
use GratisAiAgent\Tools\CustomTools;

class Database {

	const DB_VERSION_OPTION = 'gratis_ai_agent_db_version';
	const DB_VERSION        = '11.0.0';

	/**
	 * Get the sessions table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_sessions';
	}

	/**
	 * Get the usage table name.
	 */
	public static function usage_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_usage';
	}

	/**
	 * Get the memories table name.
	 */
	public static function memories_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_memories';
	}

	/**
	 * Get the skills table name.
	 */
	public static function skills_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_skills';
	}

	/**
	 * Install or upgrade the database table.
	 */
	/**
	 * Get the custom tools table name.
	 */
	public static function custom_tools_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_custom_tools';
	}

	/**
	 * Get the automations table name.
	 */
	public static function automations_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_automations';
	}

	/**
	 * Get the automation logs table name.
	 */
	public static function automation_logs_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_automation_logs';
	}

	/**
	 * Get the event automations table name.
	 */
	public static function event_automations_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_event_automations';
	}

	/**
	 * Get the conversation templates table name.
	 */
	public static function conversation_templates_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_conversation_templates';
	}

	/**
	 * Get the git tracked files table name.
	 */
	public static function git_tracked_files_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_git_tracked_files';
	}

	/**
	 * Get the changes log table name.
	 */
	public static function changes_log_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_changes_log';
	}

	/**
	 * Get the modified files table name.
	 *
	 * Tracks files written or edited by the AI agent so modified plugins
	 * can be identified and offered as downloads.
	 */
	public static function modified_files_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_modified_files';
	}

	/**
	 * Get the agents table name.
	 */
	public static function agents_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_agents';
	}

	/**
	 * Install or upgrade the database table.
	 */
	public static function install(): void {
		global $wpdb;

		// Migrate from old "ai_agent" naming if upgrading from pre-rename version.
		self::maybe_migrate_from_old_names();

		$installed_version = get_option( self::DB_VERSION_OPTION );

		if ( $installed_version === self::DB_VERSION ) {
			return;
		}

		$table                        = self::table_name();
		$usage_table                  = self::usage_table_name();
		$memories_table               = self::memories_table_name();
		$skills_table                 = self::skills_table_name();
		$custom_tools_table           = self::custom_tools_table_name();
		$automations_table            = self::automations_table_name();
		$automation_logs_table        = self::automation_logs_table_name();
		$event_automations_table      = self::event_automations_table_name();
		$conversation_templates_table = self::conversation_templates_table_name();
		$git_tracked_files_table      = self::git_tracked_files_table_name();
		$changes_log_table            = self::changes_log_table_name();
		$modified_files_table         = self::modified_files_table_name();
		$agents_table                 = self::agents_table_name();
		$charset                      = $wpdb->get_charset_collate();

		// Knowledge tables.
		$sql = KnowledgeDatabase::get_schema( $charset );

		// Webhook tables.
		$sql .= WebhookDatabase::get_schema( $charset );

		// Resale API tables.
		$sql .= ResaleApiDatabase::get_schema( $charset );

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
			notification_channels longtext NOT NULL DEFAULT '',
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
		) {$charset};

		CREATE TABLE {$conversation_templates_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text NOT NULL DEFAULT '',
			prompt longtext NOT NULL,
			category varchar(50) NOT NULL DEFAULT 'general',
			icon varchar(100) NOT NULL DEFAULT 'admin-comments',
			is_builtin tinyint(1) NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY category (category),
			KEY is_builtin (is_builtin)
		) {$charset};

		CREATE TABLE {$git_tracked_files_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			file_path varchar(500) NOT NULL,
			file_type varchar(20) NOT NULL DEFAULT 'plugin',
			package_slug varchar(255) NOT NULL DEFAULT '',
			original_hash varchar(64) NOT NULL DEFAULT '',
			original_content longblob NOT NULL,
			current_hash varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'unchanged',
			tracked_at datetime NOT NULL,
			modified_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY file_path (file_path(255)),
			KEY package_slug (package_slug),
			KEY file_type (file_type),
			KEY status (status)
		) {$charset};

		CREATE TABLE {$changes_log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_title varchar(255) NOT NULL DEFAULT '',
			ability_name varchar(100) NOT NULL DEFAULT '',
			field_name varchar(100) NOT NULL DEFAULT '',
			before_value longtext NOT NULL,
			after_value longtext NOT NULL,
			reverted tinyint(1) NOT NULL DEFAULT 0,
			reverted_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY object_type_id (object_type, object_id),
			KEY reverted (reverted),
			KEY created_at (created_at)
		) {$charset};

		CREATE TABLE {$modified_files_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			plugin_slug varchar(255) NOT NULL DEFAULT '',
			file_path varchar(1000) NOT NULL DEFAULT '',
			action varchar(20) NOT NULL DEFAULT 'write',
			session_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			modified_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY plugin_slug (plugin_slug),
			KEY session_id (session_id),
			KEY modified_at (modified_at)
		) {$charset};

		CREATE TABLE {$agents_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text NOT NULL DEFAULT '',
			system_prompt longtext NOT NULL DEFAULT '',
			provider_id varchar(100) NOT NULL DEFAULT '',
			model_id varchar(100) NOT NULL DEFAULT '',
			tool_profile varchar(100) NOT NULL DEFAULT '',
			temperature decimal(3,2) DEFAULT NULL,
			max_iterations int(11) DEFAULT NULL,
			greeting text NOT NULL DEFAULT '',
			avatar_icon varchar(100) NOT NULL DEFAULT '',
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
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

		// Seed built-in conversation templates.
		ConversationTemplate::seed_builtins();

		// Seed example custom tools.
		CustomTools::seed_examples();

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create a new session.
	 *
	 * @param array<string, mixed> $data Session data: user_id, title, provider_id, model_id.
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
	 * @param int                  $user_id WordPress user ID.
	 * @param array<string, mixed> $filters Optional filters: status, folder, search, pinned.
	 * @return array<string, mixed> Array of session summary objects.
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
	 * @return array<string, mixed> Array of folder name strings.
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
	 * @param array<string, mixed> $session_ids Array of session IDs.
	 * @param int                  $user_id     User ID for ownership check.
	 * @param array<string, mixed> $data        Fields to update (status, pinned, folder).
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
	 * @param array<string, mixed> $data Usage data: user_id, session_id, provider_id, model_id, prompt_tokens, completion_tokens, cost_usd.
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
	 * @param array<string, mixed> $filters Optional: user_id, period (7d, 30d, all), start_date, end_date.
	 * @return array<string, mixed> Summary with totals and per-model breakdown.
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
	 * @param int                  $session_id Session ID.
	 * @param array<string, mixed> $data       Fields to update.
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
	 *
	 * @phpstan-param list<mixed>                $messages
	 * @phpstan-param list<array<string, mixed>> $tool_calls
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

	/**
	 * Migrate database tables, options, and cron hooks from the old "ai_agent" naming.
	 *
	 * This runs once on upgrade from the pre-rename plugin version. It detects old
	 * table names and options, renames/migrates them, then sets a flag so it won't
	 * run again.
	 */
	private static function maybe_migrate_from_old_names(): void {
		// Skip if migration already completed.
		if ( get_option( 'gratis_ai_agent_migrated_from_ai_agent' ) ) {
			return;
		}

		// Skip if there's no old DB version option (fresh install, never had old plugin).
		$old_db_version = get_option( 'ai_agent_db_version' );
		if ( false === $old_db_version ) {
			return;
		}

		global $wpdb;

		// 1. Rename database tables.
		$old_tables = [
			'ai_agent_sessions',
			'ai_agent_usage',
			'ai_agent_memories',
			'ai_agent_skills',
			'ai_agent_custom_tools',
			'ai_agent_automations',
			'ai_agent_automation_logs',
			'ai_agent_event_automations',
			'ai_agent_knowledge_collections',
			'ai_agent_knowledge_sources',
			'ai_agent_knowledge_chunks',
		];

		foreach ( $old_tables as $old_suffix ) {
			$old_name = $wpdb->prefix . $old_suffix;
			$new_name = $wpdb->prefix . 'gratis_' . $old_suffix;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time migration rename.
			$table_exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $old_name )
			);

			if ( $table_exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time migration; table names from internal constants.
				$wpdb->query( "RENAME TABLE `{$old_name}` TO `{$new_name}`" );
			}
		}

		// 2. Migrate options.
		$option_map = [
			'ai_agent_db_version'          => self::DB_VERSION_OPTION,
			'ai_agent_settings'            => 'gratis_ai_agent_settings',
			'ai_agent_claude_max_token'    => 'gratis_ai_agent_claude_max_token',
			'ai_agent_tool_profiles'       => 'gratis_ai_agent_tool_profiles',
			'ai_agent_custom_tools_seeded' => 'gratis_ai_agent_custom_tools_seeded',
		];

		foreach ( $option_map as $old_key => $new_key ) {
			$old_value = get_option( $old_key );
			if ( false !== $old_value ) {
				update_option( $new_key, $old_value );
				delete_option( $old_key );
			}
		}

		// 3. Migrate cron hooks.
		$old_cron_hook = 'wp_ai_agent_reindex';
		$new_cron_hook = 'wp_gratis_ai_agent_reindex';
		$timestamp     = wp_next_scheduled( $old_cron_hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $old_cron_hook );
			if ( ! wp_next_scheduled( $new_cron_hook ) ) {
				wp_schedule_event( time(), 'hourly', $new_cron_hook );
			}
		}

		// Mark migration as complete.
		update_option( 'gratis_ai_agent_migrated_from_ai_agent', '1' );
	}

	/**
	 * Record a file modification by the AI agent.
	 *
	 * @param string $file_path  Relative path from wp-content (e.g. "plugins/my-plugin/file.php").
	 * @param string $action     The action performed: 'write' or 'edit'.
	 * @param int    $session_id Session ID (0 if not in a session).
	 * @param int    $user_id    User ID performing the action.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function record_modified_file( string $file_path, string $action = 'write', int $session_id = 0, int $user_id = 0 ) {
		global $wpdb;

		// Extract plugin slug from path like "plugins/my-plugin/..." → "my-plugin".
		$plugin_slug = self::extract_plugin_slug( $file_path );

		// Only track files inside a plugin directory.
		if ( '' === $plugin_slug ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert; caching not applicable.
		$result = $wpdb->insert(
			self::modified_files_table_name(),
			[
				'plugin_slug' => $plugin_slug,
				'file_path'   => $file_path,
				'action'      => $action,
				'session_id'  => $session_id,
				'user_id'     => $user_id,
				'modified_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%d', '%d', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a list of plugins that have been modified by the AI agent.
	 *
	 * Returns one row per plugin slug with the modification count and
	 * the timestamp of the most recent modification.
	 *
	 * @return list<object{plugin_slug: string, modification_count: int, last_modified: string}>
	 */
	public static function get_modified_plugins(): array {
		global $wpdb;

		$table = self::modified_files_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; table name from internal method.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT plugin_slug,
				        COUNT(*) AS modification_count,
				        MAX(modified_at) AS last_modified
				 FROM %i
				 GROUP BY plugin_slug
				 ORDER BY last_modified DESC',
				$table
			)
		);

		return $rows ?? [];
	}

	/**
	 * Get all modified file records for a specific plugin slug.
	 *
	 * @param string $plugin_slug Plugin directory slug.
	 * @return list<object>
	 */
	public static function get_modified_files_for_plugin( string $plugin_slug ): array {
		global $wpdb;

		$table = self::modified_files_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; table name from internal method.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE plugin_slug = %s ORDER BY modified_at DESC',
				$table,
				$plugin_slug
			)
		);

		return $rows ?? [];
	}

	/**
	 * Extract the plugin slug (directory name) from a wp-content-relative path.
	 *
	 * E.g. "plugins/my-plugin/includes/file.php" → "my-plugin"
	 *      "themes/my-theme/style.css"            → "" (not a plugin)
	 *
	 * @param string $file_path Path relative to wp-content.
	 * @return string Plugin slug, or empty string if not inside a plugin directory.
	 */
	public static function extract_plugin_slug( string $file_path ): string {
		$file_path = ltrim( $file_path, '/\\' );

		// Must start with "plugins/".
		if ( strpos( $file_path, 'plugins/' ) !== 0 ) {
			return '';
		}

		// Strip the "plugins/" prefix and get the first path segment.
		$remainder = substr( $file_path, strlen( 'plugins/' ) );
		$parts     = explode( '/', $remainder, 2 );

		return $parts[0] ?? '';
	}
}
