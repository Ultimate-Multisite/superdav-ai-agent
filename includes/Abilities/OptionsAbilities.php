<?php

declare(strict_types=1);
/**
 * Options management abilities for the AI agent.
 *
 * Provides get, update, and delete operations for WordPress options. Writes
 * are default-deny: only plugin-owned options and site-allowlisted option
 * names can be modified, and critical core options remain blocklisted.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptionsAbilities {

	/**
	 * Placeholder substituted for a secret option value in any response that
	 * cannot omit the row entirely (e.g. database query results, WP-CLI
	 * `option list` output).
	 *
	 * Centralised so every read surface — get-option, list-options, db-query,
	 * run-php, wp-cli — uses the same opaque token. Reviewers and automated
	 * tests can grep on this constant.
	 *
	 * @var string
	 */
	public const SECRET_REDACTED_PLACEHOLDER = '[redacted: secret option]';

	/**
	 * Authentication keys and salts that must NEVER be returned to the AI
	 * agent, even when stored in the options table.
	 *
	 * WordPress writes these names into `wp_options` when `wp-config.php`
	 * does not define them, so a read path that names them by string would
	 * leak the values. This list is the single source of truth used by every
	 * read surface in the plugin (get-option, list-options, db-query,
	 * run-php with `get_option`/`get_transient`, and wp-cli `option get`).
	 *
	 * Extend via the `sd_ai_agent_options_read_blocklist` filter.
	 *
	 * @var string[]
	 */
	private const SECRET_READ_BLOCKLIST = [
		// Cryptographic keys and salts used to sign auth cookies. Leaking
		// any of these enables session forgery / impersonation.
		'auth_key',
		'secure_auth_key',
		'logged_in_key',
		'nonce_key',
		'auth_salt',
		'secure_auth_salt',
		'logged_in_salt',
		'nonce_salt',
		// Plugin integration credentials are stored separately from general
		// settings and must not be readable through generic option tools.
		'sd_ai_agent_gsc_credentials',
		'sd_ai_agent_google_calendar_credentials',
		'sd_ai_agent_sms_provider',
		'sd_ai_agent_whatsapp_provider',
		'sd_ai_agent_telegram_provider',
	];

	/**
	 * Options that the AI agent is never allowed to modify or delete.
	 *
	 * These are critical WordPress core options whose corruption would break
	 * the site or compromise security. The list can be extended via the
	 * `sd_ai_agent_options_blocklist` filter.
	 *
	 * @var string[]
	 */
	private const WRITE_BLOCKLIST = [
		// Core site identity / URLs — changing these breaks the site.
		'siteurl',
		'home',
		// Admin contact — changing silently locks out the admin.
		'admin_email',
		// Plugin/theme activation state — must go through the Upgrader API.
		'active_plugins',
		'active_sitewide_plugins',
		'template',
		'stylesheet',
		// Database schema version — must only be changed by upgrade routines.
		'db_version',
		'db_upgraded',
		// WordPress core update channel.
		'auto_update_core_type',
		// User roles — changing breaks capability checks site-wide.
		'user_roles',
		// Cron schedule — corrupting this stops all scheduled events.
		'cron',
		// Auth keys / salts — regenerating these logs out all users.
		'auth_key',
		'secure_auth_key',
		'logged_in_key',
		'nonce_key',
		'auth_salt',
		'secure_auth_salt',
		'logged_in_salt',
		'nonce_salt',
		// WordPress secret keys stored as options (some setups).
		'wp_user_roles',
		// Multisite network options.
		'site_admins',
		'allowedthemes',
		// Plugin/theme file editing gate.
		'disallow_file_edit',
		'disallow_file_mods',
		// Integration credentials must be changed only through capability-gated
		// provider settings routes, never through generic option tools.
		'sd_ai_agent_gsc_credentials',
		'sd_ai_agent_google_calendar_credentials',
		'sd_ai_agent_sms_provider',
		'sd_ai_agent_whatsapp_provider',
		'sd_ai_agent_telegram_provider',
	];

	/**
	 * Exact option names the AI agent may modify by default.
	 *
	 * Keep this list narrow and limited to non-secret presentation settings the
	 * agent's own setup/page-build instructions explicitly tell it to manage.
	 * Site owners can opt specific third-party options into AI write access with
	 * the `sd_ai_agent_options_write_allowlist` filter.
	 *
	 * @var string[]
	 */
	private const WRITE_ALLOWLIST = [
		'blogname',
		'blogdescription',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		// WooCommerce launch visibility is a non-secret presentation setting.
		// Setup must be able to publish the real site instead of validating the
		// editor-only homepage while anonymous visitors see Coming soon.
		'woocommerce_coming_soon',
	];

	/**
	 * Option-name prefixes the AI agent may modify by default.
	 *
	 * Limit default write/delete access to this plugin's own option namespace so
	 * arbitrary WordPress core or third-party options cannot be changed merely
	 * because they were missed by the finite blocklist above.
	 *
	 * @var string[]
	 */
	private const WRITE_ALLOWLIST_PREFIXES = [
		'sd_ai_agent_',
	];

	/**
	 * Register all options management abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'sd-ai-agent/get-option',
			[
				'label'         => __( 'Get Option', 'superdav-ai-agent' ),
				'description'   => __( 'Read a WordPress option by name. Returns the stored value or a default if the option does not exist.', 'superdav-ai-agent' ),
				'ability_class' => GetOptionAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/update-option',
			[
				'label'         => __( 'Update Option', 'superdav-ai-agent' ),
				'description'   => __( 'Create or update an allowed WordPress option. Default write access is limited to plugin-owned options; critical system options remain blocked.', 'superdav-ai-agent' ),
				'ability_class' => UpdateOptionAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/delete-option',
			[
				'label'         => __( 'Delete Option', 'superdav-ai-agent' ),
				'description'   => __( 'Delete an allowed WordPress option by name. Default delete access is limited to plugin-owned options; critical system options remain blocked.', 'superdav-ai-agent' ),
				'ability_class' => DeleteOptionAbility::class,
			]
		);

		wp_register_ability(
			'sd-ai-agent/list-options',
			[
				'label'         => __( 'List Options', 'superdav-ai-agent' ),
				'description'   => __( 'List WordPress options with optional prefix filtering. Returns option names and values (truncated for large values). Useful for discovering plugin/theme settings.', 'superdav-ai-agent' ),
				'ability_class' => ListOptionsAbility::class,
			]
		);
	}

	/**
	 * Get the runtime write blocklist (built-in + filtered).
	 *
	 * @return string[]
	 */
	public static function get_write_blocklist(): array {
		/**
		 * Filters the list of WordPress options the AI agent is blocked from writing.
		 *
		 * @since 1.2.0
		 *
		 * @param string[] $blocklist List of blocked option names.
		 */
		$blocklist = apply_filters( 'sd_ai_agent_options_blocklist', self::WRITE_BLOCKLIST );

		return array_values( array_filter( (array) $blocklist, 'is_string' ) );
	}

	/**
	 * Get exact option names the AI agent may modify.
	 *
	 * @return string[]
	 */
	public static function get_write_allowlist(): array {
		/**
		 * Filters the exact WordPress option names the AI agent may write/delete.
		 *
		 * Use this only for options that are safe for an AI-assisted administrator
		 * to manage. The write blocklist still takes precedence.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $allowlist List of allowed option names.
		 */
		$allowlist = apply_filters( 'sd_ai_agent_options_write_allowlist', self::WRITE_ALLOWLIST );

		return array_values( array_filter( (array) $allowlist, 'is_string' ) );
	}

	/**
	 * Get option-name prefixes the AI agent may modify.
	 *
	 * @return string[]
	 */
	public static function get_write_allowlist_prefixes(): array {
		/**
		 * Filters option-name prefixes the AI agent may write/delete.
		 *
		 * The default allows only this plugin's `sd_ai_agent_` options. The write
		 * blocklist still takes precedence over every prefix.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $prefixes List of allowed option-name prefixes.
		 */
		$prefixes = apply_filters( 'sd_ai_agent_options_write_allowlist_prefixes', self::WRITE_ALLOWLIST_PREFIXES );

		return array_values( array_filter( (array) $prefixes, 'is_string' ) );
	}

	/**
	 * Predicate: may the AI agent modify or delete the given option name?
	 *
	 * The write policy is default-deny. Exact allowlist entries and allowed
	 * prefixes grant access, but the blocklist always takes precedence.
	 *
	 * @param string $option_name Option name to test.
	 * @return bool True if the option is safe for write/delete access.
	 */
	public static function is_write_allowed_option( string $option_name ): bool {
		if ( '' === $option_name ) {
			return false;
		}

		if ( in_array( $option_name, self::get_write_blocklist(), true ) ) {
			return false;
		}

		if ( in_array( $option_name, self::get_write_allowlist(), true ) ) {
			return true;
		}

		foreach ( self::get_write_allowlist_prefixes() as $prefix ) {
			if ( '' !== $prefix && str_starts_with( $option_name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the runtime read blocklist for secret option names.
	 *
	 * The list is intentionally narrower than the write blocklist: a few
	 * options (e.g. `siteurl`, `active_plugins`) are write-blocked because
	 * mutating them would break the site, but their values are not secrets
	 * and may legitimately be inspected. Only values whose disclosure would
	 * enable session forgery or impersonation belong here.
	 *
	 * @return string[]
	 */
	public static function get_secret_read_blocklist(): array {
		/**
		 * Filters the list of WordPress option names whose values must never
		 * be returned by any AI-agent read surface.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $blocklist List of secret option names.
		 */
		$blocklist = apply_filters( 'sd_ai_agent_options_read_blocklist', self::SECRET_READ_BLOCKLIST );

		return array_values( array_filter( (array) $blocklist, 'is_string' ) );
	}

	/**
	 * Predicate: is the given option name in the secret read blocklist?
	 *
	 * Comparison is exact-match and case-sensitive — WordPress option names
	 * are case-sensitive at the storage layer.
	 *
	 * @param string $option_name Option name to test.
	 * @return bool True if the name is a known secret.
	 */
	public static function is_secret_option_name( string $option_name ): bool {
		if ( '' === $option_name ) {
			return false;
		}

		return in_array( $option_name, self::get_secret_read_blocklist(), true );
	}

	/**
	 * Build a uniform WP_Error for a blocked secret read across surfaces.
	 *
	 * @param string $option_name Option name that was requested.
	 * @return WP_Error
	 */
	public static function secret_read_error( string $option_name ): WP_Error {
		return new WP_Error(
			'sd_ai_agent_option_secret_redacted',
			sprintf(
				/* translators: %s: option name */
				__( 'The option "%s" stores an authentication secret and cannot be read by the AI agent.', 'superdav-ai-agent' ),
				$option_name
			),
			array( 'status' => 403 )
		);
	}
}
