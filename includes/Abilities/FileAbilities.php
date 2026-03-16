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
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FileAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Read a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_read_file( array $input = [] ) {
		$ability = new FileReadAbility(
			'gratis-ai-agent/file-read',
			[
				'label'       => __( 'Read File', 'gratis-ai-agent' ),
				'description' => __( 'Read the contents of a file within the wp-content directory.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Write a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_write_file( array $input = [] ) {
		$ability = new FileWriteAbility(
			'gratis-ai-agent/file-write',
			[
				'label'       => __( 'Write File', 'gratis-ai-agent' ),
				'description' => __( 'Write or overwrite a file within wp-content. Use for creating NEW files. For modifying existing files, use ai-agent/file-edit instead.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Edit a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_edit_file( array $input = [] ) {
		$ability = new FileEditAbility(
			'gratis-ai-agent/file-edit',
			[
				'label'       => __( 'Edit File', 'gratis-ai-agent' ),
				'description' => __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Delete a file.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_delete_file( array $input = [] ) {
		$ability = new FileDeleteAbility(
			'gratis-ai-agent/file-delete',
			[
				'label'       => __( 'Delete File', 'gratis-ai-agent' ),
				'description' => __( 'Delete a file within the wp-content directory.', 'gratis-ai-agent' ),
			]
		);
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
			'gratis-ai-agent/file-list',
			[
				'label'       => __( 'List Directory', 'gratis-ai-agent' ),
				'description' => __( 'List files and directories within a directory in wp-content.', 'gratis-ai-agent' ),
			]
		);
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
			'gratis-ai-agent/file-search',
			[
				'label'       => __( 'Search Files', 'gratis-ai-agent' ),
				'description' => __( 'Search for files matching a glob pattern within wp-content.', 'gratis-ai-agent' ),
			]
		);
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
			'gratis-ai-agent/content-search',
			[
				'label'       => __( 'Search Content', 'gratis-ai-agent' ),
				'description' => __( 'Search for text content within files in wp-content.', 'gratis-ai-agent' ),
			]
		);
		return $ability->run( $input );
	}

	/**
	 * Register file abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all file operation abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/file-read',
			[
				'label'         => __( 'Read File', 'gratis-ai-agent' ),
				'description'   => __( 'Read the contents of a file within the wp-content directory.', 'gratis-ai-agent' ),
				'ability_class' => FileReadAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/file-write',
			[
				'label'         => __( 'Write File', 'gratis-ai-agent' ),
				'description'   => __( 'Write or overwrite a file within wp-content. Use for creating NEW files. For modifying existing files, use ai-agent/file-edit instead.', 'gratis-ai-agent' ),
				'ability_class' => FileWriteAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/file-edit',
			[
				'label'         => __( 'Edit File', 'gratis-ai-agent' ),
				'description'   => __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'gratis-ai-agent' ),
				'ability_class' => FileEditAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/file-delete',
			[
				'label'         => __( 'Delete File', 'gratis-ai-agent' ),
				'description'   => __( 'Delete a file within the wp-content directory.', 'gratis-ai-agent' ),
				'ability_class' => FileDeleteAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/file-list',
			[
				'label'         => __( 'List Directory', 'gratis-ai-agent' ),
				'description'   => __( 'List files and directories within a directory in wp-content.', 'gratis-ai-agent' ),
				'ability_class' => FileListAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/file-search',
			[
				'label'         => __( 'Search Files', 'gratis-ai-agent' ),
				'description'   => __( 'Search for files matching a glob pattern within wp-content.', 'gratis-ai-agent' ),
				'ability_class' => FileSearchAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/content-search',
			[
				'label'         => __( 'Search Content', 'gratis-ai-agent' ),
				'description'   => __( 'Search for text content within files in wp-content.', 'gratis-ai-agent' ),
				'ability_class' => ContentSearchAbility::class,
			]
		);
	}
}

/**
 * Shared file path resolution and PHP linting helpers.
 *
 * @since 1.0.0
 */
abstract class AbstractFileAbility extends AbstractAbility {

	/**
	 * Validate and resolve a path within wp-content.
	 *
	 * @param string $relative_path Path relative to wp-content.
	 * @return string|WP_Error Full path on success, WP_Error on failure.
	 */
	protected function resolve_path( string $relative_path ) {
		$relative_path = ltrim( $relative_path, '/\\' );

		if ( empty( $relative_path ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_path', __( 'Path cannot be empty.', 'gratis-ai-agent' ) );
		}

		$wp_content_path = WP_CONTENT_DIR;
		$full_path       = $wp_content_path . '/' . $relative_path;

		// Resolve real path for security check.
		$real_path = realpath( dirname( $full_path ) );
		if ( false === $real_path ) {
			// Directory doesn't exist yet, check parent chain.
			$parent = dirname( $full_path );
			while ( ! file_exists( $parent ) && $parent !== dirname( $parent ) ) {
				$parent = dirname( $parent );
			}
			$real_path = realpath( $parent );
		}

		$wp_content_real = realpath( $wp_content_path );

		if ( false === $real_path || false === $wp_content_real ) {
			return new WP_Error(
				'gratis_ai_agent_path_resolve_failed',
				__( 'Cannot resolve path.', 'gratis-ai-agent' )
			);
		}

		if ( strpos( $real_path, $wp_content_real ) !== 0 ) {
			return new WP_Error(
				'gratis_ai_agent_path_traversal',
				__( 'Access denied: path is outside wp-content directory.', 'gratis-ai-agent' )
			);
		}

		return $full_path;
	}

	/**
	 * Check if a path is a PHP file.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	protected function is_php_file( string $path ): bool {
		return (bool) preg_match( '/\.php$/i', $path );
	}

	/**
	 * Lint PHP content for syntax errors.
	 *
	 * @param string $content PHP source code.
	 * @return array{valid: bool, error?: string, line?: int}
	 */
	// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions -- Intentional: error_reporting and set_error_handler used for PHP syntax validation.
	protected function lint_php( string $content ): array {
		$previous = error_reporting( 0 );

		set_error_handler(
			function ( $severity, $message, $file, $line ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- ErrorException constructor arguments are not output; PHPCS false positive.
				throw new \ErrorException( $message, 0, $severity, $file, $line );
			}
		);

		try {
			$tokens = token_get_all( $content, TOKEN_PARSE );
			unset( $tokens ); // Result unused — we only care about parse errors.
			restore_error_handler();
			error_reporting( $previous );
			return [ 'valid' => true ];
		} catch ( \ParseError $e ) {
			restore_error_handler();
			error_reporting( $previous );
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		} catch ( \ErrorException $e ) {
			restore_error_handler();
			error_reporting( $previous );
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		} catch ( \Throwable $e ) {
			restore_error_handler();
			error_reporting( $previous );
			return [
				'valid' => false,
				'error' => $e->getMessage(),
				'line'  => $e->getLine(),
			];
		}
	}
	// phpcs:enable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions
}

/**
 * File Read ability.
 *
 * @since 1.0.0
 */
class FileReadAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Read File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Read the contents of a file within the wp-content directory.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content (e.g., "plugins/my-plugin/file.php")',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'     => [ 'type' => 'string' ],
				'content'  => [ 'type' => 'string' ],
				'size'     => [ 'type' => 'integer' ],
				'modified' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$path      = $input['path'] ?? '';
		$full_path = $this->resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'gratis_ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		if ( ! is_readable( $full_path ) ) {
			return new WP_Error( 'gratis_ai_agent_file_not_readable', sprintf( 'File not readable: %s', $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file, not remote URL.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			return new WP_Error( 'gratis_ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
		}

		return [
			'path'     => $path,
			'content'  => $content,
			'size'     => filesize( $full_path ),
			'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $full_path ) ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * File Write ability.
 *
 * @since 1.0.0
 */
class FileWriteAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Write File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Write or overwrite a file within wp-content. Use for creating NEW files. For modifying existing files, use ai-agent/file-edit instead.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'    => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content (e.g., "plugins/my-plugin/file.php")',
				],
				'content' => [
					'type'        => 'string',
					'description' => 'The content to write to the file',
				],
			],
			'required'   => [ 'path', 'content' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'   => [ 'type' => 'string' ],
				'action' => [ 'type' => 'string' ],
				'size'   => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$path    = $input['path'] ?? '';
		$content = $input['content'] ?? '';

		$full_path = $this->resolve_path( $path );
		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		// Validate PHP syntax before writing.
		if ( $this->is_php_file( $path ) ) {
			$lint = $this->lint_php( $content );
			if ( ! $lint['valid'] ) {
				return new WP_Error(
					'gratis_ai_agent_php_syntax_error',
					sprintf(
						'PHP syntax error: %s (line %d)',
						$lint['error'] ?? 'Unknown',
						$lint['line'] ?? 0
					)
				);
			}
		}

		// Create directory if needed.
		$dir = dirname( $full_path );
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'gratis_ai_agent_mkdir_failed', sprintf( 'Failed to create directory: %s', dirname( $path ) ) );
			}
		}

		$existed = file_exists( $full_path );

		// Snapshot the original file content before overwriting (for git change tracking).
		do_action( 'gratis_ai_agent_before_file_write', $full_path );

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'gratis_ai_agent_file_write_failed', sprintf( 'Failed to write file: %s', $path ) );
		}

		// Record the modification for git change tracking.
		do_action( 'gratis_ai_agent_after_file_write', $full_path );

		return [
			'path'   => $path,
			'action' => $existed ? 'updated' : 'created',
			'size'   => strlen( $content ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * File Edit ability.
 *
 * @since 1.0.0
 */
class FileEditAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Edit File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'gratis-ai-agent' );
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
					'description' => 'Array of {search, replace} edit operations to apply in order',
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
		$path  = $input['path'] ?? '';
		$edits = $input['edits'] ?? [];

		$full_path = $this->resolve_path( $path );
		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'gratis_ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		// Snapshot the original file content before editing (for git change tracking).
		do_action( 'gratis_ai_agent_before_file_edit', $full_path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			return new WP_Error( 'gratis_ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
		}

		// Normalize edits: handle single edit object.
		if ( isset( $edits['search'] ) && isset( $edits['replace'] ) ) {
			$edits = [ $edits ];
		}

		$applied = [];
		$failed  = [];

		foreach ( $edits as $index => $edit ) {
			$search  = $edit['search'] ?? '';
			$replace = $edit['replace'] ?? '';

			if ( empty( $search ) ) {
				$failed[] = [
					'index'  => $index,
					'reason' => 'Empty search string',
				];
				continue;
			}

			$count = substr_count( $content, $search );

			if ( 0 === $count ) {
				$failed[] = [
					'index'  => $index,
					'reason' => 'Search string not found',
					'search' => substr( $search, 0, 50 ),
				];
				continue;
			}

			if ( $count > 1 ) {
				$failed[] = [
					'index'  => $index,
					'reason' => sprintf( 'Search string found %d times (must be unique)', $count ),
					'search' => substr( $search, 0, 50 ),
				];
				continue;
			}

			$content   = str_replace( $search, $replace, $content );
			$applied[] = [
				'index'          => $index,
				'search_length'  => strlen( $search ),
				'replace_length' => strlen( $replace ),
			];
		}

		if ( count( $applied ) > 0 ) {
			// Validate PHP syntax after edits.
			if ( $this->is_php_file( $path ) ) {
				$lint = $this->lint_php( $content );
				if ( ! $lint['valid'] ) {
					return new WP_Error(
						'gratis_ai_agent_php_syntax_error',
						sprintf(
							'PHP syntax error after edits: %s (line %d)',
							$lint['error'] ?? 'Unknown',
							$lint['line'] ?? 0
						)
					);
				}
			}

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( ! $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE ) ) {
				return new WP_Error( 'gratis_ai_agent_file_write_failed', sprintf( 'Failed to write file: %s', $path ) );
			}

			// Record the modification for git change tracking.
			do_action( 'gratis_ai_agent_after_file_edit', $full_path );
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
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * File Delete ability.
 *
 * @since 1.0.0
 */
class FileDeleteAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Delete File', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Delete a file within the wp-content directory.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'   => [ 'type' => 'string' ],
				'action' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$path      = $input['path'] ?? '';
		$full_path = $this->resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'gratis_ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( is_dir( $full_path ) ) {
			$result = $wp_filesystem->rmdir( $full_path, true );
		} else {
			$result = $wp_filesystem->delete( $full_path );
		}

		if ( ! $result ) {
			return new WP_Error( 'gratis_ai_agent_file_delete_failed', sprintf( 'Failed to delete: %s', $path ) );
		}

		return [
			'path'   => $path,
			'action' => 'deleted',
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * File List ability.
 *
 * @since 1.0.0
 */
class FileListAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'List Directory', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List files and directories within a directory in wp-content.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'Relative path from wp-content (e.g., "plugins" or "themes/theme-name")',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'  => [ 'type' => 'string' ],
				'items' => [ 'type' => 'array' ],
				'count' => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$path      = $input['path'] ?? '';
		$full_path = $this->resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) || ! is_dir( $full_path ) ) {
			return new WP_Error( 'gratis_ai_agent_dir_not_found', sprintf( 'Directory not found: %s', $path ) );
		}

		$entries = scandir( $full_path );
		$items   = [];

		if ( false !== $entries ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}

				$entry_path = $full_path . '/' . $entry;
				$items[]    = [
					'name'     => $entry,
					'type'     => is_dir( $entry_path ) ? 'directory' : 'file',
					'size'     => is_file( $entry_path ) ? filesize( $entry_path ) : null,
					'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $entry_path ) ),
				];
			}
		}

		return [
			'path'  => $path,
			'items' => $items,
			'count' => count( $items ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * File Search ability.
 *
 * @since 1.0.0
 */
class FileSearchAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Search Files', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Search for files matching a glob pattern within wp-content.', 'gratis-ai-agent' );
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
		$pattern      = $input['pattern'] ?? '';
		$full_pattern = WP_CONTENT_DIR . '/' . ltrim( $pattern, '/' );

		$files   = glob( $full_pattern );
		$results = [];

		if ( false !== $files ) {
			foreach ( $files as $file ) {
				$relative  = str_replace( WP_CONTENT_DIR . '/', '', $file );
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
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Content Search ability.
 *
 * @since 1.0.0
 */
class ContentSearchAbility extends AbstractFileAbility {

	protected function label(): string {
		return __( 'Search Content', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Search for text content within files in wp-content.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'needle'       => [
					'type'        => 'string',
					'description' => 'The text to search for',
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
		$needle       = $input['needle'] ?? '';
		$directory    = $input['directory'] ?? '';
		$file_pattern = $input['file_pattern'] ?? '*.php';

		if ( empty( $needle ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_needle', __( 'Search text cannot be empty.', 'gratis-ai-agent' ) );
		}

		$search_path = WP_CONTENT_DIR;
		if ( ! empty( $directory ) ) {
			$resolved = $this->resolve_path( $directory );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
			$search_path = $resolved;
		}

		$results = [];
		$this->search_content_recursive( $search_path, $needle, $file_pattern, $results );

		return [
			'needle'    => $needle,
			'directory' => $directory ?: 'wp-content',
			'matches'   => $results,
			'count'     => count( $results ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
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

		$files = glob( $dir . '/' . $pattern );
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
					'path'    => str_replace( WP_CONTENT_DIR . '/', '', $file ),
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
