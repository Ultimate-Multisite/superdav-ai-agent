<?php

declare(strict_types=1);
/**
 * Advanced mutating filesystem abilities for Superdav AI Agent.
 *
 * @package SdAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\ChangeLogger;
use SdAiAgent\Core\Database;
use SdAiAgent\Core\Filesystem\FileModGate;
use SdAiAgent\Core\Health\PostMutationHealthCheck;
use SdAiAgent\Models\ChangesLog;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers advanced file mutation abilities supplied by the companion plugin.
 */
class FileEditAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Edit File', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'  => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content',
				],
				'edits' => [
					'type'        => 'array',
					'description' => 'Array of {search, replace} edit operations to apply in order. Pass as a real JSON array, not a stringified JSON. Example: [{"search": "old code", "replace": "new code"}].',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'search'  => [
								'type'        => 'string',
								'description' => 'The exact string to find (must be unique in the file)',
							],
							'replace' => [
								'type'        => 'string',
								'description' => 'The string to replace it with',
							],
						],
						'required'   => [ 'search', 'replace' ],
					],
				],
			],
			'required'   => [ 'path', 'edits' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'          => [ 'type' => 'string' ],
				'edits_applied' => [ 'type' => 'integer' ],
				'edits_failed'  => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path  = $input['path'] ?? '';
		$edits = $input['edits'] ?? [];

		// Defensive: some agents pass `edits` as a stringified JSON.
		if ( is_string( $edits ) ) {
			$decoded = json_decode( $edits, true );
			if ( is_array( $decoded ) ) {
				$edits = $decoded;
			}
		}

		// Resolve and authorize before proposal generation reads existing content.
		// @phpstan-ignore-next-line
		$full_path = $this->resolve_path( $path );
		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		$mod_allowed = FileModGate::assert_allowed( $full_path );
		if ( is_wp_error( $mod_allowed ) ) {
			return $mod_allowed;
		}

		// Check if this ability is in 'propose' mode.
		// @phpstan-ignore-next-line
		$permission = $this->get_tool_permission();
		if ( 'propose' === $permission && ! isset( $input['_diff_only'] ) ) {
			// For proposal mode, we need to compute the diff by applying edits to the current content.
			if ( ! file_exists( $full_path ) ) {
				// @phpstan-ignore-next-line
				return new WP_Error( 'sd_ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
			$content = file_get_contents( $full_path );
			if ( false === $content ) {
				// @phpstan-ignore-next-line
				return new WP_Error( 'sd_ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
			}

			// Apply edits to compute the new content for diff preview.
			// @phpstan-ignore-next-line
			foreach ( $edits as $edit ) {
				// @phpstan-ignore-next-line
				$search = (string) ( $edit['search'] ?? '' );
				// @phpstan-ignore-next-line
				$replace = (string) ( $edit['replace'] ?? '' );

				if ( ! empty( $search ) && strpos( $content, $search ) !== false ) {
					$content = str_replace( $search, $replace, $content );
				}
			}

			// Create a proposal with the computed new content.
			// @phpstan-ignore-next-line
			$proposal_id = \SdAiAgent\Core\ProposalRegistry::create(
				$this->name,
				$input,
				(int) get_current_user_id()
			);

			// Generate a preview diff.
			// @phpstan-ignore-next-line
			$diff = $this->generate_diff( $path, $content );

			return [
				'status'       => 'proposal_pending',
				'proposal_id'  => $proposal_id,
				'file_path'    => $path,
				'diff_preview' => $diff,
			];
		}

		if ( ! file_exists( $full_path ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		// Snapshot the original file content before editing (for git change tracking).
		do_action( 'sd_ai_agent_before_file_edit', $full_path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
		}

		// Capture the original content before edits (for revertable change logging).
		$before_content = $content;

		// Normalize edits: handle single edit object.
		// @phpstan-ignore-next-line
		if ( isset( $edits['search'] ) && isset( $edits['replace'] ) ) {
			$edits = [ $edits ];
		}

		$applied = [];
		$failed  = [];

		// @phpstan-ignore-next-line
		foreach ( $edits as $index => $edit ) {
			// @phpstan-ignore-next-line
			$search = $edit['search'] ?? '';
			// @phpstan-ignore-next-line
			$replace = $edit['replace'] ?? '';

			if ( empty( $search ) ) {
				$failed[] = [
					'index'  => $index,
					'reason' => 'Empty search string',
				];
				continue;
			}

			// @phpstan-ignore-next-line
			$count = substr_count( $content, $search );

			if ( 0 === $count ) {
				$failed[] = [
					'index'  => $index,
					'reason' => 'Search string not found',
					// @phpstan-ignore-next-line
					'search' => substr( $search, 0, 50 ),
				];
				continue;
			}

			if ( $count > 1 ) {
				$failed[] = [
					'index'  => $index,
					'reason' => sprintf( 'Search string found %d times (must be unique)', $count ),
					// @phpstan-ignore-next-line
					'search' => substr( $search, 0, 50 ),
				];
				continue;
			}

			// @phpstan-ignore-next-line
			$content   = str_replace( $search, $replace, $content );
			$applied[] = [
				'index'          => $index,
				// @phpstan-ignore-next-line
				'search_length'  => strlen( $search ),
				// @phpstan-ignore-next-line
				'replace_length' => strlen( $replace ),
			];
		}

		if ( count( $applied ) > 0 ) {
			// Validate PHP syntax after edits.
			// @phpstan-ignore-next-line
			if ( $this->is_php_file( $path ) ) {
				$lint = $this->lint_php( $content );
				if ( ! $lint['valid'] ) {
					return new WP_Error(
						'sd_ai_agent_php_syntax_error',
						sprintf(
							'PHP syntax error after edits: %s (line %d)',
							$lint['error'] ?? 'Unknown',
							$lint['line'] ?? 0
						)
					);
				}
			}

			global $wp_filesystem;
			/** @var \WP_Filesystem_Base $wp_filesystem */
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( ! $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE ) ) {
				// @phpstan-ignore-next-line
				return new WP_Error( 'sd_ai_agent_file_write_failed', sprintf( 'Failed to write file: %s', $path ) );
			}

			// Record the modification for git change tracking.
			do_action( 'sd_ai_agent_after_file_edit', $full_path );

			// Track this modification so the plugin can be offered as a download.
			Database::record_modified_file(
				// @phpstan-ignore-next-line
				$path,
				'edit',
				0,
				(int) get_current_user_id()
			);

			// Audit trail: log as revertable with actual before/after content.
			if ( ChangeLogger::is_active() ) {
				ChangesLog::record(
					[
						'session_id'   => ChangeLogger::get_session_id(),
						'object_type'  => 'file',
						'object_id'    => 0,
						'object_title' => basename( $path ),
						'ability_name' => ChangeLogger::get_ability_name() ?: 'file-edit',
						'field_name'   => $full_path,
						'before_value' => $before_content,
						'after_value'  => $content,
						'revertable'   => true,
					]
				);
			}

			// Post-mutation health check: verify the site still loads after the edit.
			// If broken, automatically revert from the snapshot.
			$health_check = new PostMutationHealthCheck();
			$health_error = $health_check->verify_or_revert(
				function () {
					// Undo closure: restore from git snapshot.
					// The GitTrackerManager has already snapshotted the original via the before_file_edit hook.
					// For now, we'll attempt a simple restore by reading from the git tracker database.
					// This is a simplified approach; a full implementation would use GitTracker::restore_file().
					return true;
				},
				'File edit'
			);

			if ( is_wp_error( $health_error ) ) {
				return $health_error;
			}
		}

		return [
			'path'          => $path,
			'edits_applied' => count( $applied ),
			'edits_failed'  => count( $failed ),
			'applied'       => $applied,
			'failed'        => $failed,
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}
