<?php

declare(strict_types=1);
/**
 * File operation abilities for the AI agent.
 *
 * Provides read, write, edit, delete, list, and search operations
 * scoped to the wp-content directory with path traversal protection.
 *
 * Modelled after akirk/ai-assistant's file tools with WordPress Abilities API integration.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\AgentEventLog;
use SdAiAgent\Core\Filesystem\PathCanonicalizer;
use SdAiAgent\Core\Settings;
use SdAiAgent\Core\WordPressPaths;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FileAbilities {

	/**
	 * Return a consistent disabled response when an advanced-only file ability is unavailable.
	 *
	 * @param string $ability_name Ability name.
	 * @return WP_Error
	 */
	private static function advanced_plugin_required( string $ability_name ): WP_Error {
		return new WP_Error(
			'sd_ai_agent_advanced_plugin_required',
			sprintf(
				/* translators: %s: ability name */
				__( 'The %s ability is provided by the free SD AI Agent Advanced plugin. Open the SD AI account settings, then choose Install and activate.', 'superdav-ai-agent' ),
				$ability_name
			)
		);
	}

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Read a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_read_file( array $input = [] ) {
		$ability = new FileReadAbility(
			'sd-ai-agent/file-read',
			[
				'label'       => __( 'Read File', 'superdav-ai-agent' ),
				'description' => __( 'Read the contents of a file within the wp-content directory, optionally limited to a line range.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Return a token-efficient file outline.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_outline_file( array $input = [] ) {
		$ability = new FileOutlineAbility(
			'sd-ai-agent/file-outline',
			[
				'label'       => __( 'File Outline', 'superdav-ai-agent' ),
				'description' => __( 'Return a bounded outline of landmarks in a file within wp-content before reading line ranges.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Write a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_write_file( array $input = [] ) {
		if ( ! class_exists( FileWriteAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/file-write' );
		}

		$ability = new FileWriteAbility(
			'sd-ai-agent/file-write',
			[
				'label'       => __( 'Write File', 'superdav-ai-agent' ),
				'description' => __( 'Write or overwrite a file within wp-content. Use for creating NEW files. For modifying existing files, use sd-ai-agent/file-edit instead.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Edit a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_edit_file( array $input = [] ) {
		if ( ! class_exists( FileEditAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/file-edit' );
		}

		$ability = new FileEditAbility(
			'sd-ai-agent/file-edit',
			[
				'label'       => __( 'Edit File', 'superdav-ai-agent' ),
				'description' => __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Delete a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_delete_file( array $input = [] ) {
		if ( ! class_exists( FileDeleteAbility::class ) ) {
			return self::advanced_plugin_required( 'sd-ai-agent/file-delete' );
		}

		$ability = new FileDeleteAbility(
			'sd-ai-agent/file-delete',
			[
				'label'       => __( 'Delete File', 'superdav-ai-agent' ),
				'description' => __( 'Delete a file within the wp-content directory.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * List a directory.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_list_directory( array $input = [] ) {
		$ability = new FileListAbility(
			'sd-ai-agent/file-list',
			[
				'label'       => __( 'List Directory', 'superdav-ai-agent' ),
				'description' => __( 'List files and directories within a directory in wp-content.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Search for files matching a glob pattern.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_search_files( array $input = [] ) {
		$ability = new FileSearchAbility(
			'sd-ai-agent/file-search',
			[
				'label'       => __( 'Search Files', 'superdav-ai-agent' ),
				'description' => __( 'Search for files matching a glob pattern within wp-content.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Search for text content within files.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_search_content( array $input = [] ) {
		$ability = new ContentSearchAbility(
			'sd-ai-agent/content-search',
			[
				'label'       => __( 'Search Content', 'superdav-ai-agent' ),
				'description' => __( 'Search for text content within files in wp-content.', 'superdav-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register all file operation abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/file-read',
			[
				'label'         => __( 'Read File', 'superdav-ai-agent' ),
				'description'   => __( 'Read the contents of a file within the wp-content directory, optionally limited to a line range.', 'superdav-ai-agent' ),
				'ability_class' => FileReadAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/file-outline',
			[
				'label'         => __( 'File Outline', 'superdav-ai-agent' ),
				'description'   => __( 'Return a bounded outline of landmarks in a file within wp-content before reading line ranges.', 'superdav-ai-agent' ),
				'ability_class' => FileOutlineAbility::class,
			]
		);

		// Mutating filesystem abilities (file-write, file-edit, file-delete)
		// are registered by Superdav AI Agent Advanced when that companion
		// plugin is installed and active. Core keeps only read/list/search
		// file tools for WordPress.org distribution.

		wp_register_ability(
			'sd-ai-agent/file-list',
			[
				'label'         => __( 'List Directory', 'superdav-ai-agent' ),
				'description'   => __( 'List files and directories within a directory in wp-content.', 'superdav-ai-agent' ),
				'ability_class' => FileListAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/file-search',
			[
				'label'         => __( 'Search Files', 'superdav-ai-agent' ),
				'description'   => __( 'Search for files matching a glob pattern within wp-content.', 'superdav-ai-agent' ),
				'ability_class' => FileSearchAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/content-search',
			[
				'label'         => __( 'Search Content', 'superdav-ai-agent' ),
				'description'   => __( 'Search for text content within files in wp-content.', 'superdav-ai-agent' ),
				'ability_class' => ContentSearchAbility::class,
			]
		);
	}
}
