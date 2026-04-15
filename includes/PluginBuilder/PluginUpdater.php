<?php

declare(strict_types=1);
/**
 * Plugin Updater — sandboxed live updates for AI-generated plugins.
 *
 * Flow: backup current files → stage new files → run layers 1+2 → swap on
 * success, restore backup on failure.
 *
 * @package GratisAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\PluginBuilder;

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
	 * Update an installed AI-generated plugin with new file content.
	 *
	 * Steps:
	 *   1. Backup existing plugin directory.
	 *   2. Stage new files to a temp directory.
	 *   3. Run layers 1 + 2 of PluginSandbox against the staged files.
	 *   4. If tests pass: swap staged directory in place of original.
	 *   5. If tests fail: restore backup and return WP_Error.
	 *
	 * @param string               $slug        Plugin slug (directory name under wp-content/plugins/).
	 * @param array<string,string> $new_files   Map of relative path → PHP source.
	 * @param string               $plugin_file Main plugin file relative to plugins dir.
	 * @return array{updated: bool, plugin_file: string, backup_dir: string}|\WP_Error
	 */
	public static function update(
		string $slug,
		array $new_files,
		string $plugin_file
	): array|\WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'gratis-ai-agent' )
			);
		}

		$plugins_dir = WP_CONTENT_DIR . '/plugins/';
		$plugin_dir  = $plugins_dir . $slug . '/';

		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_plugin_not_found',
				/* translators: %s: plugin directory */
				sprintf( __( 'Plugin directory not found: %s', 'gratis-ai-agent' ), $plugin_dir )
			);
		}

		// Step 1: Backup existing plugin directory.
		$backup_dir = $plugins_dir . $slug . '-backup-' . time() . '/';
		$copied     = self::copy_directory( $plugin_dir, $backup_dir );
		if ( is_wp_error( $copied ) ) {
			return $copied;
		}

		// Step 2: Stage new files to temp directory.
		$stage_dir = $plugins_dir . $slug . '-staging-' . time() . '/';
		if ( ! wp_mkdir_p( $stage_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_staging_failed',
				/* translators: %s: directory */
				sprintf( __( 'Could not create staging directory: %s', 'gratis-ai-agent' ), $stage_dir )
			);
		}

		foreach ( $new_files as $relative_path => $content ) {
			$relative_path = ltrim( $relative_path, '/\\' );
			$abs_path      = $stage_dir . $relative_path;
			wp_mkdir_p( dirname( $abs_path ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to staging temp dir; WP_Filesystem not available at this stage.
			if ( false === file_put_contents( $abs_path, $content ) ) {
				self::remove_directory( $stage_dir );
				return new WP_Error(
					'gratis_ai_agent_staging_write_failed',
					/* translators: %s: file path */
					sprintf( __( 'Could not write staging file: %s', 'gratis-ai-agent' ), $relative_path )
				);
			}
		}

		// Step 3: Run sandbox layers 1+2 on staged files.
		$stage_plugin_file = ltrim( str_replace( $slug . '/', '', $plugin_file ), '/' );
		$sandbox_result    = PluginSandbox::run_all( $stage_dir, $stage_plugin_file );

		if ( is_wp_error( $sandbox_result ) ) {
			self::remove_directory( $stage_dir );
			return $sandbox_result;
		}

		if ( ! $sandbox_result['passed'] ) {
			self::remove_directory( $stage_dir );
			return new WP_Error(
				'gratis_ai_agent_sandbox_failed',
				implode( '; ', $sandbox_result['errors'] )
			);
		}

		// Step 4: Swap staged dir into original location.
		self::remove_directory( $plugin_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic directory swap; WP_Filesystem::move() does not support directory rename.
		$renamed = rename( $stage_dir, $plugin_dir );
		if ( ! $renamed ) {
			// Restore backup.
			self::copy_directory( $backup_dir, $plugin_dir );
			self::remove_directory( $backup_dir );
			return new WP_Error(
				'gratis_ai_agent_swap_failed',
				__( 'Could not replace plugin directory. Backup restored.', 'gratis-ai-agent' )
			);
		}

		return [
			'updated'     => true,
			'plugin_file' => $plugin_file,
			'backup_dir'  => $backup_dir,
		];
	}

	/**
	 * Copy a directory recursively.
	 *
	 * @param string $source      Source directory.
	 * @param string $destination Destination directory.
	 * @return true|\WP_Error
	 */
	private static function copy_directory( string $source, string $destination ): bool|\WP_Error {
		if ( ! wp_mkdir_p( $destination ) ) {
			return new WP_Error(
				'gratis_ai_agent_mkdir_failed',
				/* translators: %s: directory */
				sprintf( __( 'Could not create directory: %s', 'gratis-ai-agent' ), $destination )
			);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$dest_path = $destination . str_replace( $source, '', $item->getRealPath() );
			if ( $item->isDir() ) {
				wp_mkdir_p( $dest_path );
			} else {
				copy( $item->getRealPath(), $dest_path );
			}
		}

		return true;
	}

	/**
	 * Remove a directory and its contents recursively.
	 *
	 * @param string $dir Directory to remove.
	 * @return void
	 */
	private static function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( [] );
		$fs->rmdir( $dir, true );
	}
}
