<?php

declare(strict_types=1);
/**
 * Per-tool WordPress capability checks for Gratis AI Agent abilities.
 *
 * Provides granular capability checks so each ability can be granted
 * independently via user roles. Capability names follow the pattern
 * `gratis_ai_agent_tool_{name}` where `{name}` is derived from the ability ID
 * (e.g. `gratis-ai-agent/memory-save` → `gratis_ai_agent_tool_memory_save`).
 *
 * Fallback: if the capability has not been granted to any role, the check
 * falls back to `manage_options` (admin only) so the default behaviour is
 * unchanged until an administrator explicitly delegates a capability.
 *
 * Filter: `gratis_ai_agent_tool_capability` allows overriding the resolved
 * capability name per tool.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ToolCapabilities
 *
 * Static helper that resolves and checks per-tool WordPress capabilities.
 */
class ToolCapabilities {

	/**
	 * Fallback capability used when a tool-specific capability has not been
	 * granted to any role.
	 */
	public const FALLBACK_CAP = 'manage_options';

	/**
	 * Derive the tool-specific capability name from an ability ID.
	 *
	 * Examples:
	 *   gratis-ai-agent/memory-save  → gratis_ai_agent_tool_memory_save
	 *   gratis-ai-agent/db-query     → gratis_ai_agent_tool_db_query
	 *   gratis-ai-agent/run-php      → gratis_ai_agent_tool_run_php
	 *
	 * @param string $ability_id The ability ID (e.g. "gratis-ai-agent/memory-save").
	 * @return string The derived capability name.
	 */
	public static function cap_name( string $ability_id ): string {
		// Strip the "gratis-ai-agent/" namespace prefix.
		$name = str_replace( 'gratis-ai-agent/', '', $ability_id );

		// Replace hyphens and slashes with underscores.
		$name = str_replace( [ '-', '/' ], '_', $name );

		return 'gratis_ai_agent_tool_' . $name;
	}

	/**
	 * Check whether the current user can execute a given ability.
	 *
	 * Resolution order:
	 * 1. Apply the `gratis_ai_agent_tool_capability` filter to allow overrides.
	 * 2. If the resolved capability exists in any role, use it.
	 * 3. Otherwise fall back to `manage_options`.
	 *
	 * @param string $ability_id The ability ID (e.g. "gratis-ai-agent/memory-save").
	 * @return bool True if the current user has permission.
	 */
	public static function current_user_can( string $ability_id ): bool {
		$tool_cap = self::cap_name( $ability_id );

		/**
		 * Filter the capability name used to gate a specific Gratis AI Agent tool.
		 *
		 * @param string $tool_cap   The derived capability name (e.g. "gratis_ai_agent_tool_memory_save").
		 * @param string $ability_id The full ability ID (e.g. "gratis-ai-agent/memory-save").
		 */
		$resolved_cap = (string) apply_filters( 'gratis_ai_agent_tool_capability', $tool_cap, $ability_id );

		// If the capability has been granted to at least one role, use it.
		// Otherwise fall back to manage_options so the default is admin-only.
		if ( self::capability_exists( $resolved_cap ) ) {
			return current_user_can( $resolved_cap );
		}

		return current_user_can( self::FALLBACK_CAP );
	}

	/**
	 * Check whether a capability has been granted to at least one role.
	 *
	 * This is used to distinguish between "capability exists but user lacks it"
	 * and "capability has never been registered/granted", so we can fall back
	 * to manage_options in the latter case.
	 *
	 * @param string $cap Capability name.
	 * @return bool True if the capability is present in at least one role.
	 */
	public static function capability_exists( string $cap ): bool {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return false;
		}

		foreach ( $wp_roles->roles as $role ) {
			if ( ! empty( $role['capabilities'][ $cap ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register all Gratis AI Agent tool capabilities on the Administrator role.
	 *
	 * Called on plugin activation and `admin_init` so that the capabilities
	 * are available for role-management plugins (e.g. Members, User Role Editor)
	 * to discover and assign.
	 *
	 * The capabilities are added to the Administrator role with `true` so that
	 * admins retain full access. Other roles start with no access and must be
	 * explicitly granted capabilities.
	 *
	 * @param string[] $ability_ids List of all ability IDs to register.
	 */
	public static function register_capabilities( array $ability_ids ): void {
		$admin_role = get_role( 'administrator' );

		if ( ! $admin_role instanceof \WP_Role ) {
			return;
		}

		foreach ( $ability_ids as $ability_id ) {
			$cap = self::cap_name( $ability_id );

			/**
			 * Allow overriding the capability name at registration time.
			 *
			 * @param string $cap        The derived capability name.
			 * @param string $ability_id The full ability ID.
			 */
			$cap = (string) apply_filters( 'gratis_ai_agent_tool_capability', $cap, $ability_id );

			if ( ! isset( $admin_role->capabilities[ $cap ] ) ) {
				$admin_role->add_cap( $cap, true );
			}
		}
	}

	/**
	 * Return the list of all Gratis AI Agent ability IDs that have tool capabilities.
	 *
	 * This list is used both for capability registration and for documentation.
	 *
	 * @return string[]
	 */
	public static function all_ability_ids(): array {
		return [
			// Memory.
			'gratis-ai-agent/memory-save',
			'gratis-ai-agent/memory-list',
			'gratis-ai-agent/memory-delete',
			// Skills.
			'gratis-ai-agent/skill-load',
			'gratis-ai-agent/skill-list',
			// Knowledge.
			'gratis-ai-agent/knowledge-search',
			// Ability discovery.
			'gratis-ai-agent/discovery-list',
			'gratis-ai-agent/discovery-get',
			'gratis-ai-agent/discovery-execute',
			// Stock images.
			'gratis-ai-agent/import-stock-image',
			// SEO.
			'gratis-ai-agent/seo-audit-url',
			'gratis-ai-agent/seo-analyze-content',
			// Content.
			'gratis-ai-agent/content-analyze',
			'gratis-ai-agent/content-performance-report',
			// Marketing.
			'gratis-ai-agent/fetch-url',
			'gratis-ai-agent/analyze-headers',
			// Blocks.
			'gratis-ai-agent/markdown-to-blocks',
			'gratis-ai-agent/list-block-types',
			'gratis-ai-agent/get-block-type',
			'gratis-ai-agent/list-block-patterns',
			'gratis-ai-agent/list-block-templates',
			'gratis-ai-agent/create-block-content',
			'gratis-ai-agent/parse-block-content',
			// Files.
			'gratis-ai-agent/file-read',
			'gratis-ai-agent/file-write',
			'gratis-ai-agent/file-edit',
			'gratis-ai-agent/file-delete',
			'gratis-ai-agent/file-list',
			'gratis-ai-agent/file-search',
			'gratis-ai-agent/content-search',
			// Database.
			'gratis-ai-agent/db-query',
			// WordPress management.
			'gratis-ai-agent/get-plugins',
			'gratis-ai-agent/get-themes',
			'gratis-ai-agent/install-plugin',
			'gratis-ai-agent/run-php',
			// Navigation.
			'gratis-ai-agent/navigate',
			'gratis-ai-agent/get-page-html',
			// Git.
			'gratis-ai-agent/git-list',
			'gratis-ai-agent/git-diff',
			'gratis-ai-agent/git-snapshot',
			'gratis-ai-agent/git-restore',
			'gratis-ai-agent/git-revert-package',
			'gratis-ai-agent/git-package-summary',
			// Site health.
			'gratis-ai-agent/site-health-summary',
			'gratis-ai-agent/check-plugin-updates',
			'gratis-ai-agent/check-security',
			'gratis-ai-agent/check-performance',
			'gratis-ai-agent/check-disk-space',
			'gratis-ai-agent/scan-php-error-log',
			// Google Analytics.
			'gratis-ai-agent/ga-traffic-summary',
			'gratis-ai-agent/ga-top-pages',
			'gratis-ai-agent/ga-realtime',
		];
	}
}
