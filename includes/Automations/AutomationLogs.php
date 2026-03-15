<?php

declare(strict_types=1);
/**
 * Automation Logs — execution history for scheduled and event-driven automations.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Automations;

class AutomationLogs {

	/**
	 * Get the logs table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_automation_logs';
	}

	/**
	 * Create a log entry.
	 *
	 * @param array $data Log data.
	 * @return int|false Inserted ID or false.
	 */
	public static function create( array $data ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				'automation_id'     => absint( $data['automation_id'] ?? 0 ),
				'trigger_type'      => sanitize_text_field( $data['trigger_type'] ?? 'scheduled' ),
				'trigger_name'      => sanitize_text_field( $data['trigger_name'] ?? '' ),
				'status'            => sanitize_text_field( $data['status'] ?? 'success' ),
				'reply'             => wp_kses_post( $data['reply'] ?? '' ),
				'tool_calls'        => wp_json_encode( $data['tool_calls'] ?? [] ),
				'prompt_tokens'     => absint( $data['prompt_tokens'] ?? 0 ),
				'completion_tokens' => absint( $data['completion_tokens'] ?? 0 ),
				'duration_ms'       => absint( $data['duration_ms'] ?? 0 ),
				'error_message'     => sanitize_textarea_field( $data['error_message'] ?? '' ),
				'created_at'        => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * List logs for an automation.
	 *
	 * @param int $automation_id Automation ID.
	 * @param int $limit         Max results.
	 * @param int $offset        Offset for pagination.
	 * @return array
	 */
	public static function list_for_automation( int $automation_id, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE automation_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
				self::table_name(),
				$automation_id,
				$limit,
				$offset
			)
		);

		return array_map( [ __CLASS__, 'decode_row' ], $rows ?: [] );
	}

	/**
	 * List recent logs across all automations.
	 *
	 * @param int $limit Max results.
	 * @return array
	 */
	public static function list_recent( int $limit = 50 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d',
				self::table_name(),
				$limit
			)
		);

		return array_map( [ __CLASS__, 'decode_row' ], $rows ?: [] );
	}

	/**
	 * Get a single log entry.
	 *
	 * @param int $id Log ID.
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table_name(), $id )
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * Delete all logs for an automation.
	 *
	 * @param int $automation_id Automation ID.
	 * @return int Rows deleted.
	 */
	public static function delete_for_automation( int $automation_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'automation_id' => $automation_id ],
			[ '%d' ]
		);

		return $result !== false ? (int) $result : 0;
	}

	/**
	 * Prune old logs (keep last N per automation).
	 *
	 * @param int $keep_per_automation Max logs to keep per automation.
	 */
	public static function prune( int $keep_per_automation = 100 ): void {
		global $wpdb;

		$table = self::table_name();

		// Get all automation IDs with logs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query; table name from internal method.
		$automation_ids = $wpdb->get_col( "SELECT DISTINCT automation_id FROM {$table}" );

		foreach ( $automation_ids as $aid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			$count = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE automation_id = %d', $table, $aid )
			);

			if ( (int) $count > $keep_per_automation ) {
				$delete_count = (int) $count - $keep_per_automation;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
				$wpdb->query(
					$wpdb->prepare(
						'DELETE FROM %i WHERE automation_id = %d ORDER BY created_at ASC LIMIT %d',
						$table,
						$aid,
						$delete_count
					)
				);
			}
		}
	}

	/**
	 * Decode a database row.
	 *
	 * @param object $row Database row.
	 * @return array
	 */
	private static function decode_row( object $row ): array {
		return [
			'id'                => (int) $row->id,
			'automation_id'     => (int) $row->automation_id,
			'trigger_type'      => $row->trigger_type,
			'trigger_name'      => $row->trigger_name,
			'status'            => $row->status,
			'reply'             => $row->reply,
			'tool_calls'        => json_decode( $row->tool_calls, true ) ?: [],
			'prompt_tokens'     => (int) $row->prompt_tokens,
			'completion_tokens' => (int) $row->completion_tokens,
			'duration_ms'       => (int) $row->duration_ms,
			'error_message'     => $row->error_message,
			'created_at'        => $row->created_at,
		];
	}
}
