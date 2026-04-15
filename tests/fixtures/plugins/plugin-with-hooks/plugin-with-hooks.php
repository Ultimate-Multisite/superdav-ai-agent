<?php

declare(strict_types=1);
/**
 * Plugin Name: Plugin With Hooks
 * Plugin URI:  https://example.com
 * Description: Fixture plugin that declares several WordPress hooks for HookScanner tests.
 * Version:     1.0.0
 * Author:      Test
 * License:     GPL-2.0-or-later
 */

namespace PluginWithHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Actions — add_action / do_action.
add_action( 'init', __NAMESPACE__ . '\\plugin_init' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );
do_action( 'plugin_with_hooks_loaded' );

// Filters — add_filter / apply_filters.
add_filter( 'the_content', __NAMESPACE__ . '\\filter_content' );
add_filter( 'the_title', __NAMESPACE__ . '\\filter_title' );
apply_filters( 'plugin_with_hooks_config', [] );

/**
 * Plugin initialisation callback.
 */
function plugin_init(): void {
	// Emit a custom action so extension plugins can hook in.
	do_action( 'plugin_with_hooks_init' );
}

/**
 * Enqueue plugin assets.
 */
function enqueue_assets(): void {
	// No-op for fixture.
}

/**
 * Filter post content.
 *
 * @param string $content Post content.
 * @return string
 */
function filter_content( string $content ): string {
	return apply_filters( 'plugin_with_hooks_content', $content );
}

/**
 * Filter post title.
 *
 * @param string $title Post title.
 * @return string
 */
function filter_title( string $title ): string {
	return $title;
}
