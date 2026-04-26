<?php

declare(strict_types=1);
/**
 * Changes log model — records AI-made content changes for audit, diff, and revert.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

use GratisAiAgent\Core\Database;
use GratisAiAgent\Models\DTO\ChangesLogRow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChangesLog {

	/**
	 * Record a single field change made by an AI ability.
	 *
	 * Pass `revertable => false` for changes the system cannot automatically
	 * undo (filesystem writes, WP-CLI commands, deleted media, etc.). Those
	 * rows still appear in the log as an audit trail but the UI shows a
	 * "Cannot undo" notice instead of a Revert button.
	 *
	 * @param array<string,mixed> $data Change data: session_id, user_id, object_type,
	 *                                  object_id, object_title, ability_name, field_name,
	 *                                  before_value, after_value, revertable (optional, default true).
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function record( array $data ): int|false {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table; caching not applicable.
		$result = $wpdb->insert(
			Database::changes_log_table_name(),
			[
				// @phpstan-ignore-next-line
				'session_id'   => (int) ( $data['session_id'] ?? 0 ),
				// @phpstan-ignore-next-line
				'user_id'      => (int) ( $data['user_id'] ?? get_current_user_id() ),
				// @phpstan-ignore-next-line
				'object_type'  => sanitize_key( $data['object_type'] ?? '' ),
				// @phpstan-ignore-next-line
				'object_id'    => (int) ( $data['object_id'] ?? 0 ),
				// @phpstan-ignore-next-line
				'object_title' => sanitize_text_field( $data['object_title'] ?? '' ),
				// @phpstan-ignore-next-line
				'ability_name' => sanitize_text_field( $data['ability_name'] ?? '' ),
				// @phpstan-ignore-next-line
				'field_name'   => sanitize_key( $data['field_name'] ?? '' ),
				'before_value' => $data['before_value'] ?? '',
				'after_value'  => $data['after_value'] ?? '',
				'reverted'     => 0,
				// @phpstan-ignore-next-line
				'revertable'   => isset( $data['revertable'] ) ? ( $data['revertable'] ? 1 : 0 ) : 1,
				'created_at'   => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * List change records with optional filters.
	 *
	 * @param array<string,mixed> $filters Optional filters: session_id, user_id, object_type, object_id, reverted, per_page, page.
	 * @return array<string,mixed> Array with 'items' and 'total'.
	 */
	public static function list( array $filters = [] ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::changes_log_table_name();
		$where = [];
		// @phpstan-ignore-next-line
		$per_page = max( 1, (int) ( $filters['per_page'] ?? 50 ) );
		// @phpstan-ignore-next-line
		$page   = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$offset = ( $page - 1 ) * $per_page;

		if ( ! empty( $filters['session_id'] ) ) {
			$where[] = $wpdb->prepare( 'session_id = %d', $filters['session_id'] );
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $filters['user_id'] );
		}

		if ( ! empty( $filters['object_type'] ) ) {
			$where[] = $wpdb->prepare( 'object_type = %s', $filters['object_type'] );
		}

		if ( ! empty( $filters['object_id'] ) ) {
			$where[] = $wpdb->prepare( 'object_id = %d', $filters['object_id'] );
		}

		if ( isset( $filters['reverted'] ) ) {
			$where[] = $wpdb->prepare( 'reverted = %d', $filters['reverted'] ? 1 : 0 );
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; built from prepared fragments.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where_sql}" );

		$raw_items = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from internal method; where_sql built from prepared fragments.
				"SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Map through the typed DTO so that scalar fields are correctly typed in
		// the JSON response. Without this, tinyint columns such as `reverted`
		// arrive as the string "0" in JavaScript, which is truthy — causing every
		// record to appear as already-reverted in the UI.
		$items = array_map(
			static fn( \stdClass $row ) => ChangesLogRow::from_row( $row ),
			$raw_items ?: []
		);

		return [
			'items' => $items,
			'total' => $total,
		];
	}

	/**
	 * Get a single change record by ID.
	 *
	 * @param int $id Change log ID.
	 * @return ChangesLogRow|null Typed DTO or null when not found.
	 */
	public static function get( int $id ): ?ChangesLogRow {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; caching not applicable.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				Database::changes_log_table_name(),
				$id
			)
		);

		return $row instanceof \stdClass ? ChangesLogRow::from_row( $row ) : null;
	}

	/**
	 * Mark a change record as reverted.
	 *
	 * @param int $id Change log ID.
	 * @return bool Whether the update succeeded.
	 */
	public static function mark_reverted( int $id ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; caching not applicable.
		$result = $wpdb->update(
			Database::changes_log_table_name(),
			[
				'reverted'    => 1,
				'reverted_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a change record.
	 *
	 * @param int $id Change log ID.
	 * @return bool Whether the delete succeeded.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; caching not applicable.
		$result = $wpdb->delete(
			Database::changes_log_table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Generate a unified diff string between before and after values.
	 *
	 * Uses wp_text_diff() when available (requires the text/plain MIME type),
	 * otherwise falls back to a simple line-by-line diff.
	 *
	 * @param string $before Before value.
	 * @param string $after  After value.
	 * @return string Unified diff string.
	 */
	public static function generate_diff( string $before, string $after ): string {
		if ( function_exists( 'wp_text_diff' ) ) {
			$diff = wp_text_diff( $before, $after, [ 'show_split_view' => false ] );
			if ( $diff ) {
				return $diff;
			}
		}

		// Fallback: simple unified diff.
		$before_lines = explode( "\n", $before );
		$after_lines  = explode( "\n", $after );
		$output       = [];

		$output[] = '--- before';
		$output[] = '+++ after';

		$max = max( count( $before_lines ), count( $after_lines ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$b = $before_lines[ $i ] ?? null;
			$a = $after_lines[ $i ] ?? null;

			if ( $b === $a ) {
				$output[] = ' ' . ( $b ?? '' );
			} else {
				if ( null !== $b ) {
					$output[] = '-' . $b;
				}
				if ( null !== $a ) {
					$output[] = '+' . $a;
				}
			}
		}

		return implode( "\n", $output );
	}

	/**
	 * Generate a patch file (unified diff format) for one or more change records.
	 *
	 * @param int[] $ids Change log IDs to include in the patch.
	 * @return string Patch file content.
	 */
	public static function generate_patch( array $ids ): string {
		$lines   = [];
		$lines[] = '# Gratis AI Agent — Changes Patch';
		$lines[] = '# Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$lines[] = '';

		foreach ( $ids as $id ) {
			$change = self::get( (int) $id );
			if ( ! $change ) {
				continue;
			}

			$lines[] = sprintf(
				'## Change #%d — %s %s (field: %s) — %s',
				$change->id,
				$change->object_type,
				$change->object_title ?: $change->object_id,
				$change->field_name,
				$change->created_at
			);
			$lines[] = '';

			$before_lines = explode( "\n", $change->before_value );
			$after_lines  = explode( "\n", $change->after_value );

			$lines[] = sprintf(
				'--- a/%s/%s/%s',
				$change->object_type,
				$change->object_id,
				$change->field_name
			);
			$lines[] = sprintf(
				'+++ b/%s/%s/%s',
				$change->object_type,
				$change->object_id,
				$change->field_name
			);
			$lines[] = sprintf( '@@ -1,%d +1,%d @@', count( $before_lines ), count( $after_lines ) );

			foreach ( $before_lines as $line ) {
				$lines[] = '-' . $line;
			}
			foreach ( $after_lines as $line ) {
				$lines[] = '+' . $line;
			}

			$lines[] = '';
		}

		return implode( "\n", $lines );
	}
}
