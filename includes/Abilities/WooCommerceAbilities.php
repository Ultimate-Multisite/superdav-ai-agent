<?php

declare(strict_types=1);
/**
 * WooCommerce abilities for the AI agent.
 *
 * Provides product CRUD, order queries, and store statistics via the
 * WooCommerce REST API (when available) or direct WooCommerce PHP API.
 *
 * All write operations require `manage_woocommerce` capability.
 * Read operations require `view_woocommerce_reports` or `manage_woocommerce`.
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
 * WooCommerce abilities registry and static proxy.
 *
 * @since 1.2.0
 */
class WooCommerceAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Get a product or list products.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_products( array $input = [] ) {
		$ability = new WooGetProductsAbility(
			'gratis-ai-agent/woo-get-products',
			[
				'label'       => __( 'Get WooCommerce Products', 'gratis-ai-agent' ),
				'description' => __( 'List or search WooCommerce products. Filter by status, category, stock status, or search term.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Create a product.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_create_product( array $input = [] ) {
		$ability = new WooCreateProductAbility(
			'gratis-ai-agent/woo-create-product',
			[
				'label'       => __( 'Create WooCommerce Product', 'gratis-ai-agent' ),
				'description' => __( 'Create a new WooCommerce product (simple, variable, grouped, or external).', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Update a product.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_update_product( array $input = [] ) {
		$ability = new WooUpdateProductAbility(
			'gratis-ai-agent/woo-update-product',
			[
				'label'       => __( 'Update WooCommerce Product', 'gratis-ai-agent' ),
				'description' => __( 'Update an existing WooCommerce product by ID. Supports partial updates.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Delete a product.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_delete_product( array $input = [] ) {
		$ability = new WooDeleteProductAbility(
			'gratis-ai-agent/woo-delete-product',
			[
				'label'       => __( 'Delete WooCommerce Product', 'gratis-ai-agent' ),
				'description' => __( 'Delete a WooCommerce product by ID. Optionally force-delete (bypass trash).', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Query orders.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_orders( array $input = [] ) {
		$ability = new WooGetOrdersAbility(
			'gratis-ai-agent/woo-get-orders',
			[
				'label'       => __( 'Get WooCommerce Orders', 'gratis-ai-agent' ),
				'description' => __( 'Query WooCommerce orders. Filter by status, customer, date range, or product.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Get store statistics.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_store_stats( array $input = [] ) {
		$ability = new WooGetStoreStatsAbility(
			'gratis-ai-agent/woo-get-store-stats',
			[
				'label'       => __( 'Get WooCommerce Store Stats', 'gratis-ai-agent' ),
				'description' => __( 'Retrieve WooCommerce store statistics: total revenue, order counts, top products, and customer counts for a date range.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register all WooCommerce abilities (only when WooCommerce is active).
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/woo-get-products',
			[
				'label'         => __( 'Get WooCommerce Products', 'gratis-ai-agent' ),
				'description'   => __( 'List or search WooCommerce products. Filter by status, category, stock status, or search term.', 'gratis-ai-agent' ),
				'ability_class' => WooGetProductsAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/woo-create-product',
			[
				'label'         => __( 'Create WooCommerce Product', 'gratis-ai-agent' ),
				'description'   => __( 'Create a new WooCommerce product (simple, variable, grouped, or external).', 'gratis-ai-agent' ),
				'ability_class' => WooCreateProductAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/woo-update-product',
			[
				'label'         => __( 'Update WooCommerce Product', 'gratis-ai-agent' ),
				'description'   => __( 'Update an existing WooCommerce product by ID. Supports partial updates.', 'gratis-ai-agent' ),
				'ability_class' => WooUpdateProductAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/woo-delete-product',
			[
				'label'         => __( 'Delete WooCommerce Product', 'gratis-ai-agent' ),
				'description'   => __( 'Delete a WooCommerce product by ID. Optionally force-delete (bypass trash).', 'gratis-ai-agent' ),
				'ability_class' => WooDeleteProductAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/woo-get-orders',
			[
				'label'         => __( 'Get WooCommerce Orders', 'gratis-ai-agent' ),
				'description'   => __( 'Query WooCommerce orders. Filter by status, customer, date range, or product.', 'gratis-ai-agent' ),
				'ability_class' => WooGetOrdersAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/woo-get-store-stats',
			[
				'label'         => __( 'Get WooCommerce Store Stats', 'gratis-ai-agent' ),
				'description'   => __( 'Retrieve WooCommerce store statistics: total revenue, order counts, top products, and customer counts for a date range.', 'gratis-ai-agent' ),
				'ability_class' => WooGetStoreStatsAbility::class,
			]
		);
	}

	/**
	 * Check whether WooCommerce is active and its core classes are available.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Serialize a WC_Product into a compact array for API responses.
	 *
	 * @param \WC_Product $product The product object.
	 * @return array<string,mixed>
	 */
	public static function serialize_product( \WC_Product $product ): array {
		return [
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'stock_status'      => $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'manage_stock'      => $product->get_manage_stock(),
			'categories'        => array_map(
				static fn( $term ) => [
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				],
				self::get_term_array( $product->get_id(), 'product_cat' )
			),
			'tags'              => array_map(
				static fn( $term ) => [
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				],
				self::get_term_array( $product->get_id(), 'product_tag' )
			),
			'permalink'         => get_permalink( $product->get_id() ),
			'date_created'      => $product->get_date_created() ? $product->get_date_created()->date( 'c' ) : null,
			'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->date( 'c' ) : null,
		];
	}

	/**
	 * Safely retrieve terms for a post, returning an empty array on failure.
	 *
	 * `get_the_terms()` can return false, an array, or a WP_Error. The ?: []
	 * shorthand only handles falsy values; WP_Error is truthy and would cause
	 * `array_map()` to receive an object instead of an array.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy name.
	 * @return \WP_Term[] Array of term objects, or empty array on failure.
	 */
	private static function get_term_array( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms : [];
	}

	/**
	 * Serialize a WC_Order into a compact array for API responses.
	 *
	 * @param \WC_Order $order The order object.
	 * @return array<string,mixed>
	 */
	public static function serialize_order( \WC_Order $order ): array {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$items[] = [
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
			];
		}

		return [
			'id'             => $order->get_id(),
			'status'         => $order->get_status(),
			'currency'       => $order->get_currency(),
			'total'          => $order->get_total(),
			'subtotal'       => $order->get_subtotal(),
			'total_tax'      => $order->get_total_tax(),
			'shipping_total' => $order->get_shipping_total(),
			'customer_id'    => $order->get_customer_id(),
			'customer_email' => $order->get_billing_email(),
			'billing_name'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'items'          => $items,
			'item_count'     => count( $items ),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
			'date_modified'  => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : null,
			'payment_method' => $order->get_payment_method_title(),
		];
	}
}

// ─── Ability implementations ──────────────────────────────────────────────────

/**
 * Get WooCommerce Products ability.
 *
 * @since 1.2.0
 */
class WooGetProductsAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get WooCommerce Products', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'List or search WooCommerce products. Filter by status, category, stock status, or search term.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'search'       => [
					'type'        => 'string',
					'description' => 'Search term to filter products by name or SKU.',
				],
				'status'       => [
					'type'        => 'string',
					'enum'        => [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ],
					'description' => 'Product status filter. Defaults to "publish".',
				],
				'category'     => [
					'type'        => 'string',
					'description' => 'Category slug to filter products.',
				],
				'stock_status' => [
					'type'        => 'string',
					'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
					'description' => 'Stock status filter.',
				],
				'per_page'     => [
					'type'        => 'integer',
					'description' => 'Number of products to return (1–100, default 20).',
					'minimum'     => 1,
					'maximum'     => 100,
				],
				'page'         => [
					'type'        => 'integer',
					'description' => 'Page number for pagination (default 1).',
					'minimum'     => 1,
				],
				'product_id'   => [
					'type'        => 'integer',
					'description' => 'Fetch a single product by ID.',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'products'    => [ 'type' => 'array' ],
				'total'       => [ 'type' => 'integer' ],
				'total_pages' => [ 'type' => 'integer' ],
				'page'        => [ 'type' => 'integer' ],
				'per_page'    => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		if ( ! WooCommerceAbilities::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active.', 'gratis-ai-agent' ) );
		}

		// Single product lookup.
		// @phpstan-ignore-next-line
		$product_id = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return new WP_Error(
					'product_not_found',
					sprintf(
						/* translators: %d: product ID */
						__( 'Product %d not found.', 'gratis-ai-agent' ),
						$product_id
					)
				);
			}
			return [
				'products'    => [ WooCommerceAbilities::serialize_product( $product ) ],
				'total'       => 1,
				'total_pages' => 1,
				'page'        => 1,
				'per_page'    => 1,
			];
		}

		// @phpstan-ignore-next-line
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		// @phpstan-ignore-next-line
		$page   = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status = $input['status'] ?? 'publish';

		$args = [
			'status'   => $status,
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		];

		if ( ! empty( $input['search'] ) ) {
			// @phpstan-ignore-next-line
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['category'] ) ) {
			// @phpstan-ignore-next-line
			$args['category'] = [ sanitize_text_field( $input['category'] ) ];
		}

		if ( ! empty( $input['stock_status'] ) ) {
			// @phpstan-ignore-next-line
			$args['stock_status'] = sanitize_text_field( $input['stock_status'] );
		}

		$result   = wc_get_products( $args );
		$products = [];

		if ( ! $result instanceof \stdClass ) {
			return [
				'products'    => [],
				'total'       => 0,
				'total_pages' => 0,
				'page'        => $page,
				'per_page'    => $per_page,
			];
		}

		foreach ( $result->products as $product ) {
			if ( $product instanceof \WC_Product ) {
				$products[] = WooCommerceAbilities::serialize_product( $product );
			}
		}

		return [
			'products'    => $products,
			'total'       => (int) $result->total,
			'total_pages' => (int) $result->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'view_woocommerce_reports' ) || current_user_can( 'manage_woocommerce' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Create WooCommerce Product ability.
 *
 * @since 1.2.0
 */
class WooCreateProductAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Create WooCommerce Product', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Create a new WooCommerce product (simple, variable, grouped, or external).', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'              => [
					'type'        => 'string',
					'description' => 'Product name (required).',
				],
				'type'              => [
					'type'        => 'string',
					'enum'        => [ 'simple', 'variable', 'grouped', 'external' ],
					'description' => 'Product type. Defaults to "simple".',
				],
				'status'            => [
					'type'        => 'string',
					'enum'        => [ 'publish', 'draft', 'pending', 'private' ],
					'description' => 'Product status. Defaults to "publish".',
				],
				'description'       => [
					'type'        => 'string',
					'description' => 'Full product description (HTML allowed).',
				],
				'short_description' => [
					'type'        => 'string',
					'description' => 'Short product description (HTML allowed).',
				],
				'sku'               => [
					'type'        => 'string',
					'description' => 'Stock Keeping Unit identifier.',
				],
				'regular_price'     => [
					'type'        => 'string',
					'description' => 'Regular price (e.g. "19.99").',
				],
				'sale_price'        => [
					'type'        => 'string',
					'description' => 'Sale price (e.g. "14.99"). Leave empty for no sale.',
				],
				'manage_stock'      => [
					'type'        => 'boolean',
					'description' => 'Whether to manage stock for this product.',
				],
				'stock_quantity'    => [
					'type'        => 'integer',
					'description' => 'Stock quantity (requires manage_stock: true).',
				],
				'stock_status'      => [
					'type'        => 'string',
					'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
					'description' => 'Stock status.',
				],
				'categories'        => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Array of category term IDs.',
				],
				'tags'              => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Array of tag term IDs.',
				],
				'virtual'           => [
					'type'        => 'boolean',
					'description' => 'Whether the product is virtual (no shipping).',
				],
				'downloadable'      => [
					'type'        => 'boolean',
					'description' => 'Whether the product is downloadable.',
				],
			],
			'required'   => [ 'name' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'product' => [ 'type' => 'object' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		if ( ! WooCommerceAbilities::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active.', 'gratis-ai-agent' ) );
		}

		// @phpstan-ignore-next-line
		$name = sanitize_text_field( $input['name'] ?? '' );
		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', __( 'Product name is required.', 'gratis-ai-agent' ) );
		}

		$type    = $input['type'] ?? 'simple';
		$product = new \WC_Product_Simple();

		// Map type to the correct WC product class.
		switch ( $type ) {
			case 'variable':
				$product = new \WC_Product_Variable();
				break;
			case 'grouped':
				$product = new \WC_Product_Grouped();
				break;
			case 'external':
				$product = new \WC_Product_External();
				break;
			default:
				$product = new \WC_Product_Simple();
		}

		$product->set_name( $name );
		// @phpstan-ignore-next-line
		$product->set_status( $input['status'] ?? 'publish' );

		if ( isset( $input['description'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_description( wp_kses_post( $input['description'] ) );
		}

		if ( isset( $input['short_description'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_short_description( wp_kses_post( $input['short_description'] ) );
		}

		if ( isset( $input['sku'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_sku( sanitize_text_field( $input['sku'] ) );
		}

		if ( isset( $input['regular_price'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_regular_price( wc_format_decimal( $input['regular_price'] ) );
		}

		if ( isset( $input['sale_price'] ) && '' !== $input['sale_price'] ) {
			// @phpstan-ignore-next-line
			$product->set_sale_price( wc_format_decimal( $input['sale_price'] ) );
		}

		if ( isset( $input['manage_stock'] ) ) {
			$product->set_manage_stock( (bool) $input['manage_stock'] );
		}

		if ( isset( $input['stock_quantity'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_stock_quantity( (int) $input['stock_quantity'] );
		}

		if ( isset( $input['stock_status'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_stock_status( sanitize_text_field( $input['stock_status'] ) );
		}

		if ( isset( $input['virtual'] ) ) {
			$product->set_virtual( (bool) $input['virtual'] );
		}

		if ( isset( $input['downloadable'] ) ) {
			$product->set_downloadable( (bool) $input['downloadable'] );
		}

		if ( ! empty( $input['categories'] ) && is_array( $input['categories'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_category_ids( array_map( 'intval', $input['categories'] ) );
		}

		if ( ! empty( $input['tags'] ) && is_array( $input['tags'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_tag_ids( array_map( 'intval', $input['tags'] ) );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create product.', 'gratis-ai-agent' ) );
		}

		$saved = wc_get_product( $product_id );
		if ( ! $saved ) {
			return new WP_Error( 'create_failed', __( 'Product created but could not be retrieved.', 'gratis-ai-agent' ) );
		}

		return [
			'product' => WooCommerceAbilities::serialize_product( $saved ),
			'message' => sprintf(
				/* translators: 1: product name, 2: product ID */
				__( 'Product "%1$s" created successfully (ID: %2$d).', 'gratis-ai-agent' ),
				$name,
				$product_id
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Update WooCommerce Product ability.
 *
 * @since 1.2.0
 */
class WooUpdateProductAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Update WooCommerce Product', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Update an existing WooCommerce product by ID. Supports partial updates — only provided fields are changed.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'product_id'        => [
					'type'        => 'integer',
					'description' => 'ID of the product to update (required).',
				],
				'name'              => [
					'type'        => 'string',
					'description' => 'New product name.',
				],
				'status'            => [
					'type'        => 'string',
					'enum'        => [ 'publish', 'draft', 'pending', 'private', 'trash' ],
					'description' => 'New product status.',
				],
				'description'       => [
					'type'        => 'string',
					'description' => 'Full product description (HTML allowed).',
				],
				'short_description' => [
					'type'        => 'string',
					'description' => 'Short product description (HTML allowed).',
				],
				'sku'               => [
					'type'        => 'string',
					'description' => 'New SKU.',
				],
				'regular_price'     => [
					'type'        => 'string',
					'description' => 'New regular price.',
				],
				'sale_price'        => [
					'type'        => 'string',
					'description' => 'New sale price. Pass empty string to remove sale.',
				],
				'manage_stock'      => [
					'type'        => 'boolean',
					'description' => 'Whether to manage stock.',
				],
				'stock_quantity'    => [
					'type'        => 'integer',
					'description' => 'New stock quantity.',
				],
				'stock_status'      => [
					'type'        => 'string',
					'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
					'description' => 'New stock status.',
				],
				'categories'        => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Replace category term IDs.',
				],
				'tags'              => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Replace tag term IDs.',
				],
			],
			'required'   => [ 'product_id' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'product' => [ 'type' => 'object' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		if ( ! WooCommerceAbilities::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active.', 'gratis-ai-agent' ) );
		}

		// @phpstan-ignore-next-line
		$product_id = (int) ( $input['product_id'] ?? 0 );
		if ( $product_id <= 0 ) {
			return new WP_Error( 'missing_product_id', __( 'product_id is required.', 'gratis-ai-agent' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				sprintf(
					/* translators: %d: product ID */
					__( 'Product %d not found.', 'gratis-ai-agent' ),
					$product_id
				)
			);
		}

		if ( isset( $input['name'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_name( sanitize_text_field( $input['name'] ) );
		}

		if ( isset( $input['status'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_status( sanitize_text_field( $input['status'] ) );
		}

		if ( isset( $input['description'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_description( wp_kses_post( $input['description'] ) );
		}

		if ( isset( $input['short_description'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_short_description( wp_kses_post( $input['short_description'] ) );
		}

		if ( isset( $input['sku'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_sku( sanitize_text_field( $input['sku'] ) );
		}

		if ( isset( $input['regular_price'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_regular_price( wc_format_decimal( $input['regular_price'] ) );
		}

		if ( array_key_exists( 'sale_price', $input ) ) {
			// @phpstan-ignore-next-line
			$product->set_sale_price( '' !== $input['sale_price'] ? wc_format_decimal( $input['sale_price'] ) : '' );
		}

		if ( isset( $input['manage_stock'] ) ) {
			$product->set_manage_stock( (bool) $input['manage_stock'] );
		}

		if ( isset( $input['stock_quantity'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_stock_quantity( (int) $input['stock_quantity'] );
		}

		if ( isset( $input['stock_status'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_stock_status( sanitize_text_field( $input['stock_status'] ) );
		}

		if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_category_ids( array_map( 'intval', $input['categories'] ) );
		}

		if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
			// @phpstan-ignore-next-line
			$product->set_tag_ids( array_map( 'intval', $input['tags'] ) );
		}

		$product->save();

		$updated = wc_get_product( $product_id );
		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Product updated but could not be retrieved.', 'gratis-ai-agent' ) );
		}

		return [
			'product' => WooCommerceAbilities::serialize_product( $updated ),
			'message' => sprintf(
				/* translators: %d: product ID */
				__( 'Product %d updated successfully.', 'gratis-ai-agent' ),
				$product_id
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Delete WooCommerce Product ability.
 *
 * @since 1.2.0
 */
class WooDeleteProductAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Delete WooCommerce Product', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Delete a WooCommerce product by ID. By default moves to trash; set force_delete to permanently remove.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'product_id'   => [
					'type'        => 'integer',
					'description' => 'ID of the product to delete (required).',
				],
				'force_delete' => [
					'type'        => 'boolean',
					'description' => 'If true, permanently deletes the product (bypasses trash). Defaults to false.',
				],
			],
			'required'   => [ 'product_id' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'deleted'    => [ 'type' => 'boolean' ],
				'product_id' => [ 'type' => 'integer' ],
				'message'    => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		if ( ! WooCommerceAbilities::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active.', 'gratis-ai-agent' ) );
		}

		// @phpstan-ignore-next-line
		$product_id   = (int) ( $input['product_id'] ?? 0 );
		$force_delete = (bool) ( $input['force_delete'] ?? false );

		if ( $product_id <= 0 ) {
			return new WP_Error( 'missing_product_id', __( 'product_id is required.', 'gratis-ai-agent' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				sprintf(
					/* translators: %d: product ID */
					__( 'Product %d not found.', 'gratis-ai-agent' ),
					$product_id
				)
			);
		}

		$product_name = $product->get_name();
		$deleted      = $product->delete( $force_delete );

		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				sprintf(
					/* translators: %d: product ID */
					__( 'Failed to delete product %d.', 'gratis-ai-agent' ),
					$product_id
				)
			);
		}

		$action = $force_delete
			? __( 'permanently deleted', 'gratis-ai-agent' )
			: __( 'moved to trash', 'gratis-ai-agent' );

		return [
			'deleted'    => true,
			'product_id' => $product_id,
			'message'    => sprintf(
				/* translators: 1: product name, 2: product ID, 3: action (deleted/trashed) */
				__( 'Product "%1$s" (ID: %2$d) %3$s.', 'gratis-ai-agent' ),
				$product_name,
				$product_id,
				$action
			),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Get WooCommerce Orders ability.
 *
 * @since 1.2.0
 */
class WooGetOrdersAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get WooCommerce Orders', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Query WooCommerce orders. Filter by status, customer, date range, or product.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'order_id'       => [
					'type'        => 'integer',
					'description' => 'Fetch a single order by ID.',
				],
				'status'         => [
					'type'        => 'string',
					'description' => 'Order status filter (e.g. "processing", "completed", "pending", "on-hold", "cancelled", "refunded", "failed", "any"). Defaults to "any".',
				],
				'customer_id'    => [
					'type'        => 'integer',
					'description' => 'Filter orders by customer user ID.',
				],
				'customer_email' => [
					'type'        => 'string',
					'description' => 'Filter orders by customer billing email.',
				],
				'date_after'     => [
					'type'        => 'string',
					'description' => 'Return orders created after this date (ISO 8601, e.g. "2024-01-01").',
				],
				'date_before'    => [
					'type'        => 'string',
					'description' => 'Return orders created before this date (ISO 8601, e.g. "2024-12-31").',
				],
				'product_id'     => [
					'type'        => 'integer',
					'description' => 'Filter orders containing a specific product ID.',
				],
				'per_page'       => [
					'type'        => 'integer',
					'description' => 'Number of orders to return (1–100, default 20).',
					'minimum'     => 1,
					'maximum'     => 100,
				],
				'page'           => [
					'type'        => 'integer',
					'description' => 'Page number for pagination (default 1).',
					'minimum'     => 1,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'orders'      => [ 'type' => 'array' ],
				'total'       => [ 'type' => 'integer' ],
				'total_pages' => [ 'type' => 'integer' ],
				'page'        => [ 'type' => 'integer' ],
				'per_page'    => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		if ( ! WooCommerceAbilities::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active.', 'gratis-ai-agent' ) );
		}

		// Single order lookup.
		// @phpstan-ignore-next-line
		$order_id = isset( $input['order_id'] ) ? (int) $input['order_id'] : 0;
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || ! ( $order instanceof \WC_Order ) ) {
				return new WP_Error(
					'order_not_found',
					sprintf(
						/* translators: %d: order ID */
						__( 'Order %d not found.', 'gratis-ai-agent' ),
						$order_id
					)
				);
			}
			return [
				'orders'      => [ WooCommerceAbilities::serialize_order( $order ) ],
				'total'       => 1,
				'total_pages' => 1,
				'page'        => 1,
				'per_page'    => 1,
			];
		}

		// @phpstan-ignore-next-line
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		// @phpstan-ignore-next-line
		$page   = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status = $input['status'] ?? 'any';

		$args = [
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'type'     => 'shop_order',
		];

		if ( 'any' !== $status ) {
			// @phpstan-ignore-next-line
			$args['status'] = sanitize_text_field( $status );
		}

		if ( ! empty( $input['customer_id'] ) ) {
			// @phpstan-ignore-next-line
			$args['customer_id'] = (int) $input['customer_id'];
		}

		if ( ! empty( $input['customer_email'] ) ) {
			// @phpstan-ignore-next-line
			$args['billing_email'] = sanitize_email( $input['customer_email'] );
		}

		if ( ! empty( $input['date_after'] ) ) {
			// @phpstan-ignore-next-line
			$args['date_created'] = '>' . sanitize_text_field( $input['date_after'] );
		}

		if ( ! empty( $input['date_before'] ) ) {
			// If both are set, use a range.
			if ( ! empty( $input['date_after'] ) ) {
				// @phpstan-ignore-next-line
				$args['date_created'] = sanitize_text_field( $input['date_after'] ) . '...' . sanitize_text_field( $input['date_before'] );
			} else {
				// @phpstan-ignore-next-line
				$args['date_created'] = '<' . sanitize_text_field( $input['date_before'] );
			}
		}

		if ( ! empty( $input['product_id'] ) ) {
			// @phpstan-ignore-next-line
			$args['product_id'] = (int) $input['product_id'];
		}

		$result = wc_get_orders( $args );
		$orders = [];

		if ( ! $result instanceof \stdClass ) {
			return [
				'orders'      => [],
				'total'       => 0,
				'total_pages' => 0,
				'page'        => $page,
				'per_page'    => $per_page,
			];
		}

		foreach ( $result->orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$orders[] = WooCommerceAbilities::serialize_order( $order );
			}
		}

		return [
			'orders'      => $orders,
			'total'       => (int) $result->total,
			'total_pages' => (int) $result->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'view_woocommerce_reports' ) || current_user_can( 'manage_woocommerce' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Get WooCommerce Store Stats ability.
 *
 * @since 1.2.0
 */
class WooGetStoreStatsAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get WooCommerce Store Stats', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Retrieve WooCommerce store statistics: total revenue, order counts by status, top-selling products, and customer counts for a date range.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'date_after'         => [
					'type'        => 'string',
					'description' => 'Start of date range (ISO 8601, e.g. "2024-01-01"). Defaults to 30 days ago.',
				],
				'date_before'        => [
					'type'        => 'string',
					'description' => 'End of date range (ISO 8601, e.g. "2024-12-31"). Defaults to today.',
				],
				'top_products_limit' => [
					'type'        => 'integer',
					'description' => 'Number of top-selling products to return (1–20, default 5).',
					'minimum'     => 1,
					'maximum'     => 20,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'period'         => [ 'type' => 'object' ],
				'revenue'        => [ 'type' => 'object' ],
				'orders'         => [ 'type' => 'object' ],
				'customers'      => [ 'type' => 'object' ],
				'top_products'   => [ 'type' => 'array' ],
				'product_counts' => [ 'type' => 'object' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		if ( ! WooCommerceAbilities::is_woocommerce_active() ) {
			return new WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active.', 'gratis-ai-agent' ) );
		}

		$date_after  = $input['date_after'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$date_before = $input['date_before'] ?? gmdate( 'Y-m-d' );
		// @phpstan-ignore-next-line
		$top_limit = min( 20, max( 1, (int) ( $input['top_products_limit'] ?? 5 ) ) );

		// Validate dates.
		// @phpstan-ignore-next-line
		$after_ts = strtotime( $date_after );
		// @phpstan-ignore-next-line
		$before_ts = strtotime( $date_before );

		if ( false === $after_ts || false === $before_ts ) {
			return new WP_Error( 'invalid_date', __( 'Invalid date format. Use ISO 8601 (YYYY-MM-DD).', 'gratis-ai-agent' ) );
		}

		// Query completed/processing orders in the date range.
		$revenue_statuses = [ 'wc-completed', 'wc-processing' ];

		$revenue_args = [
			'limit'        => -1,
			'type'         => 'shop_order',
			'status'       => $revenue_statuses,
			// @phpstan-ignore-next-line
			'date_created' => $date_after . '...' . $date_before,
			'return'       => 'ids',
		];

		$revenue_order_ids = wc_get_orders( $revenue_args );

		$total_revenue  = 0.0;
		$total_tax      = 0.0;
		$total_shipping = 0.0;
		$product_sales  = [];

		foreach ( is_array( $revenue_order_ids ) ? $revenue_order_ids : [] as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$total_revenue  += (float) $order->get_total();
			$total_tax      += (float) $order->get_total_tax();
			$total_shipping += (float) $order->get_shipping_total();

			foreach ( $order->get_items() as $item ) {
				/** @var \WC_Order_Item_Product $item */
				$pid = $item->get_product_id();
				if ( ! isset( $product_sales[ $pid ] ) ) {
					$product_sales[ $pid ] = [
						'product_id' => $pid,
						'name'       => $item->get_name(),
						'quantity'   => 0,
						'revenue'    => 0.0,
					];
				}
				$product_sales[ $pid ]['quantity'] += $item->get_quantity();
				$product_sales[ $pid ]['revenue']  += (float) $item->get_total();
			}
		}

		// Sort top products by quantity sold.
		usort( $product_sales, static fn( $a, $b ) => $b['quantity'] <=> $a['quantity'] );
		$top_products = array_slice( $product_sales, 0, $top_limit );

		// Order counts by status.
		$all_statuses = wc_get_order_statuses();
		$order_counts = [];

		foreach ( array_keys( $all_statuses ) as $status_key ) {
			$count_args = [
				'limit'        => -1,
				'type'         => 'shop_order',
				'status'       => [ $status_key ],
				// @phpstan-ignore-next-line
				'date_created' => $date_after . '...' . $date_before,
				'return'       => 'ids',
			];
			$ids = wc_get_orders( $count_args );
			// Strip the "wc-" prefix for cleaner output.
			$clean_status                  = ltrim( $status_key, 'wc-' );
			$order_counts[ $clean_status ] = is_array( $ids ) ? count( $ids ) : 0;
		}

		$total_orders = array_sum( $order_counts );

		// Customer counts.
		$new_customer_args = [
			'limit'        => -1,
			'type'         => 'shop_order',
			// @phpstan-ignore-next-line
			'date_created' => $date_after . '...' . $date_before,
			'return'       => 'ids',
		];
		$all_period_ids    = wc_get_orders( $new_customer_args );

		$unique_customers = [];
		$guest_orders     = 0;

		foreach ( is_array( $all_period_ids ) ? $all_period_ids : [] as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$cid = $order->get_customer_id();
			if ( $cid > 0 ) {
				$unique_customers[ $cid ] = true;
			} else {
				++$guest_orders;
			}
		}

		// Product catalogue counts.
		$published_count = (int) wp_count_posts( 'product' )->publish;
		$draft_count     = (int) wp_count_posts( 'product' )->draft;

		return [
			'period'         => [
				'date_after'  => $date_after,
				'date_before' => $date_before,
			],
			'revenue'        => [
				'total'    => round( $total_revenue, 2 ),
				'tax'      => round( $total_tax, 2 ),
				'shipping' => round( $total_shipping, 2 ),
				'net'      => round( $total_revenue - $total_tax - $total_shipping, 2 ),
				'currency' => get_woocommerce_currency(),
			],
			'orders'         => [
				'total'     => $total_orders,
				'by_status' => $order_counts,
			],
			'customers'      => [
				'unique_registered' => count( $unique_customers ),
				'guest_orders'      => $guest_orders,
			],
			'top_products'   => $top_products,
			'product_counts' => [
				'published' => $published_count,
				'draft'     => $draft_count,
			],
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'view_woocommerce_reports' ) || current_user_can( 'manage_woocommerce' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}
