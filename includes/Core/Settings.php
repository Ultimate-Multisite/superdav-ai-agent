<?php

declare(strict_types=1);
/**
 * Plugin settings management.
 *
 * Stores all AI Agent settings in a single WordPress option and provides
 * a React-based settings page under Tools > AI Agent Settings.
 *
 * @package AiAgent
 */

namespace AiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/**
	 * Option name in the wp_options table.
	 */
	const OPTION_NAME = 'ai_agent_settings';

	/**
	 * Separate option name for the Claude Max OAuth token (stored apart from
	 * general settings so it can be given stricter access control).
	 */
	const CLAUDE_MAX_TOKEN_OPTION = 'ai_agent_claude_max_token';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'ai-agent-settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return [
			'default_provider'         => '',
			'default_model'            => '',
			'max_iterations'           => 25,
			'greeting_message'         => '',
			'system_prompt'            => '',
			'auto_memory'              => true,
			'disabled_abilities'       => [],
			'tool_permissions'         => [],
			'temperature'              => 0.7,
			'max_output_tokens'        => 4096,
			'context_window_default'   => 128000,
			'onboarding_complete'      => false,
			'use_claude_max'           => false,
			'knowledge_enabled'        => true,
			'knowledge_auto_index'     => true,
			'tool_discovery_mode'      => 'auto',
			'tool_discovery_threshold' => 20,
			'active_tool_profile'      => '',
			'max_history_turns'        => 20,
			'suggestion_count'         => 3,
		];
	}

	/**
	 * Get the stored Claude Max OAuth access token.
	 *
	 * The token is stored in its own option rather than the general settings
	 * blob so that it can be excluded from REST API exposure and treated as a
	 * credential (not a preference).
	 *
	 * @return string Empty string when not configured.
	 */
	public static function get_claude_max_token(): string {
		return (string) get_option( self::CLAUDE_MAX_TOKEN_OPTION, '' );
	}

	/**
	 * Persist the Claude Max OAuth access token.
	 *
	 * Pass an empty string to clear the credential.
	 *
	 * @param string $token The OAuth access token (sk-ant-oat01-… or similar).
	 * @return bool True on success.
	 */
	public static function set_claude_max_token( string $token ): bool {
		if ( '' === $token ) {
			return delete_option( self::CLAUDE_MAX_TOKEN_OPTION );
		}
		return update_option( self::CLAUDE_MAX_TOKEN_OPTION, $token );
	}

	/**
	 * Get a single setting or all settings merged with defaults.
	 *
	 * @param string|null $key Optional key to retrieve.
	 * @return mixed
	 */
	public static function get( ?string $key = null ) {
		$saved    = get_option( self::OPTION_NAME, [] );
		$defaults = self::get_defaults();
		$merged   = wp_parse_args( $saved, $defaults );

		if ( null === $key ) {
			return $merged;
		}

		return $merged[ $key ] ?? ( $defaults[ $key ] ?? null );
	}

	/**
	 * Partial-update settings (merge incoming data with existing).
	 *
	 * @param array $data Key-value pairs to update.
	 * @return bool
	 */
	public static function update( array $data ): bool {
		$current  = get_option( self::OPTION_NAME, [] );
		$defaults = self::get_defaults();

		// Only allow known keys.
		$allowed = array_keys( $defaults );
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		$merged = array_merge( $current, $data );

		return update_option( self::OPTION_NAME, $merged );
	}

	/**
	 * Register the admin menu page.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'AI Agent Settings', 'ai-agent' ),
			__( 'AI Agent Settings', 'ai-agent' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render' ]
		);

		if ( $hook ) {
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		}
	}

	/**
	 * Enqueue the settings page React app.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = AI_AGENT_DIR . '/build/settings-page.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'ai-agent-settings-page',
			AI_AGENT_URL . 'build/style-settings-page.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'ai-agent-settings-page',
			AI_AGENT_URL . 'build/settings-page.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Render the settings page mount point.
	 */
	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Agent Settings', 'ai-agent' ); ?></h1>
			<div id="ai-agent-settings-root"></div>
		</div>
		<?php
	}
}
