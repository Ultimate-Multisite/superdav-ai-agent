<?php
/**
 * WP-CLI `--require` loader: run the checkout in SD_AUDIT_PLUGIN instead of the
 * installed plugin. Use with `--skip-plugins=superdav-ai-agent`, so a shared
 * WordPress install can measure any worktree without changing plugin symlinks.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

// Runs before WordPress loads: hooks can only be registered by pre-populating $wp_filter.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

$sd_audit_plugin = getenv( 'SD_AUDIT_PLUGIN' );
if ( ! is_string( $sd_audit_plugin ) || ! is_file( $sd_audit_plugin . '/superdav-ai-agent.php' ) ) {
	fwrite( STDERR, "Set SD_AUDIT_PLUGIN to a checkout containing superdav-ai-agent.php.\n" );
	exit( 1 );
}

// WordPress converts pre-populated $wp_filter arrays into hooks during bootstrap.
$GLOBALS['wp_filter']['muplugins_loaded'][10][] = array(
	'function'      => static function () use ( $sd_audit_plugin ) {
		require $sd_audit_plugin . '/superdav-ai-agent.php';
	},
	'accepted_args' => 0,
);
