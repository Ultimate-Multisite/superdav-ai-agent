<?php

declare(strict_types=1);
/**
 * Block-related abilities for the AI agent.
 *
 * Provides tools for Gutenberg block discovery, content creation,
 * and markdown-to-blocks conversion.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\MarkdownToBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlockAbilities {

	/**
	 * Register abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all block-related abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/markdown-to-blocks',
			[
				'label'               => __( 'Markdown to Blocks', 'gratis-ai-agent' ),
				'description'         => __( 'Convert markdown text into serialized Gutenberg block HTML ready for post_content. Best for text-heavy content like blog posts and articles.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'markdown' => [
							'type'        => 'string',
							'description' => 'Markdown text to convert into Gutenberg blocks.',
						],
					],
					'required'   => [ 'markdown' ],
				],
				'execute_callback'    => [ __CLASS__, 'handle_markdown_to_blocks' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/list-block-types',
			[
				'label'               => __( 'List Block Types', 'gratis-ai-agent' ),
				'description'         => __( 'List registered Gutenberg block types. Filter by category or search term. Returns block names, titles, descriptions, and categories.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => 'Filter by block category slug (e.g. "text", "media", "design").',
						],
						'search'   => [
							'type'        => 'string',
							'description' => 'Search term to filter block types by name, title, or keywords.',
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => 'Results per page (default: 20).',
						],
						'page'     => [
							'type'        => 'integer',
							'description' => 'Page number (default: 1).',
						],
					],
					'required'   => [],
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_block_types' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/get-block-type',
			[
				'label'               => __( 'Get Block Type', 'gratis-ai-agent' ),
				'description'         => __( 'Get detailed metadata for a specific block type including attributes schema, supports, styles, and variations.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'name' => [
							'type'        => 'string',
							'description' => 'Block type name (e.g. "core/paragraph", "core/image").',
						],
					],
					'required'   => [ 'name' ],
				],
				'execute_callback'    => [ __CLASS__, 'handle_get_block_type' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/list-block-patterns',
			[
				'label'               => __( 'List Block Patterns', 'gratis-ai-agent' ),
				'description'         => __( 'List registered block patterns. Filter by category or search. Returns pattern names, titles, descriptions, and optionally full content.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'category'     => [
							'type'        => 'string',
							'description' => 'Filter by pattern category slug.',
						],
						'search'       => [
							'type'        => 'string',
							'description' => 'Search term to filter patterns by name or title.',
						],
						'per_page'     => [
							'type'        => 'integer',
							'description' => 'Results per page (default: 10).',
						],
						'full_content' => [
							'type'        => 'boolean',
							'description' => 'Return full pattern content instead of truncated (default: false).',
						],
					],
					'required'   => [],
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_block_patterns' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/list-block-templates',
			[
				'label'               => __( 'List Block Templates', 'gratis-ai-agent' ),
				'description'         => __( 'List block templates available in the current theme. Returns template slugs, titles, and descriptions.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'search' => [
							'type'        => 'string',
							'description' => 'Search term to filter templates.',
						],
					],
					'required'   => [],
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_block_templates' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/create-block-content',
			[
				'label'               => __( 'Create Block Content', 'gratis-ai-agent' ),
				'description'         => __( 'Build serialized Gutenberg block HTML from a structured block array. Best for layouts with columns, buttons, groups, and other complex blocks. Each block needs blockName, optional attrs, content, and innerBlocks.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'blocks' => [
							'type'        => 'array',
							'description' => 'Array of block objects. Each has: blockName (string, required), attrs (object, optional), content (string, optional — inner text/HTML), innerBlocks (array, optional — nested blocks).',
						],
					],
					'required'   => [ 'blocks' ],
				],
				'execute_callback'    => [ __CLASS__, 'handle_create_block_content' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/parse-block-content',
			[
				'label'               => __( 'Parse Block Content', 'gratis-ai-agent' ),
				'description'         => __( 'Parse existing Gutenberg block content into a structured block tree. Provide either a post_id to read from the database, or raw content string.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_id'  => [
							'type'        => 'integer',
							'description' => 'Post ID to read block content from.',
						],
						'content'  => [
							'type'        => 'string',
							'description' => 'Raw block content string to parse.',
						],
						'site_url' => [
							'type'        => 'string',
							'description' => 'Subsite URL for multisite (e.g. "https://example.com/mysite").',
						],
					],
					'required'   => [],
				],
				'execute_callback'    => [ __CLASS__, 'handle_parse_block_content' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	// ─── Handlers ─────────────────────────────────────────────────

	/**
	 * Handle markdown-to-blocks conversion.
	 *
	 * @param array $input Input with 'markdown' key.
	 * @return array Result with block_content and block_count.
	 */
	public static function handle_markdown_to_blocks( array $input ): array {
		$markdown = $input['markdown'] ?? '';

		if ( empty( $markdown ) ) {
			return [ 'error' => 'markdown is required.' ];
		}

		$blocks        = MarkdownToBlocks::parse( $markdown );
		$block_content = MarkdownToBlocks::convert( $markdown );

		return [
			'block_content' => $block_content,
			'block_count'   => count( $blocks ),
		];
	}

	/**
	 * Handle listing block types.
	 *
	 * @param array $input Input with optional category, search, per_page, page.
	 * @return array Result with block_types, total, and categories.
	 */
	public static function handle_list_block_types( array $input ): array {
		$registry = \WP_Block_Type_Registry::get_instance();
		$all      = $registry->get_all_registered();

		$category = $input['category'] ?? '';
		$search   = strtolower( $input['search'] ?? '' );
		$per_page = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		// Build category overview.
		$categories = [];
		foreach ( $all as $block ) {
			$cat = $block->category ?? 'uncategorized';
			if ( ! isset( $categories[ $cat ] ) ) {
				$categories[ $cat ] = 0;
			}
			++$categories[ $cat ];
		}

		// Filter blocks.
		$filtered = [];
		foreach ( $all as $name => $block ) {
			if ( ! empty( $category ) && ( $block->category ?? '' ) !== $category ) {
				continue;
			}

			if ( ! empty( $search ) ) {
				$searchable = strtolower(
					$name . ' ' . ( $block->title ?? '' ) . ' ' .
					( $block->description ?? '' ) . ' ' .
					implode( ' ', $block->keywords ?? [] )
				);
				if ( strpos( $searchable, $search ) === false ) {
					continue;
				}
			}

			$filtered[] = [
				'name'        => $name,
				'title'       => $block->title ?? '',
				'description' => $block->description ?? '',
				'category'    => $block->category ?? '',
				'keywords'    => $block->keywords ?? [],
			];
		}

		// Sort by name.
		usort(
			$filtered,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		$total  = count( $filtered );
		$offset = ( $page - 1 ) * $per_page;
		$paged  = array_slice( $filtered, $offset, $per_page );

		return [
			'block_types' => $paged,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'categories'  => $categories,
		];
	}

	/**
	 * Handle getting a single block type's full metadata.
	 *
	 * @param array $input Input with 'name' key.
	 * @return array Full block type metadata.
	 */
	public static function handle_get_block_type( array $input ): array {
		$name = $input['name'] ?? '';

		if ( empty( $name ) ) {
			return [ 'error' => 'name is required.' ];
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( $name );

		if ( ! $block ) {
			return [ 'error' => "Block type '{$name}' not found." ];
		}

		$result = [
			'name'        => $block->name,
			'title'       => $block->title ?? '',
			'description' => $block->description ?? '',
			'category'    => $block->category ?? '',
			'keywords'    => $block->keywords ?? [],
			'attributes'  => $block->attributes ?? [],
			'supports'    => $block->supports ?? [],
		];

		if ( ! empty( $block->styles ) ) {
			$result['styles'] = $block->styles;
		}

		if ( ! empty( $block->variations ) ) {
			$result['variations'] = array_map(
				function ( $v ) {
					return [
						'name'        => $v['name'] ?? '',
						'title'       => $v['title'] ?? '',
						'description' => $v['description'] ?? '',
						'isDefault'   => $v['isDefault'] ?? false,
					];
				},
				$block->variations
			);
		}

		// Generate example markup if example data exists.
		if ( ! empty( $block->example ) ) {
			$example_block            = [
				'blockName'    => $block->name,
				'attrs'        => $block->example['attributes'] ?? [],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			];
			$result['example_markup'] = serialize_block( $example_block );
		}

		return $result;
	}

	/**
	 * Handle listing block patterns.
	 *
	 * @param array $input Input with optional category, search, per_page, full_content.
	 * @return array Result with patterns, total, and categories.
	 */
	public static function handle_list_block_patterns( array $input ): array {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$all      = $registry->get_all_registered();

		$category     = $input['category'] ?? '';
		$search       = strtolower( $input['search'] ?? '' );
		$per_page     = max( 1, min( 50, (int) ( $input['per_page'] ?? 10 ) ) );
		$full_content = ! empty( $input['full_content'] );

		// Build category overview.
		$categories = [];
		foreach ( $all as $pattern ) {
			foreach ( $pattern['categories'] ?? [] as $cat ) {
				if ( ! isset( $categories[ $cat ] ) ) {
					$categories[ $cat ] = 0;
				}
				++$categories[ $cat ];
			}
		}

		// Filter patterns.
		$filtered = [];
		foreach ( $all as $pattern ) {
			if ( ! empty( $category ) ) {
				if ( ! in_array( $category, $pattern['categories'] ?? [], true ) ) {
					continue;
				}
			}

			if ( ! empty( $search ) ) {
				$searchable = strtolower(
					( $pattern['name'] ?? '' ) . ' ' .
					( $pattern['title'] ?? '' ) . ' ' .
					( $pattern['description'] ?? '' )
				);
				if ( strpos( $searchable, $search ) === false ) {
					continue;
				}
			}

			$content = $pattern['content'] ?? '';
			if ( ! $full_content && strlen( $content ) > 500 ) {
				$content = substr( $content, 0, 500 ) . '...';
			}

			$filtered[] = [
				'name'        => $pattern['name'] ?? '',
				'title'       => $pattern['title'] ?? '',
				'description' => $pattern['description'] ?? '',
				'categories'  => $pattern['categories'] ?? [],
				'blockTypes'  => $pattern['blockTypes'] ?? [],
				'content'     => $content,
			];
		}

		$total = count( $filtered );
		$paged = array_slice( $filtered, 0, $per_page );

		return [
			'patterns'   => $paged,
			'total'      => $total,
			'categories' => $categories,
		];
	}

	/**
	 * Handle listing block templates.
	 *
	 * @param array $input Input with optional search.
	 * @return array Result with templates and total.
	 */
	public static function handle_list_block_templates( array $input ): array {
		$search = strtolower( $input['search'] ?? '' );

		$templates = get_block_templates();

		$result = [];
		foreach ( $templates as $template ) {
			$title = $template->title ?? $template->slug;
			$desc  = $template->description ?? '';

			if ( ! empty( $search ) ) {
				$searchable = strtolower( $template->slug . ' ' . $title . ' ' . $desc );
				if ( strpos( $searchable, $search ) === false ) {
					continue;
				}
			}

			$result[] = [
				'slug'        => $template->slug,
				'title'       => $title,
				'description' => $desc,
				'type'        => $template->type ?? 'wp_template',
				'post_types'  => $template->post_types ?? [],
			];
		}

		return [
			'templates' => $result,
			'total'     => count( $result ),
		];
	}

	/**
	 * Handle creating block content from a structured array.
	 *
	 * @param array $input Input with 'blocks' array.
	 * @return array Result with block_content and block_count.
	 */
	public static function handle_create_block_content( array $input ): array {
		$blocks = $input['blocks'] ?? [];

		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return [ 'error' => 'blocks array is required.' ];
		}

		$output      = '';
		$block_count = 0;

		foreach ( $blocks as $block_data ) {
			$normalized = self::normalize_block( $block_data );
			$output    .= serialize_block( $normalized ) . "\n\n";
			++$block_count;
			$block_count += self::count_inner_blocks( $normalized );
		}

		return [
			'block_content' => trim( $output ),
			'block_count'   => $block_count,
		];
	}

	/**
	 * Handle parsing existing block content.
	 *
	 * @param array $input Input with post_id or content, optional site_url.
	 * @return array Result with blocks and block_count.
	 */
	public static function handle_parse_block_content( array $input ): array {
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$content  = $input['content'] ?? '';
		$site_url = $input['site_url'] ?? '';

		if ( ! $post_id && empty( $content ) ) {
			return [ 'error' => 'Either post_id or content is required.' ];
		}

		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				wp_parse_url( $site_url, PHP_URL_HOST ),
				wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			} elseif ( ! $blog_id ) {
				return [ 'error' => "Could not find a site matching URL: {$site_url}" ];
			}
		}

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				if ( $switched ) {
					restore_current_blog();
				}
				return [ 'error' => "Post {$post_id} not found." ];
			}
			$content = $post->post_content;
		}

		$parsed = parse_blocks( $content );
		$blocks = self::clean_parsed_blocks( $parsed );

		if ( $switched ) {
			restore_current_blog();
		}

		return [
			'blocks'      => $blocks,
			'block_count' => count( $blocks ),
		];
	}

	// ─── Private helpers ──────────────────────────────────────────

	/**
	 * Normalize a simplified agent-friendly block into serialize_block() format.
	 *
	 * @param array $data Block data with blockName, attrs, content, innerBlocks.
	 * @return array Full block array for serialize_block().
	 */
	private static function normalize_block( array $data ): array {
		$block_name = $data['blockName'] ?? '';
		$attrs      = $data['attrs'] ?? [];
		$content    = $data['content'] ?? '';
		$inner_data = $data['innerBlocks'] ?? [];

		// Recursively normalize inner blocks.
		$inner_blocks = [];
		foreach ( $inner_data as $child ) {
			$inner_blocks[] = self::normalize_block( $child );
		}

		// Generate markup based on block type.
		switch ( $block_name ) {
			case 'core/paragraph':
				$html = '<p>' . $content . '</p>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/heading':
				$level = (int) ( $attrs['level'] ?? 2 );
				$html  = '<h' . $level . ' class="wp-block-heading">' . $content . '</h' . $level . '>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/list':
				$ordered = ! empty( $attrs['ordered'] );
				$tag     = $ordered ? 'ol' : 'ul';

				if ( ! empty( $inner_blocks ) ) {
					$inner_html    = '<' . $tag . '>';
					$inner_content = [ '<' . $tag . '>' ];
					foreach ( $inner_blocks as $item ) {
						$inner_content[] = null;
						$inner_html     .= $item['innerHTML'] ?? '';
					}
					$inner_content[] = '</' . $tag . '>';
					$inner_html     .= '</' . $tag . '>';

					return [
						'blockName'    => $block_name,
						'attrs'        => $attrs,
						'innerBlocks'  => $inner_blocks,
						'innerHTML'    => $inner_html,
						'innerContent' => $inner_content,
					];
				}

				return self::build_block( $block_name, $attrs, $inner_blocks, '<' . $tag . '></' . $tag . '>' );

			case 'core/list-item':
				$html = '<li>' . $content . '</li>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/image':
				$url  = esc_url( $attrs['url'] ?? '' );
				$alt  = esc_attr( $attrs['alt'] ?? '' );
				$html = '<figure class="wp-block-image"><img src="' . $url . '" alt="' . $alt . '"/></figure>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/quote':
				$html = '<blockquote class="wp-block-quote"><p>' . $content . '</p></blockquote>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/code':
				$escaped = esc_html( $content );
				$html    = '<pre class="wp-block-code"><code>' . $escaped . '</code></pre>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/buttons':
				return self::build_container( $block_name, $attrs, $inner_blocks, 'div', 'wp-block-buttons' );

			case 'core/button':
				$url  = esc_url( $attrs['url'] ?? '' );
				$text = $attrs['text'] ?? $content;
				$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . $url . '">' . $text . '</a></div>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/columns':
				return self::build_container( $block_name, $attrs, $inner_blocks, 'div', 'wp-block-columns' );

			case 'core/column':
				return self::build_container( $block_name, $attrs, $inner_blocks, 'div', 'wp-block-column' );

			case 'core/group':
				return self::build_container( $block_name, $attrs, $inner_blocks, 'div', 'wp-block-group' );

			case 'core/separator':
				$html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/spacer':
				$height = $attrs['height'] ?? '50px';
				$html   = '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );

			case 'core/cover':
				return self::build_container( $block_name, $attrs, $inner_blocks, 'div', 'wp-block-cover' );

			default:
				// Unknown blocks: pass content as raw innerHTML.
				$html = $content;
				if ( ! empty( $inner_blocks ) ) {
					return self::build_container_raw( $block_name, $attrs, $inner_blocks, $html );
				}
				return self::build_block( $block_name, $attrs, $inner_blocks, $html );
		}
	}

	/**
	 * Build a simple block array (no inner blocks in innerContent).
	 *
	 * @param string $block_name  Block name.
	 * @param array  $attrs       Block attributes.
	 * @param array  $inner_blocks Inner blocks.
	 * @param string $html        Inner HTML.
	 * @return array Block array.
	 */
	private static function build_block( string $block_name, array $attrs, array $inner_blocks, string $html ): array {
		return [
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $html,
			'innerContent' => [ $html ],
		];
	}

	/**
	 * Build a container block with inner block placeholders in innerContent.
	 *
	 * @param string $block_name  Block name.
	 * @param array  $attrs       Block attributes.
	 * @param array  $inner_blocks Inner blocks.
	 * @param string $tag         HTML tag (div, section, etc.).
	 * @param string $class       CSS class.
	 * @return array Block array.
	 */
	private static function build_container( string $block_name, array $attrs, array $inner_blocks, string $tag, string $class ): array {
		$open  = '<' . $tag . ' class="' . esc_attr( $class ) . '">';
		$close = '</' . $tag . '>';

		$inner_content = [ $open ];
		$inner_html    = $open;

		foreach ( $inner_blocks as $child ) {
			$inner_content[] = null;
			$inner_html     .= $child['innerHTML'] ?? '';
		}

		$inner_content[] = $close;
		$inner_html     .= $close;

		return [
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $inner_html,
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Build a container for unknown blocks with inner block placeholders.
	 *
	 * @param string $block_name  Block name.
	 * @param array  $attrs       Block attributes.
	 * @param array  $inner_blocks Inner blocks.
	 * @param string $wrapper_html Optional wrapper HTML.
	 * @return array Block array.
	 */
	private static function build_container_raw( string $block_name, array $attrs, array $inner_blocks, string $wrapper_html ): array {
		$inner_content = [];
		$inner_html    = '';

		if ( ! empty( $wrapper_html ) ) {
			$inner_content[] = $wrapper_html;
			$inner_html     .= $wrapper_html;
		}

		foreach ( $inner_blocks as $child ) {
			$inner_content[] = null;
			$inner_html     .= $child['innerHTML'] ?? '';
		}

		return [
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $inner_html,
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Count inner blocks recursively.
	 *
	 * @param array $block Block array.
	 * @return int Total inner block count.
	 */
	private static function count_inner_blocks( array $block ): int {
		$count = 0;
		foreach ( $block['innerBlocks'] ?? [] as $child ) {
			++$count;
			$count += self::count_inner_blocks( $child );
		}
		return $count;
	}

	/**
	 * Clean up parsed blocks from parse_blocks(), removing empty freeform blocks.
	 *
	 * @param array $blocks Parsed blocks from parse_blocks().
	 * @return array Cleaned block tree.
	 */
	private static function clean_parsed_blocks( array $blocks ): array {
		$cleaned = [];

		foreach ( $blocks as $block ) {
			// Skip null/empty freeform blocks (whitespace between blocks).
			if ( empty( $block['blockName'] ) ) {
				$content = trim( $block['innerHTML'] ?? '' );
				if ( empty( $content ) ) {
					continue;
				}
			}

			$result = [
				'blockName' => $block['blockName'] ?? null,
				'attrs'     => $block['attrs'] ?? [],
				'innerHTML' => trim( $block['innerHTML'] ?? '' ),
			];

			if ( ! empty( $block['innerBlocks'] ) ) {
				$result['innerBlocks'] = self::clean_parsed_blocks( $block['innerBlocks'] );
			}

			$cleaned[] = $result;
		}

		return $cleaned;
	}
}
