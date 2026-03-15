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
 * @package AiAgent
 */

namespace AiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FileAbilities {

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
			'ai-agent/file-read',
			[
				'label'               => __( 'Read File', 'ai-agent' ),
				'description'         => __( 'Read the contents of a file within the wp-content directory.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'path' => [
							'type'        => 'string',
							'description' => 'Relative path from wp-content (e.g., "plugins/my-plugin/file.php")',
						],
					],
					'required'   => [ 'path' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'path'     => [ 'type' => 'string' ],
						'content'  => [ 'type' => 'string' ],
						'size'     => [ 'type' => 'integer' ],
						'modified' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_read_file' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/file-write',
			[
				'label'               => __( 'Write File', 'ai-agent' ),
				'description'         => __( 'Write or overwrite a file within wp-content. Use for creating NEW files. For modifying existing files, use ai-agent/file-edit instead.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
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
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'path'   => [ 'type' => 'string' ],
						'action' => [ 'type' => 'string' ],
						'size'   => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_write_file' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/file-edit',
			[
				'label'               => __( 'Edit File', 'ai-agent' ),
				'description'         => __( 'Edit an existing file by applying search and replace operations. More efficient than write for targeted changes. Each edit finds a unique string and replaces it.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
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
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'path'          => [ 'type' => 'string' ],
						'edits_applied' => [ 'type' => 'integer' ],
						'edits_failed'  => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_edit_file' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/file-delete',
			[
				'label'               => __( 'Delete File', 'ai-agent' ),
				'description'         => __( 'Delete a file within the wp-content directory.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'path' => [
							'type'        => 'string',
							'description' => 'Relative path from wp-content',
						],
					],
					'required'   => [ 'path' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'path'   => [ 'type' => 'string' ],
						'action' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_delete_file' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/file-list',
			[
				'label'               => __( 'List Directory', 'ai-agent' ),
				'description'         => __( 'List files and directories within a directory in wp-content.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'path' => [
							'type'        => 'string',
							'description' => 'Relative path from wp-content (e.g., "plugins" or "themes/theme-name")',
						],
					],
					'required'   => [ 'path' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'path'  => [ 'type' => 'string' ],
						'items' => [ 'type' => 'array' ],
						'count' => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_directory' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/file-search',
			[
				'label'               => __( 'Search Files', 'ai-agent' ),
				'description'         => __( 'Search for files matching a glob pattern within wp-content.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'pattern' => [
							'type'        => 'string',
							'description' => 'Glob pattern (e.g., "plugins/*/*.php" or "themes/**/*.css")',
						],
					],
					'required'   => [ 'pattern' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'pattern' => [ 'type' => 'string' ],
						'matches' => [ 'type' => 'array' ],
						'count'   => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_search_files' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/content-search',
			[
				'label'               => __( 'Search Content', 'ai-agent' ),
				'description'         => __( 'Search for text content within files in wp-content.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
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
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'needle'  => [ 'type' => 'string' ],
						'matches' => [ 'type' => 'array' ],
						'count'   => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_search_content' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Validate and resolve a path within wp-content.
	 *
	 * @param string $relative_path Path relative to wp-content.
	 * @return string|WP_Error Full path on success, WP_Error on failure.
	 */
	private static function resolve_path( string $relative_path ) {
		$relative_path = ltrim( $relative_path, '/\\' );

		if ( empty( $relative_path ) ) {
			return new WP_Error( 'ai_agent_empty_path', __( 'Path cannot be empty.', 'ai-agent' ) );
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
				'ai_agent_path_resolve_failed',
				__( 'Cannot resolve path.', 'ai-agent' )
			);
		}

		if ( strpos( $real_path, $wp_content_real ) !== 0 ) {
			return new WP_Error(
				'ai_agent_path_traversal',
				__( 'Access denied: path is outside wp-content directory.', 'ai-agent' )
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
	private static function is_php_file( string $path ): bool {
		return (bool) preg_match( '/\.php$/i', $path );
	}

	/**
	 * Lint PHP content for syntax errors.
	 *
	 * @param string $content PHP source code.
	 * @return array{valid: bool, error?: string, line?: int}
	 */
	// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions -- Intentional: error_reporting and set_error_handler used for PHP syntax validation.
	private static function lint_php( string $content ): array {
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

	/**
	 * Handle the file-read ability.
	 *
	 * @param array $input Input with path.
	 * @return array|WP_Error
	 */
	public static function handle_read_file( array $input ) {
		$path      = $input['path'] ?? '';
		$full_path = self::resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		if ( ! is_readable( $full_path ) ) {
			return new WP_Error( 'ai_agent_file_not_readable', sprintf( 'File not readable: %s', $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file, not remote URL.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			return new WP_Error( 'ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
		}

		return [
			'path'     => $path,
			'content'  => $content,
			'size'     => filesize( $full_path ),
			'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $full_path ) ),
		];
	}

	/**
	 * Handle the file-write ability.
	 *
	 * @param array $input Input with path and content.
	 * @return array|WP_Error
	 */
	public static function handle_write_file( array $input ) {
		$path    = $input['path'] ?? '';
		$content = $input['content'] ?? '';

		$full_path = self::resolve_path( $path );
		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		// Validate PHP syntax before writing.
		if ( self::is_php_file( $path ) ) {
			$lint = self::lint_php( $content );
			if ( ! $lint['valid'] ) {
				return new WP_Error(
					'ai_agent_php_syntax_error',
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
				return new WP_Error( 'ai_agent_mkdir_failed', sprintf( 'Failed to create directory: %s', dirname( $path ) ) );
			}
		}

		$existed = file_exists( $full_path );

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'ai_agent_file_write_failed', sprintf( 'Failed to write file: %s', $path ) );
		}

		return [
			'path'   => $path,
			'action' => $existed ? 'updated' : 'created',
			'size'   => strlen( $content ),
		];
	}

	/**
	 * Handle the file-edit ability.
	 *
	 * @param array $input Input with path and edits array.
	 * @return array|WP_Error
	 */
	public static function handle_edit_file( array $input ) {
		$path  = $input['path'] ?? '';
		$edits = $input['edits'] ?? [];

		$full_path = self::resolve_path( $path );
		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			return new WP_Error( 'ai_agent_file_read_failed', sprintf( 'Failed to read file: %s', $path ) );
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
			if ( self::is_php_file( $path ) ) {
				$lint = self::lint_php( $content );
				if ( ! $lint['valid'] ) {
					return new WP_Error(
						'ai_agent_php_syntax_error',
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
				return new WP_Error( 'ai_agent_file_write_failed', sprintf( 'Failed to write file: %s', $path ) );
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

	/**
	 * Handle the file-delete ability.
	 *
	 * @param array $input Input with path.
	 * @return array|WP_Error
	 */
	public static function handle_delete_file( array $input ) {
		$path      = $input['path'] ?? '';
		$full_path = self::resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'ai_agent_file_not_found', sprintf( 'File not found: %s', $path ) );
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
			return new WP_Error( 'ai_agent_file_delete_failed', sprintf( 'Failed to delete: %s', $path ) );
		}

		return [
			'path'   => $path,
			'action' => 'deleted',
		];
	}

	/**
	 * Handle the file-list ability.
	 *
	 * @param array $input Input with path.
	 * @return array|WP_Error
	 */
	public static function handle_list_directory( array $input ) {
		$path      = $input['path'] ?? '';
		$full_path = self::resolve_path( $path );

		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) || ! is_dir( $full_path ) ) {
			return new WP_Error( 'ai_agent_dir_not_found', sprintf( 'Directory not found: %s', $path ) );
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

	/**
	 * Handle the file-search ability.
	 *
	 * @param array $input Input with pattern.
	 * @return array|WP_Error
	 */
	public static function handle_search_files( array $input ) {
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

	/**
	 * Handle the content-search ability.
	 *
	 * @param array $input Input with needle, optional directory and file_pattern.
	 * @return array|WP_Error
	 */
	public static function handle_search_content( array $input ) {
		$needle       = $input['needle'] ?? '';
		$directory    = $input['directory'] ?? '';
		$file_pattern = $input['file_pattern'] ?? '*.php';

		if ( empty( $needle ) ) {
			return new WP_Error( 'ai_agent_empty_needle', __( 'Search text cannot be empty.', 'ai-agent' ) );
		}

		$search_path = WP_CONTENT_DIR;
		if ( ! empty( $directory ) ) {
			$resolved = self::resolve_path( $directory );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
			$search_path = $resolved;
		}

		$results = [];
		self::search_content_recursive( $search_path, $needle, $file_pattern, $results );

		return [
			'needle'    => $needle,
			'directory' => $directory ?: 'wp-content',
			'matches'   => $results,
			'count'     => count( $results ),
		];
	}

	/**
	 * Recursively search file contents.
	 *
	 * @param string $dir          Directory to search.
	 * @param string $needle       Text to find.
	 * @param string $pattern      File glob pattern.
	 * @param array  $results      Results accumulator (passed by reference).
	 * @param int    $limit        Maximum results.
	 */
	private static function search_content_recursive( string $dir, string $needle, string $pattern, array &$results, int $limit = 50 ): void {
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
				self::search_content_recursive( $subdir, $needle, $pattern, $results, $limit );
			}
		}
	}
}
