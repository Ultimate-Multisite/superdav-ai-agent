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
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolResultTruncator {

	/**
	 * Maximum JSON-encoded size (in bytes) before truncation kicks in.
	 * ~4KB is roughly 1000 tokens — a reasonable budget per tool result.
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
		if ( ! is_array( $result ) ) {
			return $result;
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

		return $result;
	}

	/**
	 * Apply tool-specific truncation strategies.
	 *
	 * @param array<string, mixed> $result    The tool result.
	 * @param string               $tool_name The tool name.
	 * @return array<string, mixed> The truncated result.
	 */
	private static function apply_tool_strategy( array $result, string $tool_name ): array {
		switch ( $tool_name ) {
			// Plugin list: keep name + active status + file, drop description/version details.
			case 'gratis-ai-agent/get-plugins':
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
			case 'ai-agent/content-analyze':
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
			case 'gratis-ai-agent/db-query':
				if ( isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
					$total             = count( $result['rows'] );
					$result['rows']    = array_slice( $result['rows'], 0, self::MAX_ARRAY_ITEMS );
					$result['total']   = $total;
					$result['showing'] = min( $total, self::MAX_ARRAY_ITEMS );
				}
				break;

			// Block type listings: keep names only.
			case 'ai-agent/list-block-types':
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
			case 'gratis-ai-agent/navigate':
			case 'gratis-ai-agent/get-page-html':
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
