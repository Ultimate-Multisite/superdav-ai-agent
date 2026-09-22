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

class FileOutlineAbility extends AbstractFileAbility {

	private const MAX_OUTLINE_ITEMS = 200;

	protected function label(): string {
		return __( 'File Outline', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Return a bounded outline of landmarks in a file within wp-content before reading line ranges.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content (e.g., "themes/my-theme/functions.php")',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'        => [ 'type' => 'string' ],
				'type'        => [ 'type' => 'string' ],
				'total_lines' => [ 'type' => 'integer' ],
				'outline'     => [ 'type' => 'array' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$path = $input['path'] ?? '';
		// @phpstan-ignore-next-line
		$full_path = $this->resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) || ! is_file( $full_path ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		if ( ! is_readable( $full_path ) ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_not_readable', sprintf( 'File not readable: %s', $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file, not remote URL.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			// @phpstan-ignore-next-line
			return new WP_Error( 'sd_ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
		}

		$lines = $this->split_file_lines( $content );
		$type  = $this->detect_type( $path );

		return [
			'path'        => $path,
			'type'        => $type,
			'total_lines' => count( $lines ),
			'outline'     => $this->build_outline( $lines, $type ),
		];
	}

	/**
	 * Detect a coarse file type for outline generation.
	 *
	 * @param string $path Relative file path.
	 * @return string
	 */
	private function detect_type( string $path ): string {
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		return match ( $extension ) {
			'php' => 'php',
			'css', 'scss', 'sass' => 'css',
			'js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs' => 'js',
			'html', 'htm' => 'html',
			default => 'text',
		};
	}

	/**
	 * Build deterministic landmarks for the supported source types.
	 *
	 * @param array<int,string> $lines File lines.
	 * @param string            $type  Detected type.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_outline( array $lines, string $type ): array {
		$outline = [];
		foreach ( $lines as $index => $line ) {
			$line_number = $index + 1;
			$trimmed     = trim( $line );

			if ( '' === $trimmed ) {
				continue;
			}

			if ( 'php' === $type ) {
				$this->collect_php_landmarks( $outline, $trimmed, $line_number );
			} elseif ( 'css' === $type ) {
				$this->collect_css_landmarks( $outline, $trimmed, $line_number );
			} elseif ( 'js' === $type ) {
				$this->collect_js_landmarks( $outline, $trimmed, $line_number );
			} elseif ( 'html' === $type ) {
				$this->collect_html_landmarks( $outline, $trimmed, $line_number );
			}

			if ( count( $outline ) >= self::MAX_OUTLINE_ITEMS ) {
				break;
			}
		}

		return $outline;
	}

	/**
	 * Collect PHP and PHP-template landmarks from one line.
	 *
	 * @param array<int,array<string,mixed>> $outline     Outline accumulator.
	 * @param string                         $line        Trimmed line.
	 * @param int                            $line_number 1-based line number.
	 */
	private function collect_php_landmarks( array &$outline, string $line, int $line_number ): void {
		if ( preg_match( '/^(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'php_symbol', $match[1] );
		}

		if ( preg_match( '/^(?:public|protected|private|static|final|abstract|\s)*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'php_function', $match[1] );
		}

		if ( preg_match( '/\badd_(action|filter)\s*\(\s*[\'\"]([^\'\"]+)/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'wp_hook', $match[1] . ':' . $match[2] );
		}

		if ( preg_match( '/\b(get_template_part|get_header|get_footer|get_sidebar)\s*\(([^)]*)\)/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'template_part', $match[1] . '(' . trim( $match[2] ) . ')' );
		}
	}

	/**
	 * Collect CSS landmarks from one line.
	 *
	 * @param array<int,array<string,mixed>> $outline     Outline accumulator.
	 * @param string                         $line        Trimmed line.
	 * @param int                            $line_number 1-based line number.
	 */
	private function collect_css_landmarks( array &$outline, string $line, int $line_number ): void {
		if ( preg_match( '/^@(media|supports|container|keyframes)\b\s*([^{}]*)/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'css_at_rule', '@' . $match[1] . ' ' . trim( $match[2] ) );
			return;
		}

		if ( str_contains( $line, '{' ) && preg_match( '/^([^{}@][^{]+)\{/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'css_selector', trim( $match[1] ) );
		}
	}

	/**
	 * Collect JavaScript landmarks from one line.
	 *
	 * @param array<int,array<string,mixed>> $outline     Outline accumulator.
	 * @param string                         $line        Trimmed line.
	 * @param int                            $line_number 1-based line number.
	 */
	private function collect_js_landmarks( array &$outline, string $line, int $line_number ): void {
		if ( preg_match( '/^(?:export\s+default\s+|export\s+)?class\s+([A-Za-z_$][A-Za-z0-9_$]*)\b/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'js_class', $match[1] );
		}

		if ( preg_match( '/^(?:export\s+)?(?:async\s+)?function\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'js_function', $match[1] );
		}

		if ( preg_match( '/^(?:const|let|var)\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*(?:async\s*)?\(?[^=]*\)?\s*=>/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'js_function', $match[1] );
		}

		if ( preg_match( '/\.addEventListener\s*\(\s*[\'\"]([^\'\"]+)/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'js_event_listener', $match[1] );
		}
	}

	/**
	 * Collect HTML landmarks from one line.
	 *
	 * @param array<int,array<string,mixed>> $outline     Outline accumulator.
	 * @param string                         $line        Trimmed line.
	 * @param int                            $line_number 1-based line number.
	 */
	private function collect_html_landmarks( array &$outline, string $line, int $line_number ): void {
		if ( preg_match_all( '/<\s*(main|header|footer|nav|section|article|aside|form|h[1-6])\b([^>]*)>/i', $line, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( $match[1] );
				if ( preg_match( '/\b(?:id|class)\s*=\s*[\'\"]([^\'\"]+)/', $match[2], $attribute_match ) ) {
					$name .= '#' . $attribute_match[1];
				}
				$this->add_outline_entry( $outline, $line_number, 'html_landmark', $name );
			}
		}

		if ( preg_match( '/<!--\s+wp:(template-part|pattern)\s+({.*?})?\s+-->/', $line, $match ) ) {
			$this->add_outline_entry( $outline, $line_number, 'block_' . $match[1], isset( $match[2] ) ? $match[2] : $match[1] );
		}
	}

	/**
	 * Add one outline entry when within the output bound.
	 *
	 * @param array<int,array<string,mixed>> $outline Outline accumulator.
	 * @param int                            $line    1-based line number.
	 * @param string                         $type    Landmark type.
	 * @param string                         $name    Landmark name.
	 */
	private function add_outline_entry( array &$outline, int $line, string $type, string $name ): void {
		if ( count( $outline ) >= self::MAX_OUTLINE_ITEMS ) {
			return;
		}

		$outline[] = [
			'line' => $line,
			'type' => $type,
			'name' => mb_substr( $name, 0, 160 ),
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
