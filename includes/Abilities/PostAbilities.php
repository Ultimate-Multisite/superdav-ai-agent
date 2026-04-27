<?php

declare(strict_types=1);
/**
 * Post management abilities for the AI agent.
 *
 * Provides post creation, retrieval, update, and deletion.
 * Ported from the WordPress/ai experiments plugin pattern.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Models\MarkdownToBlocks;
use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostAbilities {

	/**
	 * Register all post management abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'ai-agent/get-post',
			[
				'label'               => __( 'Get Post', 'gratis-ai-agent' ),
				'description'         => __( 'Retrieve a WordPress post by ID. Returns title, content, excerpt, status, author, categories, tags, featured image, and meta.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_id'   => [
							'type'        => 'integer',
							'description' => 'The ID of the post to retrieve.',
						],
						'post_type' => [
							'type'        => 'string',
							'description' => 'Post type to validate against (default: any).',
						],
					],
					'required'   => [ 'post_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'id'             => [ 'type' => 'integer' ],
						'title'          => [ 'type' => 'string' ],
						'content'        => [ 'type' => 'string' ],
						'excerpt'        => [ 'type' => 'string' ],
						'status'         => [ 'type' => 'string' ],
						'post_type'      => [ 'type' => 'string' ],
						'author_id'      => [ 'type' => 'integer' ],
						'author_name'    => [ 'type' => 'string' ],
						'date'           => [ 'type' => 'string' ],
						'modified'       => [ 'type' => 'string' ],
						'permalink'      => [ 'type' => 'string' ],
						'categories'     => [ 'type' => 'array' ],
						'tags'           => [ 'type' => 'array' ],
						'featured_image' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_get_post' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/create-post',
			[
				'label'               => __( 'Create Post', 'gratis-ai-agent' ),
				'description'         => __( 'Create a new WordPress post or page. This is the PRIMARY tool for creating any content — blog posts, landing pages, about pages, etc. Write content directly as HTML or markdown (auto-converted to Gutenberg blocks). Set post_type to "page" for pages or "post" for blog posts. Set status to "publish" to make it live immediately.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'title'             => [
							'type'        => 'string',
							'description' => 'The post title.',
						],
						'content'           => [
							'type'        => 'string',
							'description' => 'The post content. Write in markdown (headings with ##, lists with -, bold with **) or HTML — markdown is automatically converted to Gutenberg blocks.',
						],
						'excerpt'           => [
							'type'        => 'string',
							'description' => 'Optional post excerpt.',
						],
						'status'            => [
							'type'        => 'string',
							'description' => 'Post status: "draft" (default), "publish", "pending", "private", or "future".',
							'enum'        => [ 'draft', 'publish', 'pending', 'private', 'future' ],
						],
						'post_type'         => [
							'type'        => 'string',
							'description' => 'Post type (default: "post"). Use "page" for pages.',
						],
						'categories'        => [
							'type'        => 'array',
							'description' => 'Array of category IDs (integers) or names (strings) to assign.',
							'items'       => [
								'oneOf' => [
									[ 'type' => 'string' ],
									[ 'type' => 'integer' ],
								],
							],
						],
						'tags'              => [
							'type'        => 'array',
							'description' => 'Array of tag names to assign.',
							'items'       => [ 'type' => 'string' ],
						],
						'featured_image_id' => [
							'type'        => 'integer',
							'description' => 'Attachment ID to set as the featured image (e.g. from stock-image or generate-image result).',
						],
						'meta'              => [
							'type'        => 'object',
							'description' => 'Key-value pairs of post meta to set.',
						],
						'site_url'          => [
							'type'        => 'string',
							'description' => 'Subsite URL for multisite. Omit for the main site.',
						],
					],
					'required'   => [ 'title' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'post_id'   => [ 'type' => 'integer' ],
						'permalink' => [ 'type' => 'string' ],
						'status'    => [ 'type' => 'string' ],
						'post_type' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_create_post' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/update-post',
			[
				'label'               => __( 'Update Post', 'gratis-ai-agent' ),
				'description'         => __( 'Update an existing WordPress post. Only provided fields are changed; omitted fields are left as-is.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_id'           => [
							'type'        => 'integer',
							'description' => 'The ID of the post to update.',
						],
						'title'             => [
							'type'        => 'string',
							'description' => 'New post title.',
						],
						'content'           => [
							'type'        => 'string',
							'description' => 'New post content.',
						],
						'excerpt'           => [
							'type'        => 'string',
							'description' => 'New post excerpt.',
						],
						'status'            => [
							'type'        => 'string',
							'description' => 'New post status.',
							'enum'        => [ 'draft', 'publish', 'pending', 'private', 'future', 'trash' ],
						],
						'categories'        => [
							'type'        => 'array',
							'description' => 'Replace categories with this array of IDs (integers) or names (strings).',
							'items'       => [
								'oneOf' => [
									[ 'type' => 'string' ],
									[ 'type' => 'integer' ],
								],
							],
						],
						'tags'              => [
							'type'        => 'array',
							'description' => 'Replace tags with this array of names.',
							'items'       => [ 'type' => 'string' ],
						],
						'featured_image_id' => [
							'type'        => 'integer',
							'description' => 'Attachment ID to set as the featured image.',
						],
						'meta'              => [
							'type'        => 'object',
							'description' => 'Key-value pairs of post meta to update.',
						],
						'site_url'          => [
							'type'        => 'string',
							'description' => 'Subsite URL for multisite. Omit for the main site.',
						],
					],
					'required'   => [ 'post_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'post_id'   => [ 'type' => 'integer' ],
						'permalink' => [ 'type' => 'string' ],
						'status'    => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_update_post' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/delete-post',
			[
				'label'               => __( 'Delete Post', 'gratis-ai-agent' ),
				'description'         => __( 'Move a WordPress post to the trash, or permanently delete it. Defaults to trash (recoverable). Set force_delete to true for permanent deletion.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_id'      => [
							'type'        => 'integer',
							'description' => 'The ID of the post to delete.',
						],
						'force_delete' => [
							'type'        => 'boolean',
							'description' => 'If true, permanently delete instead of trashing (default: false).',
						],
						'site_url'     => [
							'type'        => 'string',
							'description' => 'Subsite URL for multisite. Omit for the main site.',
						],
					],
					'required'   => [ 'post_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'post_id'      => [ 'type' => 'integer' ],
						'title'        => [ 'type' => 'string' ],
						'action'       => [ 'type' => 'string' ],
						'force_delete' => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_delete_post' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_posts' );
				},
			]
		);
	}

	/**
	 * Handle the get-post ability.
	 *
	 * @param array<string, mixed> $input Input with post_id and optional post_type.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_get_post( array $input ) {
		// @phpstan-ignore-next-line
		$post_id = (int) ( $input['post_id'] ?? 0 );
		// @phpstan-ignore-next-line
		$post_type = sanitize_text_field( $input['post_type'] ?? 'any' );

		if ( ! $post_id ) {
			return new WP_Error( 'ai_agent_empty_post_id', __( 'post_id is required.', 'gratis-ai-agent' ) );
		}

		$post = get_post( $post_id );

		if ( ! ( $post instanceof WP_Post ) ) {
			return new WP_Error(
				'ai_agent_post_not_found',
				/* translators: %d: post ID */
				sprintf( __( 'Post %d not found.', 'gratis-ai-agent' ), $post_id )
			);
		}

		if ( $post_type !== 'any' && $post->post_type !== $post_type ) {
			return new WP_Error(
				'ai_agent_post_type_mismatch',
				/* translators: 1: post ID, 2: expected type, 3: actual type */
				sprintf( __( 'Post %1$d is of type "%2$s", not "%3$s".', 'gratis-ai-agent' ), $post_id, $post->post_type, $post_type )
			);
		}

		$categories = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );
		$tags       = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );

		$featured_image_url = '';
		$thumbnail_id       = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$image_src          = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			$featured_image_url = $image_src ? $image_src[0] : '';
		}

		return [
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'status'         => $post->post_status,
			'post_type'      => $post->post_type,
			'author_id'      => (int) $post->post_author,
			'author_name'    => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'date'           => $post->post_date,
			'modified'       => $post->post_modified,
			'permalink'      => get_permalink( $post_id ) ?: '',
			'categories'     => is_wp_error( $categories ) ? [] : $categories,
			'tags'           => is_wp_error( $tags ) ? [] : $tags,
			'featured_image' => $featured_image_url,
		];
	}

	/**
	 * Handle the create-post ability.
	 *
	 * @param array<string, mixed> $input Input with title, content, status, etc.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_create_post( array $input ) {
		// @phpstan-ignore-next-line
		$title       = sanitize_text_field( $input['title'] ?? '' );
		$raw_content = $input['content'] ?? '';
		// @phpstan-ignore-next-line
		$content = wp_kses_post( self::maybe_convert_markdown( $raw_content ) );
		// @phpstan-ignore-next-line
		$excerpt = sanitize_textarea_field( $input['excerpt'] ?? '' );
		// @phpstan-ignore-next-line
		$status = sanitize_text_field( $input['status'] ?? 'draft' );
		// @phpstan-ignore-next-line
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );
		$site_url  = $input['site_url'] ?? '';

		if ( empty( $title ) ) {
			return new WP_Error( 'ai_agent_empty_title', __( 'Post title is required.', 'gratis-ai-agent' ) );
		}

		$allowed_statuses = [ 'draft', 'publish', 'pending', 'private', 'future' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'draft';
		}

		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_HOST ) ?? '' ),
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/' )
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}
		}

		$post_data = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
			'post_type'    => $post_type,
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return $post_id;
		}

		// Assign categories.
		$categories = $input['categories'] ?? [];
		if ( ! empty( $categories ) && is_array( $categories ) ) {
			// @phpstan-ignore-next-line
			$cat_ids = self::resolve_category_ids( $categories );
			wp_set_post_categories( $post_id, $cat_ids );
		}

		// Assign tags.
		$tags = $input['tags'] ?? [];
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			// @phpstan-ignore-next-line
			$tag_names = array_map( 'sanitize_text_field', $tags );
			wp_set_post_tags( $post_id, $tag_names );
		}

		// Set featured image if provided.
		// @phpstan-ignore-next-line
		$featured_image_id = (int) ( $input['featured_image_id'] ?? 0 );
		if ( $featured_image_id > 0 ) {
			set_post_thumbnail( $post_id, $featured_image_id );
		}

		// Set post meta.
		$meta = $input['meta'] ?? [];
		if ( ! empty( $meta ) && is_array( $meta ) ) {
			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}

		$permalink = get_permalink( $post_id );

		if ( $switched ) {
			restore_current_blog();
		}

		return [
			'post_id'   => $post_id,
			'permalink' => $permalink ?: '',
			'status'    => $status,
			'post_type' => $post_type,
		];
	}

	/**
	 * Handle the update-post ability.
	 *
	 * @param array<string, mixed> $input Input with post_id and fields to update.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_update_post( array $input ) {
		// @phpstan-ignore-next-line
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$site_url = $input['site_url'] ?? '';

		if ( ! $post_id ) {
			return new WP_Error( 'ai_agent_empty_post_id', __( 'post_id is required.', 'gratis-ai-agent' ) );
		}

		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_HOST ) ?? '' ),
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/' )
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}
		}

		$post = get_post( $post_id );

		if ( ! ( $post instanceof WP_Post ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return new WP_Error(
				'ai_agent_post_not_found',
				/* translators: %d: post ID */
				sprintf( __( 'Post %d not found.', 'gratis-ai-agent' ), $post_id )
			);
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $input['title'] ) ) {
			// @phpstan-ignore-next-line
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			// @phpstan-ignore-next-line
			$post_data['post_content'] = wp_kses_post( self::maybe_convert_markdown( $input['content'] ) );
		}
		if ( isset( $input['excerpt'] ) ) {
			// @phpstan-ignore-next-line
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}
		if ( isset( $input['status'] ) ) {
			$allowed_statuses = [ 'draft', 'publish', 'pending', 'private', 'future', 'trash' ];
			// @phpstan-ignore-next-line
			$new_status = sanitize_text_field( $input['status'] );
			if ( in_array( $new_status, $allowed_statuses, true ) ) {
				$post_data['post_status'] = $new_status;
			}
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return $result;
		}

		// Update categories if provided.
		if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
			// @phpstan-ignore-next-line
			$cat_ids = self::resolve_category_ids( $input['categories'] );
			wp_set_post_categories( $post_id, $cat_ids );
		}

		// Update tags if provided.
		if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
			// @phpstan-ignore-next-line
			$tag_names = array_map( 'sanitize_text_field', $input['tags'] );
			wp_set_post_tags( $post_id, $tag_names );
		}

		// Update featured image if provided.
		// @phpstan-ignore-next-line
		$featured_image_id = isset( $input['featured_image_id'] ) ? (int) $input['featured_image_id'] : 0;
		if ( $featured_image_id > 0 ) {
			set_post_thumbnail( $post_id, $featured_image_id );
		}

		// Update meta if provided.
		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}

		$updated_post = get_post( $post_id );
		$permalink    = get_permalink( $post_id );

		if ( $switched ) {
			restore_current_blog();
		}

		return [
			'post_id'   => $post_id,
			'permalink' => $permalink ?: '',
			'status'    => $updated_post instanceof WP_Post ? $updated_post->post_status : '',
		];
	}

	/**
	 * Handle the delete-post ability.
	 *
	 * @param array<string, mixed> $input Input with post_id and optional force_delete, site_url.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_delete_post( array $input ) {
		// @phpstan-ignore-next-line
		$post_id      = (int) ( $input['post_id'] ?? 0 );
		$force_delete = (bool) ( $input['force_delete'] ?? false );
		$site_url     = $input['site_url'] ?? '';

		if ( ! $post_id ) {
			return new WP_Error( 'ai_agent_empty_post_id', __( 'post_id is required.', 'gratis-ai-agent' ) );
		}

		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_HOST ) ?? '' ),
				// @phpstan-ignore-next-line
				(string) ( wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/' )
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}
		}

		$post = get_post( $post_id );

		if ( ! ( $post instanceof WP_Post ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return new WP_Error(
				'ai_agent_post_not_found',
				/* translators: %d: post ID */
				sprintf( __( 'Post %d not found.', 'gratis-ai-agent' ), $post_id )
			);
		}

		$title  = $post->post_title;
		$result = wp_delete_post( $post_id, $force_delete );

		if ( $switched ) {
			restore_current_blog();
		}

		if ( ! $result ) {
			return new WP_Error(
				'ai_agent_delete_failed',
				/* translators: %d: post ID */
				sprintf( __( 'Failed to delete post %d.', 'gratis-ai-agent' ), $post_id )
			);
		}

		return [
			'post_id'      => $post_id,
			'title'        => $title,
			'action'       => $force_delete ? 'permanently_deleted' : 'trashed',
			'force_delete' => $force_delete,
		];
	}

	/**
	 * Detect whether content looks like markdown and convert to Gutenberg blocks.
	 *
	 * Handles three cases:
	 * 1. Pure block markup (<!-- wp: ... -->) — returned as-is.
	 * 2. Pure markdown — converted entirely via MarkdownToBlocks.
	 * 3. Mixed content (blocks + markdown) — parsed with parse_blocks(),
	 *    freeform segments containing markdown signals are converted
	 *    individually, real blocks are preserved.
	 *
	 * @param string $content Raw content from the model.
	 * @return string Content ready for post_content (blocks HTML or original).
	 */
	private static function maybe_convert_markdown( string $content ): string {
		if ( '' === $content ) {
			return $content;
		}

		$has_blocks = str_contains( $content, '<!-- wp:' );

		// Mixed content: blocks + potential markdown in freeform segments.
		if ( $has_blocks ) {
			return self::convert_mixed_content( $content );
		}

		// Pure HTML (3+ block-level tags) — leave as-is.
		$html_block_tags = preg_match_all( '/<(?:p|h[1-6]|div|section|ul|ol|table|blockquote|figure|header|footer|article|nav)\b/i', $content );
		if ( $html_block_tags >= 3 ) {
			return $content;
		}

		// Check for markdown signals.
		$markdown_signals = self::count_markdown_signals( $content );

		if ( $markdown_signals < 2 ) {
			return $content;
		}

		return MarkdownToBlocks::convert( $content );
	}

	/**
	 * Count markdown formatting signals in a string.
	 *
	 * @param string $text Text to check for markdown patterns.
	 * @return int Number of distinct markdown patterns found.
	 */
	private static function count_markdown_signals( string $text ): int {
		$signals = 0;

		if ( preg_match( '/^#{1,6}\s+\S/m', $text ) ) {
			++$signals;
		}
		if ( preg_match( '/\*{1,2}[^*\n]+\*{1,2}/', $text ) || preg_match( '/_{1,2}[^_\n]+_{1,2}/', $text ) ) {
			++$signals;
		}
		if ( preg_match( '/^[\-\*]\s+\S/m', $text ) ) {
			++$signals;
		}
		if ( preg_match( '/^\d+\.\s+\S/m', $text ) ) {
			++$signals;
		}
		if ( preg_match( '/\[[^\]]+\]\([^)]+\)/', $text ) ) {
			++$signals;
		}
		if ( str_contains( $text, '```' ) ) {
			++$signals;
		}

		return $signals;
	}

	/**
	 * Handle mixed content: real blocks are preserved, freeform segments
	 * containing markdown are converted to blocks individually.
	 *
	 * Uses WordPress core's parse_blocks() to split the content. Freeform
	 * blocks (blockName === null) that contain markdown signals get their
	 * innerHTML converted via MarkdownToBlocks. Everything else is
	 * re-serialized as-is.
	 *
	 * @param string $content Mixed block + markdown content.
	 * @return string Fully blockified content.
	 */
	private static function convert_mixed_content( string $content ): string {
		$parsed = parse_blocks( $content );
		$output = '';

		foreach ( $parsed as $block ) {
			$block_name  = $block['blockName'] ?? null;
			$block_inner = $block['innerHTML'] ?? '';

			// Real block — serialize as-is.
			if ( null !== $block_name ) {
				// @phpstan-ignore-next-line
				$output .= serialize_block( $block ) . "\n\n";
				continue;
			}

			// Freeform block — check if it contains markdown worth converting.
			$trimmed = trim( (string) $block_inner );
			if ( '' === $trimmed ) {
				continue;
			}

			$signals = self::count_markdown_signals( $trimmed );
			if ( $signals >= 1 ) {
				// Convert the freeform markdown segment to blocks.
				$output .= MarkdownToBlocks::convert( $trimmed ) . "\n\n";
			} elseif ( '' !== trim( wp_strip_all_tags( $trimmed ) ) ) {
				// Plain text without markdown — wrap in a paragraph block.
				// Only if it has actual visible content (not just whitespace/newlines).
				// @phpstan-ignore-next-line
				$output .= serialize_block( MarkdownToBlocks::make_paragraph( $trimmed ) ) . "\n\n";
			}
		}

		return trim( $output );
	}

	/**
	 * Resolve an array of category IDs or names to an array of IDs.
	 *
	 * @param array<int|string> $categories Array of category IDs or names.
	 * @return int[] Array of category IDs.
	 */
	private static function resolve_category_ids( array $categories ): array {
		$ids = [];
		foreach ( $categories as $cat ) {
			if ( is_numeric( $cat ) ) {
				$ids[] = (int) $cat;
			} else {
				$term = get_term_by( 'name', sanitize_text_field( (string) $cat ), 'category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$ids[] = $term->term_id;
				}
			}
		}
		return $ids;
	}
}
