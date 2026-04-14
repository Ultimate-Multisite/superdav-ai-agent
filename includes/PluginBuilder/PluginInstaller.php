<?php

declare(strict_types=1);
/**
 * Plugin Installer — writes AI-generated plugin files to wp-content/plugins/
 * and tracks them in the generated_plugins database table.
 *
 * @package GratisAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\PluginBuilder;

use GratisAiAgent\Core\Database;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PluginInstaller — installs AI-generated plugins on disk and in the DB.
 *
 * @since 1.5.0
 */
class PluginInstaller {

	/**
	 * Get the generated plugins table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		return Database::generated_plugins_table_name();
	}

	/**
	 * Install a generated plugin to disk and record it in the database.
	 *
	 * @param string               $slug        Plugin slug (directory name).
	 * @param array<string,string> $files       Map of relative path → PHP source.
	 * @param string               $description Human-readable plugin description.
	 * @param string               $plan        Implementation plan JSON or text.
	 * @param string               $plugin_file Main plugin file relative to plugins dir (e.g. "slug/slug.php").
	 * @return array{id: int, plugin_dir: string, plugin_file: string}|\WP_Error
	 */
	public static function install(
		string $slug,
		array $files,
		string $description,
		string $plan,
		string $plugin_file
	): array|\WP_Error {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'gratis-ai-agent' )
			);
		}

		if ( empty( $files ) ) {
			return new WP_Error(
				'gratis_ai_agent_no_files',
				__( 'No plugin files provided for installation.', 'gratis-ai-agent' )
			);
		}

		$plugins_dir = WP_CONTENT_DIR . '/plugins/';
		$plugin_dir  = $plugins_dir . $slug . '/';

		// Validate paths to prevent directory traversal.
		$canonical_plugins = realpath( $plugins_dir );
		if ( false === $canonical_plugins ) {
			return new WP_Error(
				'gratis_ai_agent_plugins_dir_missing',
				__( 'WordPress plugins directory not found.', 'gratis-ai-agent' )
			);
		}

		// Create plugin directory.
		if ( ! wp_mkdir_p( $plugin_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_mkdir_failed',
				/* translators: %s: directory path */
				sprintf( __( 'Could not create plugin directory: %s', 'gratis-ai-agent' ), $plugin_dir )
			);
		}

		// Write each file.
		$written = [];
		foreach ( $files as $relative_path => $content ) {
			// Sanitize path to prevent traversal.
			$relative_path = ltrim( $relative_path, '/\\' );
			$abs_path      = realpath( $plugin_dir ) . '/' . $relative_path;

			// Ensure destination is inside plugin dir.
			$dest_real = dirname( $abs_path );
			if ( false === wp_mkdir_p( $dest_real ) ) {
				return new WP_Error(
					'gratis_ai_agent_mkdir_failed',
					/* translators: %s: directory path */
					sprintf( __( 'Could not create directory: %s', 'gratis-ai-agent' ), $dest_real )
				);
			}

			$result = file_put_contents( $abs_path, $content );
			if ( false === $result ) {
				return new WP_Error(
					'gratis_ai_agent_write_failed',
					/* translators: %s: file path */
					sprintf( __( 'Could not write file: %s', 'gratis-ai-agent' ), $relative_path )
				);
			}

			$written[] = $relative_path;
		}

		// Determine the plugin file path relative to the plugins directory.
		if ( empty( $plugin_file ) ) {
			$plugin_file = $slug . '/' . $slug . '.php';
		}

		// Record in database.
		$now    = current_time( 'mysql' );
		$insert = $wpdb->insert(
			self::table_name(),
			[
				'slug'             => $slug,
				'description'      => $description,
				'plan'             => $plan,
				'plugin_file'      => $plugin_file,
				'files'            => wp_json_encode( $written ),
				'status'           => 'installed',
				'sandbox_result'   => wp_json_encode( [] ),
				'activation_error' => '',
				'created_at'       => $now,
				'updated_at'       => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $insert ) {
			return new WP_Error(
				'gratis_ai_agent_db_insert_failed',
				/* translators: %s: database error */
				sprintf( __( 'Database insert failed: %s', 'gratis-ai-agent' ), $wpdb->last_error )
			);
		}

		$id = (int) $wpdb->insert_id;

		return [
			'id'          => $id,
			'plugin_dir'  => $plugin_dir,
			'plugin_file' => $plugin_file,
		];
	}

	/**
	 * Update the status and sandbox result for a generated plugin record.
	 *
	 * @param int                  $id             Record ID.
	 * @param string               $status         New status (installed, sandbox_passed, active, error).
	 * @param array<string,mixed>  $sandbox_result Sandbox test result array.
	 * @param string               $activation_error Error message if activation failed.
	 * @return bool
	 */
	public static function update_status(
		int $id,
		string $status,
		array $sandbox_result = [],
		string $activation_error = ''
	): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$updated = $wpdb->update(
			self::table_name(),
			[
				'status'           => $status,
				'sandbox_result'   => wp_json_encode( $sandbox_result ),
				'activation_error' => $activation_error,
				'updated_at'       => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Get a generated plugin record by ID.
	 *
	 * @param int $id Record ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin lookup.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		return $row ?? null;
	}

	/**
	 * List generated plugin records, newest first.
	 *
	 * @param int $limit Maximum records to return.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list( int $limit = 20 ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' ORDER BY created_at DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Remove a generated plugin record and optionally delete its files from disk.
	 *
	 * @param int  $id          Record ID.
	 * @param bool $delete_files Whether to delete the plugin directory from disk.
	 * @return bool
	 */
	public static function delete( int $id, bool $delete_files = false ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$record = self::get( $id );
		if ( null === $record ) {
			return false;
		}

		if ( $delete_files && ! empty( $record['slug'] ) ) {
			$plugin_dir = WP_CONTENT_DIR . '/plugins/' . sanitize_title( $record['slug'] ) . '/';
			if ( is_dir( $plugin_dir ) ) {
				// Use WP Filesystem for safe deletion.
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
				$fs = new \WP_Filesystem_Direct( [] );
				$fs->rmdir( $plugin_dir, true );
			}
		}

		$deleted = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $deleted;
	}
}
