<?php

declare(strict_types=1);
/**
 * Event Trigger Registry — catalog of available WordPress hooks with metadata.
 *
 * @package AiAgent
 */

namespace AiAgent\Automations;

class EventTriggerRegistry {

	/**
	 * Get all available triggers grouped by category.
	 *
	 * @return array
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
		return apply_filters( 'ai_agent_event_triggers', $triggers );
	}

	/**
	 * Get triggers grouped by category for the UI.
	 *
	 * @return array
	 */
	public static function get_grouped(): array {
		$all     = self::get_all();
		$grouped = [];

		foreach ( $all as $trigger ) {
			$cat = $trigger['category'] ?? 'other';
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
	 * @return array|null
	 */
	public static function get( string $hook_name ): ?array {
		foreach ( self::get_all() as $trigger ) {
			if ( isset( $trigger['hook_name'] ) && $trigger['hook_name'] === $hook_name ) {
				return $trigger;
			}
		}
		return null;
	}

	/**
	 * WordPress core triggers.
	 *
	 * @return array
	 */
	private static function get_wordpress_triggers(): array {
		return [
			[
				'hook_name'    => 'transition_post_status',
				'label'        => __( 'Post Status Changed', 'ai-agent' ),
				'description'  => __( 'Fires when a post status transitions (e.g. draft to publish).', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'new_status', 'old_status', 'post' ],
				'placeholders' => [
					'new_status'   => __( 'New post status', 'ai-agent' ),
					'old_status'   => __( 'Previous post status', 'ai-agent' ),
					'post.ID'      => __( 'Post ID', 'ai-agent' ),
					'post.title'   => __( 'Post title', 'ai-agent' ),
					'post.type'    => __( 'Post type', 'ai-agent' ),
					'post.author'  => __( 'Post author ID', 'ai-agent' ),
					'post.content' => __( 'Post content (excerpt)', 'ai-agent' ),
				],
				'conditions'   => [
					'post_type'  => __( 'Post type equals', 'ai-agent' ),
					'new_status' => __( 'New status equals', 'ai-agent' ),
					'old_status' => __( 'Old status equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'user_register',
				'label'        => __( 'New User Registered', 'ai-agent' ),
				'description'  => __( 'Fires when a new user account is created.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_id' ],
				'placeholders' => [
					'user.id'           => __( 'User ID', 'ai-agent' ),
					'user.login'        => __( 'Username', 'ai-agent' ),
					'user.email'        => __( 'User email', 'ai-agent' ),
					'user.display_name' => __( 'Display name', 'ai-agent' ),
					'user.role'         => __( 'User role', 'ai-agent' ),
				],
				'conditions'   => [
					'role' => __( 'User role equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'wp_login',
				'label'        => __( 'User Login', 'ai-agent' ),
				'description'  => __( 'Fires when a user successfully logs in.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_login', 'user' ],
				'placeholders' => [
					'user.login'        => __( 'Username', 'ai-agent' ),
					'user.email'        => __( 'User email', 'ai-agent' ),
					'user.display_name' => __( 'Display name', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'comment_post',
				'label'        => __( 'New Comment', 'ai-agent' ),
				'description'  => __( 'Fires when a new comment is posted.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'comment_id', 'comment_approved' ],
				'placeholders' => [
					'comment.id'           => __( 'Comment ID', 'ai-agent' ),
					'comment.author'       => __( 'Comment author name', 'ai-agent' ),
					'comment.author_email' => __( 'Comment author email', 'ai-agent' ),
					'comment.content'      => __( 'Comment text', 'ai-agent' ),
					'comment.post_id'      => __( 'Post ID', 'ai-agent' ),
					'comment.approved'     => __( 'Approval status', 'ai-agent' ),
				],
				'conditions'   => [
					'approved' => __( 'Approval status equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'delete_post',
				'label'        => __( 'Post Deleted', 'ai-agent' ),
				'description'  => __( 'Fires when a post is permanently deleted.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'post_id' ],
				'placeholders' => [
					'post.ID'    => __( 'Post ID', 'ai-agent' ),
					'post.title' => __( 'Post title', 'ai-agent' ),
					'post.type'  => __( 'Post type', 'ai-agent' ),
				],
				'conditions'   => [
					'post_type' => __( 'Post type equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'activated_plugin',
				'label'        => __( 'Plugin Activated', 'ai-agent' ),
				'description'  => __( 'Fires when a plugin is activated.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'plugin' ],
				'placeholders' => [
					'plugin' => __( 'Plugin file path', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'deactivated_plugin',
				'label'        => __( 'Plugin Deactivated', 'ai-agent' ),
				'description'  => __( 'Fires when a plugin is deactivated.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'plugin' ],
				'placeholders' => [
					'plugin' => __( 'Plugin file path', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'switch_theme',
				'label'        => __( 'Theme Switched', 'ai-agent' ),
				'description'  => __( 'Fires when the active theme is changed.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'new_name', 'new_theme' ],
				'placeholders' => [
					'new_name' => __( 'New theme name', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'profile_update',
				'label'        => __( 'User Profile Updated', 'ai-agent' ),
				'description'  => __( 'Fires when a user profile is updated.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_id', 'old_user_data' ],
				'placeholders' => [
					'user.id'           => __( 'User ID', 'ai-agent' ),
					'user.email'        => __( 'User email', 'ai-agent' ),
					'user.display_name' => __( 'Display name', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'wp_login_failed',
				'label'        => __( 'Failed Login Attempt', 'ai-agent' ),
				'description'  => __( 'Fires when a login attempt fails.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'username' ],
				'placeholders' => [
					'username' => __( 'Attempted username', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'added_option',
				'label'        => __( 'Option Added', 'ai-agent' ),
				'description'  => __( 'Fires when a new option is added to the database.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'option_name', 'option_value' ],
				'placeholders' => [
					'option_name' => __( 'Option name', 'ai-agent' ),
				],
				'conditions'   => [
					'option_name' => __( 'Option name equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'updated_option',
				'label'        => __( 'Option Updated', 'ai-agent' ),
				'description'  => __( 'Fires when an existing option is updated.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'option_name', 'old_value', 'new_value' ],
				'placeholders' => [
					'option_name' => __( 'Option name', 'ai-agent' ),
				],
				'conditions'   => [
					'option_name' => __( 'Option name equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'add_attachment',
				'label'        => __( 'Media Uploaded', 'ai-agent' ),
				'description'  => __( 'Fires when a new media file is uploaded.', 'ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'post_id' ],
				'placeholders' => [
					'post.ID'    => __( 'Attachment ID', 'ai-agent' ),
					'post.title' => __( 'Attachment title', 'ai-agent' ),
				],
				'conditions'   => [],
			],
		];
	}

	/**
	 * WooCommerce triggers.
	 *
	 * @return array
	 */
	private static function get_woocommerce_triggers(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}

		return [
			[
				'hook_name'    => 'woocommerce_new_order',
				'label'        => __( 'New Order Created', 'ai-agent' ),
				'description'  => __( 'Fires when a new WooCommerce order is created.', 'ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id' ],
				'placeholders' => [
					'order.id'     => __( 'Order ID', 'ai-agent' ),
					'order.total'  => __( 'Order total', 'ai-agent' ),
					'order.status' => __( 'Order status', 'ai-agent' ),
					'order.email'  => __( 'Customer email', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_order_status_changed',
				'label'        => __( 'Order Status Changed', 'ai-agent' ),
				'description'  => __( 'Fires when an order status changes.', 'ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id', 'old_status', 'new_status' ],
				'placeholders' => [
					'order.id'   => __( 'Order ID', 'ai-agent' ),
					'old_status' => __( 'Previous status', 'ai-agent' ),
					'new_status' => __( 'New status', 'ai-agent' ),
				],
				'conditions'   => [
					'new_status' => __( 'New status equals', 'ai-agent' ),
					'old_status' => __( 'Old status equals', 'ai-agent' ),
				],
			],
			[
				'hook_name'    => 'woocommerce_low_stock',
				'label'        => __( 'Product Low Stock', 'ai-agent' ),
				'description'  => __( 'Fires when a product reaches low stock threshold.', 'ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'product' ],
				'placeholders' => [
					'product.id'    => __( 'Product ID', 'ai-agent' ),
					'product.name'  => __( 'Product name', 'ai-agent' ),
					'product.stock' => __( 'Stock quantity', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_payment_complete',
				'label'        => __( 'Payment Complete', 'ai-agent' ),
				'description'  => __( 'Fires when a payment is completed.', 'ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id' ],
				'placeholders' => [
					'order.id'    => __( 'Order ID', 'ai-agent' ),
					'order.total' => __( 'Order total', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_product_on_backorder',
				'label'        => __( 'Product On Backorder', 'ai-agent' ),
				'description'  => __( 'Fires when a product goes on backorder.', 'ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'item' ],
				'placeholders' => [
					'product.name' => __( 'Product name', 'ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_refund_created',
				'label'        => __( 'Refund Created', 'ai-agent' ),
				'description'  => __( 'Fires when a refund is created.', 'ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'refund_id', 'args' ],
				'placeholders' => [
					'refund_id' => __( 'Refund ID', 'ai-agent' ),
				],
				'conditions'   => [],
			],
		];
	}

	/**
	 * Form plugin triggers.
	 *
	 * @return array
	 */
	private static function get_form_triggers(): array {
		$triggers = [];

		// Contact Form 7.
		if ( defined( 'WPCF7_VERSION' ) ) {
			$triggers[] = [
				'hook_name'    => 'wpcf7_mail_sent',
				'label'        => __( 'CF7 Form Submitted', 'ai-agent' ),
				'description'  => __( 'Fires when a Contact Form 7 submission email is sent.', 'ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'contact_form' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'ai-agent' ),
					'form.id'    => __( 'Form ID', 'ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		// Gravity Forms.
		if ( class_exists( 'GFForms' ) ) {
			$triggers[] = [
				'hook_name'    => 'gform_after_submission',
				'label'        => __( 'Gravity Form Submitted', 'ai-agent' ),
				'description'  => __( 'Fires after a Gravity Forms entry is created.', 'ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'entry', 'form' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'ai-agent' ),
					'form.id'    => __( 'Form ID', 'ai-agent' ),
					'entry.id'   => __( 'Entry ID', 'ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		// WPForms.
		if ( defined( 'WPFORMS_VERSION' ) ) {
			$triggers[] = [
				'hook_name'    => 'wpforms_process_complete',
				'label'        => __( 'WPForms Form Submitted', 'ai-agent' ),
				'description'  => __( 'Fires when a WPForms entry is processed.', 'ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'fields', 'entry', 'form_data', 'entry_id' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'ai-agent' ),
					'entry_id'   => __( 'Entry ID', 'ai-agent' ),
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
			'wordpress'   => __( 'WordPress', 'ai-agent' ),
			'woocommerce' => __( 'WooCommerce', 'ai-agent' ),
			'forms'       => __( 'Forms', 'ai-agent' ),
			'other'       => __( 'Other', 'ai-agent' ),
		];

		return $labels[ $category ] ?? ucfirst( $category );
	}
}
