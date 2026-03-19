<?php

declare(strict_types=1);
/**
 * Detects whether the current WordPress installation is a fresh/empty site.
 *
 * A "fresh install" is defined as a site that has no meaningful user-created
 * content: no published posts beyond the default "Hello World" sample post,
 * no published pages beyond the default "Sample Page", and is still running
 * the default theme (Twenty* series). When all conditions are met the plugin
 * sets the `site_builder_mode` flag so the floating widget can open
 * automatically in expanded mode and guide the user through site setup.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FreshInstallDetector {

	/**
	 * Option name used to cache the fresh-install detection result.
	 *
	 * Stored as a transient so it is re-evaluated after content changes.
	 */
	const TRANSIENT_KEY = 'gratis_ai_agent_fresh_install';

	/**
	 * How long (in seconds) to cache the detection result.
	 *
	 * 5 minutes — short enough that adding real content quickly clears
	 * site-builder mode, long enough to avoid per-request DB queries.
	 */
	const CACHE_TTL = 300;

	/**
	 * WordPress default post titles that ship with every fresh install.
	 * Posts/pages whose titles match these are excluded from the "real content"
	 * count.
	 */
	const DEFAULT_POST_TITLES = [
		'Hello world!',
		'Sample Page',
		'Privacy Policy',
	];

	/**
	 * Theme stylesheet slugs considered "default" WordPress themes.
	 * A site still running one of these has not chosen a custom theme.
	 */
	const DEFAULT_THEME_SLUGS = [
		'twentytwentyfive',
		'twentytwentyfour',
		'twentytwentythree',
		'twentytwentytwo',
		'twentytwentyone',
		'twentytwenty',
		'twentynineteen',
		'twentyseventeen',
		'twentysixteen',
		'twentyfifteen',
	];

	/**
	 * Register hooks.
	 *
	 * Clears the cached detection result whenever content is published or
	 * deleted so the widget state updates promptly.
	 */
	public static function register(): void {
		add_action( 'transition_post_status', [ __CLASS__, 'clearCache' ], 10, 3 );
		add_action( 'delete_post', [ __CLASS__, 'clearCache' ] );
		add_action( 'switch_theme', [ __CLASS__, 'clearCache' ] );
	}

	/**
	 * Clear the cached detection result.
	 *
	 * Accepts any number of arguments so it can be used as a hook callback
	 * for actions with different signatures.
	 *
	 * @param mixed ...$args Ignored hook arguments.
	 */
	public static function clearCache( ...$args ): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Determine whether the current site qualifies as a fresh install.
	 *
	 * Returns true when ALL of the following are true:
	 *   - No published posts exist beyond the default "Hello world!" sample.
	 *   - No published pages exist beyond the default "Sample Page" and
	 *     "Privacy Policy" pages.
	 *   - The active theme is one of the built-in WordPress default themes.
	 *
	 * The result is cached in a short-lived transient to avoid repeated DB
	 * queries on every admin page load.
	 *
	 * @return bool True when the site looks like a fresh install.
	 */
	public static function isFreshInstall(): bool {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$result = self::evaluate();

		set_transient( self::TRANSIENT_KEY, $result ? '1' : '0', self::CACHE_TTL );

		return $result;
	}

	/**
	 * Run the actual detection logic (no caching).
	 *
	 * @return bool True when the site looks like a fresh install.
	 */
	private static function evaluate(): bool {
		// Check for real published posts (post_type = post).
		if ( self::hasRealContent( 'post' ) ) {
			return false;
		}

		// Check for real published pages (post_type = page).
		if ( self::hasRealContent( 'page' ) ) {
			return false;
		}

		// Check whether the active theme is a default WordPress theme.
		if ( ! self::isDefaultTheme() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether any published content of the given post type exists beyond
	 * the WordPress defaults.
	 *
	 * @param string $post_type Post type to query ('post' or 'page').
	 * @return bool True when real (non-default) published content exists.
	 */
	private static function hasRealContent( string $post_type ): bool {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( empty( $posts ) ) {
			return false;
		}

		foreach ( $posts as $post_id ) {
			$title = get_the_title( (int) $post_id );
			if ( ! in_array( $title, self::DEFAULT_POST_TITLES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the active theme is one of the built-in WordPress defaults.
	 *
	 * @return bool True when the active theme is a default WordPress theme.
	 */
	private static function isDefaultTheme(): bool {
		$theme = wp_get_theme();
		$slug  = $theme->get_stylesheet();

		return in_array( $slug, self::DEFAULT_THEME_SLUGS, true );
	}

	/**
	 * Return a structured summary of the detection result for the REST API.
	 *
	 * @return array{is_fresh_install: bool, has_real_posts: bool, has_real_pages: bool, is_default_theme: bool, active_theme: string}
	 */
	public static function getStatus(): array {
		$has_real_posts = self::hasRealContent( 'post' );
		$has_real_pages = self::hasRealContent( 'page' );
		$is_default     = self::isDefaultTheme();
		$is_fresh       = ! $has_real_posts && ! $has_real_pages && $is_default;

		return [
			'is_fresh_install' => $is_fresh,
			'has_real_posts'   => $has_real_posts,
			'has_real_pages'   => $has_real_pages,
			'is_default_theme' => $is_default,
			'active_theme'     => wp_get_theme()->get_stylesheet(),
		];
	}
}
