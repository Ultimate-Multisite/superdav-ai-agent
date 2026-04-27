<?php

declare(strict_types=1);
/**
 * Active jobs repository — persistent storage for background job state.
 *
 * Tracks the lifecycle of background AI jobs so that clients can reconnect
 * after page navigation and resume polling for completion.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

use GratisAiAgent\Models\DTO\ActiveJobRow;

class ActiveJobRepository {

	/**
	 * Valid job status values.
	 */
	const STATUSES = [ 'processing', 'awaiting_confirmation', 'awaiting_client_tools', 'complete', 'error' ];

	/**
	 * Get the active jobs table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		/** @var \wpdb $wpdb */
		return $wpdb->prefix . 'gratis_ai_agent_active_jobs';
	}

	/**
	 * Create a new active job record.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $job_id     UUID identifying the background job.
	 * @param int    $user_id    WordPress user ID.
	 * @param string $status     Initial job status (default 'processing').
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( int $session_id, string $job_id, int $user_id, string $status = 'processing' ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = 'processing';
		}

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				'session_id'    => $session_id,
				'job_id'        => $job_id,
				'user_id'       => $user_id,
				'status'        => $status,
				'pending_tools' => '[]',
				'tool_calls'    => '[]',
				'created_at'    => $now,
				'updated_at'    => $now,
			],
			[ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get an active job row by its UUID.
	 *
	 * @param string $job_id The job UUID.
	 * @return ActiveJobRow|null Row DTO or null if not found.
	 */
	public static function get_by_job_id( string $job_id ): ?ActiveJobRow {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE job_id = %s LIMIT 1',
				$table,
				$job_id
			)
		);

		if ( null === $row ) {
			return null;
		}

		return ActiveJobRow::from_row( $row );
	}

	/**
	 * Get the active (non-terminal) job for a session.
	 *
	 * Returns the most-recently-created job that is still in a non-terminal
	 * state (processing or awaiting_confirmation).
	 *
	 * @param int $session_id Session ID.
	 * @return ActiveJobRow|null Row DTO or null if no active job exists.
	 */
	public static function get_by_session_id( int $session_id ): ?ActiveJobRow {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_id = %d AND status IN ('processing', 'awaiting_confirmation', 'awaiting_client_tools') ORDER BY created_at DESC LIMIT 1",
				$table,
				$session_id
			)
		);

		if ( null === $row ) {
			return null;
		}

		return ActiveJobRow::from_row( $row );
	}

	/**
	 * Get all active (non-terminal) jobs for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return list<ActiveJobRow>
	 */
	public static function get_active_for_user( int $user_id ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d AND status IN ('processing', 'awaiting_confirmation', 'awaiting_client_tools') ORDER BY created_at DESC",
				$table,
				$user_id
			)
		);

		return array_map( [ ActiveJobRow::class, 'from_row' ], $rows ?: [] );
	}

	/**
	 * Update the status (and optional fields) of an active job.
	 *
	 * @param string               $job_id The job UUID.
	 * @param string               $status New status value.
	 * @param array<string, mixed> $extra  Optional extra fields to update (pending_tools, tool_calls).
	 * @return bool True on success, false on failure.
	 */
	public static function update_status( string $job_id, string $status, array $extra = [] ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		$allowed = [ 'pending_tools', 'tool_calls' ];
		$data    = array_intersect_key( $extra, array_flip( $allowed ) );

		$data['status']     = $status;
		$data['updated_at'] = current_time( 'mysql', true );

		$formats = array_fill( 0, count( $data ), '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'job_id' => $job_id ],
			$formats,
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Delete an active job record by its UUID.
	 *
	 * @param string $job_id The job UUID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $job_id ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'job_id' => $job_id ],
			[ '%s' ]
		);

		return $result !== false;
	}
}
