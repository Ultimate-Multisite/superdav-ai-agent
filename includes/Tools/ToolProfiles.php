<?php

declare(strict_types=1);
/**
 * Tool Profiles — named sets of abilities for quick scoping.
 *
 * Profiles are stored in a single wp_option (not a DB table) since
 * the dataset is small and read-heavy.
 *
 * @package AiAgent
 */

namespace AiAgent\Tools;

class ToolProfiles {

	const OPTION_NAME = 'ai_agent_tool_profiles';

	/**
	 * Get all profiles (built-in + custom).
	 *
	 * @return array
	 */
	public static function list(): array {
		$custom  = get_option( self::OPTION_NAME, [] );
		$builtin = self::get_builtins();

		// Merge — custom profiles can override built-in slugs.
		$merged = [];
		foreach ( $builtin as $profile ) {
			$merged[ $profile['slug'] ] = $profile;
		}
		foreach ( $custom as $profile ) {
			$merged[ $profile['slug'] ] = $profile;
		}

		return array_values( $merged );
	}

	/**
	 * Get a single profile by slug.
	 *
	 * @param string $slug Profile slug.
	 * @return array|null
	 */
	public static function get( string $slug ): ?array {
		$all = self::list();
		foreach ( $all as $profile ) {
			if ( $profile['slug'] === $slug ) {
				return $profile;
			}
		}
		return null;
	}

	/**
	 * Create or update a custom profile.
	 *
	 * @param array $data Profile data: slug, name, description, tool_names.
	 * @return bool
	 */
	public static function save( array $data ): bool {
		if ( empty( $data['slug'] ) || empty( $data['name'] ) ) {
			return false;
		}

		$data['slug']        = sanitize_title( $data['slug'] );
		$data['name']        = sanitize_text_field( $data['name'] );
		$data['description'] = sanitize_textarea_field( $data['description'] ?? '' );
		$data['tool_names']  = array_map( 'sanitize_text_field', $data['tool_names'] ?? [] );
		$data['is_builtin']  = false;

		$custom = get_option( self::OPTION_NAME, [] );

		// Replace existing or add new.
		$found = false;
		foreach ( $custom as $i => $existing ) {
			if ( $existing['slug'] === $data['slug'] ) {
				$custom[ $i ] = $data;
				$found        = true;
				break;
			}
		}

		if ( ! $found ) {
			$custom[] = $data;
		}

		return update_option( self::OPTION_NAME, $custom );
	}

	/**
	 * Delete a custom profile.
	 *
	 * @param string $slug Profile slug.
	 * @return bool
	 */
	public static function delete( string $slug ): bool {
		$custom = get_option( self::OPTION_NAME, [] );
		$custom = array_filter( $custom, fn( $p ) => $p['slug'] !== $slug );

		return update_option( self::OPTION_NAME, array_values( $custom ) );
	}

	/**
	 * Filter abilities through the active profile.
	 *
	 * Returns only abilities whose names are in the profile's tool_names.
	 * If profile slug is empty or "all", returns all abilities unfiltered.
	 *
	 * @param \WP_Ability[] $abilities All available abilities.
	 * @param string        $profile_slug Active profile slug.
	 * @return \WP_Ability[]
	 */
	public static function filter_abilities( array $abilities, string $profile_slug ): array {
		if ( empty( $profile_slug ) || 'all' === $profile_slug ) {
			return $abilities;
		}

		$profile = self::get( $profile_slug );
		if ( ! $profile || empty( $profile['tool_names'] ) ) {
			return $abilities;
		}

		$allowed = array_flip( $profile['tool_names'] );

		return array_filter(
			$abilities,
			function ( $ability ) use ( $allowed ) {
				return isset( $allowed[ $ability->get_name() ] );
			}
		);
	}

	/**
	 * Export profiles as JSON.
	 *
	 * @return string
	 */
	public static function export(): string {
		return wp_json_encode( self::list(), JSON_PRETTY_PRINT );
	}

	/**
	 * Import profiles from JSON.
	 *
	 * @param string $json JSON string.
	 * @return int Number of profiles imported.
	 */
	public static function import( string $json ): int {
		$profiles = json_decode( $json, true );
		if ( ! is_array( $profiles ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $profiles as $profile ) {
			if ( ! empty( $profile['slug'] ) && ! empty( $profile['name'] ) ) {
				if ( self::save( $profile ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Get built-in profiles.
	 *
	 * Uses category-based patterns to match abilities without hardcoding
	 * every ability name. The tool_names use prefix patterns that are
	 * expanded at filter time.
	 *
	 * @return array
	 */
	private static function get_builtins(): array {
		return [
			[
				'slug'        => 'wp-read-only',
				'name'        => __( 'WP Read Only', 'ai-agent' ),
				'description' => __( 'Read-only WordPress tools. No modifications.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'site/get-',
						'site/list-',
						'site/search-',
						'user/get-',
						'user/list-',
						'ai-agent/memory-',
						'ai-agent/skill-',
						'ai-agent/knowledge-',
						'ai-agent/list-tools',
						'ai-agent/execute-tool',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'wp-full-management',
				'name'        => __( 'WP Full Management', 'ai-agent' ),
				'description' => __( 'All WordPress management tools.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'site/',
						'user/',
						'ai-agent/',
						'wpcli/',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'content-management',
				'name'        => __( 'Content Management', 'ai-agent' ),
				'description' => __( 'Post, page, and media management tools.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'site/get-post',
						'site/list-post',
						'site/create-post',
						'site/update-post',
						'site/delete-post',
						'site/get-page',
						'site/list-page',
						'site/create-page',
						'site/update-page',
						'site/get-media',
						'site/list-media',
						'site/upload-media',
						'site/get-categor',
						'site/list-categor',
						'site/get-tag',
						'site/list-tag',
						'ai-agent/',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'user-management',
				'name'        => __( 'User Management', 'ai-agent' ),
				'description' => __( 'User and role management tools.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'user/',
						'ai-agent/',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'maintenance',
				'name'        => __( 'Maintenance', 'ai-agent' ),
				'description' => __( 'Cache, updates, and site health tools.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'site/get-option',
						'site/update-option',
						'site/get-plugin',
						'site/list-plugin',
						'site/activate-plugin',
						'site/deactivate-plugin',
						'site/update-plugin',
						'site/get-theme',
						'site/list-theme',
						'site/activate-theme',
						'wpcli/',
						'ai-agent-custom/',
						'ai-agent/',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'developer',
				'name'        => __( 'Developer', 'ai-agent' ),
				'description' => __( 'Full access including WP-CLI and custom tools.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'site/',
						'user/',
						'wpcli/',
						'ai-agent/',
						'ai-agent-custom/',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'marketing',
				'name'        => __( 'Marketing & SEO', 'ai-agent' ),
				'description' => __( 'SEO auditing, content analysis, and competitive research tools.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'ai-agent/seo-',
						'ai-agent/content-',
						'ai-agent/fetch-url',
						'ai-agent/analyze-headers',
						'ai-agent/import-stock-image',
						'site/get-post',
						'site/list-post',
						'site/create-post',
						'site/update-post',
						'site/get-option',
						'site/update-option',
						'ai-agent/memory-',
						'ai-agent/skill-',
						'ai-agent/knowledge-',
						'ai-agent/list-tools',
						'ai-agent/execute-tool',
					]
				),
				'is_builtin'  => true,
			],
			[
				'slug'        => 'content-creator',
				'name'        => __( 'Content Creator', 'ai-agent' ),
				'description' => __( 'Content creation with Gutenberg blocks, media, and post management.', 'ai-agent' ),
				'tool_names'  => self::get_abilities_by_pattern(
					[
						'ai-agent/markdown-to-blocks',
						'ai-agent/list-block-',
						'ai-agent/get-block-type',
						'ai-agent/create-block-content',
						'ai-agent/parse-block-content',
						'ai-agent/import-stock-image',
						'ai-agent/content-',
						'site/get-post',
						'site/list-post',
						'site/create-post',
						'site/update-post',
						'site/get-page',
						'site/list-page',
						'site/create-page',
						'site/update-page',
						'site/get-media',
						'site/list-media',
						'site/upload-media',
						'site/get-categor',
						'site/list-categor',
						'ai-agent/memory-',
						'ai-agent/skill-',
						'ai-agent/knowledge-',
						'ai-agent/list-tools',
						'ai-agent/execute-tool',
					]
				),
				'is_builtin'  => true,
			],
		];
	}

	/**
	 * Get ability names matching any of the given prefixes.
	 *
	 * @param string[] $prefixes Array of ability name prefixes.
	 * @return string[]
	 */
	private static function get_abilities_by_pattern( array $prefixes ): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		$all     = wp_get_abilities();
		$matched = [];

		foreach ( $all as $ability ) {
			$name = $ability->get_name();
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( $name, $prefix ) ) {
					$matched[] = $name;
					break;
				}
			}
		}

		return $matched;
	}
}
