<?php
/**
 * MU-Plugin: Test helpers for AI Agent development.
 *
 * Loaded automatically by wp-env in the development environment.
 * Provides debugging aids and test fixtures.
 *
 * @package AiAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enable error display in development.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
}
