<?php

declare(strict_types=1);
/**
 * Plugin Updater — sandboxed live updates for AI-generated plugins.
 *
 * Flow: backup → stage → test → swap → verify → rollback on failure.
 *
 * Backups and staging directories live in a private temporary root outside
 * WordPress's web-served directories. The root is site-specific and may be
 * relocated with the `sd_ai_agent_plugin_builder_private_root` filter.
 *
 * @package SdAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\PluginBuilder;

use SdAiAgent\Core\Filesystem\FileModGate;
use SdAiAgent\Core\Filesystem\PathCanonicalizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PluginUpdater — sandboxed updates for running AI-generated plugins.
 *
 * @since 1.5.0
 */
class PluginUpdater {

	/**
	 * Staging directories created by this updater instance.
	 *
	 * @var array<string,true>
	 */
	private array $owned_stages = [];

	/**
	 * Backup directories created by this updater instance.
	 *
	 * @var array<string,true>
	 */
	private array $owned_backups = [];

	/**
	 * Per-plugin locks held by the current update transaction.
	 *
	 * @var array<string,resource>
	 */
	private array $held_update_locks = [];

	/**
	 * Get the absolute path to the backups root directory.
	 *
	 * @return string|WP_Error Trailing-slashed absolute path or an error when a
	 *                         private location cannot be established.
	 */
	private function backups_root(): string|WP_Error {
		return $this->private_subdirectory( 'backups' );
	}

	/**
	 * Get the absolute path to the staging root directory.
	 *
	 * @return string|WP_Error Trailing-slashed absolute path or an error when a
	 *                         private location cannot be established.
	 */
	private function staging_root(): string|WP_Error {
		return $this->private_subdirectory( 'staging' );
	}

	/**
	 * Get the absolute path to the per-plugin update lock directory.
	 *
	 * @return string|WP_Error Trailing-slashed absolute path or an error.
	 */
	private function locks_root(): string|WP_Error {
		return $this->private_subdirectory( 'locks' );
	}

	/**
	 * Establish a site-specific private storage root outside web-served paths.
	 *
	 * @return string|WP_Error Trailing-slashed canonical root or an error.
	 */
	private function private_storage_root(): string|WP_Error {
		$site_path = PathCanonicalizer::canonicalize_missing_path( ABSPATH );
		if ( is_wp_error( $site_path ) ) {
			$site_path = ABSPATH;
		}

		$default_root = trailingslashit( dirname( rtrim( (string) $site_path, '/\\' ) ) )
			. '.sd-ai-agent-plugin-builder-'
			. substr( hash_hmac( 'sha256', (string) $site_path, wp_salt( 'auth' ) ), 0, 24 );

		/**
		 * Filter the private root used for plugin-builder backups and staging.
		 *
		 * The selected directory must remain outside WordPress core, content,
		 * plugins, and uploads directories.
		 *
		 * @param string $default_root Site-specific default private root.
		 */
		$root       = (string) apply_filters( 'sd_ai_agent_plugin_builder_private_root', $default_root );
		$existed    = is_dir( $root );
		$is_symlink = is_link( $root );
		$canonical  = PathCanonicalizer::canonicalize_missing_path( $root );
		if ( is_wp_error( $canonical ) || $is_symlink ) {
			if ( $is_symlink ) {
				return new WP_Error(
					'sd_ai_agent_private_storage_symlink',
					__( 'Private plugin backup storage cannot be a symbolic link.', 'superdav-ai-agent' )
				);
			}
			return $canonical;
		}

		$uploads     = wp_upload_dir( null, false );
		$public_dirs = [
			ABSPATH,
			WP_CONTENT_DIR,
			WP_PLUGIN_DIR,
			! empty( $uploads['basedir'] ) ? (string) $uploads['basedir'] : WP_CONTENT_DIR . '/uploads',
		];
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$public_dirs[] = sanitize_text_field( wp_unslash( (string) $_SERVER['DOCUMENT_ROOT'] ) );
		}

		foreach ( array_unique( $public_dirs ) as $public_dir ) {
			$public_path = PathCanonicalizer::canonicalize_missing_path( $public_dir );
			if (
				! is_wp_error( $public_path )
				&& (
					PathCanonicalizer::path_is_inside( (string) $canonical, (string) $public_path )
					|| PathCanonicalizer::path_is_inside( (string) $public_path, (string) $canonical )
				)
			) {
				return new WP_Error(
					'sd_ai_agent_private_storage_public',
					__( 'Plugin backup storage must be outside web-served WordPress directories.', 'superdav-ai-agent' )
				);
			}
		}

		if ( ! wp_mkdir_p( (string) $canonical ) || ! is_dir( (string) $canonical ) ) {
			return new WP_Error(
				'sd_ai_agent_private_storage_unavailable',
				__( 'Could not create private plugin backup storage.', 'superdav-ai-agent' )
			);
		}

		// The root boundary protects all descendants from other local users. Never
		// change permissions on a host-supplied existing directory.
		if ( ! $existed ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private storage requires restrictive filesystem permissions.
			if ( ! chmod( (string) $canonical, 0700 ) ) {
				return new WP_Error(
					'sd_ai_agent_private_storage_permissions',
					__( 'Could not secure private plugin backup storage.', 'superdav-ai-agent' )
				);
			}
		}
		clearstatcache( true, (string) $canonical );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fileperms -- Verifying that private storage is inaccessible to other local users.
		$permissions = fileperms( (string) $canonical );
		if ( false === $permissions || 0 !== ( $permissions & 0077 ) ) {
			return new WP_Error(
				'sd_ai_agent_private_storage_permissions',
				__( 'Private plugin backup storage permissions are not restrictive.', 'superdav-ai-agent' )
			);
		}
		if ( function_exists( 'posix_geteuid' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fileowner -- Ownership verification closes shared-host directory substitution.
			$owner = fileowner( (string) $canonical );
			if ( false === $owner || $owner !== posix_geteuid() ) {
				return new WP_Error(
					'sd_ai_agent_private_storage_owner',
					__( 'Private plugin backup storage is not owned by the current process user.', 'superdav-ai-agent' )
				);
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Validating a local private storage path before filesystem operations.
		if ( ! is_writable( (string) $canonical ) ) {
			return new WP_Error(
				'sd_ai_agent_private_storage_unavailable',
				__( 'Private plugin backup storage is not writable.', 'superdav-ai-agent' )
			);
		}

		return trailingslashit( (string) $canonical );
	}

	/**
	 * Create and validate a directory beneath the private storage root.
	 *
	 * @param string $name Fixed internal subdirectory name.
	 * @return string|WP_Error Trailing-slashed canonical path or an error.
	 */
	private function private_subdirectory( string $name ): string|WP_Error {
		$root = $this->private_storage_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$directory = PathCanonicalizer::canonicalize_missing_path_inside( $root . $name, $root );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		if ( ! wp_mkdir_p( $directory ) || ! is_dir( $directory ) ) {
			return new WP_Error(
				'sd_ai_agent_private_storage_unavailable',
				__( 'Could not create private plugin backup storage.', 'superdav-ai-agent' )
			);
		}

		return trailingslashit( $directory );
	}

	/**
	 * Move historical public backup and staging directories into private storage.
	 *
	 * Each directory is copied and compared by relative path, type, size, and
	 * SHA-256 hash before the public source is removed. A failed or ambiguous
	 * migration always leaves the source intact for recovery.
	 *
	 * @return true|WP_Error True when all legacy content is private or absent.
	 */
	public function migrate_legacy_storage(): bool|WP_Error {
		$uploads      = wp_upload_dir( null, false );
		$uploads_root = ! empty( $uploads['basedir'] ) ? (string) $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$legacy_paths = [
			trailingslashit( $uploads_root ) . 'sd-ai-backups',
			trailingslashit( $uploads_root ) . 'sd-ai-staging',
			trailingslashit( WP_CONTENT_DIR ) . 'sd-ai-backups',
		];
		if ( ! array_filter( $legacy_paths, 'is_dir' ) ) {
			return true;
		}

		$lock_name = 'sd_ai_agent_plugin_storage_migration_lock';
		$lock_time = (int) get_option( $lock_name, 0 );
		if ( $lock_time > time() - ( 5 * MINUTE_IN_SECONDS ) ) {
			return true;
		}
		if ( $lock_time ) {
			delete_option( $lock_name );
		}
		if ( ! add_option( $lock_name, time(), '', false ) ) {
			return true;
		}

		try {
			$backups_root = $this->backups_root();
			$staging_root = $this->staging_root();
			if ( is_wp_error( $backups_root ) ) {
				return $backups_root;
			}
			if ( is_wp_error( $staging_root ) ) {
				return $staging_root;
			}

			$legacy_roots = [
				[ $legacy_paths[0], $uploads_root, $backups_root ],
				[ $legacy_paths[1], $uploads_root, $staging_root ],
				[ $legacy_paths[2], WP_CONTENT_DIR, $backups_root ],
			];
			$first_error  = null;
			foreach ( $legacy_roots as [ $source, $boundary, $destination ] ) {
				$result = $this->migrate_legacy_root( $source, $boundary, $destination );
				if ( is_wp_error( $result ) && null === $first_error ) {
					$first_error = $result;
				}
			}

			return $first_error ?? true;
		} finally {
			delete_option( $lock_name );
		}
	}

	/**
	 * Get the trailing-slashed absolute path to an installed plugin's directory.
	 *
	 * Uses WP_PLUGIN_DIR per wp.org guidelines for referencing other plugins.
	 *
	 * @param string $slug Plugin slug (already validated by caller).
	 * @return string
	 */
	private function plugin_dir_path( string $slug ): string {
		$path        = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/';
		$canonical   = PathCanonicalizer::canonicalize_missing_path( $path );
		$plugin_root = realpath( WP_PLUGIN_DIR );

		if ( is_wp_error( $canonical ) || false === $plugin_root || ! PathCanonicalizer::path_is_inside( (string) $canonical, $plugin_root ) ) {
			return $path;
		}

		return trailingslashit( (string) $canonical );
	}

	/**
	 * Backup an installed plugin to an atomically claimed private directory.
	 *
	 * @param string $slug Plugin slug (directory name under wp-content/plugins/).
	 * @return string|\WP_Error Absolute path to the backup directory on success.
	 */
	public function backup( string $slug ): string|\WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}

		$plugin_dir = $this->plugin_dir_path( $slug );
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_found',
				/* translators: %s: plugin directory */
				sprintf( __( 'Plugin directory not found: %s', 'superdav-ai-agent' ), $plugin_dir )
			);
		}

		$backups_root = $this->backups_root();
		if ( is_wp_error( $backups_root ) ) {
			return $backups_root;
		}

		$backup_dir = $this->claim_operation_directory(
			$backups_root,
			$slug . '-' . gmdate( 'Y-m-d-His' ) . '-'
		);
		if ( is_wp_error( $backup_dir ) ) {
			return $backup_dir;
		}

		$result = $this->copy_directory( $plugin_dir, $backup_dir );
		if ( is_wp_error( $result ) ) {
			$this->remove_directory( $backup_dir );
			return $result;
		}

		$this->owned_backups[ $backup_dir ] = true;
		return $backup_dir;
	}

	/**
	 * Stage new file content for a plugin.
	 *
	 * Copies the live plugin directory to the staging location first so the
	 * staged copy is always complete, then overlays the provided modified files.
	 *
	 * @param string               $slug           Plugin slug.
	 * @param array<string,string> $modified_files Map of relative path → PHP source.
	 * @return string|\WP_Error Absolute path to the staging directory on success.
	 */
	public function stage( string $slug, array $modified_files ): string|\WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}

		$plugin_dir = $this->plugin_dir_path( $slug );
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_found',
				/* translators: %s: plugin directory */
				sprintf( __( 'Plugin directory not found: %s', 'superdav-ai-agent' ), $plugin_dir )
			);
		}

		$staging_root = $this->staging_root();
		if ( is_wp_error( $staging_root ) ) {
			return $staging_root;
		}

		$staging_dir = $this->claim_operation_directory( $staging_root, $slug . '-' );
		if ( is_wp_error( $staging_dir ) ) {
			return $staging_dir;
		}
		$this->owned_stages[ $staging_dir ] = true;

		// Copy live plugin to staging so the staged copy is always complete.
		$result = $this->copy_directory( $plugin_dir, $staging_dir );
		if ( is_wp_error( $result ) ) {
			$this->remove_owned_stage( $staging_dir );
			return $result;
		}

		// Overlay the modified files.
		foreach ( $modified_files as $relative_path => $content ) {
			$relative_path = ltrim( (string) $relative_path, '/\\' );
			$abs_path      = $staging_dir . $relative_path;
			$dir           = PathCanonicalizer::canonicalize_missing_path_inside( dirname( $abs_path ), $staging_dir );
			if ( is_wp_error( $dir ) ) {
				$this->remove_owned_stage( $staging_dir );
				return $dir;
			}

			if ( ! wp_mkdir_p( $dir ) || ! is_dir( $dir ) ) {
				$this->remove_owned_stage( $staging_dir );
				return new WP_Error(
					'sd_ai_agent_staging_write_failed',
					/* translators: %s: relative file path */
					sprintf( __( 'Could not prepare staging directory for file: %s', 'superdav-ai-agent' ), $relative_path )
				);
			}

			// Validate PHP syntax before writing.
			if ( $this->is_php_file( $relative_path ) ) {
				$lint = $this->lint_php( $content );
				if ( ! $lint['valid'] ) {
					$this->remove_owned_stage( $staging_dir );
					return new WP_Error(
						'sd_ai_agent_php_syntax_error',
						sprintf(
							/* translators: 1: file path 2: error message 3: line number */
							__( 'PHP syntax error in %1$s: %2$s (line %3$d)', 'superdav-ai-agent' ),
							$relative_path,
							$lint['error'] ?? 'Unknown',
							$lint['line'] ?? 0
						)
					);
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Staging: WP_Filesystem not applicable when writing generated PHP source to a temp location.
			if ( false === file_put_contents( $abs_path, $content ) ) {
				$this->remove_owned_stage( $staging_dir );
				return new WP_Error(
					'sd_ai_agent_staging_write_failed',
					/* translators: %s: relative file path */
					sprintf( __( 'Could not write staging file: %s', 'superdav-ai-agent' ), $relative_path )
				);
			}
		}

		return $staging_dir;
	}

	/**
	 * Run PluginSandbox checks on a staged plugin directory.
	 *
	 * @param string      $slug        Plugin slug (used to derive the default main plugin file name).
	 * @param string      $staging_dir Absolute path to the staging directory.
	 * @param string|null $main_file   Main plugin file relative to the plugin directory.
	 * @return array<string,mixed> Sandbox result with at least `passed` (bool) and `errors` (string[]).
	 */
	public function test_staged( string $slug, string $staging_dir, ?string $main_file = null ): array {
		$plugin_file    = $main_file ?? $slug . '.php';
		$sandbox_result = PluginSandbox::run_all( $staging_dir, $plugin_file );

		if ( is_wp_error( $sandbox_result ) ) {
			return [
				'passed' => false,
				'errors' => [ $sandbox_result->get_error_message() ],
			];
		}

		return $sandbox_result;
	}

	/**
	 * Swap the staged plugin over the live plugin.
	 *
	 * Deactivates the plugin (if active), prepares a complete sibling directory,
	 * atomically renames it over the live directory, then reactivates. If
	 * reactivation fails, restores from backup automatically.
	 *
	 * @param string      $slug        Plugin slug.
	 * @param string      $staging_dir Absolute path to the staging directory.
	 * @param string      $backup_dir  Absolute path to the backup directory (used for rollback on failure).
	 * @param string|null $main_file   Main plugin file relative to the plugin directory.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function swap( string $slug, string $staging_dir, string $backup_dir, ?string $main_file = null ): array|\WP_Error {
		if ( ! isset( $this->owned_stages[ $staging_dir ] ) || ! isset( $this->owned_backups[ $backup_dir ] ) ) {
			return new WP_Error(
				'sd_ai_agent_update_resource_not_owned',
				__( 'The staged plugin and backup must belong to the current update operation.', 'superdav-ai-agent' )
			);
		}

		if ( ! is_dir( $staging_dir ) ) {
			return new WP_Error(
				'sd_ai_agent_staging_not_found',
				/* translators: %s: staging directory */
				sprintf( __( 'Staging directory not found: %s', 'superdav-ai-agent' ), $staging_dir )
			);
		}

		$plugin_file = $slug . '/' . ( $main_file ?? $slug . '.php' );
		$plugin_dir  = $this->plugin_dir_path( $slug );

		// Check if plugin file modifications are allowed.
		$mod_allowed = FileModGate::assert_allowed( $plugin_dir );
		if ( is_wp_error( $mod_allowed ) ) {
			return $mod_allowed;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$was_active = is_plugin_active( $plugin_file );
		if ( $was_active ) {
			deactivate_plugins( $plugin_file, true );
		}

		// Build the replacement outside the live tree so copy() can never follow a
		// leaf symlink inserted into the live directory during publication.
		$copy_result = $this->publish_directory_atomically( $staging_dir, $plugin_dir );
		if ( is_wp_error( $copy_result ) ) {
			return new WP_Error(
				'sd_ai_agent_swap_copy_failed',
				/* translators: %s: underlying error message */
				sprintf( __( 'Swap failed before the prepared update could be published: %s', 'superdav-ai-agent' ), $copy_result->get_error_message() )
			);
		}

		// Reactivate if the plugin was active before the swap.
		if ( $was_active ) {
			$activate_result = activate_plugin( $plugin_file );
			if ( is_wp_error( $activate_result ) ) {
				// Reactivation failed — restore from backup.
				$this->rollback( $slug, $backup_dir );
				return new WP_Error(
					'sd_ai_agent_reactivation_failed',
					sprintf(
						/* translators: 1: plugin file, 2: underlying error */
						__( 'Reactivation of "%1$s" failed after swap, backup restored: %2$s', 'superdav-ai-agent' ),
						$plugin_file,
						$activate_result->get_error_message()
					)
				);
			}
		}

		return [
			'swapped'     => true,
			'plugin_file' => $plugin_file,
			'was_active'  => $was_active,
		];
	}

	/**
	 * Roll back a plugin to a previously created backup.
	 *
	 * @param string $slug       Plugin slug.
	 * @param string $backup_dir Absolute path to the backup directory.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function rollback( string $slug, string $backup_dir ): array|\WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}

		if ( ! is_dir( $backup_dir ) ) {
			return new WP_Error(
				'sd_ai_agent_backup_not_found',
				/* translators: %s: backup directory */
				sprintf( __( 'Backup directory not found: %s', 'superdav-ai-agent' ), $backup_dir )
			);
		}

		$backups_root = $this->backups_root();
		$backup_real  = realpath( $backup_dir );
		$root_real    = is_wp_error( $backups_root ) ? false : realpath( $backups_root );
		$backup_name  = false === $backup_real ? '' : basename( $backup_real );
		$name_pattern = '/^' . preg_quote( $slug, '/' ) . '-\d{4}-\d{2}-\d{2}-\d{6}(?:-[a-f0-9]{24}|-legacy-[a-f0-9]{12})?$/';
		if (
			false === $backup_real
			|| false === $root_real
			|| ! PathCanonicalizer::path_is_inside( $backup_real, $root_real )
			|| ! preg_match( $name_pattern, $backup_name )
		) {
			return new WP_Error(
				'sd_ai_agent_backup_not_owned',
				__( 'The backup does not belong to this plugin update storage.', 'superdav-ai-agent' )
			);
		}

		$acquired_here = ! isset( $this->held_update_locks[ $slug ] );
		if ( $acquired_here ) {
			$lock = $this->acquire_update_lock( $slug );
			if ( is_wp_error( $lock ) ) {
				return $lock;
			}
			$this->held_update_locks[ $slug ] = $lock;
		}

		try {
			$plugin_dir = $this->plugin_dir_path( $slug );

			$result = $this->publish_directory_atomically( $backup_real, $plugin_dir );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return [
				'rolled_back' => true,
				'slug'        => $slug,
				'backup_dir'  => trailingslashit( $backup_real ),
			];
		} finally {
			if ( $acquired_here ) {
				$this->release_update_lock( $this->held_update_locks[ $slug ] );
				unset( $this->held_update_locks[ $slug ] );
			}
		}
	}

	/**
	 * Remove old plugin backups beyond the configured retention window.
	 *
	 * Never deletes the most recent backup for any slug regardless of age.
	 * Retention window is configurable via the `sd_ai_agent_backup_retention_days` filter.
	 *
	 * @param int $max_age_days Maximum backup age in days. Default 7.
	 * @return int Number of backup directories removed.
	 */
	public function cleanup_old_backups( int $max_age_days = 7 ): int {
		/**
		 * Filter the number of days to retain plugin backups.
		 *
		 * @param int $max_age_days Default retention in days.
		 */
		$max_age_days = (int) apply_filters( 'sd_ai_agent_backup_retention_days', $max_age_days );
		$backups_root = $this->backups_root();
		if ( is_wp_error( $backups_root ) ) {
			return 0;
		}

		if ( ! is_dir( $backups_root ) ) {
			return 0;
		}

		$cutoff  = time() - ( $max_age_days * DAY_IN_SECONDS );
		$removed = 0;

		// Collect all backup directories grouped by slug.
		$entries = glob( $backups_root . '*', GLOB_ONLYDIR );
		if ( false === $entries || empty( $entries ) ) {
			return 0;
		}

		/** @var array<string,list<array{dir:string,timestamp:string}>> $by_slug */
		$by_slug = [];
		foreach ( $entries as $entry ) {
			$basename = basename( $entry );
			// Accept historical timestamp-only names and operation-owned names.
			if ( preg_match( '/^(.+)-(\d{4}-\d{2}-\d{2}-\d{6})(?:-[a-f0-9]{24}|-legacy-[a-f0-9]{12})?$/', $basename, $m ) ) {
				$by_slug[ $m[1] ][] = [
					'dir'       => $entry,
					'timestamp' => $m[2],
				];
			}
		}

		foreach ( $by_slug as $backups ) {
			// Sort descending by timestamp so index 0 is the most recent.
			usort(
				$backups,
				static function ( array $a, array $b ): int {
					return strcmp( $b['timestamp'], $a['timestamp'] );
				}
			);

			foreach ( $backups as $index => $backup ) {
				// Always preserve the most recent backup.
				if ( 0 === $index ) {
					continue;
				}

				$mtime = filemtime( $backup['dir'] );
				if ( false !== $mtime && $mtime < $cutoff ) {
					$this->remove_directory( $backup['dir'] );
					++$removed;
				}
			}
		}

		return $removed;
	}

	/**
	 * Orchestrate the full update flow for an installed plugin.
	 *
	 * Steps: backup → stage → test → swap → optional post-swap validation → cleanup staging.
	 *
	 * @param string               $slug           Plugin slug.
	 * @param array<string,string> $modified_files Map of relative path → PHP source.
	 * @param callable|null        $post_swap      Optional validation executed while the per-plugin lock remains held.
	 * @param string|null          $main_file      Main plugin file relative to the plugin directory.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function update( string $slug, array $modified_files, ?callable $post_swap = null, ?string $main_file = null ): array|\WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'sd_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'superdav-ai-agent' )
			);
		}
		if ( ! is_dir( $this->plugin_dir_path( $slug ) ) ) {
			return new WP_Error(
				'sd_ai_agent_plugin_not_found',
				__( 'Plugin directory not found.', 'superdav-ai-agent' )
			);
		}

		$lock = $this->acquire_update_lock( $slug );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		$this->held_update_locks[ $slug ] = $lock;

		try {
			// Step 1: Backup.
			$backup_dir = $this->backup( $slug );
			if ( is_wp_error( $backup_dir ) ) {
				return $backup_dir;
			}

			// Step 2: Stage.
			$staging_dir = $this->stage( $slug, $modified_files );
			if ( is_wp_error( $staging_dir ) ) {
				return $staging_dir;
			}

			// Step 3: Test staged copy.
			$test_result = $this->test_staged( $slug, $staging_dir, $main_file );
			if ( ! $test_result['passed'] ) {
				$this->remove_owned_stage( $staging_dir );
				return new WP_Error(
					'sd_ai_agent_sandbox_failed',
					implode( '; ', $test_result['errors'] )
				);
			}

			// Step 4: Swap.
			$swap_result = $this->swap( $slug, $staging_dir, $backup_dir, $main_file );
			if ( is_wp_error( $swap_result ) ) {
				$this->remove_owned_stage( $staging_dir );
				return $swap_result;
			}

			// Keep the transaction lock through caller-owned health validation and
			// rollback so a later update cannot be overwritten by a stale snapshot.
			if ( null !== $post_swap ) {
				try {
					$post_swap_result = $post_swap( $swap_result, $backup_dir );
				} catch ( \Throwable $e ) {
					$rollback_result = $this->rollback( $slug, $backup_dir );
					$this->remove_owned_stage( $staging_dir );
					if ( is_wp_error( $rollback_result ) ) {
						return new WP_Error(
							'sd_ai_agent_post_swap_rollback_failed',
							sprintf(
								/* translators: %s: rollback error message */
								__( 'Post-update validation failed, and the backup could not be restored: %s', 'superdav-ai-agent' ),
								$rollback_result->get_error_message()
							),
							[ 'rollback_error_code' => $rollback_result->get_error_code() ]
						);
					}
					return new WP_Error(
						'sd_ai_agent_post_swap_validation_failed',
						__( 'Post-update validation failed and the backup was restored.', 'superdav-ai-agent' )
					);
				}
				if ( is_wp_error( $post_swap_result ) ) {
					$this->remove_owned_stage( $staging_dir );
					return $post_swap_result;
				}
			}

			// Step 5: Cleanup only this operation's staging directory.
			$this->remove_owned_stage( $staging_dir );

			return array_merge(
				$swap_result,
				[ 'backup_dir' => $backup_dir ]
			);
		} finally {
			$this->release_update_lock( $this->held_update_locks[ $slug ] );
			unset( $this->held_update_locks[ $slug ] );
		}
	}

	// ─── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Atomically claim a unique directory for one backup or staging operation.
	 *
	 * @param string $root   Validated private storage root.
	 * @param string $prefix Directory-name prefix.
	 * @return string|WP_Error Trailing-slashed claimed directory or an error.
	 */
	private function claim_operation_directory( string $root, string $prefix ): string|WP_Error {
		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			try {
				$suffix = bin2hex( random_bytes( 12 ) );
			} catch ( \Throwable $e ) {
				return new WP_Error(
					'sd_ai_agent_operation_identity_failed',
					__( 'Could not create a unique plugin update operation.', 'superdav-ai-agent' )
				);
			}

			$directory = PathCanonicalizer::canonicalize_missing_path_inside( $root . $prefix . $suffix, $root );
			if ( is_wp_error( $directory ) ) {
				return $directory;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Atomic exclusive directory creation provides operation identity.
			if ( mkdir( $directory, 0700 ) ) {
				return trailingslashit( $directory );
			}
		}

		return new WP_Error(
			'sd_ai_agent_operation_identity_failed',
			__( 'Could not claim unique private storage for the plugin update.', 'superdav-ai-agent' )
		);
	}

	/**
	 * Acquire a bounded exclusive lock for one live plugin.
	 *
	 * @param string $slug Sanitized plugin slug.
	 * @return resource|WP_Error Open locked file handle or an error.
	 */
	private function acquire_update_lock( string $slug ) {
		$locks_root = $this->locks_root();
		if ( is_wp_error( $locks_root ) ) {
			return $locks_root;
		}

		$lock_path = $locks_root . hash( 'sha256', $slug ) . '.lock';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flock requires a process-local file handle.
		$handle = fopen( $lock_path, 'c+' );
		if ( false === $handle ) {
			return new WP_Error(
				'sd_ai_agent_update_lock_failed',
				__( 'Could not create the plugin update lock.', 'superdav-ai-agent' )
			);
		}

		/**
		 * Filter how long an update waits for another operation on the same plugin.
		 *
		 * @param int    $timeout_ms Maximum lock wait in milliseconds.
		 * @param string $slug       Sanitized plugin slug.
		 */
		$timeout_ms = max( 0, (int) apply_filters( 'sd_ai_agent_plugin_update_lock_timeout_ms', 5000, $slug ) );
		$deadline   = microtime( true ) + ( $timeout_ms / 1000 );
		do {
			if ( flock( $handle, LOCK_EX | LOCK_NB ) ) {
				return $handle;
			}
			if ( microtime( true ) >= $deadline ) {
				break;
			}
			usleep( 50000 );
		} while ( true );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the process-local flock handle.
		fclose( $handle );
		return new WP_Error(
			'sd_ai_agent_plugin_update_in_progress',
			__( 'Another update is already in progress for this plugin.', 'superdav-ai-agent' )
		);
	}

	/**
	 * Release a plugin update lock.
	 *
	 * @param resource $handle Locked file handle.
	 * @return void
	 */
	private function release_update_lock( $handle ): void {
		flock( $handle, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the process-local flock handle.
		fclose( $handle );
	}

	/**
	 * Remove a staging directory only when this updater created it.
	 *
	 * @param string $staging_dir Absolute staging directory.
	 * @return void
	 */
	private function remove_owned_stage( string $staging_dir ): void {
		if ( ! isset( $this->owned_stages[ $staging_dir ] ) ) {
			return;
		}
		$this->remove_directory( $staging_dir );
		unset( $this->owned_stages[ $staging_dir ] );
	}

	/**
	 * Migrate each child directory from one historical public root.
	 *
	 * @param string $source_root      Historical public root.
	 * @param string $public_boundary  Public directory that must contain it.
	 * @param string $destination_root Private destination root.
	 * @return true|WP_Error
	 */
	private function migrate_legacy_root( string $source_root, string $public_boundary, string $destination_root ): bool|WP_Error {
		if ( ! is_dir( $source_root ) ) {
			return true;
		}

		if ( is_link( $source_root ) ) {
			return new WP_Error(
				'sd_ai_agent_legacy_storage_symlink',
				__( 'Legacy plugin backup storage cannot be migrated through a symbolic link.', 'superdav-ai-agent' )
			);
		}

		$source_real   = realpath( $source_root );
		$boundary_real = realpath( $public_boundary );
		if ( false === $source_real || false === $boundary_real || ! PathCanonicalizer::path_is_inside( $source_real, $boundary_real ) ) {
			return new WP_Error(
				'sd_ai_agent_legacy_storage_boundary',
				__( 'Legacy plugin backup storage is outside its expected boundary.', 'superdav-ai-agent' )
			);
		}

		$first_error = null;
		$entries     = new \FilesystemIterator( $source_real, \FilesystemIterator::SKIP_DOTS );
		foreach ( $entries as $entry ) {
			/** @var \SplFileInfo $entry */
			if ( $entry->isLink() || ! $entry->isDir() ) {
				$first_error ??= new WP_Error(
					'sd_ai_agent_legacy_storage_unexpected_entry',
					__( 'Legacy plugin backup storage contains an unsafe entry.', 'superdav-ai-agent' )
				);
				continue;
			}

			$source      = $entry->getPathname();
			$destination = $destination_root . $entry->getBasename();
			if ( is_dir( $destination ) ) {
				$matches = $this->directories_match( $source, $destination );
				if ( is_wp_error( $matches ) ) {
					$first_error ??= $matches;
					continue;
				}
				if ( $matches ) {
					$this->remove_directory( $source );
					continue;
				}
				$destination .= '-legacy-' . substr( hash( 'sha256', $source_real ), 0, 12 );
			}

			if ( is_dir( $destination ) ) {
				$matches = $this->directories_match( $source, $destination );
				if ( is_wp_error( $matches ) || ! $matches ) {
					$first_error ??= is_wp_error( $matches ) ? $matches : new WP_Error(
						'sd_ai_agent_legacy_storage_conflict',
						__( 'A conflicting private plugin backup already exists.', 'superdav-ai-agent' )
					);
					continue;
				}
				$this->remove_directory( $source );
				continue;
			}

			$copied = $this->copy_verified_directory( $source, $destination );
			if ( is_wp_error( $copied ) ) {
				$first_error ??= $copied;
				continue;
			}

			$this->remove_directory( $source );
		}

		$remaining = new \FilesystemIterator( $source_real, \FilesystemIterator::SKIP_DOTS );
		if ( ! $remaining->valid() ) {
			$this->remove_directory( $source_real );
		}

		return $first_error ?? true;
	}

	/**
	 * Copy into a temporary sibling, verify it, then publish it atomically.
	 *
	 * @param string $source      Source directory.
	 * @param string $destination Final private destination.
	 * @return true|WP_Error
	 */
	private function copy_verified_directory( string $source, string $destination ): bool|WP_Error {
		$temporary = $destination . '.migration-' . bin2hex( random_bytes( 8 ) );
		$copied    = $this->copy_directory( $source, $temporary );
		if ( is_wp_error( $copied ) ) {
			$this->remove_directory( $temporary );
			return $copied;
		}

		$verified = $this->directories_match( $source, $temporary );
		if ( is_wp_error( $verified ) || ! $verified ) {
			$this->remove_directory( $temporary );
			return is_wp_error( $verified ) ? $verified : new WP_Error(
				'sd_ai_agent_legacy_storage_verification_failed',
				__( 'A private plugin backup copy could not be verified.', 'superdav-ai-agent' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Same-filesystem rename atomically publishes a verified private copy.
		if ( ! rename( $temporary, $destination ) ) {
			$this->remove_directory( $temporary );
			return new WP_Error(
				'sd_ai_agent_legacy_storage_publish_failed',
				__( 'A verified private plugin backup could not be published.', 'superdav-ai-agent' )
			);
		}

		return true;
	}

	/**
	 * Publish a directory without copying into the live plugin tree.
	 *
	 * The complete candidate is built in an exclusively claimed sibling directory.
	 * The live directory is then renamed aside before the candidate is renamed into
	 * place. A leaf symlink inserted into the live tree while the candidate is built
	 * therefore moves with the old tree and is never used as a copy destination.
	 *
	 * @param string $source      Complete source directory.
	 * @param string $destination Live plugin directory.
	 * @return true|WP_Error
	 */
	private function publish_directory_atomically( string $source, string $destination ): bool|WP_Error {
		$destination  = untrailingslashit( $destination );
		$plugins_root = realpath( dirname( $destination ) );
		if ( false === $plugins_root || is_link( $destination ) || ! is_dir( $destination ) ) {
			return new WP_Error(
				'sd_ai_agent_publish_destination_invalid',
				__( 'The live plugin directory cannot be published safely.', 'superdav-ai-agent' )
			);
		}

		$destination_path = PathCanonicalizer::canonicalize_missing_path_inside( $destination, $plugins_root );
		if ( is_wp_error( $destination_path ) || untrailingslashit( $destination_path ) !== $destination ) {
			return is_wp_error( $destination_path ) ? $destination_path : new WP_Error(
				'sd_ai_agent_publish_destination_invalid',
				__( 'The live plugin directory cannot be published safely.', 'superdav-ai-agent' )
			);
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_lstat -- Device/inode identity detects replacement of the live directory during candidate preparation.
		$destination_stat = lstat( $destination );
		if ( false === $destination_stat ) {
			return new WP_Error(
				'sd_ai_agent_publish_destination_invalid',
				__( 'The live plugin directory cannot be published safely.', 'superdav-ai-agent' )
			);
		}

		$candidate = $this->claim_publish_sibling( $plugins_root, basename( $destination ) . '.candidate-' );
		if ( is_wp_error( $candidate ) ) {
			return $candidate;
		}

		$copied = $this->copy_directory( $source, $candidate );
		if ( is_wp_error( $copied ) ) {
			$this->remove_directory( $candidate );
			return $copied;
		}

		/**
		 * Fires after a complete candidate exists but before the live tree is renamed.
		 *
		 * @param string $destination Live plugin directory.
		 * @param string $candidate   Prepared sibling candidate directory.
		 */
		do_action( 'sd_ai_agent_plugin_update_candidate_ready', trailingslashit( $destination ), trailingslashit( $candidate ) );

		clearstatcache( true, $destination );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_lstat -- Re-check the exact live directory identity immediately before rename.
		$current_stat = lstat( $destination );
		if (
			false === $current_stat
			|| $current_stat['dev'] !== $destination_stat['dev']
			|| $current_stat['ino'] !== $destination_stat['ino']
		) {
			$this->remove_directory( $candidate );
			return new WP_Error(
				'sd_ai_agent_publish_destination_changed',
				__( 'The live plugin directory changed before the update could be published.', 'superdav-ai-agent' )
			);
		}

		$previous = $this->publish_sibling_path( $plugins_root, basename( $destination ) . '.previous-' );
		if ( is_wp_error( $previous ) ) {
			$this->remove_directory( $candidate );
			return $previous;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Same-filesystem rename moves the complete live tree aside without following leaf symlinks.
		if ( ! rename( $destination, $previous ) ) {
			$this->remove_directory( $candidate );
			return new WP_Error(
				'sd_ai_agent_publish_previous_failed',
				__( 'The current plugin directory could not be preserved for rollback.', 'superdav-ai-agent' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Same-filesystem rename atomically publishes the complete candidate tree.
		if ( ! rename( $candidate, $destination ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Restore the preserved live tree when candidate publication fails.
			$restored = rename( $previous, $destination );
			$this->remove_directory( $candidate );
			return new WP_Error(
				$restored ? 'sd_ai_agent_publish_candidate_failed' : 'sd_ai_agent_publish_restore_failed',
				$restored
					? __( 'The prepared plugin update could not be published; the previous plugin was restored.', 'superdav-ai-agent' )
					: __( 'The prepared plugin update could not be published or restored.', 'superdav-ai-agent' )
			);
		}

		$this->remove_directory( $previous );
		return true;
	}

	/**
	 * Atomically claim a private sibling directory used to build a candidate.
	 *
	 * @param string $plugins_root Validated plugins directory.
	 * @param string $prefix Sibling name prefix.
	 * @return string|WP_Error
	 */
	private function claim_publish_sibling( string $plugins_root, string $prefix ): string|WP_Error {
		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			$path = $this->publish_sibling_path( $plugins_root, $prefix );
			if ( is_wp_error( $path ) ) {
				return $path;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Exclusive creation prevents a pre-existing sibling from being reused.
			if ( mkdir( $path, 0700 ) ) {
				return $path;
			}
		}

		return new WP_Error(
			'sd_ai_agent_publish_identity_failed',
			__( 'Could not claim temporary storage for the plugin update.', 'superdav-ai-agent' )
		);
	}

	/**
	 * Generate a unique, validated path directly beneath the plugins directory.
	 *
	 * @param string $plugins_root Validated plugins directory.
	 * @param string $prefix Sibling name prefix.
	 * @return string|WP_Error
	 */
	private function publish_sibling_path( string $plugins_root, string $prefix ): string|WP_Error {
		try {
			$suffix = bin2hex( random_bytes( 12 ) );
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'sd_ai_agent_publish_identity_failed',
				__( 'Could not create a unique plugin update directory.', 'superdav-ai-agent' )
			);
		}

		return PathCanonicalizer::canonicalize_missing_path_inside(
			trailingslashit( $plugins_root ) . '.' . $prefix . $suffix,
			$plugins_root
		);
	}

	/**
	 * Compare two directories using a symlink-free content manifest.
	 *
	 * @param string $left  First directory.
	 * @param string $right Second directory.
	 * @return bool|WP_Error
	 */
	private function directories_match( string $left, string $right ): bool|WP_Error {
		$left_manifest = $this->directory_manifest( $left );
		if ( is_wp_error( $left_manifest ) ) {
			return $left_manifest;
		}

		$right_manifest = $this->directory_manifest( $right );
		if ( is_wp_error( $right_manifest ) ) {
			return $right_manifest;
		}

		return $left_manifest === $right_manifest;
	}

	/**
	 * Build a stable directory manifest and reject symbolic links.
	 *
	 * @param string $directory Directory to inspect.
	 * @return array<string,string>|WP_Error Relative paths mapped to type/hash data.
	 */
	private function directory_manifest( string $directory ): array|WP_Error {
		$root = realpath( $directory );
		if ( false === $root || is_link( $directory ) ) {
			return new WP_Error(
				'sd_ai_agent_path_resolve_failed',
				__( 'Cannot resolve plugin backup directory.', 'superdav-ai-agent' )
			);
		}

		$manifest = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isLink() ) {
				return new WP_Error(
					'sd_ai_agent_backup_symlink',
					__( 'Plugin backup directories cannot contain symbolic links.', 'superdav-ai-agent' )
				);
			}

			$path     = $item->getPathname();
			$relative = ltrim( substr( $path, strlen( $root ) ), '/\\' );
			if ( $item->isDir() ) {
				$manifest[ $relative . '/' ] = 'directory';
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Migration verification requires exact byte size.
			$size = filesize( $path );
			$hash = hash_file( 'sha256', $path );
			if ( false === $size || false === $hash ) {
				return new WP_Error(
					'sd_ai_agent_backup_hash_failed',
					__( 'Could not verify a plugin backup file.', 'superdav-ai-agent' )
				);
			}
			$manifest[ $relative ] = $size . ':' . $hash;
		}

		ksort( $manifest );
		return $manifest;
	}

	/**
	 * Check if a file path is a PHP file.
	 *
	 * @param string $path File path (relative or absolute).
	 * @return bool True if the file has a .php extension.
	 */
	private function is_php_file( string $path ): bool {
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
	private function lint_php( string $content ): array {
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
	 * Recursively copy a directory.
	 *
	 * @param string $source      Source directory (must exist).
	 * @param string $destination Destination directory (will be created).
	 * @return true|\WP_Error
	 */
	private function copy_directory( string $source, string $destination ): bool|\WP_Error {
		$source_real = realpath( $source );
		if ( false === $source_real ) {
			return new WP_Error(
				'sd_ai_agent_path_resolve_failed',
				__( 'Cannot resolve source directory.', 'superdav-ai-agent' )
			);
		}

		$destination = PathCanonicalizer::canonicalize_missing_path( $destination );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}

		if ( ! wp_mkdir_p( $destination ) ) {
			return new WP_Error(
				'sd_ai_agent_mkdir_failed',
				/* translators: %s: directory path */
				sprintf( __( 'Could not create directory: %s', 'superdav-ai-agent' ), $destination )
			);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_real, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isLink() ) {
				return new WP_Error(
					'sd_ai_agent_backup_symlink',
					__( 'Plugin backup directories cannot contain symbolic links.', 'superdav-ai-agent' )
				);
			}

			$real_path = $item->getRealPath();
			if ( false === $real_path ) {
				return new WP_Error(
					'sd_ai_agent_path_resolve_failed',
					__( 'Cannot resolve a plugin backup path.', 'superdav-ai-agent' )
				);
			}

			$dest_path = $destination . str_replace( $source_real, '', $real_path );

			if ( $item->isDir() ) {
				$dest_path = PathCanonicalizer::canonicalize_missing_path_inside( $dest_path, $destination );
				if ( is_wp_error( $dest_path ) ) {
					return $dest_path;
				}
				if ( ! wp_mkdir_p( $dest_path ) ) {
					return new WP_Error(
						'sd_ai_agent_mkdir_failed',
						__( 'Could not create a plugin backup directory.', 'superdav-ai-agent' )
					);
				}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Direct filesystem copy is required for plugin rollback data.
			} elseif ( ! copy( $real_path, $dest_path ) ) {
					return new WP_Error(
						'sd_ai_agent_copy_failed',
						__( 'Could not copy a plugin backup file.', 'superdav-ai-agent' )
					);
			}
		}

		return true;
	}

	/**
	 * Recursively remove a directory without traversing symbolic links.
	 *
	 * @param string $dir Absolute path to the directory to remove.
	 * @return void
	 */
	private function remove_directory( string $dir ): void {
		if ( is_link( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unlink the directory entry itself; never recurse through a symbolic link.
			unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $entry ) {
			/** @var \SplFileInfo $entry */
			$path = $entry->getPathname();
			if ( $entry->isLink() || ! $entry->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Owned cleanup must unlink entries directly without following symlinks.
				unlink( $path );
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Child-first cleanup removes only the owned real directory.
			rmdir( $path );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove the now-empty owned root.
		rmdir( $dir );
	}
}
