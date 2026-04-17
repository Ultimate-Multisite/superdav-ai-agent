<?php

declare(strict_types=1);
/**
 * Repository for tracking files modified by the AI agent.
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
 * Handles persistence for the modified-files audit log.
 */
class ModifiedFilesRepository {

	/**
	 * Record a file modification by the AI agent.
	 *
	 * @param string $file_path  Relative path from wp-content (e.g. "plugins/my-plugin/file.php").
	 * @param string $action     The action performed: 'write' or 'edit'.
	 * @param int    $session_id Session ID (0 if not in a session).
	 * @param int    $user_id    User ID performing the action.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function record( string $file_path, string $action = 'write', int $session_id = 0, int $user_id = 0 ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// Extract plugin slug from path like "plugins/my-plugin/..." → "my-plugin".
		$plugin_slug = self::extract_plugin_slug( $file_path );

		// Only track files inside a plugin directory.
		if ( '' === $plugin_slug ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert; caching not applicable.
		$result = $wpdb->insert(
			Database::modified_files_table_name(),
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
	 * @return list<object>
	 */
	public static function get_modified_plugins(): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::modified_files_table_name();

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
	public static function get_files_for_plugin( string $plugin_slug ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::modified_files_table_name();

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
