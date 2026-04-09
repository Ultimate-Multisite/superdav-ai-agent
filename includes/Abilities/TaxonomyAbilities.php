<?php

declare(strict_types=1);
/**
 * Taxonomy management abilities for the AI agent.
 *
 * Provides custom taxonomy registration with persistence, term CRUD,
 * and taxonomy listing. Custom taxonomies registered via these abilities
 * are stored in a network-scoped site option so they survive page loads
 * and are re-registered on every `init` hook across all subsites.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use WP_Error;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy abilities coordinator.
 *
 * Handles registration of all taxonomy-related abilities and re-registration
 * of persisted custom taxonomies on `init`.
 *
 * @since 1.0.0
 */
class TaxonomyAbilities {

	/**
	 * Option key used to persist custom taxonomy definitions.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'gratis_ai_agent_custom_taxonomies';

	/**
	 * Register taxonomy abilities and hook persisted taxonomies into init.
	 */
	public static function register(): void {
		// Re-register persisted custom taxonomies on every init.
		add_action( 'init', [ __CLASS__, 'restore_persisted_taxonomies' ], 5 );

		// Register abilities with the Abilities API.
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Re-register all custom taxonomies that were previously persisted.
	 *
	 * Called on `init` (priority 5) so taxonomies are available before
	 * most plugins and themes run their own init hooks. Validates each
	 * stored entry before calling register_taxonomy() to avoid fatals on
	 * malformed rows.
	 */
	public static function restore_persisted_taxonomies(): void {
		$definitions = self::get_persisted_taxonomies();

		foreach ( $definitions as $taxonomy => $args ) {
			// Skip if already registered or taxonomy key is invalid.
			if ( ! is_string( $taxonomy ) || '' === $taxonomy ) {
				continue;
			}

			if ( taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Validate and normalise object_types.
			$raw_types = $args['object_types'] ?? null;
			if ( ! is_array( $raw_types ) || empty( $raw_types ) ) {
				$raw_types = [ 'post' ];
			}

			$object_types = [];
			foreach ( $raw_types as $type ) {
				if ( is_string( $type ) && '' !== $type ) {
					$object_types[] = sanitize_key( $type );
				}
			}

			if ( empty( $object_types ) ) {
				$object_types = [ 'post' ];
			}

			// Validate register_args.
			$register_args = $args['args'] ?? null;
			if ( ! is_array( $register_args ) ) {
				$register_args = [];
			}

			register_taxonomy( $taxonomy, $object_types, $register_args );
		}
	}

	/**
	 * Register all taxonomy management abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/register-taxonomy',
			[
				'label'               => __( 'Register Custom Taxonomy', 'gratis-ai-agent' ),
				'description'         => __( 'Register a new custom taxonomy and persist it so it survives page loads. The taxonomy will be re-registered automatically on every page load. Supports hierarchical (category-like) and flat (tag-like) taxonomies.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'taxonomy'     => [
							'type'        => 'string',
							'description' => 'Taxonomy key. Must not exceed 32 characters and may only contain lowercase alphanumeric characters, dashes, and underscores.',
						],
						'object_types' => [
							'type'        => 'array',
							'description' => 'Post types to attach this taxonomy to (e.g. ["post", "page"]).',
							'items'       => [ 'type' => 'string' ],
						],
						'label'        => [
							'type'        => 'string',
							'description' => 'Human-readable name for the taxonomy (plural), e.g. "Genres".',
						],
						'singular'     => [
							'type'        => 'string',
							'description' => 'Singular human-readable name, e.g. "Genre".',
						],
						'hierarchical' => [
							'type'        => 'boolean',
							'description' => 'Whether the taxonomy is hierarchical (like categories). Default false (like tags).',
						],
						'public'       => [
							'type'        => 'boolean',
							'description' => 'Whether the taxonomy is publicly queryable. Default true.',
						],
						'show_in_rest' => [
							'type'        => 'boolean',
							'description' => 'Whether to expose the taxonomy in the REST API. Default true.',
						],
						'rewrite'      => [
							'type'        => 'object',
							'description' => 'Rewrite rules. Pass {"slug": "genre"} to customise the URL slug.',
						],
					],
					'required'   => [ 'taxonomy', 'object_types', 'label' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'taxonomy'     => [ 'type' => 'string' ],
						'object_types' => [ 'type' => 'array' ],
						'label'        => [ 'type' => 'string' ],
						'hierarchical' => [ 'type' => 'boolean' ],
						'persisted'    => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_register_taxonomy' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/list-taxonomies',
			[
				'label'               => __( 'List Taxonomies', 'gratis-ai-agent' ),
				'description'         => __( 'List all registered taxonomies, including built-in ones (category, post_tag) and any custom taxonomies. Optionally filter by object type.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [
							'type'        => 'string',
							'description' => 'Filter taxonomies by the post type they are attached to (e.g. "post"). Omit to list all.',
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'taxonomies' => [ 'type' => 'array' ],
						'total'      => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_taxonomies' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/get-terms',
			[
				'label'               => __( 'Get Terms', 'gratis-ai-agent' ),
				'description'         => __( 'Retrieve terms from a taxonomy. Supports pagination and search.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'taxonomy'   => [
							'type'        => 'string',
							'description' => 'The taxonomy to retrieve terms from (e.g. "category", "post_tag", or a custom taxonomy slug).',
						],
						'search'     => [
							'type'        => 'string',
							'description' => 'Search string to filter terms by name.',
						],
						'per_page'   => [
							'type'        => 'integer',
							'description' => 'Number of terms to return (default: 50, max: 200).',
						],
						'page'       => [
							'type'        => 'integer',
							'description' => 'Page number for pagination (default: 1).',
						],
						'hide_empty' => [
							'type'        => 'boolean',
							'description' => 'Whether to hide terms with no posts. Default false.',
						],
					],
					'required'   => [ 'taxonomy' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'terms'    => [ 'type' => 'array' ],
						'total'    => [ 'type' => 'integer' ],
						'taxonomy' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_get_terms' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/create-term',
			[
				'label'               => __( 'Create Term', 'gratis-ai-agent' ),
				'description'         => __( 'Create a new term in a taxonomy. Returns the new term ID and slug.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'taxonomy'    => [
							'type'        => 'string',
							'description' => 'The taxonomy to add the term to.',
						],
						'name'        => [
							'type'        => 'string',
							'description' => 'The term name.',
						],
						'slug'        => [
							'type'        => 'string',
							'description' => 'Optional URL slug. Auto-generated from name if omitted.',
						],
						'description' => [
							'type'        => 'string',
							'description' => 'Optional term description.',
						],
						'parent'      => [
							'type'        => 'integer',
							'description' => 'Parent term ID for hierarchical taxonomies. Default 0 (top-level).',
						],
					],
					'required'   => [ 'taxonomy', 'name' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'term_id'  => [ 'type' => 'integer' ],
						'slug'     => [ 'type' => 'string' ],
						'name'     => [ 'type' => 'string' ],
						'taxonomy' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_create_term' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/update-term',
			[
				'label'               => __( 'Update Term', 'gratis-ai-agent' ),
				'description'         => __( 'Update an existing term in a taxonomy. Only provided fields are changed.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'term_id'     => [
							'type'        => 'integer',
							'description' => 'The ID of the term to update.',
						],
						'taxonomy'    => [
							'type'        => 'string',
							'description' => 'The taxonomy the term belongs to.',
						],
						'name'        => [
							'type'        => 'string',
							'description' => 'New term name.',
						],
						'slug'        => [
							'type'        => 'string',
							'description' => 'New URL slug.',
						],
						'description' => [
							'type'        => 'string',
							'description' => 'New term description.',
						],
						'parent'      => [
							'type'        => 'integer',
							'description' => 'New parent term ID.',
						],
					],
					'required'   => [ 'term_id', 'taxonomy' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'term_id'  => [ 'type' => 'integer' ],
						'slug'     => [ 'type' => 'string' ],
						'name'     => [ 'type' => 'string' ],
						'taxonomy' => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_update_term' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/delete-term',
			[
				'label'               => __( 'Delete Term', 'gratis-ai-agent' ),
				'description'         => __( 'Delete a term from a taxonomy. Posts assigned to this term will have the term removed.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'term_id'  => [
							'type'        => 'integer',
							'description' => 'The ID of the term to delete.',
						],
						'taxonomy' => [
							'type'        => 'string',
							'description' => 'The taxonomy the term belongs to.',
						],
					],
					'required'   => [ 'term_id', 'taxonomy' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'term_id'  => [ 'type' => 'integer' ],
						'name'     => [ 'type' => 'string' ],
						'taxonomy' => [ 'type' => 'string' ],
						'deleted'  => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_delete_term' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
				},
			]
		);
	}

	// ─── Handlers ────────────────────────────────────────────────────────────

	/**
	 * Handle the register-taxonomy ability.
	 *
	 * @param array<string, mixed> $input Input with taxonomy, object_types, label, etc.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_register_taxonomy( array $input ): array|WP_Error {
		// @phpstan-ignore-next-line
		$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );

		if ( empty( $taxonomy ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_taxonomy', __( 'taxonomy is required.', 'gratis-ai-agent' ) );
		}

		if ( strlen( $taxonomy ) > 32 ) {
			return new WP_Error(
				'gratis_ai_agent_taxonomy_too_long',
				__( 'Taxonomy key must not exceed 32 characters.', 'gratis-ai-agent' )
			);
		}

		$object_types = $input['object_types'] ?? [];
		if ( ! is_array( $object_types ) || empty( $object_types ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_object_types', __( 'object_types must be a non-empty array.', 'gratis-ai-agent' ) );
		}

		// @phpstan-ignore-next-line
		$object_types = array_map( 'sanitize_key', $object_types );

		// @phpstan-ignore-next-line
		$label = sanitize_text_field( $input['label'] ?? '' );
		// @phpstan-ignore-next-line
		$singular = sanitize_text_field( $input['singular'] ?? $label );

		$hierarchical = (bool) ( $input['hierarchical'] ?? false );
		$public       = (bool) ( $input['public'] ?? true );
		$show_in_rest = (bool) ( $input['show_in_rest'] ?? true );

		$register_args = [
			'labels'       => [
				'name'          => $label,
				'singular_name' => $singular,
			],
			'hierarchical' => $hierarchical,
			'public'       => $public,
			'show_in_rest' => $show_in_rest,
		];

		// Optional rewrite rules.
		if ( isset( $input['rewrite'] ) && is_array( $input['rewrite'] ) ) {
			$register_args['rewrite'] = $input['rewrite'];
		}

		// @phpstan-ignore-next-line
		$result = register_taxonomy( $taxonomy, $object_types, $register_args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Persist the definition so it survives page loads.
		$definitions              = self::get_persisted_taxonomies();
		$definitions[ $taxonomy ] = [
			'object_types' => $object_types,
			'args'         => $register_args,
		];
		update_site_option( self::OPTION_KEY, $definitions );

		return [
			'taxonomy'     => $taxonomy,
			'object_types' => $object_types,
			'label'        => $label,
			'hierarchical' => $hierarchical,
			'persisted'    => true,
		];
	}

	/**
	 * Handle the list-taxonomies ability.
	 *
	 * @param array<string, mixed> $input Input with optional object_type filter.
	 * @return array<string, mixed>
	 */
	public static function handle_list_taxonomies( array $input ): array {
		$object_type = isset( $input['object_type'] ) ? sanitize_key( (string) $input['object_type'] ) : '';

		$args = [];
		if ( ! empty( $object_type ) ) {
			$args['object_type'] = [ $object_type ];
		}

		$taxonomies = get_taxonomies( $args, 'objects' );

		$result = [];
		foreach ( $taxonomies as $taxonomy_obj ) {
			$result[] = [
				'name'         => $taxonomy_obj->name,
				'label'        => $taxonomy_obj->label,
				'hierarchical' => $taxonomy_obj->hierarchical,
				'public'       => $taxonomy_obj->public,
				'show_in_rest' => $taxonomy_obj->show_in_rest,
				'object_types' => $taxonomy_obj->object_type,
				'built_in'     => $taxonomy_obj->_builtin,
			];
		}

		return [
			'taxonomies' => $result,
			'total'      => count( $result ),
		];
	}

	/**
	 * Handle the get-terms ability.
	 *
	 * @param array<string, mixed> $input Input with taxonomy, optional search/pagination.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_get_terms( array $input ): array|WP_Error {
		// @phpstan-ignore-next-line
		$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );

		if ( empty( $taxonomy ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_taxonomy', __( 'taxonomy is required.', 'gratis-ai-agent' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'gratis_ai_agent_taxonomy_not_found',
				/* translators: %s: taxonomy slug */
				sprintf( __( 'Taxonomy "%s" does not exist.', 'gratis-ai-agent' ), $taxonomy )
			);
		}

		$per_page   = max( 1, min( (int) ( $input['per_page'] ?? 50 ), 200 ) );
		$page       = max( (int) ( $input['page'] ?? 1 ), 1 );
		$hide_empty = (bool) ( $input['hide_empty'] ?? false );
		// @phpstan-ignore-next-line
		$search = sanitize_text_field( $input['search'] ?? '' );

		$query_args = [
			'taxonomy'   => $taxonomy,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'hide_empty' => $hide_empty,
		];

		if ( ! empty( $search ) ) {
			$query_args['search'] = $search;
		}

		$terms = get_terms( $query_args );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$result = [];
		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}
			$result[] = [
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => $term->parent,
				'count'       => $term->count,
			];
		}

		// Get total count (without pagination).
		$count_args           = $query_args;
		$count_args['number'] = 0;
		$count_args['offset'] = 0;
		$count_args['fields'] = 'count';
		$count_result         = get_terms( $count_args );
		$total                = is_wp_error( $count_result ) ? count( $result ) : (int) $count_result;

		return [
			'terms'    => $result,
			'total'    => $total,
			'taxonomy' => $taxonomy,
		];
	}

	/**
	 * Handle the create-term ability.
	 *
	 * @param array<string, mixed> $input Input with taxonomy, name, optional slug/description/parent.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_create_term( array $input ): array|WP_Error {
		// @phpstan-ignore-next-line
		$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );
		// @phpstan-ignore-next-line
		$name = sanitize_text_field( $input['name'] ?? '' );

		if ( empty( $taxonomy ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_taxonomy', __( 'taxonomy is required.', 'gratis-ai-agent' ) );
		}

		if ( empty( $name ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_name', __( 'name is required.', 'gratis-ai-agent' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'gratis_ai_agent_taxonomy_not_found',
				/* translators: %s: taxonomy slug */
				sprintf( __( 'Taxonomy "%s" does not exist.', 'gratis-ai-agent' ), $taxonomy )
			);
		}

		$term_args = [];

		if ( ! empty( $input['slug'] ) ) {
			// @phpstan-ignore-next-line
			$term_args['slug'] = sanitize_title( (string) $input['slug'] );
		}

		if ( isset( $input['description'] ) ) {
			// @phpstan-ignore-next-line
			$term_args['description'] = sanitize_textarea_field( (string) $input['description'] );
		}

		if ( isset( $input['parent'] ) ) {
			$term_args['parent'] = (int) $input['parent'];
		}

		$result = wp_insert_term( $name, $taxonomy, $term_args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = isset( $result['term_id'] ) ? intval( $result['term_id'] ) : 0;
		$term    = get_term( $term_id, $taxonomy );

		if ( ! ( $term instanceof WP_Term ) ) {
			return new WP_Error( 'gratis_ai_agent_term_not_found', __( 'Term created but could not be retrieved.', 'gratis-ai-agent' ) );
		}

		return [
			'term_id'  => $term->term_id,
			'slug'     => $term->slug,
			'name'     => $term->name,
			'taxonomy' => $taxonomy,
		];
	}

	/**
	 * Handle the update-term ability.
	 *
	 * @param array<string, mixed> $input Input with term_id, taxonomy, and fields to update.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_update_term( array $input ): array|WP_Error {
		$term_id = (int) ( $input['term_id'] ?? 0 );
		// @phpstan-ignore-next-line
		$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );

		if ( ! $term_id ) {
			return new WP_Error( 'gratis_ai_agent_empty_term_id', __( 'term_id is required.', 'gratis-ai-agent' ) );
		}

		if ( empty( $taxonomy ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_taxonomy', __( 'taxonomy is required.', 'gratis-ai-agent' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'gratis_ai_agent_taxonomy_not_found',
				/* translators: %s: taxonomy slug */
				sprintf( __( 'Taxonomy "%s" does not exist.', 'gratis-ai-agent' ), $taxonomy )
			);
		}

		$existing = get_term( $term_id, $taxonomy );

		if ( ! ( $existing instanceof WP_Term ) ) {
			return new WP_Error(
				'gratis_ai_agent_term_not_found',
				/* translators: %d: term ID */
				sprintf( __( 'Term %d not found.', 'gratis-ai-agent' ), $term_id )
			);
		}

		$update_args = [];

		if ( isset( $input['name'] ) ) {
			// @phpstan-ignore-next-line
			$update_args['name'] = sanitize_text_field( (string) $input['name'] );
		}

		if ( isset( $input['slug'] ) ) {
			// @phpstan-ignore-next-line
			$update_args['slug'] = sanitize_title( (string) $input['slug'] );
		}

		if ( isset( $input['description'] ) ) {
			// @phpstan-ignore-next-line
			$update_args['description'] = sanitize_textarea_field( (string) $input['description'] );
		}

		if ( isset( $input['parent'] ) ) {
			$update_args['parent'] = (int) $input['parent'];
		}

		if ( empty( $update_args ) ) {
			return new WP_Error( 'gratis_ai_agent_no_fields', __( 'No fields provided to update.', 'gratis-ai-agent' ) );
		}

		$result = wp_update_term( $term_id, $taxonomy, $update_args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$updated_term_id = isset( $result['term_id'] ) ? (int) $result['term_id'] : $term_id;
		$term            = get_term( $updated_term_id, $taxonomy );

		if ( ! ( $term instanceof WP_Term ) ) {
			return new WP_Error( 'gratis_ai_agent_term_not_found', __( 'Term updated but could not be retrieved.', 'gratis-ai-agent' ) );
		}

		return [
			'term_id'  => $term->term_id,
			'slug'     => $term->slug,
			'name'     => $term->name,
			'taxonomy' => $taxonomy,
		];
	}

	/**
	 * Handle the delete-term ability.
	 *
	 * @param array<string, mixed> $input Input with term_id and taxonomy.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_delete_term( array $input ): array|WP_Error {
		$term_id = (int) ( $input['term_id'] ?? 0 );
		// @phpstan-ignore-next-line
		$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );

		if ( ! $term_id ) {
			return new WP_Error( 'gratis_ai_agent_empty_term_id', __( 'term_id is required.', 'gratis-ai-agent' ) );
		}

		if ( empty( $taxonomy ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_taxonomy', __( 'taxonomy is required.', 'gratis-ai-agent' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'gratis_ai_agent_taxonomy_not_found',
				/* translators: %s: taxonomy slug */
				sprintf( __( 'Taxonomy "%s" does not exist.', 'gratis-ai-agent' ), $taxonomy )
			);
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! ( $term instanceof WP_Term ) ) {
			return new WP_Error(
				'gratis_ai_agent_term_not_found',
				/* translators: %d: term ID */
				sprintf( __( 'Term %d not found.', 'gratis-ai-agent' ), $term_id )
			);
		}

		$name   = $term->name;
		$result = wp_delete_term( $term_id, $taxonomy );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new WP_Error(
				'gratis_ai_agent_delete_failed',
				/* translators: %d: term ID */
				sprintf( __( 'Failed to delete term %d.', 'gratis-ai-agent' ), $term_id )
			);
		}

		return [
			'term_id'  => $term_id,
			'name'     => $name,
			'taxonomy' => $taxonomy,
			'deleted'  => true,
		];
	}

	// ─── Persistence helpers ──────────────────────────────────────────────────

	/**
	 * Get all persisted custom taxonomy definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_persisted_taxonomies(): array {
		$definitions = get_site_option( self::OPTION_KEY, [] );
		if ( ! is_array( $definitions ) ) {
			return [];
		}
		/** @var array<string, array<string, mixed>> $definitions */
		return $definitions;
	}
}
