<?php

declare(strict_types=1);
/**
 * Post management abilities for the AI agent.
 *
 * Provides post creation, retrieval, update, and deletion.
 * Ported from the WordPress/ai experiments plugin pattern.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostAbilities {

	/**
	 * Register post abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

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
				'description'         => __( 'Create a new WordPress post or custom post type entry. Supports title, content, excerpt, status, categories, tags, and meta fields.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'title'      => [
							'type'        => 'string',
							'description' => 'The post title.',
						],
						'content'    => [
							'type'        => 'string',
							'description' => 'The post content (HTML or plain text).',
						],
						'excerpt'    => [
							'type'        => 'string',
							'description' => 'Optional post excerpt.',
						],
						'status'     => [
							'type'        => 'string',
							'description' => 'Post status: "draft" (default), "publish", "pending", "private", or "future".',
							'enum'        => [ 'draft', 'publish', 'pending', 'private', 'future' ],
						],
						'post_type'  => [
							'type'        => 'string',
							'description' => 'Post type (default: "post"). Use "page" for pages.',
						],
						'categories' => [
							'type'        => 'array',
							'description' => 'Array of category IDs or names to assign.',
							'items'       => [ 'type' => 'string' ],
						],
						'tags'       => [
							'type'        => 'array',
							'description' => 'Array of tag names to assign.',
							'items'       => [ 'type' => 'string' ],
						],
						'meta'       => [
							'type'        => 'object',
							'description' => 'Key-value pairs of post meta to set.',
						],
						'site_url'   => [
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
						'post_id'    => [
							'type'        => 'integer',
							'description' => 'The ID of the post to update.',
						],
						'title'      => [
							'type'        => 'string',
							'description' => 'New post title.',
						],
						'content'    => [
							'type'        => 'string',
							'description' => 'New post content.',
						],
						'excerpt'    => [
							'type'        => 'string',
							'description' => 'New post excerpt.',
						],
						'status'     => [
							'type'        => 'string',
							'description' => 'New post status.',
							'enum'        => [ 'draft', 'publish', 'pending', 'private', 'future', 'trash' ],
						],
						'categories' => [
							'type'        => 'array',
							'description' => 'Replace categories with this array of IDs or names.',
							'items'       => [ 'type' => 'string' ],
						],
						'tags'       => [
							'type'        => 'array',
							'description' => 'Replace tags with this array of names.',
							'items'       => [ 'type' => 'string' ],
						],
						'meta'       => [
							'type'        => 'object',
							'description' => 'Key-value pairs of post meta to update.',
						],
						'site_url'   => [
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
		$post_id   = (int) ( $input['post_id'] ?? 0 );
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
			'permalink'      => get_permalink( $post_id ),
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
		$title     = sanitize_text_field( $input['title'] ?? '' );
		$content   = wp_kses_post( $input['content'] ?? '' );
		$excerpt   = sanitize_textarea_field( $input['excerpt'] ?? '' );
		$status    = sanitize_text_field( $input['status'] ?? 'draft' );
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
				wp_parse_url( $site_url, PHP_URL_HOST ),
				wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
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
			$cat_ids = self::resolve_category_ids( $categories );
			wp_set_post_categories( $post_id, $cat_ids );
		}

		// Assign tags.
		$tags = $input['tags'] ?? [];
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			$tag_names = array_map( 'sanitize_text_field', $tags );
			wp_set_post_tags( $post_id, $tag_names );
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
			'permalink' => $permalink,
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
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$site_url = $input['site_url'] ?? '';

		if ( ! $post_id ) {
			return new WP_Error( 'ai_agent_empty_post_id', __( 'post_id is required.', 'gratis-ai-agent' ) );
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
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}
		if ( isset( $input['status'] ) ) {
			$allowed_statuses = [ 'draft', 'publish', 'pending', 'private', 'future', 'trash' ];
			$new_status       = sanitize_text_field( $input['status'] );
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
			$cat_ids = self::resolve_category_ids( $input['categories'] );
			wp_set_post_categories( $post_id, $cat_ids );
		}

		// Update tags if provided.
		if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
			$tag_names = array_map( 'sanitize_text_field', $input['tags'] );
			wp_set_post_tags( $post_id, $tag_names );
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
			'permalink' => $permalink,
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
		$post_id      = (int) ( $input['post_id'] ?? 0 );
		$force_delete = (bool) ( $input['force_delete'] ?? false );
		$site_url     = $input['site_url'] ?? '';

		if ( ! $post_id ) {
			return new WP_Error( 'ai_agent_empty_post_id', __( 'post_id is required.', 'gratis-ai-agent' ) );
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
