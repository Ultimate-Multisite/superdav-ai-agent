<?php

declare(strict_types=1);
/**
 * Navigation menu management abilities for the AI agent.
 *
 * Provides abilities to create, list, and delete WordPress navigation menus,
 * add/update/remove menu items, and assign menus to theme locations.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Navigation menu management abilities.
 *
 * Wraps WordPress nav menu functions (wp_create_nav_menu, wp_update_nav_menu_item,
 * wp_delete_nav_menu, set_theme_mod) to expose them as AI abilities.
 *
 * @since 1.3.3
 */
class MenuAbilities {

	/**
	 * Register all menu management abilities.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all navigation menu abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/create-menu',
			[
				'label'               => __( 'Create Navigation Menu', 'gratis-ai-agent' ),
				'description'         => __( 'Create a new WordPress navigation menu with the given name. Returns the new menu ID and name.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'name' => [
							'type'        => 'string',
							'description' => 'The display name for the new navigation menu (e.g. "Main Menu", "Footer Links").',
						],
					],
					'required'   => [ 'name' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'menu_id' => [ 'type' => 'integer' ],
						'name'    => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_create_menu' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/list-menus',
			[
				'label'               => __( 'List Navigation Menus', 'gratis-ai-agent' ),
				'description'         => __( 'List all registered WordPress navigation menus, including their IDs, names, item counts, and assigned theme locations.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => (object) [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'menus'     => [ 'type' => 'array' ],
						'total'     => [ 'type' => 'integer' ],
						'locations' => [ 'type' => 'object' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_menus' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/delete-menu',
			[
				'label'               => __( 'Delete Navigation Menu', 'gratis-ai-agent' ),
				'description'         => __( 'Delete a WordPress navigation menu by ID or name. This removes the menu and all its items permanently.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'menu_id' => [
							'type'        => 'integer',
							'description' => 'The ID of the menu to delete. Use list-menus to find menu IDs.',
						],
					],
					'required'   => [ 'menu_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'menu_id' => [ 'type' => 'integer' ],
						'deleted' => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_delete_menu' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/add-menu-item',
			[
				'label'               => __( 'Add Menu Item', 'gratis-ai-agent' ),
				'description'         => __( 'Add a new item to a WordPress navigation menu. Supports page, post, custom URL, and category link types. Returns the new menu item ID.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'menu_id'   => [
							'type'        => 'integer',
							'description' => 'The ID of the menu to add the item to.',
						],
						'type'      => [
							'type'        => 'string',
							'description' => 'The type of menu item: "custom" (URL), "post_type" (page/post), or "taxonomy" (category/tag). Default: "custom".',
							'enum'        => [ 'custom', 'post_type', 'taxonomy' ],
						],
						'object'    => [
							'type'        => 'string',
							'description' => 'For post_type items: the post type slug (e.g. "page", "post"). For taxonomy items: the taxonomy slug (e.g. "category", "post_tag"). Not needed for custom items.',
						],
						'object_id' => [
							'type'        => 'integer',
							'description' => 'For post_type or taxonomy items: the ID of the post or term to link to.',
						],
						'title'     => [
							'type'        => 'string',
							'description' => 'The display title for the menu item. For post_type/taxonomy items, defaults to the post/term title if omitted.',
						],
						'url'       => [
							'type'        => 'string',
							'description' => 'The URL for custom menu items.',
						],
						'parent_id' => [
							'type'        => 'integer',
							'description' => 'The menu item ID of the parent item (for nested/dropdown menus). Default: 0 (top-level).',
						],
						'position'  => [
							'type'        => 'integer',
							'description' => 'The sort order position of the item within the menu. Default: 0 (appended at end).',
						],
						'target'    => [
							'type'        => 'string',
							'description' => 'Link target attribute. Use "_blank" to open in a new tab. Default: "" (same window).',
						],
						'classes'   => [
							'type'        => 'string',
							'description' => 'Space-separated CSS classes to add to the menu item\'s <li> element.',
						],
					],
					'required'   => [ 'menu_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'item_id' => [ 'type' => 'integer' ],
						'menu_id' => [ 'type' => 'integer' ],
						'title'   => [ 'type' => 'string' ],
						'url'     => [ 'type' => 'string' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_add_menu_item' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/update-menu-item',
			[
				'label'               => __( 'Update Menu Item', 'gratis-ai-agent' ),
				'description'         => __( 'Update an existing navigation menu item. Change its title, URL, position, parent, target, or CSS classes.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'menu_id'   => [
							'type'        => 'integer',
							'description' => 'The ID of the menu that contains the item.',
						],
						'item_id'   => [
							'type'        => 'integer',
							'description' => 'The ID of the menu item to update.',
						],
						'title'     => [
							'type'        => 'string',
							'description' => 'New display title for the menu item.',
						],
						'url'       => [
							'type'        => 'string',
							'description' => 'New URL for the menu item.',
						],
						'parent_id' => [
							'type'        => 'integer',
							'description' => 'New parent menu item ID (0 for top-level).',
						],
						'position'  => [
							'type'        => 'integer',
							'description' => 'New sort order position.',
						],
						'target'    => [
							'type'        => 'string',
							'description' => 'New link target attribute (e.g. "_blank").',
						],
						'classes'   => [
							'type'        => 'string',
							'description' => 'New space-separated CSS classes for the menu item.',
						],
					],
					'required'   => [ 'menu_id', 'item_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'item_id' => [ 'type' => 'integer' ],
						'menu_id' => [ 'type' => 'integer' ],
						'updated' => [ 'type' => 'boolean' ],
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
				'execute_callback'    => [ __CLASS__, 'handle_update_menu_item' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/remove-menu-item',
			[
				'label'               => __( 'Remove Menu Item', 'gratis-ai-agent' ),
				'description'         => __( 'Remove an item from a WordPress navigation menu by item ID.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'item_id' => [
							'type'        => 'integer',
							'description' => 'The ID of the menu item to remove.',
						],
					],
					'required'   => [ 'item_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'item_id' => [ 'type' => 'integer' ],
						'removed' => [ 'type' => 'boolean' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_remove_menu_item' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/assign-menu-to-location',
			[
				'label'               => __( 'Assign Menu to Theme Location', 'gratis-ai-agent' ),
				'description'         => __( 'Assign a navigation menu to a registered theme location (e.g. "primary", "footer"). Use list-menus to see available locations and menu IDs.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'menu_id'  => [
							'type'        => 'integer',
							'description' => 'The ID of the menu to assign.',
						],
						'location' => [
							'type'        => 'string',
							'description' => 'The theme location slug to assign the menu to (e.g. "primary", "footer", "social"). Use list-menus to see registered locations.',
						],
					],
					'required'   => [ 'menu_id', 'location' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'menu_id'  => [ 'type' => 'integer' ],
						'location' => [ 'type' => 'string' ],
						'assigned' => [ 'type' => 'boolean' ],
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
				'execute_callback'    => [ __CLASS__, 'handle_assign_menu_to_location' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'gratis-ai-agent/list-menu-items',
			[
				'label'               => __( 'List Menu Items', 'gratis-ai-agent' ),
				'description'         => __( 'List all items in a WordPress navigation menu, including their IDs, titles, URLs, types, parent IDs, and positions.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'menu_id' => [
							'type'        => 'integer',
							'description' => 'The ID of the menu to list items for.',
						],
					],
					'required'   => [ 'menu_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'items'   => [ 'type' => 'array' ],
						'total'   => [ 'type' => 'integer' ],
						'menu_id' => [ 'type' => 'integer' ],
					],
				],
				'meta'                => [
					'annotations'  => [
						'readonly'   => true,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_menu_items' ],
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);
	}

	/**
	 * Handle the create-menu ability.
	 *
	 * @param array<string, mixed> $input Input with menu name.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_create_menu( array $input ) {
		$name = sanitize_text_field( $input['name'] ?? '' );

		if ( empty( $name ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_menu_name', __( 'Menu name is required.', 'gratis-ai-agent' ) );
		}

		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		return [
			'menu_id' => (int) $menu_id,
			'name'    => $name,
		];
	}

	/**
	 * Handle the list-menus ability.
	 *
	 * @param array<string, mixed> $input Input (unused).
	 * @return array<string, mixed>
	 */
	public static function handle_list_menus( array $input ) {
		$menus = wp_get_nav_menus();

		if ( ! is_array( $menus ) ) {
			$menus = [];
		}

		// Get current theme location assignments.
		$locations      = get_nav_menu_locations();
		$location_names = get_registered_nav_menus();

		// Build a reverse map: menu_id => [location_slug, ...].
		$menu_locations = [];
		foreach ( $locations as $location_slug => $menu_id ) {
			if ( ! isset( $menu_locations[ $menu_id ] ) ) {
				$menu_locations[ $menu_id ] = [];
			}
			$menu_locations[ $menu_id ][] = $location_slug;
		}

		$result = [];
		foreach ( $menus as $menu ) {
			$result[] = [
				'menu_id'    => (int) $menu->term_id,
				'name'       => $menu->name,
				'slug'       => $menu->slug,
				'item_count' => (int) $menu->count,
				'locations'  => $menu_locations[ $menu->term_id ] ?? [],
			];
		}

		// Build registered locations map with labels and current assignment.
		$locations_info = [];
		foreach ( $location_names as $slug => $label ) {
			$locations_info[ $slug ] = [
				'label'            => $label,
				'assigned_menu_id' => isset( $locations[ $slug ] ) ? (int) $locations[ $slug ] : null,
			];
		}

		return [
			'menus'     => $result,
			'total'     => count( $result ),
			'locations' => $locations_info,
		];
	}

	/**
	 * Handle the delete-menu ability.
	 *
	 * @param array<string, mixed> $input Input with menu_id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_delete_menu( array $input ) {
		$menu_id = (int) ( $input['menu_id'] ?? 0 );

		if ( $menu_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_menu_id', __( 'A valid menu_id is required.', 'gratis-ai-agent' ) );
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu || is_wp_error( $menu ) ) {
			return new WP_Error(
				'gratis_ai_agent_menu_not_found',
				/* translators: %d: menu ID */
				sprintf( __( 'Menu with ID %d was not found.', 'gratis-ai-agent' ), $menu_id )
			);
		}

		$result = wp_delete_nav_menu( $menu_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'menu_id' => $menu_id,
			'deleted' => (bool) $result,
		];
	}

	/**
	 * Handle the add-menu-item ability.
	 *
	 * @param array<string, mixed> $input Input with menu_id and item details.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_add_menu_item( array $input ) {
		$menu_id = (int) ( $input['menu_id'] ?? 0 );

		if ( $menu_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_menu_id', __( 'A valid menu_id is required.', 'gratis-ai-agent' ) );
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu || is_wp_error( $menu ) ) {
			return new WP_Error(
				'gratis_ai_agent_menu_not_found',
				/* translators: %d: menu ID */
				sprintf( __( 'Menu with ID %d was not found.', 'gratis-ai-agent' ), $menu_id )
			);
		}

		$type      = sanitize_text_field( $input['type'] ?? 'custom' );
		$object    = sanitize_text_field( $input['object'] ?? '' );
		$object_id = (int) ( $input['object_id'] ?? 0 );
		$title     = sanitize_text_field( $input['title'] ?? '' );
		$url       = esc_url_raw( $input['url'] ?? '' );
		$parent_id = (int) ( $input['parent_id'] ?? 0 );
		$position  = (int) ( $input['position'] ?? 0 );
		$target    = sanitize_text_field( $input['target'] ?? '' );
		$classes   = sanitize_text_field( $input['classes'] ?? '' );

		// Validate type.
		$allowed_types = [ 'custom', 'post_type', 'taxonomy' ];
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_item_type',
				/* translators: %s: item type */
				sprintf( __( 'Invalid menu item type "%s". Must be one of: custom, post_type, taxonomy.', 'gratis-ai-agent' ), $type )
			);
		}

		// For post_type/taxonomy items, object is required.
		if ( 'custom' !== $type && empty( $object ) ) {
			return new WP_Error(
				'gratis_ai_agent_missing_object',
				__( 'The "object" field is required for post_type and taxonomy menu items.', 'gratis-ai-agent' )
			);
		}

		// For post_type/taxonomy items, object_id is required.
		if ( 'custom' !== $type && $object_id <= 0 ) {
			return new WP_Error(
				'gratis_ai_agent_missing_object_id',
				__( 'The "object_id" field is required for post_type and taxonomy menu items.', 'gratis-ai-agent' )
			);
		}

		// For custom items, URL is required.
		if ( 'custom' === $type && empty( $url ) ) {
			return new WP_Error(
				'gratis_ai_agent_missing_url',
				__( 'The "url" field is required for custom menu items.', 'gratis-ai-agent' )
			);
		}

		$item_data = [
			'menu-item-type'      => $type,
			'menu-item-object'    => $object,
			'menu-item-object-id' => $object_id,
			'menu-item-title'     => $title,
			'menu-item-url'       => $url,
			'menu-item-parent-id' => $parent_id,
			'menu-item-position'  => $position,
			'menu-item-target'    => $target,
			'menu-item-classes'   => $classes,
			'menu-item-status'    => 'publish',
		];

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		// Retrieve the saved item to return accurate data.
		$saved_item = get_post( $item_id );
		$saved_url  = get_post_meta( $item_id, '_menu_item_url', true );

		return [
			'item_id' => (int) $item_id,
			'menu_id' => $menu_id,
			'title'   => $saved_item ? $saved_item->post_title : $title,
			'url'     => is_string( $saved_url ) ? $saved_url : $url,
		];
	}

	/**
	 * Handle the update-menu-item ability.
	 *
	 * @param array<string, mixed> $input Input with menu_id, item_id, and fields to update.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_update_menu_item( array $input ) {
		$menu_id = (int) ( $input['menu_id'] ?? 0 );
		$item_id = (int) ( $input['item_id'] ?? 0 );

		if ( $menu_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_menu_id', __( 'A valid menu_id is required.', 'gratis-ai-agent' ) );
		}

		if ( $item_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_item_id', __( 'A valid item_id is required.', 'gratis-ai-agent' ) );
		}

		// Fetch existing item data to merge with updates.
		$existing_item = get_post( $item_id );
		if ( ! $existing_item || 'nav_menu_item' !== $existing_item->post_type ) {
			return new WP_Error(
				'gratis_ai_agent_item_not_found',
				/* translators: %d: item ID */
				sprintf( __( 'Menu item with ID %d was not found.', 'gratis-ai-agent' ), $item_id )
			);
		}

		// Build update data, preserving existing values for fields not provided.
		$item_data = [
			'menu-item-type'      => get_post_meta( $item_id, '_menu_item_type', true ),
			'menu-item-object'    => get_post_meta( $item_id, '_menu_item_object', true ),
			'menu-item-object-id' => (int) get_post_meta( $item_id, '_menu_item_object_id', true ),
			'menu-item-title'     => $existing_item->post_title,
			'menu-item-url'       => get_post_meta( $item_id, '_menu_item_url', true ),
			'menu-item-parent-id' => (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true ),
			'menu-item-position'  => (int) $existing_item->menu_order,
			'menu-item-target'    => get_post_meta( $item_id, '_menu_item_target', true ),
			'menu-item-classes'   => implode( ' ', (array) get_post_meta( $item_id, '_menu_item_classes', true ) ),
			'menu-item-status'    => 'publish',
		];

		// Apply provided updates.
		if ( isset( $input['title'] ) ) {
			$item_data['menu-item-title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['url'] ) ) {
			$item_data['menu-item-url'] = esc_url_raw( $input['url'] );
		}
		if ( isset( $input['parent_id'] ) ) {
			$item_data['menu-item-parent-id'] = (int) $input['parent_id'];
		}
		if ( isset( $input['position'] ) ) {
			$item_data['menu-item-position'] = (int) $input['position'];
		}
		if ( isset( $input['target'] ) ) {
			$item_data['menu-item-target'] = sanitize_text_field( $input['target'] );
		}
		if ( isset( $input['classes'] ) ) {
			$item_data['menu-item-classes'] = sanitize_text_field( $input['classes'] );
		}

		$result = wp_update_nav_menu_item( $menu_id, $item_id, $item_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'item_id' => $item_id,
			'menu_id' => $menu_id,
			'updated' => true,
		];
	}

	/**
	 * Handle the remove-menu-item ability.
	 *
	 * @param array<string, mixed> $input Input with item_id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_remove_menu_item( array $input ) {
		$item_id = (int) ( $input['item_id'] ?? 0 );

		if ( $item_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_item_id', __( 'A valid item_id is required.', 'gratis-ai-agent' ) );
		}

		$existing_item = get_post( $item_id );
		if ( ! $existing_item || 'nav_menu_item' !== $existing_item->post_type ) {
			return new WP_Error(
				'gratis_ai_agent_item_not_found',
				/* translators: %d: item ID */
				sprintf( __( 'Menu item with ID %d was not found.', 'gratis-ai-agent' ), $item_id )
			);
		}

		$result = wp_delete_post( $item_id, true );

		return [
			'item_id' => $item_id,
			'removed' => (bool) $result,
		];
	}

	/**
	 * Handle the assign-menu-to-location ability.
	 *
	 * @param array<string, mixed> $input Input with menu_id and location.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_assign_menu_to_location( array $input ) {
		$menu_id  = (int) ( $input['menu_id'] ?? 0 );
		$location = sanitize_key( $input['location'] ?? '' );

		if ( $menu_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_menu_id', __( 'A valid menu_id is required.', 'gratis-ai-agent' ) );
		}

		if ( empty( $location ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_location', __( 'A theme location slug is required.', 'gratis-ai-agent' ) );
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu || is_wp_error( $menu ) ) {
			return new WP_Error(
				'gratis_ai_agent_menu_not_found',
				/* translators: %d: menu ID */
				sprintf( __( 'Menu with ID %d was not found.', 'gratis-ai-agent' ), $menu_id )
			);
		}

		// Validate the location is registered.
		$registered_locations = get_registered_nav_menus();
		if ( ! array_key_exists( $location, $registered_locations ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_location',
				/* translators: %s: location slug */
				sprintf( __( 'Theme location "%s" is not registered. Use list-menus to see available locations.', 'gratis-ai-agent' ), $location )
			);
		}

		// Merge with existing location assignments.
		$locations              = get_nav_menu_locations();
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		return [
			'menu_id'  => $menu_id,
			'location' => $location,
			'assigned' => true,
		];
	}

	/**
	 * Handle the list-menu-items ability.
	 *
	 * @param array<string, mixed> $input Input with menu_id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle_list_menu_items( array $input ) {
		$menu_id = (int) ( $input['menu_id'] ?? 0 );

		if ( $menu_id <= 0 ) {
			return new WP_Error( 'gratis_ai_agent_invalid_menu_id', __( 'A valid menu_id is required.', 'gratis-ai-agent' ) );
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu || is_wp_error( $menu ) ) {
			return new WP_Error(
				'gratis_ai_agent_menu_not_found',
				/* translators: %d: menu ID */
				sprintf( __( 'Menu with ID %d was not found.', 'gratis-ai-agent' ), $menu_id )
			);
		}

		$items = wp_get_nav_menu_items( $menu_id );

		if ( ! is_array( $items ) ) {
			$items = [];
		}

		$result = [];
		foreach ( $items as $item ) {
			if ( ! ( $item instanceof \WP_Post ) ) {
				continue;
			}
			$raw_classes = get_post_meta( $item->ID, '_menu_item_classes', true );
			$classes     = is_array( $raw_classes ) ? array_map( 'strval', array_filter( $raw_classes, 'is_string' ) ) : [];
			$result[]    = [
				'item_id'   => (int) $item->ID,
				'title'     => $item->post_title,
				'url'       => (string) get_post_meta( $item->ID, '_menu_item_url', true ),
				'type'      => (string) get_post_meta( $item->ID, '_menu_item_type', true ),
				'object'    => (string) get_post_meta( $item->ID, '_menu_item_object', true ),
				'object_id' => (int) get_post_meta( $item->ID, '_menu_item_object_id', true ),
				'parent_id' => (int) get_post_meta( $item->ID, '_menu_item_menu_item_parent', true ),
				'position'  => (int) $item->menu_order,
				'target'    => (string) get_post_meta( $item->ID, '_menu_item_target', true ),
				'classes'   => implode( ' ', $classes ),
			];
		}

		return [
			'items'   => $result,
			'total'   => count( $result ),
			'menu_id' => $menu_id,
		];
	}
}
