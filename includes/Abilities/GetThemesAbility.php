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

class GetThemesAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'List Themes', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'List all installed WordPress themes with their status.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'offset' => [
					'type'        => 'integer',
					'description' => __( 'Zero-based offset of the first theme to return.', 'superdav-ai-agent' ),
					'default'     => 0,
					'minimum'     => 0,
				],
				'limit'  => [
					'type'        => 'integer',
					'description' => __( 'Maximum themes to return. Use next_offset to continue.', 'superdav-ai-agent' ),
					'default'     => 100,
					'minimum'     => 1,
					'maximum'     => 100,
				],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'themes'      => [ 'type' => 'array' ],
				'total'       => [ 'type' => 'integer' ],
				'showing'     => [ 'type' => 'integer' ],
				'offset'      => [ 'type' => 'integer' ],
				'limit'       => [ 'type' => 'integer' ],
				'has_more'    => [ 'type' => 'boolean' ],
				'next_offset' => [ 'type' => [ 'integer', 'null' ] ],
				'active'      => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
		$all_themes   = wp_get_themes();
		$active_theme = get_stylesheet();
		$offset       = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$limit        = min( 100, max( 1, (int) ( $input['limit'] ?? 100 ) ) );

		uksort( $all_themes, 'strnatcasecmp' );
		$total       = count( $all_themes );
		$page_themes = array_slice( $all_themes, $offset, $limit, true );

		$themes = [];
		foreach ( $page_themes as $theme_slug => $theme ) {
			$themes[] = [
				'slug'    => $theme_slug,
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'active'  => $theme_slug === $active_theme,
			];
		}
		$showing     = count( $themes );
		$next_offset = $offset + $showing;
		$has_more    = $next_offset < $total;

		return [
			'themes'      => $themes,
			'total'       => $total,
			'showing'     => $showing,
			'offset'      => $offset,
			'limit'       => $limit,
			'has_more'    => $has_more,
			'next_offset' => $has_more ? $next_offset : null,
			'active'      => $active_theme,
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
