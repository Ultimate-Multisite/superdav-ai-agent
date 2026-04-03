<?php
/**
 * Uninstall handler for Gratis AI Agent.
 *
 * Runs when the user deletes the plugin from the WordPress admin.
 * Removes all plugin data: database tables, options, user meta, and cron events.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Guard: only run when WordPress triggers uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── 1. Drop all plugin database tables ──────────────────────────────────────
$tables = [
	$wpdb->prefix . 'gratis_ai_agent_sessions',
	$wpdb->prefix . 'gratis_ai_agent_usage',
	$wpdb->prefix . 'gratis_ai_agent_memories',
	$wpdb->prefix . 'gratis_ai_agent_skills',
	$wpdb->prefix . 'gratis_ai_agent_custom_tools',
	$wpdb->prefix . 'gratis_ai_agent_automations',
	$wpdb->prefix . 'gratis_ai_agent_automation_logs',
	$wpdb->prefix . 'gratis_ai_agent_event_automations',
	$wpdb->prefix . 'gratis_ai_agent_conversation_templates',
	$wpdb->prefix . 'gratis_ai_agent_git_tracked_files',
	$wpdb->prefix . 'gratis_ai_agent_changes_log',
	$wpdb->prefix . 'gratis_ai_agent_modified_files',
	$wpdb->prefix . 'gratis_ai_agent_agents',
	$wpdb->prefix . 'gratis_ai_agent_shared_sessions',
	$wpdb->prefix . 'gratis_ai_agent_benchmark_runs',
	$wpdb->prefix . 'gratis_ai_agent_benchmark_results',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall handler must drop tables; names are from $wpdb->prefix only.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// ── 2. Delete all plugin options ─────────────────────────────────────────────
$options = [
	'gratis_ai_agent_settings',
	'gratis_ai_agent_db_version',
	'gratis_ai_agent_claude_max_token',
	'gratis_ai_agent_provider_keys',
	'gratis_ai_agent_gsc_credentials',
	'gratis_ai_agent_tool_profiles',
	'gratis_ai_agent_custom_tools_seeded',
	'gratis_ai_agent_migrated_from_ai_agent',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── 3. Delete user meta with plugin prefix ───────────────────────────────────
// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- uninstall-only, acceptable cost.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'gratis_ai_agent_' ) . '%'
	)
);

// ── 4. Clear scheduled cron events ───────────────────────────────────────────
$cron_hooks = [
	'gratis_ai_agent_run_automation',
	'gratis_ai_agent_run_event_automation',
	'gratis_ai_agent_site_scan',
	'wp_gratis_ai_agent_reindex',
];

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
