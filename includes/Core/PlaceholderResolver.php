<?php

declare(strict_types=1);
/**
 * Placeholder Resolver — resolves {{post.title}}, {{user.email}}, etc. from hook arguments.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Automations\EventTriggerRegistry;

class PlaceholderResolver {

	/**
	 * Resolve all {{placeholders}} in a prompt template given hook arguments.
	 *
	 * @param string $template  The prompt template with placeholders.
	 * @param string $hook_name The WordPress hook that fired.
	 * @param array  $hook_args The arguments passed to the hook.
	 * @phpstan-param list<mixed> $hook_args
	 * @return string Resolved prompt.
	 */
	public static function resolve( string $template, string $hook_name, array $hook_args ): string {
		// Build a context map from hook arguments.
		$context = self::build_context( $hook_name, $hook_args );

		// Replace {{placeholders}}.
		return preg_replace_callback(
			'/\{\{(\w[\w.]*)\}\}/',
			function ( $matches ) use ( $context ) {
				$key = $matches[1];

				// Direct lookup.
				if ( isset( $context[ $key ] ) ) {
					$val = $context[ $key ];
					return is_scalar( $val ) ? (string) $val : wp_json_encode( $val );
				}

				// Dot-notation traversal.
				if ( str_contains( $key, '.' ) ) {
					$parts = explode( '.', $key );
					$value = $context;
					foreach ( $parts as $part ) {
						if ( is_array( $value ) && isset( $value[ $part ] ) ) {
							$value = $value[ $part ];
						} elseif ( is_object( $value ) && isset( $value->$part ) ) {
							$value = $value->$part;
						} else {
							return $matches[0]; // Leave as-is.
						}
					}
					return is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
				}

				return $matches[0];
			},
			$template
		);
	}

	/**
	 * Build a context map from hook arguments.
	 *
	 * Enriches raw hook args with structured data (post object, user object, etc.).
	 *
	 * @param string $hook_name The hook name.
	 * @param array  $hook_args Raw hook arguments.
	 * @phpstan-param list<mixed> $hook_args
	 * @return array<string, mixed> Context map.
	 */
	private static function build_context( string $hook_name, array $hook_args ): array {
		$context = [];

		// Map raw args by position.
		$trigger_def = EventTriggerRegistry::get( $hook_name );
		if ( $trigger_def && ! empty( $trigger_def['args'] ) ) {
			foreach ( $trigger_def['args'] as $i => $arg_name ) {
				if ( isset( $hook_args[ $i ] ) ) {
					$context[ $arg_name ] = $hook_args[ $i ];
				}
			}
		}

		// Enrich with structured objects.
		$context = self::enrich_post_context( $context, $hook_name, $hook_args );
		$context = self::enrich_user_context( $context, $hook_name, $hook_args );
		$context = self::enrich_comment_context( $context, $hook_name, $hook_args );
		$context = self::enrich_order_context( $context, $hook_name, $hook_args );
		$context = self::enrich_product_context( $context, $hook_name, $hook_args );

		return $context;
	}

	/**
	 * Enrich context with post data.
	 *
	 * @param array<string, mixed> $context   Current context.
	 * @param string               $hook_name WordPress hook name.
	 * @param array                $hook_args Hook arguments.
	 * @phpstan-param list<mixed> $hook_args
	 * @return array<string, mixed> Enriched context.
	 */
	private static function enrich_post_context( array $context, string $hook_name, array $hook_args ): array {
		$post = null;

		// transition_post_status passes $new_status, $old_status, $post.
		if ( 'transition_post_status' === $hook_name && isset( $hook_args[2] ) ) {
			$post = $hook_args[2];
		}

		// delete_post, add_attachment pass $post_id.
		if ( in_array( $hook_name, [ 'delete_post', 'add_attachment' ], true ) && isset( $hook_args[0] ) ) {
			$post = get_post( $hook_args[0] );
		}

		if ( $post instanceof \WP_Post ) {
			$content_excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '...' );
			$context['post'] = [
				'ID'      => $post->ID,
				'title'   => $post->post_title,
				'type'    => $post->post_type,
				'status'  => $post->post_status,
				'author'  => $post->post_author,
				'content' => $content_excerpt,
				'url'     => get_permalink( $post->ID ),
			];
		}

		return $context;
	}

	/**
	 * Enrich context with user data.
	 *
	 * @param array<string, mixed> $context   Current context.
	 * @param string               $hook_name WordPress hook name.
	 * @param array                $hook_args Hook arguments.
	 * @phpstan-param list<mixed> $hook_args
	 * @return array<string, mixed> Enriched context.
	 */
	private static function enrich_user_context( array $context, string $hook_name, array $hook_args ): array {
		$user = null;

		if ( 'user_register' === $hook_name && isset( $hook_args[0] ) ) {
			$user = get_userdata( $hook_args[0] );
		}

		if ( 'wp_login' === $hook_name && isset( $hook_args[1] ) ) {
			$user = $hook_args[1];
		}

		if ( 'profile_update' === $hook_name && isset( $hook_args[0] ) ) {
			$user = get_userdata( $hook_args[0] );
		}

		if ( $user instanceof \WP_User ) {
			$context['user'] = [
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'role'         => implode( ', ', $user->roles ),
			];
		}

		// Failed login.
		if ( 'wp_login_failed' === $hook_name && isset( $hook_args[0] ) ) {
			$context['username'] = $hook_args[0];
		}

		return $context;
	}

	/**
	 * Enrich context with comment data.
	 *
	 * @param array<string, mixed> $context   Current context.
	 * @param string               $hook_name WordPress hook name.
	 * @param array                $hook_args Hook arguments.
	 * @phpstan-param list<mixed> $hook_args
	 * @return array<string, mixed> Enriched context.
	 */
	private static function enrich_comment_context( array $context, string $hook_name, array $hook_args ): array {
		if ( 'comment_post' !== $hook_name || empty( $hook_args[0] ) ) {
			return $context;
		}

		$comment = get_comment( $hook_args[0] );
		if ( $comment ) {
			$context['comment'] = [
				'id'           => $comment->comment_ID,
				'author'       => $comment->comment_author,
				'author_email' => $comment->comment_author_email,
				'content'      => wp_trim_words( $comment->comment_content, 100, '...' ),
				'post_id'      => $comment->comment_post_ID,
				'approved'     => $comment->comment_approved,
			];
		}

		return $context;
	}

	/**
	 * Enrich context with WooCommerce order data.
	 *
	 * @param array<string, mixed> $context   Current context.
	 * @param string               $hook_name WordPress hook name.
	 * @param array                $hook_args Hook arguments.
	 * @phpstan-param list<mixed> $hook_args
	 * @return array<string, mixed> Enriched context.
	 */
	private static function enrich_order_context( array $context, string $hook_name, array $hook_args ): array {
		$order_hooks = [
			'woocommerce_new_order',
			'woocommerce_order_status_changed',
			'woocommerce_payment_complete',
		];

		if ( ! in_array( $hook_name, $order_hooks, true ) || empty( $hook_args[0] ) ) {
			return $context;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return $context;
		}

		$order = wc_get_order( $hook_args[0] );
		if ( $order ) {
			$context['order'] = [
				'id'     => $order->get_id(),
				'total'  => $order->get_total(),
				'status' => $order->get_status(),
				'email'  => $order->get_billing_email(),
				'items'  => $order->get_item_count(),
			];
		}

		return $context;
	}

	/**
	 * Enrich context with WooCommerce product data.
	 *
	 * @param array<string, mixed> $context   Current context.
	 * @param string               $hook_name WordPress hook name.
	 * @param array                $hook_args Hook arguments.
	 * @phpstan-param list<mixed> $hook_args
	 * @return array<string, mixed> Enriched context.
	 */
	private static function enrich_product_context( array $context, string $hook_name, array $hook_args ): array {
		if ( 'woocommerce_low_stock' !== $hook_name || empty( $hook_args[0] ) ) {
			return $context;
		}

		$product = $hook_args[0];
		if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
			$context['product'] = [
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'stock' => $product->get_stock_quantity(),
				'sku'   => $product->get_sku(),
			];
		}

		return $context;
	}
}
