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

class SearchPluginDirectoryAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Search Plugin Directory', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Search the official WordPress.org plugin directory by keyword. Returns matching plugins with slug, description, active installs, and rating.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'search'   => [
					'type'        => 'string',
					'description' => 'Search keyword(s) to query the WordPress.org plugin directory.',
				],
				'per_page' => [
					'type'        => 'integer',
					'description' => 'Number of results to return (default: 10, max: 25).',
					'minimum'     => 1,
					'maximum'     => 25,
				],
			],
			'required'   => [ 'search' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugins' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'slug'              => [ 'type' => 'string' ],
							'name'              => [ 'type' => 'string' ],
							'short_description' => [ 'type' => 'string' ],
							'version'           => [ 'type' => 'string' ],
							'active_installs'   => [ 'type' => 'integer' ],
							'rating'            => [ 'type' => 'number' ],
							'author'            => [ 'type' => 'string' ],
						],
					],
				],
				'total'   => [ 'type' => 'integer' ],
				'query'   => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$search   = isset( $input['search'] ) ? (string) $input['search'] : '';
		$per_page = isset( $input['per_page'] ) ? min( 25, max( 1, (int) $input['per_page'] ) ) : 10;

		if ( '' === $search ) {
			return new WP_Error( 'sd_ai_agent_empty_search', __( 'A search keyword is required.', 'superdav-ai-agent' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$api = plugins_api(
			'query_plugins',
			// @phpstan-ignore-next-line
			[
				'search'   => $search,
				'per_page' => $per_page,
				'fields'   => [
					'short_description' => true,
					'sections'          => false,
					'tags'              => false,
					'icons'             => false,
					'banners'           => false,
				],
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$plugins = [];
		$raw     = is_object( $api ) && isset( $api->plugins ) ? (array) $api->plugins : [];

		foreach ( $raw as $plugin ) {
			// The API can return either objects or arrays depending on the response shape.
			if ( is_object( $plugin ) ) {
				$plugins[] = [
					'slug'              => (string) ( $plugin->slug ?? '' ),
					'name'              => (string) ( $plugin->name ?? '' ),
					'short_description' => (string) ( $plugin->short_description ?? '' ),
					'version'           => (string) ( $plugin->version ?? '' ),
					'active_installs'   => (int) ( $plugin->active_installs ?? 0 ),
					'rating'            => (float) ( $plugin->rating ?? 0 ),
					'author'            => (string) ( $plugin->author ?? '' ),
				];
			} elseif ( is_array( $plugin ) ) {
				$plugins[] = [
					'slug'              => (string) ( $plugin['slug'] ?? '' ),
					'name'              => (string) ( $plugin['name'] ?? '' ),
					'short_description' => (string) ( $plugin['short_description'] ?? '' ),
					'version'           => (string) ( $plugin['version'] ?? '' ),
					'active_installs'   => (int) ( $plugin['active_installs'] ?? 0 ),
					'rating'            => (float) ( $plugin['rating'] ?? 0 ),
					'author'            => (string) ( $plugin['author'] ?? '' ),
				];
			}
		}

		$total = is_object( $api ) && isset( $api->info['results'] ) ? (int) $api->info['results'] : count( $plugins );

		return [
			'plugins' => $plugins,
			'total'   => $total,
			'query'   => $search,
		];
	}

	protected function permission_callback( $input ): bool {
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
