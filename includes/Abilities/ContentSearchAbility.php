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

class ContentSearchAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Search Content', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Search for literal text within files in wp-content. The needle argument is required and must contain the text to find; derive it from the user request before calling. Never call this ability with empty arguments.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'needle'       => [
					'type'        => 'string',
					'description' => 'Required literal text to search for (for example, a template name, CSS class, URL fragment, or visible label from the user request).',
				],
				'directory'    => [
					'type'        => 'string',
					'description' => 'Directory to search in (relative to wp-content), default is entire wp-content',
				],
				'file_pattern' => [
					'type'        => 'string',
					'description' => 'File extension filter (e.g., "*.php")',
				],
			],
			'required'   => [ 'needle' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'needle'  => [ 'type' => 'string' ],
				'matches' => [ 'type' => 'array' ],
				'count'   => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$needle       = $input['needle'] ?? '';
		$directory    = $input['directory'] ?? '';
		$file_pattern = $input['file_pattern'] ?? '*.php';

		if ( empty( $needle ) ) {
			return new WP_Error( 'sd_ai_agent_empty_needle', __( 'Search text cannot be empty.', 'superdav-ai-agent' ) );
		}

		$search_path = WordPressPaths::content_dir();
		if ( ! empty( $directory ) ) {
			// @phpstan-ignore-next-line
			$resolved = $this->resolve_path( $directory );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
			$search_path = $resolved;
		}

		$results = [];
		// @phpstan-ignore-next-line
		$this->search_content_recursive( $search_path, $needle, $file_pattern, $results );

		return [
			'needle'    => $needle,
			'directory' => $directory ?: 'wp-content',
			'matches'   => $results,
			'count'     => count( $results ),
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

	/**
	 * Recursively search file contents.
	 *
	 * @param string                     $dir     Directory to search.
	 * @param string                     $needle  Text to find.
	 * @param string                     $pattern File glob pattern.
	 * @param list<array<string, mixed>> $results Results accumulator (passed by reference).
	 * @param int                        $limit   Maximum results.
	 */
	private function search_content_recursive( string $dir, string $needle, string $pattern, array &$results, int $limit = 50 ): void {
		if ( count( $results ) >= $limit || ! is_dir( $dir ) ) {
			return;
		}

		$files           = glob( $dir . '/' . $pattern );
		$wp_content_root = trailingslashit( WordPressPaths::content_dir() );
		if ( false !== $files ) {
			foreach ( $files as $file ) {
				if ( count( $results ) >= $limit ) {
					return;
				}

				if ( ! is_file( $file ) ) {
					continue;
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
				$content = file_get_contents( $file );
				if ( false === $content || stripos( $content, $needle ) === false ) {
					continue;
				}

				$lines          = explode( "\n", $content );
				$matching_lines = [];
				foreach ( $lines as $line_num => $line ) {
					if ( stripos( $line, $needle ) !== false ) {
						$matching_lines[] = [
							'line'    => $line_num + 1,
							'content' => trim( substr( $line, 0, 200 ) ),
						];
					}
				}

				$results[] = [
					'path'    => str_replace( $wp_content_root, '', $file ),
					'matches' => array_slice( $matching_lines, 0, 5 ),
				];
			}
		}

		// Search subdirectories.
		$subdirs = glob( $dir . '/*', GLOB_ONLYDIR );
		if ( false !== $subdirs ) {
			foreach ( $subdirs as $subdir ) {
				if ( count( $results ) >= $limit ) {
					return;
				}
				$basename = basename( $subdir );
				if ( 'vendor' === $basename || 'node_modules' === $basename ) {
					continue;
				}
				$this->search_content_recursive( $subdir, $needle, $pattern, $results, $limit );
			}
		}
	}
}
