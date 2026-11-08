<?php

declare(strict_types=1);
/**
 * Plugin Installer — writes AI-generated plugin files to wp-content/plugins/
 * and tracks them in the generated_plugins database table.
 *
 * @package SdAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\PluginBuilder;

use SdAiAgent\Core\Database;
use SdAiAgent\Core\Filesystem\PathCanonicalizer;
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
	 * Valid slug pattern: lowercase letters, digits, and hyphens only.
	 */
	private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

	/**
	 * Get the generated plugins table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		return Database::generated_plugins_table_name();
	}

	/**
	 * Validate that a relative file path is safe and resides inside the plugin directory.
	 *
	 * Checks:
	 *  - Path is not empty.
	 *  - Path contains no null bytes.
	 *  - Path does not contain traversal sequences (../).
	 *  - Resolved absolute path starts with WP_PLUGIN_DIR/{slug}/.
	 *
	 * @param string $slug          Validated plugin slug.
	 * @param string $relative_path Relative file path (relative to the plugin directory).
	 * @return string|\WP_Error Normalised relative path on success, WP_Error on failure.
	 */
	public static function validate_plugin_path( string $slug, string $relative_path ): string|\WP_Error {
		if ( '' === $relative_path ) {
			return new WP_Error(
				'sd_ai_agent_empty_path',
				__( 'File path must not be empty.', 'superdav-ai-agent' )
			);
		}

		// Reject null bytes.
		if ( str_contains( $relative_path, "\0" ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_path',
				__( 'File path contains invalid characters.', 'superdav-ai-agent' )
			);
		}

		// Reject explicit traversal sequences.
		if ( str_contains( $relative_path, '../' ) || str_contains( $relative_path, '..' . DIRECTORY_SEPARATOR ) ) {
			return new WP_Error(
				'sd_ai_agent_path_traversal',
				__( 'File path contains directory traversal sequences.', 'superdav-ai-agent' )
			);
		}

		// Normalise: strip leading slashes and backslashes.
		$normalised = ltrim( $relative_path, '/\\' );

		// Strip redundant "{slug}/" prefix so callers need not be consistent about it.
		if ( str_starts_with( $normalised, $slug . '/' ) ) {
			$normalised = substr( $normalised, strlen( $slug ) + 1 );
		}

		if ( '' === $normalised ) {
			return new WP_Error(
				'sd_ai_agent_empty_path',
				__( 'File path resolves to an empty path after normalisation.', 'superdav-ai-agent' )
			);
		}

		// Resolve both the plugin directory and the complete destination against
		// WP_PLUGIN_DIR. Complete-path validation rejects existing or dangling leaf
		// links instead of allowing the subsequent write to follow them.
		$plugins_root = realpath( WP_PLUGIN_DIR );
		if ( false !== $plugins_root ) {
			$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug;
			$plugin_dir = PathCanonicalizer::canonicalize_write_path_inside( $plugin_dir, $plugins_root );
			if ( is_wp_error( $plugin_dir ) ) {
				return $plugin_dir;
			}

			// A missing plugin directory cannot contain a leaf entry. Fresh installs
			// claim that directory atomically before using exclusive file creation.
			if ( is_dir( $plugin_dir ) ) {
				$destination = PathCanonicalizer::canonicalize_write_path_inside( $plugin_dir . '/' . $normalised, $plugin_dir );
				if ( is_wp_error( $destination ) ) {
					return $destination;
				}
			}
		}

		return $normalised;
	}

	/**
	 * Install a single-file AI-generated plugin.
	 *
	 * Creates wp-content/plugins/{slug}/{slug}.php and records the plugin in the
	 * generated_plugins table with status='installed'.
	 *
	 * @param string              $slug              Plugin slug (must match [a-z0-9-]).
	 * @param string              $main_file_content Full PHP source of the main plugin file.
	 * @param string              $description       Human-readable plugin description.
	 * @param array<string,mixed> $plan              Implementation plan (stored as JSON).
	 * @return array{id: int, plugin_dir: string, plugin_file: string}|\WP_Error
	 */
	public static function install_plugin(
		string $slug,
		string $main_file_content,
		string $description,
		array $plan
	): array|\WP_Error {
		if ( ! preg_match( self::SLUG_PATTERN, $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must contain only lowercase letters, digits, and hyphens.', 'superdav-ai-agent' )
			);
		}

		$main_file = $slug . '.php';
		$files     = [ $main_file => $main_file_content ];

		return self::install(
			$slug,
			$files,
			$description,
			wp_json_encode( $plan ) ?: '',
			$slug . '/' . $main_file
		);
	}

	/**
	 * Install a multi-file AI-generated plugin.
	 *
	 * Creates the full directory structure under wp-content/plugins/{slug}/ and
	 * records the plugin in the generated_plugins table with status='installed'.
	 * Path traversal protection is applied to every file path.
	 * Redundant "{slug}/" prefixes in file paths are normalised automatically.
	 *
	 * @param string               $slug        Plugin slug (must match [a-z0-9-]).
	 * @param array<string,string> $files       Map of relative path → PHP source.
	 * @param string               $description Human-readable plugin description.
	 * @param array<string,mixed>  $plan        Implementation plan (stored as JSON).
	 * @return array{id: int, plugin_dir: string, plugin_file: string}|\WP_Error
	 */
	public static function install_complex_plugin(
		string $slug,
		array $files,
		string $description,
		array $plan
	): array|\WP_Error {
		if ( ! preg_match( self::SLUG_PATTERN, $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must contain only lowercase letters, digits, and hyphens.', 'superdav-ai-agent' )
			);
		}

		if ( empty( $files ) ) {
			return new WP_Error(
				'sd_ai_agent_no_files',
				__( 'No plugin files provided for installation.', 'superdav-ai-agent' )
			);
		}

		// Validate all paths before touching the filesystem.
		$normalised_files = [];
		foreach ( $files as $relative_path => $content ) {
			$validated = self::validate_plugin_path( $slug, $relative_path );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			$normalised_files[ $validated ] = $content;
		}

		// Derive the main plugin file: prefer "{slug}.php", otherwise first file.
		$plugin_file_relative = $slug . '.php';
		if ( ! isset( $normalised_files[ $plugin_file_relative ] ) ) {
			$plugin_file_relative = array_key_first( $normalised_files );
		}

		return self::install(
			$slug,
			$normalised_files,
			$description,
			wp_json_encode( $plan ) ?: '',
			$slug . '/' . $plugin_file_relative
		);
	}

	/**
	 * Update specific files in an existing AI-generated plugin.
	 *
	 * Writes new content for the given files and updates the 'files' column and
	 * updated_at timestamp in the database record. Path traversal protection is
	 * applied to every file path.
	 *
	 * @param string               $slug  Plugin slug.
	 * @param array<string,string> $files Map of relative path → new PHP source.
	 * @return array{updated: string[]}|\WP_Error
	 */
	public static function update_plugin_files( string $slug, array $files ): array|\WP_Error {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}

		if ( empty( $files ) ) {
			return new WP_Error(
				'sd_ai_agent_no_files',
				__( 'No files provided for update.', 'superdav-ai-agent' )
			);
		}

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_found',
				/* translators: %s: plugin slug */
				sprintf( __( 'Plugin directory not found for slug: %s', 'superdav-ai-agent' ), $slug )
			);
		}

		// Validate and lint every proposed file before touching live plugin files.
		$normalised_files = [];
		foreach ( $files as $relative_path => $content ) {
			$validated = self::validate_plugin_path( $slug, $relative_path );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			if ( self::is_php_file( $validated ) ) {
				$lint = self::lint_php( $content );
				if ( ! $lint['valid'] ) {
					return new WP_Error(
						'sd_ai_agent_php_syntax_error',
						sprintf(
							/* translators: 1: file path 2: error message 3: line number */
							__( 'PHP syntax error in %1$s: %2$s (line %3$d)', 'superdav-ai-agent' ),
							$validated,
							$lint['error'] ?? 'Unknown',
							$lint['line'] ?? 0
						)
					);
				}
			}
			$normalised_files[ $validated ] = $content;
		}

		$updated = array_keys( $normalised_files );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read generated plugin metadata to preserve its actual entrypoint during the staged update.
		$stored_plugin_file = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from a trusted internal method.
			$wpdb->prepare( 'SELECT plugin_file FROM ' . self::table_name() . ' WHERE slug = %s LIMIT 1', $slug )
		);
		$main_file = $slug . '.php';
		if ( is_string( $stored_plugin_file ) && '' !== $stored_plugin_file ) {
			$validated_main = self::validate_plugin_path( $slug, $stored_plugin_file );
			if ( is_wp_error( $validated_main ) ) {
				return $validated_main;
			}
			$main_file = $validated_main;
		}

		$updater = new PluginUpdater();
		$result  = $updater->update(
			$slug,
			$normalised_files,
			static function ( array $swap_result, string $backup_dir ) use ( $wpdb, $updater, $slug, $updated ): true|WP_Error {
				if ( empty( $swap_result['swapped'] ) ) {
					return true;
				}

				// Commit metadata only after the complete staged filesystem swap succeeds.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update generated plugin record.
				$db_updated = $wpdb->update(
					self::table_name(),
					[
						'files'      => wp_json_encode( $updated ),
						'updated_at' => current_time( 'mysql' ),
					],
					[ 'slug' => $slug ],
					[ '%s', '%s' ],
					[ '%s' ]
				);
				if ( false !== $db_updated ) {
					return true;
				}

				$rollback = $updater->rollback( $slug, $backup_dir );
				if ( is_wp_error( $rollback ) ) {
					return new WP_Error(
						'sd_ai_agent_db_update_rollback_failed',
						__( 'Plugin metadata update failed, and the original plugin files could not be restored.', 'superdav-ai-agent' ),
						[ 'rollback_error_code' => $rollback->get_error_code() ]
					);
				}

				return new WP_Error(
					'sd_ai_agent_db_update_failed',
					__( 'Plugin metadata update failed, so the original plugin files were restored.', 'superdav-ai-agent' )
				);
			},
			$main_file
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'updated' => $updated ];
	}

	/**
	 * Delete an AI-generated plugin by slug.
	 *
	 * Deactivates the plugin if it is currently active, removes its directory
	 * from disk, and deletes the database record. Only works on plugins that
	 * have a record in sd_ai_agent_generated_plugins (i.e. AI-generated).
	 *
	 * @param string $slug Plugin slug.
	 * @return array{deleted: bool, deactivated: bool}|\WP_Error
	 */
	public static function delete_generated_plugin( string $slug ): array|\WP_Error {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}

		// Only delete plugins that are tracked in our database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table from trusted internal method.
		$record = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from internal method, not user input.
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE slug = %s LIMIT 1', $slug ),
			ARRAY_A
		);

		if ( null === $record ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_found',
				/* translators: %s: plugin slug */
				sprintf( __( 'No generated plugin record found for slug: %s', 'superdav-ai-agent' ), $slug )
			);
		}

		// Deactivate if currently active.
		$deactivated = false;
		if ( ! empty( $record['plugin_file'] ) ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_file_str = (string) $record['plugin_file'];
			if ( is_plugin_active( $plugin_file_str ) ) {
				deactivate_plugins( $plugin_file_str, true );
				$deactivated = true;
			}
		}

		// Remove directory from disk.
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';
		if ( is_dir( $plugin_dir ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			$fs = new \WP_Filesystem_Direct( [] );
			$fs->rmdir( $plugin_dir, true );
		}

		// Remove DB record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal plugin deletion; caching not applicable.
		$wpdb->delete(
			self::table_name(),
			[ 'slug' => $slug ],
			[ '%s' ]
		);

		return [
			'deleted'     => true,
			'deactivated' => $deactivated,
		];
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
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}

		if ( empty( $files ) ) {
			return new WP_Error(
				'sd_ai_agent_no_files',
				__( 'No plugin files provided for installation.', 'superdav-ai-agent' )
			);
		}

		$plugins_dir = trailingslashit( WP_PLUGIN_DIR );
		$plugin_dir  = $plugins_dir . $slug . '/';

		// Fresh installs must never adopt an existing on-disk plugin or tracking row.
		// Intentional replacements belong to the sandboxed update flow.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from a trusted internal method.
		$tracked_id = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from a trusted internal method.
			$wpdb->prepare( 'SELECT id FROM ' . self::table_name() . ' WHERE slug = %s LIMIT 1', $slug )
		);
		if ( null !== $tracked_id || self::path_entry_exists( $plugin_dir ) ) {
			return self::plugin_slug_exists_error( $slug );
		}

		// Validate paths to prevent directory traversal.
		$canonical_plugins = realpath( $plugins_dir );
		if ( false === $canonical_plugins ) {
			return new WP_Error(
				'sd_ai_agent_plugins_dir_missing',
				__( 'WordPress plugins directory not found.', 'superdav-ai-agent' )
			);
		}

		$canonical_plugin_dir = PathCanonicalizer::canonicalize_missing_path_inside( $plugin_dir, $canonical_plugins );
		if ( is_wp_error( $canonical_plugin_dir ) ) {
			return $canonical_plugin_dir;
		}

		// Validate and lint everything before claiming the target directory.
		$validated_files = [];
		foreach ( $files as $relative_path => $content ) {
			$validated = self::validate_plugin_path( $slug, (string) $relative_path );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			if ( $slug === $validated ) {
				return new WP_Error(
					'sd_ai_agent_invalid_plugin_file_path',
					__( 'Generated plugin file path must include a file extension.', 'superdav-ai-agent' )
				);
			}

			if ( self::is_php_file( $validated ) ) {
				$lint = self::lint_php( $content );
				if ( ! $lint['valid'] ) {
					return new WP_Error(
						'sd_ai_agent_php_syntax_error',
						sprintf(
							/* translators: 1: file path 2: error message 3: line number */
							__( 'PHP syntax error in %1$s: %2$s (line %3$d)', 'superdav-ai-agent' ),
							$validated,
							$lint['error'] ?? 'Unknown',
							$lint['line'] ?? 0
						)
					);
				}
			}

			$validated_files[ $validated ] = $content;
		}

		if ( empty( $plugin_file ) ) {
			$plugin_file = $slug . '/' . $slug . '.php';
		}

		$normalised_plugin_file = self::validate_plugin_path( $slug, $plugin_file );
		if ( is_wp_error( $normalised_plugin_file ) ) {
			return $normalised_plugin_file;
		}

		if ( ! self::is_php_file( $normalised_plugin_file ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_plugin_file_path',
				__( 'Main plugin file path must point to a PHP file.', 'superdav-ai-agent' )
			);
		}

		if ( ! array_key_exists( $normalised_plugin_file, $validated_files ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_file_missing',
				__( 'Main plugin file must be included in the generated file set.', 'superdav-ai-agent' )
			);
		}

		// FS_CHMOD_DIR is only defined once WP_Filesystem() has run; mirror the
		// core default so a fresh request cannot fatal on the undefined constant.
		$dir_mode = defined( 'FS_CHMOD_DIR' ) ? (int) FS_CHMOD_DIR : ( ( fileperms( ABSPATH ) & 0777 ) | 0755 );

		// mkdir() is the atomic slug claim. A concurrent creator that wins the
		// directory race is treated as a collision rather than being overwritten.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Atomic exclusive directory creation is required here.
		if ( ! mkdir( $canonical_plugin_dir, $dir_mode ) ) {
			if ( self::path_entry_exists( $plugin_dir ) ) {
				return self::plugin_slug_exists_error( $slug );
			}
			return new WP_Error(
				'sd_ai_agent_mkdir_failed',
				/* translators: %s: directory path */
				sprintf( __( 'Could not create plugin directory: %s', 'superdav-ai-agent' ), $canonical_plugin_dir )
			);
		}

		// Write each file.
		$written = [];
		foreach ( $validated_files as $relative_path => $content ) {
			$abs_path = trailingslashit( $canonical_plugin_dir ) . $relative_path;

			// Ensure destination is inside plugin dir.
			$dest_real = PathCanonicalizer::canonicalize_missing_path_inside( dirname( $abs_path ), $canonical_plugin_dir );
			if ( is_wp_error( $dest_real ) ) {
				self::remove_new_plugin_directory( $canonical_plugin_dir );
				return $dest_real;
			}

			if ( false === wp_mkdir_p( $dest_real ) ) {
				self::remove_new_plugin_directory( $canonical_plugin_dir );
				return new WP_Error(
					'sd_ai_agent_mkdir_failed',
					/* translators: %s: directory path */
					sprintf( __( 'Could not create directory: %s', 'superdav-ai-agent' ), $dest_real )
				);
			}

			// Exclusive creation prevents a concurrent writer from being overwritten.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive mode is required for collision safety.
			$handle = fopen( $abs_path, 'x+b' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing to the exclusive local file handle claimed above.
			$result = false !== $handle ? fwrite( $handle, $content ) : false;
			if ( false !== $handle ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the exclusive local file handle.
				fclose( $handle );
			}
			if ( false === $result || strlen( $content ) !== $result ) {
				self::remove_new_plugin_directory( $canonical_plugin_dir );
				return new WP_Error(
					'sd_ai_agent_write_failed',
					/* translators: %s: file path */
					sprintf( __( 'Could not write file: %s', 'superdav-ai-agent' ), $relative_path )
				);
			}

			$written[] = $relative_path;
		}

		$plugin_file = $slug . '/' . $normalised_plugin_file;

		// Record in database.
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal plugin installation; caching not applicable.
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
			self::remove_new_plugin_directory( $canonical_plugin_dir );
			return new WP_Error(
				'sd_ai_agent_db_insert_failed',
				/* translators: %s: database error */
				sprintf( __( 'Database insert failed: %s', 'superdav-ai-agent' ), $wpdb->last_error )
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
	 * Build the uniform error returned when a fresh install collides.
	 */
	private static function plugin_slug_exists_error( string $slug ): WP_Error {
		return new WP_Error(
			'sd_ai_agent_plugin_slug_exists',
			sprintf(
				/* translators: %s: plugin slug */
				__( 'A plugin already exists for slug "%s". Use the sandboxed update flow to change it.', 'superdav-ai-agent' ),
				$slug
			)
		);
	}

	/**
	 * Check for any filesystem entry, including a broken symbolic link.
	 *
	 * @phpstan-impure The filesystem can change between collision checks.
	 */
	private static function path_entry_exists( string $path ): bool {
		return file_exists( $path ) || is_link( untrailingslashit( $path ) );
	}

	/**
	 * Remove only the directory exclusively created by the current install.
	 */
	private static function remove_new_plugin_directory( string $plugin_dir ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$filesystem = new \WP_Filesystem_Direct( [] );
		$filesystem->rmdir( $plugin_dir, true );
	}

	/**
	 * Update the status and sandbox result for a generated plugin record.
	 *
	 * @param int                 $id             Record ID.
	 * @param string              $status         New status (installed, sandbox_passed, active, error).
	 * @param array<string,mixed> $sandbox_result Sandbox test result array.
	 * @param string              $activation_error Error message if activation failed.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal plugin status update; caching not applicable.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Admin lookup; table name is a trusted internal constant, not user input.
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from a trusted internal method, not user input.
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		// get_row( ARRAY_A ) returns associative array with string keys; cast is safe.
		/** @var array<string, mixed>|null $row */
		return $row ?? null;
	}

	/**
	 * Check if a file path is a PHP file.
	 *
	 * @param string $path File path (relative or absolute).
	 * @return bool True if the file has a .php extension.
	 */
	private static function is_php_file( string $path ): bool {
		return (bool) preg_match( '/\.php$/i', $path );
	}

	/**
	 * Lint PHP content for syntax errors.
	 *
	 * Uses {@see token_get_all()} with `TOKEN_PARSE` to surface syntax errors as
	 * `\ParseError`. A scoped `set_error_handler()` converts any notices/warnings
	 * emitted by the tokeniser into `\ErrorException` so they do not leak to the
	 * site-wide error log. The handler is always restored in a `finally` block.
	 *
	 * @param string $content PHP source code.
	 * @return array{valid: bool, error?: string, line?: int}
	 */
	private static function lint_php( string $content ): array {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped error handler is required to convert tokeniser notices into exceptions; restored in finally.
		set_error_handler(
			static function ( int $severity, string $message, string $file, int $line ): bool {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- ErrorException constructor arguments are not output; PHPCS false positive.
				throw new \ErrorException( $message, 0, $severity, $file, $line );
			}
		);

		try {
			$tokens = token_get_all( $content, TOKEN_PARSE );
			unset( $tokens ); // Result unused — we only care about parse errors.
			return [ 'valid' => true ];
		} catch ( \ParseError | \ErrorException $e ) {
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		} catch ( \Throwable $e ) {
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * List all generated plugin records.
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
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from a trusted internal method, not user input.
				'SELECT * FROM ' . self::table_name() . ' ORDER BY created_at DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		// get_results( ARRAY_A ) returns rows with string keys; cast is safe.
		/** @var array<int,array<string,mixed>> $rows */
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
			$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . sanitize_title( $record['slug'] ) . '/';
			if ( is_dir( $plugin_dir ) ) {
				// Use WP Filesystem for safe deletion.
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
				$fs = new \WP_Filesystem_Direct( [] );
				$fs->rmdir( $plugin_dir, true );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal plugin deletion; caching not applicable.
		$deleted = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $deleted;
	}
}
