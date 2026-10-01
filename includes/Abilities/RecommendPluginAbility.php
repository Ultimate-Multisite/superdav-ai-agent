<?php

declare(strict_types=1);
/**
 * WordPress management abilities for the AI agent.
 *
 * Provides core plugin/theme listing, WordPress.org plugin installation, and
 * compatibility proxies for advanced companion-plugin abilities.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use SdAiAgent\Core\AbilityPluginRegistry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecommendPluginAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Recommend Plugin', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Given a need category (e.g. "ecommerce", "forms", "seo"), return ranked plugin recommendations from the curated abilities registry. Plugins that register WordPress Abilities are ranked highest, followed by those with blocks, then by popularity. Use this before install-plugin to discover the best plugin for a task.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		$categories = AbilityPluginRegistry::get_categories();
		sort( $categories );
		return [
			'type'       => 'object',
			'properties' => [
				'category'        => [
					'type'        => 'string',
					'description' => 'The need category to search for. One of the values in `enum`. Set `list_categories: true` to enumerate them dynamically.',
					'enum'        => $categories,
				],
				'limit'           => [
					'type'        => 'integer',
					'description' => 'Maximum number of recommendations to return (default: 5, max: 20).',
					'minimum'     => 1,
					'maximum'     => 20,
				],
				'list_categories' => [
					'type'        => 'boolean',
					'description' => 'If true, return all available categories instead of recommendations. Useful for discovery.',
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'recommendations' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'slug'            => [ 'type' => 'string' ],
							'name'            => [ 'type' => 'string' ],
							'description'     => [ 'type' => 'string' ],
							'ability_count'   => [ 'type' => 'integer' ],
							'has_abilities'   => [ 'type' => 'boolean' ],
							'has_blocks'      => [ 'type' => 'boolean' ],
							'active_installs' => [ 'type' => 'integer' ],
							'categories'      => [ 'type' => 'array' ],
						],
					],
				],
				'total'           => [ 'type' => 'integer' ],
				'category'        => [ 'type' => 'string' ],
				'categories'      => [ 'type' => 'array' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
		$list_categories = (bool) ( $input['list_categories'] ?? false );

		if ( $list_categories ) {
			$categories = AbilityPluginRegistry::get_categories();
			sort( $categories );
			return [
				'categories' => $categories,
				'total'      => count( $categories ),
			];
		}

		$category = isset( $input['category'] ) ? (string) $input['category'] : '';
		$limit    = isset( $input['limit'] ) ? min( 20, max( 1, (int) $input['limit'] ) ) : 5;

		if ( '' === $category ) {
			return new WP_Error(
				'sd_ai_agent_missing_category',
				__( 'A "category" is required, or set "list_categories" to true to see all available categories.', 'superdav-ai-agent' )
			);
		}

		$matches = AbilityPluginRegistry::get_by_category( $category );

		if ( empty( $matches ) ) {
			$all     = AbilityPluginRegistry::get_categories();
			$cat_low = strtolower( trim( $category ) );
			$near    = [];
			foreach ( $all as $candidate ) {
				$lev = levenshtein( $cat_low, strtolower( $candidate ) );
				if ( $lev <= 4 || str_contains( strtolower( $candidate ), $cat_low ) || str_contains( $cat_low, strtolower( $candidate ) ) ) {
					$near[] = [
						'category' => $candidate,
						'distance' => $lev,
					];
				}
			}
			usort(
				$near,
				static function ( array $a, array $b ): int {
					return $a['distance'] - $b['distance'];
				}
			);
			$suggestions = array_slice( array_column( $near, 'category' ), 0, 5 );
			sort( $all );
			return [
				'recommendations'      => [],
				'total'                => 0,
				'category'             => $category,
				'available_categories' => $all,
				'suggested_categories' => $suggestions,
				'hint'                 => empty( $suggestions )
					? 'No close match. Choose a category from `available_categories` and call again.'
					: 'No exact match. Closest categories are in `suggested_categories`.',
			];
		}

		$ranked = AbilityPluginRegistry::rank( $matches );
		$top    = array_slice( $ranked, 0, $limit );

		return [
			'recommendations' => $top,
			'total'           => count( $top ),
			'category'        => $category,
		];
	}

	protected function permission_callback( $input = null ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
