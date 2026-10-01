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

class FileSearchAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Search Files', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Search for files matching a glob pattern within wp-content.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'pattern' => [
					'type'        => 'string',
					'description' => 'Glob pattern (e.g., "plugins/*/*.php" or "themes/**/*.css")',
				],
			],
			'required'   => [ 'pattern' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'pattern' => [ 'type' => 'string' ],
				'matches' => [ 'type' => 'array' ],
				'count'   => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$pattern         = $input['pattern'] ?? '';
		$wp_content_path = WordPressPaths::content_dir();
		$wp_content_root = trailingslashit( $wp_content_path );
		// @phpstan-ignore-next-line
		$full_pattern = $wp_content_root . ltrim( $pattern, '/' );

		$files   = glob( $full_pattern );
		$results = [];

		if ( false !== $files ) {
			foreach ( $files as $file ) {
				$relative  = str_replace( $wp_content_root, '', $file );
				$results[] = [
					'path' => $relative,
					'type' => is_dir( $file ) ? 'directory' : 'file',
					'size' => is_file( $file ) ? filesize( $file ) : null,
				];
			}
		}

		return [
			'pattern' => $pattern,
			'matches' => $results,
			'count'   => count( $results ),
		];
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
