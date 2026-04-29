<?php

declare(strict_types=1);
/**
 * Event Trigger Registry — catalog of available WordPress hooks with metadata.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Automations;

class EventTriggerRegistry {

	/**
	 * Get all available triggers grouped by category.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function get_all(): array {
		$triggers = array_merge(
			self::get_wordpress_triggers(),
			self::get_woocommerce_triggers(),
			self::get_form_triggers()
		);

		/**
		 * Filter available event triggers.
		 *
		 * @param array $triggers Array of trigger definitions.
		 */
		/** @var list<array<string, mixed>> $filtered */
		$filtered = apply_filters( 'sd_ai_agent_event_triggers', $triggers );
		return $filtered;
	}

	/**
	 * Get triggers grouped by category for the UI.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_grouped(): array {
		$all = self::get_all();
		/** @var array<string, array{label: string, triggers: list<array<string, mixed>>}> $grouped */
		$grouped = [];

		foreach ( $all as $trigger ) {
			if ( ! is_array( $trigger ) ) {
				continue;
			}
			$cat = isset( $trigger['category'] ) && is_string( $trigger['category'] ) ? $trigger['category'] : 'other';
			if ( ! isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ] = [
					'label'    => self::get_category_label( $cat ),
					'triggers' => [],
				];
			}
			$grouped[ $cat ]['triggers'][] = $trigger;
		}

		return $grouped;
	}

	/**
	 * Get a trigger definition by hook name.
	 *
	 * @param string $hook_name WordPress hook name.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $hook_name ): ?array {
		foreach ( self::get_all() as $trigger ) {
			if ( is_array( $trigger ) && isset( $trigger['hook_name'] ) && $trigger['hook_name'] === $hook_name ) {
				return $trigger;
			}
		}
		return null;
	}

	/**
	 * WordPress core triggers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function get_wordpress_triggers(): array {
		return [
			[
				'hook_name'    => 'transition_post_status',
				'label'        => __( 'Post Status Changed', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a post status transitions (e.g. draft to publish).', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'new_status', 'old_status', 'post' ],
				'placeholders' => [
					'new_status'   => __( 'New post status', 'sd-ai-agent' ),
					'old_status'   => __( 'Previous post status', 'sd-ai-agent' ),
					'post.ID'      => __( 'Post ID', 'sd-ai-agent' ),
					'post.title'   => __( 'Post title', 'sd-ai-agent' ),
					'post.type'    => __( 'Post type', 'sd-ai-agent' ),
					'post.author'  => __( 'Post author ID', 'sd-ai-agent' ),
					'post.content' => __( 'Post content (excerpt)', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'post_type'  => __( 'Post type equals', 'sd-ai-agent' ),
					'new_status' => __( 'New status equals', 'sd-ai-agent' ),
					'old_status' => __( 'Old status equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'user_register',
				'label'        => __( 'New User Registered', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a new user account is created.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_id' ],
				'placeholders' => [
					'user.id'           => __( 'User ID', 'sd-ai-agent' ),
					'user.login'        => __( 'Username', 'sd-ai-agent' ),
					'user.email'        => __( 'User email', 'sd-ai-agent' ),
					'user.display_name' => __( 'Display name', 'sd-ai-agent' ),
					'user.role'         => __( 'User role', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'role' => __( 'User role equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'wp_login',
				'label'        => __( 'User Login', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a user successfully logs in.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_login', 'user' ],
				'placeholders' => [
					'user.login'        => __( 'Username', 'sd-ai-agent' ),
					'user.email'        => __( 'User email', 'sd-ai-agent' ),
					'user.display_name' => __( 'Display name', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'comment_post',
				'label'        => __( 'New Comment', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a new comment is posted.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'comment_id', 'comment_approved' ],
				'placeholders' => [
					'comment.id'           => __( 'Comment ID', 'sd-ai-agent' ),
					'comment.author'       => __( 'Comment author name', 'sd-ai-agent' ),
					'comment.author_email' => __( 'Comment author email', 'sd-ai-agent' ),
					'comment.content'      => __( 'Comment text', 'sd-ai-agent' ),
					'comment.post_id'      => __( 'Post ID', 'sd-ai-agent' ),
					'comment.approved'     => __( 'Approval status', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'approved' => __( 'Approval status equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'delete_post',
				'label'        => __( 'Post Deleted', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a post is permanently deleted.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'post_id' ],
				'placeholders' => [
					'post.ID'    => __( 'Post ID', 'sd-ai-agent' ),
					'post.title' => __( 'Post title', 'sd-ai-agent' ),
					'post.type'  => __( 'Post type', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'post_type' => __( 'Post type equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'activated_plugin',
				'label'        => __( 'Plugin Activated', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a plugin is activated.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'plugin' ],
				'placeholders' => [
					'plugin' => __( 'Plugin file path', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'deactivated_plugin',
				'label'        => __( 'Plugin Deactivated', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a plugin is deactivated.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'plugin' ],
				'placeholders' => [
					'plugin' => __( 'Plugin file path', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'switch_theme',
				'label'        => __( 'Theme Switched', 'sd-ai-agent' ),
				'description'  => __( 'Fires when the active theme is changed.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'new_name', 'new_theme' ],
				'placeholders' => [
					'new_name' => __( 'New theme name', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'profile_update',
				'label'        => __( 'User Profile Updated', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a user profile is updated.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_id', 'old_user_data' ],
				'placeholders' => [
					'user.id'           => __( 'User ID', 'sd-ai-agent' ),
					'user.email'        => __( 'User email', 'sd-ai-agent' ),
					'user.display_name' => __( 'Display name', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'wp_login_failed',
				'label'        => __( 'Failed Login Attempt', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a login attempt fails.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'username' ],
				'placeholders' => [
					'username' => __( 'Attempted username', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'added_option',
				'label'        => __( 'Option Added', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a new option is added to the database.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'option_name', 'option_value' ],
				'placeholders' => [
					'option_name' => __( 'Option name', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'option_name' => __( 'Option name equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'updated_option',
				'label'        => __( 'Option Updated', 'sd-ai-agent' ),
				'description'  => __( 'Fires when an existing option is updated.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'option_name', 'old_value', 'new_value' ],
				'placeholders' => [
					'option_name' => __( 'Option name', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'option_name' => __( 'Option name equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'add_attachment',
				'label'        => __( 'Media Uploaded', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a new media file is uploaded.', 'sd-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'post_id' ],
				'placeholders' => [
					'post.ID'    => __( 'Attachment ID', 'sd-ai-agent' ),
					'post.title' => __( 'Attachment title', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
		];
	}

	/**
	 * WooCommerce triggers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function get_woocommerce_triggers(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}

		return [
			[
				'hook_name'    => 'woocommerce_new_order',
				'label'        => __( 'New Order Created', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a new WooCommerce order is created.', 'sd-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id' ],
				'placeholders' => [
					'order.id'     => __( 'Order ID', 'sd-ai-agent' ),
					'order.total'  => __( 'Order total', 'sd-ai-agent' ),
					'order.status' => __( 'Order status', 'sd-ai-agent' ),
					'order.email'  => __( 'Customer email', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_order_status_changed',
				'label'        => __( 'Order Status Changed', 'sd-ai-agent' ),
				'description'  => __( 'Fires when an order status changes.', 'sd-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id', 'old_status', 'new_status' ],
				'placeholders' => [
					'order.id'   => __( 'Order ID', 'sd-ai-agent' ),
					'old_status' => __( 'Previous status', 'sd-ai-agent' ),
					'new_status' => __( 'New status', 'sd-ai-agent' ),
				],
				'conditions'   => [
					'new_status' => __( 'New status equals', 'sd-ai-agent' ),
					'old_status' => __( 'Old status equals', 'sd-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'woocommerce_low_stock',
				'label'        => __( 'Product Low Stock', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a product reaches low stock threshold.', 'sd-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'product' ],
				'placeholders' => [
					'product.id'    => __( 'Product ID', 'sd-ai-agent' ),
					'product.name'  => __( 'Product name', 'sd-ai-agent' ),
					'product.stock' => __( 'Stock quantity', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_payment_complete',
				'label'        => __( 'Payment Complete', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a payment is completed.', 'sd-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id' ],
				'placeholders' => [
					'order.id'    => __( 'Order ID', 'sd-ai-agent' ),
					'order.total' => __( 'Order total', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_product_on_backorder',
				'label'        => __( 'Product On Backorder', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a product goes on backorder.', 'sd-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'item' ],
				'placeholders' => [
					'product.name' => __( 'Product name', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_refund_created',
				'label'        => __( 'Refund Created', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a refund is created.', 'sd-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'refund_id', 'args' ],
				'placeholders' => [
					'refund_id' => __( 'Refund ID', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			],
		];
	}

	/**
	 * Form plugin triggers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function get_form_triggers(): array {
		$triggers = [];

		// Contact Form 7.
		if ( defined( 'WPCF7_VERSION' ) ) {
			$triggers[] = [
				'hook_name'    => 'wpcf7_mail_sent',
				'label'        => __( 'CF7 Form Submitted', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a Contact Form 7 submission email is sent.', 'sd-ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'contact_form' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'sd-ai-agent' ),
					'form.id'    => __( 'Form ID', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		// Gravity Forms.
		if ( class_exists( 'GFForms' ) ) {
			$triggers[] = [
				'hook_name'    => 'gform_after_submission',
				'label'        => __( 'Gravity Form Submitted', 'sd-ai-agent' ),
				'description'  => __( 'Fires after a Gravity Forms entry is created.', 'sd-ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'entry', 'form' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'sd-ai-agent' ),
					'form.id'    => __( 'Form ID', 'sd-ai-agent' ),
					'entry.id'   => __( 'Entry ID', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		// WPForms.
		if ( defined( 'WPFORMS_VERSION' ) ) {
			$triggers[] = [
				'hook_name'    => 'wpforms_process_complete',
				'label'        => __( 'WPForms Form Submitted', 'sd-ai-agent' ),
				'description'  => __( 'Fires when a WPForms entry is processed.', 'sd-ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'fields', 'entry', 'form_data', 'entry_id' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'sd-ai-agent' ),
					'entry_id'   => __( 'Entry ID', 'sd-ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		return $triggers;
	}

	/**
	 * Get a human-readable category label.
	 *
	 * @param string $category Category slug.
	 * @return string
	 */
	private static function get_category_label( string $category ): string {
		$labels = [
			'wordpress'   => __( 'WordPress', 'sd-ai-agent' ),
			'woocommerce' => __( 'WooCommerce', 'sd-ai-agent' ),
			'forms'       => __( 'Forms', 'sd-ai-agent' ),
			'other'       => __( 'Other', 'sd-ai-agent' ),
		];

		return $labels[ $category ] ?? ucfirst( $category );
	}
}
