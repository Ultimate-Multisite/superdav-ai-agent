<?php

declare(strict_types=1);
/**
 * Tool Result Truncator.
 *
 * Truncates large tool results before they are added to conversation history.
 * This prevents context bloat in multi-step agentic workflows where tool
 * results (plugin lists, content analyses, database queries) can consume
 * thousands of tokens that crowd out the user's intent.
 *
 * Strategy:
 * - Small results (< threshold): pass through unchanged.
 * - Large results: summarize arrays by keeping first N items + count,
 *   truncate long string values, and strip verbose metadata.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolResultTruncator {

	/**
	 * Maximum JSON-encoded size (in bytes) retained in conversation history.
	 * ~4KB is roughly 1000 tokens — a reasonable hard budget per tool result.
	 */
	const MAX_RESULT_BYTES = 4096;

	/**
	 * Maximum number of items to keep in array results.
	 */
	const MAX_ARRAY_ITEMS = 10;

	/**
	 * Maximum length for individual string values within results.
	 */
	const MAX_STRING_LENGTH = 500;

	/**
	 * Truncate a tool result if it exceeds the size threshold.
	 *
	 * @param mixed  $result    The raw tool result (array, string, or scalar).
	 * @param string $tool_name The tool name (for tool-specific strategies).
	 * @return mixed The possibly-truncated result.
	 */
	public static function truncate( $result, string $tool_name = '' ) {
		if ( is_string( $result ) && self::is_woocommerce_order_tool( $tool_name ) ) {
			$decoded = json_decode( $result, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$redacted = self::enforce_byte_cap( self::apply_tool_strategy( $decoded, $tool_name ) );
				$encoded  = wp_json_encode( $redacted );

				return is_string( $encoded ) ? $encoded : $result;
			}
		}

		if ( ! is_array( $result ) ) {
			return $result;
		}

		if ( self::is_woocommerce_order_tool( $tool_name ) ) {
			return self::enforce_byte_cap( self::apply_tool_strategy( $result, $tool_name ) );
		}

		// Check if the result is small enough to pass through.
		$encoded = wp_json_encode( $result );
		if ( false === $encoded || strlen( $encoded ) <= self::MAX_RESULT_BYTES ) {
			return $result;
		}

		// Apply tool-specific truncation strategies.
		// @phpstan-ignore-next-line
		$result = self::apply_tool_strategy( $result, $tool_name );

		// Generic truncation: truncate arrays and long strings.
		$result = self::truncate_recursive( $result, 0 );

		return is_array( $result ) ? self::enforce_byte_cap( $result ) : $result;
	}

	/**
	 * Enforce the hard history budget after tool-specific and generic truncation.
	 *
	 * Associative maps can remain oversized when they contain many short values.
	 * Collapse their deepest nested branches first, then retain only root entries
	 * that fit if the result remains over budget.
	 *
	 * @param array<string|int, mixed> $result Truncated tool result.
	 * @return array<string|int, mixed> Result that encodes within MAX_RESULT_BYTES.
	 */
	private static function enforce_byte_cap( array $result ): array {
		$list_path = [];

		for ( $attempt = 0; $attempt < 1000; $attempt++ ) {
			$encoded = wp_json_encode( $result );
			if ( is_string( $encoded ) && strlen( $encoded ) <= self::MAX_RESULT_BYTES ) {
				return $result;
			}

			// Keep shrinking the list chosen earlier until the whole result fits, even once
			// the list itself is under the cap; otherwise the root summary could drop it.
			if ( [] !== $list_path ) {
				$shrunk = self::shrink_list_at_path( $result, $list_path );
				if ( null !== $shrunk ) {
					$result = $shrunk;
					continue;
				}
				$list_path = [];
			}

			$largest_path  = [];
			$largest_bytes = 0;
			self::find_smallest_oversized_array( $result, [], $largest_path, $largest_bytes );

			if ( [] === $largest_path ) {
				// No single branch is over the cap; shrink the largest list before summarizing.
				$list_path = self::find_largest_list( $result, [] )[0];
				if ( [] !== $list_path ) {
					continue;
				}
				break;
			}

			// Lists (posts, pages, rows...) keep their first items so the agent still gets
			// usable IDs; only branches that cannot shrink are replaced by an omission note.
			$shrunk = self::shrink_list_at_path( $result, $largest_path );
			if ( null !== $shrunk ) {
				$result    = $shrunk;
				$list_path = $largest_path;
				continue;
			}

			$result = self::replace_nested_array_with_omission( $result, $largest_path, $largest_bytes );
		}

		return self::summarize_to_byte_cap( $result );
	}

	/**
	 * Find the largest list that can still shrink (two or more items besides a "more items" note).
	 *
	 * @param array<string|int, mixed> $value Candidate branch.
	 * @param array<int, string|int>   $path  Current branch path.
	 * @return array{0: array<int, string|int>, 1: int} Path ([] when none) and its encoded size.
	 */
	private static function find_largest_list( array $value, array $path ): array {
		$best = [ [], 0 ];

		foreach ( $value as $key => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$item_path = array_merge( $path, [ $key ] );
			if ( array_is_list( $item ) ) {
				$items = count( $item );
				$last  = end( $item );
				if ( is_string( $last ) && 1 === preg_match( '/^\.\.\. and \d+ more items/', $last ) ) {
					--$items;
				}
				$bytes = strlen( (string) wp_json_encode( $item ) );
				if ( $items >= 2 && $bytes > $best[1] ) {
					$best = [ $item_path, $bytes ];
				}
			}

			$nested = self::find_largest_list( $item, $item_path );
			if ( $nested[1] > $best[1] ) {
				$best = $nested;
			}
		}

		return $best;
	}

	/**
	 * Drop the last item of the list at $path, folding it into a trailing "more items" note.
	 *
	 * When the list's parent reports a numeric `total`, the note counts every item absent
	 * from the result (including ones removed earlier by a tool strategy), and a sibling
	 * `showing` count is kept in step.
	 *
	 * @param array<string|int, mixed> $result Whole result.
	 * @param array<int, string|int>   $path   Path to the list.
	 * @return array<string|int, mixed>|null Updated result, or null when the branch is not a list of two or more items.
	 */
	private static function shrink_list_at_path( array $result, array $path ): ?array {
		$value = self::value_at_path( $result, $path );
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			return null;
		}

		$more = 0;
		$last = end( $value );
		if ( is_string( $last ) && 1 === preg_match( '/^\.\.\. and (\d+) more items/', $last, $match ) ) {
			array_pop( $value );
			$more = (int) $match[1];
		}

		if ( count( $value ) < 2 ) {
			return null;
		}

		array_pop( $value );
		$kept   = count( $value );
		$parent = count( $path ) > 1 ? self::value_at_path( $result, array_slice( $path, 0, -1 ) ) : $result;
		$total  = is_array( $parent ) && isset( $parent['total'] ) && is_numeric( $parent['total'] ) ? (int) $parent['total'] : 0;
		$more   = $total > $kept ? $total - $kept : $more + 1;

		$value[] = sprintf( '... and %d more items omitted to fit the context limit; narrow the query to see them', $more );
		$result  = self::replace_at_path( $result, $path, $value );

		if ( is_array( $parent ) && array_key_exists( 'showing', $parent ) ) {
			$result = self::replace_at_path( $result, array_merge( array_slice( $path, 0, -1 ), [ 'showing' ] ), $kept );
		}

		return $result;
	}

	/**
	 * Read a nested branch by path.
	 *
	 * @param array<string|int, mixed> $value Result.
	 * @param array<int, string|int>   $path  Nested path.
	 * @return mixed Branch value, or null when missing.
	 */
	private static function value_at_path( array $value, array $path ) {
		foreach ( $path as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Replace a nested branch by path.
	 *
	 * @param array<string|int, mixed> $value       Result.
	 * @param array<int, string|int>   $path        Nested path.
	 * @param mixed                    $replacement New branch value.
	 * @return array<string|int, mixed> Updated result.
	 */
	private static function replace_at_path( array $value, array $path, $replacement ): array {
		$key = array_shift( $path );
		if ( null === $key || ! array_key_exists( $key, $value ) ) {
			return $value;
		}

		$value[ $key ] = [] === $path || ! is_array( $value[ $key ] )
			? $replacement
			: self::replace_at_path( $value[ $key ], $path, $replacement );

		return $value;
	}

	/**
	 * Find the smallest oversized nested array that can be collapsed to meet the byte cap.
	 *
	 * @param array<string|int, mixed> $value         Candidate result branch.
	 * @param array<int, string|int>   $path          Current branch path.
	 * @param array<int, string|int>   $largest_path  Smallest oversized nested-array path.
	 * @param int                      $largest_bytes Smallest oversized nested-array size.
	 */
	private static function find_smallest_oversized_array( array $value, array $path, array &$largest_path, int &$largest_bytes ): void {
		foreach ( $value as $key => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$item_path = array_merge( $path, [ $key ] );
			$encoded   = wp_json_encode( $item );
			$bytes     = is_string( $encoded ) ? strlen( $encoded ) : 0;

			if ( $bytes > self::MAX_RESULT_BYTES && ( 0 === $largest_bytes || $bytes < $largest_bytes ) ) {
				$largest_path  = $item_path;
				$largest_bytes = $bytes;
			}

			self::find_smallest_oversized_array( $item, $item_path, $largest_path, $largest_bytes );
		}
	}

	/**
	 * Replace a nested result branch with an actionable omission note.
	 *
	 * @param array<string|int, mixed> $value Candidate result branch.
	 * @param array<int, string|int>   $path  Nested-array path to replace.
	 * @param int                      $bytes Original encoded branch size.
	 * @return array<string|int, mixed> Updated result branch.
	 */
	private static function replace_nested_array_with_omission( array $value, array $path, int $bytes ): array {
		$key = array_shift( $path );
		if ( null === $key || ! array_key_exists( $key, $value ) ) {
			return $value;
		}

		if ( [] === $path ) {
			$value[ $key ] = self::omission_note( $bytes );

			return $value;
		}

		if ( is_array( $value[ $key ] ) ) {
			$value[ $key ] = self::replace_nested_array_with_omission( $value[ $key ], $path, $bytes );
		}

		return $value;
	}

	/**
	 * Keep only root entries that fit alongside the truncation note.
	 *
	 * @param array<string|int, mixed> $result Oversized result after branch collapsing.
	 * @return array<string|int, mixed> Byte-capped result summary.
	 */
	private static function summarize_to_byte_cap( array $result ): array {
		$encoded = wp_json_encode( $result );
		$bytes   = is_string( $encoded ) ? strlen( $encoded ) : 0;
		$summary = [
			'_truncated' => true,
			'_omitted'   => self::omission_note( $bytes ),
		];

		foreach ( $result as $key => $value ) {
			$candidate         = $summary;
			$candidate[ $key ] = $value;
			$candidate_encoded = wp_json_encode( $candidate );

			if ( is_string( $candidate_encoded ) && strlen( $candidate_encoded ) <= self::MAX_RESULT_BYTES ) {
				$summary = $candidate;
			}
		}

		return $summary;
	}

	/**
	 * Build a concise instruction for retrieving a collapsed result branch.
	 *
	 * @param int $bytes Omitted JSON-encoded bytes.
	 * @return string Omission note.
	 */
	private static function omission_note( int $bytes ): string {
		return sprintf( '[%d-byte object omitted; call with a narrower section]', $bytes );
	}

	/**
	 * Apply tool-specific truncation strategies.
	 *
	 * @param array<string|int, mixed> $result    The tool result.
	 * @param string                   $tool_name The tool name.
	 * @return array<string|int, mixed> The truncated result.
	 */
	private static function apply_tool_strategy( array $result, string $tool_name ): array {
		switch ( $tool_name ) {
			case 'woocommerce/orders-list':
			case 'woocommerce/orders-get':
				$result = self::scrub_woocommerce_order_result( $result );
				break;

			// Plugin list: keep name + active status + file, drop description/version details.
			case 'sd-ai-agent/get-plugins':
				if ( isset( $result['plugins'] ) && is_array( $result['plugins'] ) ) {
					$total                = count( $result['plugins'] );
					$result['plugins']    = array_map(
						function ( $plugin ) {
							return [
								// @phpstan-ignore-next-line
								'name'   => $plugin['name'] ?? '',
								// @phpstan-ignore-next-line
								'active' => $plugin['active'] ?? false,
								// @phpstan-ignore-next-line
								'file'   => $plugin['file'] ?? '',
							];
						},
						array_slice( $result['plugins'], 0, self::MAX_ARRAY_ITEMS )
					);
					$result['total']      = $total;
					$result['_truncated'] = $total > self::MAX_ARRAY_ITEMS;
				}
				break;

			// Content analysis: keep summary stats, truncate per-post details.
			case 'sd-ai-agent/content-analyze':
				if ( isset( $result['posts_without_featured_image'] ) && is_array( $result['posts_without_featured_image'] ) ) {
					$count                                  = count( $result['posts_without_featured_image'] );
					$result['posts_without_featured_image'] = array_slice( $result['posts_without_featured_image'], 0, 5 );
					if ( $count > 5 ) {
						$result['posts_without_featured_image_total'] = $count;
					}
				}
				if ( isset( $result['posts_without_meta_description'] ) && is_array( $result['posts_without_meta_description'] ) ) {
					$count                                    = count( $result['posts_without_meta_description'] );
					$result['posts_without_meta_description'] = array_slice( $result['posts_without_meta_description'], 0, 5 );
					if ( $count > 5 ) {
						$result['posts_without_meta_description_total'] = $count;
					}
				}
				break;

			// Database queries: truncate row data.
			case 'sd-ai-agent/db-query':
				if ( isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
					$total             = count( $result['rows'] );
					$result['rows']    = array_slice( $result['rows'], 0, self::MAX_ARRAY_ITEMS );
					$result['total']   = $total;
					$result['showing'] = min( $total, self::MAX_ARRAY_ITEMS );
				}
				break;

			// Block type listings: keep names only.
			case 'sd-ai-agent/list-block-types':
				if ( isset( $result['block_types'] ) && is_array( $result['block_types'] ) ) {
					$total                 = count( $result['block_types'] );
					$result['block_types'] = array_map(
						function ( $bt ) {
							return [
								// @phpstan-ignore-next-line
								'name'     => $bt['name'] ?? '',
								// @phpstan-ignore-next-line
								'title'    => $bt['title'] ?? '',
								// @phpstan-ignore-next-line
								'category' => $bt['category'] ?? '',
							];
						},
						array_slice( $result['block_types'], 0, self::MAX_ARRAY_ITEMS )
					);
					$result['_truncated']  = $total > self::MAX_ARRAY_ITEMS;
				}
				break;

			// Navigation / page HTML: truncate the HTML body.
			case 'sd-ai-agent/navigate':
			case 'sd-ai-agent/get-page-html':
				if ( isset( $result['html'] ) && is_string( $result['html'] ) && strlen( $result['html'] ) > self::MAX_STRING_LENGTH ) {
					$result['html']       = substr( $result['html'], 0, self::MAX_STRING_LENGTH ) . '... [truncated]';
					$result['_truncated'] = true;
				}
				if ( isset( $result['content'] ) && is_string( $result['content'] ) && strlen( $result['content'] ) > self::MAX_STRING_LENGTH ) {
					$result['content']    = substr( $result['content'], 0, self::MAX_STRING_LENGTH ) . '... [truncated]';
					$result['_truncated'] = true;
				}
				break;
		}

		return $result;
	}

	/**
	 * Check whether a tool returns WooCommerce order data with customer PII.
	 *
	 * @param string $tool_name Ability name.
	 * @return bool
	 */
	private static function is_woocommerce_order_tool( string $tool_name ): bool {
		return in_array( $tool_name, array( 'woocommerce/orders-list', 'woocommerce/orders-get' ), true );
	}

	/**
	 * Scrub WooCommerce order payloads down to AI-safe audit fields.
	 *
	 * @param array<string|int, mixed> $result WooCommerce order result.
	 * @return array<string|int, mixed> Redacted result.
	 */
	private static function scrub_woocommerce_order_result( array $result ): array {
		if ( self::looks_like_woocommerce_order( $result ) ) {
			return self::summarize_woocommerce_order( $result );
		}

		$scrubbed = array();
		foreach ( $result as $key => $value ) {
			if ( is_string( $key ) && self::is_sensitive_woocommerce_order_key( $key ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$scrubbed[ $key ] = self::scrub_woocommerce_order_result( $value );
			} else {
				$scrubbed[ $key ] = $value;
			}
		}

		return $scrubbed;
	}

	/**
	 * Detect a WooCommerce order object/array.
	 *
	 * @param array<string|int, mixed> $value Candidate value.
	 * @return bool
	 */
	private static function looks_like_woocommerce_order( array $value ): bool {
		return isset( $value['id'] ) && ( isset( $value['status'] ) || isset( $value['billing'] ) || isset( $value['shipping'] ) || isset( $value['line_items'] ) );
	}

	/**
	 * Reduce a WooCommerce order to fields needed for non-PII summaries.
	 *
	 * @param array<string|int, mixed> $order Raw WooCommerce order.
	 * @return array<string, mixed> Minimized order.
	 */
	private static function summarize_woocommerce_order( array $order ): array {
		$allowed_order_keys = array(
			'id',
			'number',
			'parent_id',
			'status',
			'currency',
			'currency_symbol',
			'date_created',
			'date_created_gmt',
			'date_modified',
			'date_modified_gmt',
			'date_completed',
			'date_completed_gmt',
			'date_paid',
			'date_paid_gmt',
			'total',
			'total_tax',
			'discount_total',
			'discount_tax',
			'shipping_total',
			'shipping_tax',
			'cart_tax',
			'customer_id',
			'created_via',
			'prices_include_tax',
			'line_items',
			'tax_lines',
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			'refunds',
		);

		$summary = array();
		foreach ( $allowed_order_keys as $key ) {
			if ( array_key_exists( $key, $order ) ) {
				$summary[ $key ] = self::summarize_woocommerce_order_field( $key, $order[ $key ] );
			}
		}

		$summary['_redacted'] = true;

		return $summary;
	}

	/**
	 * Summarize nested WooCommerce order collections.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 * @return mixed Minimized value.
	 */
	private static function summarize_woocommerce_order_field( string $key, $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( 'line_items' === $key ) {
			return self::summarize_woocommerce_line_items( $value );
		}

		if ( in_array( $key, array( 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines', 'refunds' ), true ) ) {
			return self::scrub_woocommerce_order_result( $value );
		}

		return $value;
	}

	/**
	 * Keep only product summary data from WooCommerce order line items.
	 *
	 * @param array<string|int, mixed> $items Raw line items.
	 * @return array<string|int, mixed> Minimized line items.
	 */
	private static function summarize_woocommerce_line_items( array $items ): array {
		$allowed_line_item_keys = array( 'id', 'name', 'product_id', 'variation_id', 'quantity', 'sku', 'subtotal', 'subtotal_tax', 'total', 'total_tax' );
		$summary                = array();

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$summary[ $index ] = $item;
				continue;
			}

			$line = array();
			foreach ( $allowed_line_item_keys as $key ) {
				if ( array_key_exists( $key, $item ) ) {
					$line[ $key ] = $item[ $key ];
				}
			}

			$summary[ $index ] = $line;
		}

		return $summary;
	}

	/**
	 * Check for WooCommerce order keys that can expose customer/contact secrets.
	 *
	 * @param string $key Result key.
	 * @return bool
	 */
	private static function is_sensitive_woocommerce_order_key( string $key ): bool {
		$sensitive_keys = array(
			'billing',
			'shipping',
			'email',
			'phone',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
			'first_name',
			'last_name',
			'company',
			'customer_ip_address',
			'customer_user_agent',
			'transaction_id',
			'order_key',
			'cart_hash',
			'payment_url',
		);

		return in_array( strtolower( $key ), $sensitive_keys, true );
	}

	/**
	 * Recursively truncate arrays and long strings.
	 *
	 * @param mixed $value The value to truncate.
	 * @param int   $depth Current recursion depth.
	 * @return mixed The truncated value.
	 */
	private static function truncate_recursive( $value, int $depth ) {
		// Don't recurse too deep.
		if ( $depth > 5 ) {
			return is_string( $value ) ? self::truncate_string( $value ) : $value;
		}

		if ( is_string( $value ) ) {
			return self::truncate_string( $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Truncate sequential arrays (lists).
		if ( array_is_list( $value ) && count( $value ) > self::MAX_ARRAY_ITEMS ) {
			$total   = count( $value );
			$value   = array_slice( $value, 0, self::MAX_ARRAY_ITEMS );
			$value[] = sprintf( '... and %d more items', $total - self::MAX_ARRAY_ITEMS );
		}

		// Recurse into each element.
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::truncate_recursive( $item, $depth + 1 );
		}

		return $value;
	}

	/**
	 * Truncate a string if it exceeds the max length.
	 *
	 * @param string $str The string to truncate.
	 * @return string The possibly-truncated string.
	 */
	private static function truncate_string( string $str ): string {
		if ( strlen( $str ) <= self::MAX_STRING_LENGTH ) {
			return $str;
		}

		return substr( $str, 0, self::MAX_STRING_LENGTH ) . '... [truncated]';
	}
}
