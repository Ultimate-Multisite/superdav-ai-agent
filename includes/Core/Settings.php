<?php

declare(strict_types=1);
/**
 * Plugin settings management.
 *
 * Stores all Gratis AI Agent settings in a single WordPress option and provides
 * a React-based settings page under Tools > Gratis AI Agent Settings.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Core\CredentialResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/**
	 * Option name in the wp_options table.
	 */
	const OPTION_NAME = 'gratis_ai_agent_settings';

	/**
	 * Separate option name for the Claude Max OAuth token (stored apart from
	 * general settings so it can be given stricter access control).
	 */
	const CLAUDE_MAX_TOKEN_OPTION = 'gratis_ai_agent_claude_max_token';

	/**
	 * Option name for directly-configured provider API keys.
	 * Stored separately from general settings to avoid leaking credentials
	 * through the GET /settings endpoint.
	 */
	const PROVIDER_KEYS_OPTION = 'gratis_ai_agent_provider_keys';

	/**
	 * Option name for Google Search Console credentials.
	 * Stored separately from general settings to avoid leaking credentials
	 * through the GET /settings endpoint.
	 */
	const GSC_CREDENTIALS_OPTION = 'gratis_ai_agent_gsc_credentials';

	/**
	 * Option name for the feedback-report receiver API key.
	 * Stored separately from general settings to avoid leaking the credential
	 * through the GET /settings endpoint.
	 */
	const FEEDBACK_API_KEY_OPTION = 'gratis_ai_agent_feedback_api_key';

	/**
	 * Supported direct providers with their metadata.
	 */
	const DIRECT_PROVIDERS = array(
		'openai'    => array(
			'name'          => 'OpenAI',
			'default_model' => 'gpt-4.1-nano',
			'models'        => array(
				array(
					'id'   => 'gpt-4.1-nano',
					'name' => 'GPT-4.1 Nano',
				),
				array(
					'id'   => 'gpt-4.1-mini',
					'name' => 'GPT-4.1 Mini',
				),
				array(
					'id'   => 'gpt-4.1',
					'name' => 'GPT-4.1',
				),
				array(
					'id'   => 'gpt-4o',
					'name' => 'GPT-4o',
				),
				array(
					'id'   => 'gpt-4o-mini',
					'name' => 'GPT-4o Mini',
				),
				array(
					'id'   => 'gpt-4-turbo',
					'name' => 'GPT-4 Turbo',
				),
				array(
					'id'   => 'o1',
					'name' => 'o1',
				),
				array(
					'id'   => 'o1-mini',
					'name' => 'o1 Mini',
				),
				array(
					'id'   => 'o3-mini',
					'name' => 'o3 Mini',
				),
				array(
					'id'   => 'o3',
					'name' => 'o3',
				),
				array(
					'id'   => 'o4-mini',
					'name' => 'o4 Mini',
				),
			),
		),
		'anthropic' => array(
			'name'          => 'Anthropic',
			'default_model' => 'claude-sonnet-4-6',
			'models'        => array(
				array(
					'id'   => 'claude-opus-4-6',
					'name' => 'Claude Opus 4.6',
				),
				array(
					'id'   => 'claude-sonnet-4-6',
					'name' => 'Claude Sonnet 4.6',
				),
				array(
					'id'   => 'claude-opus-4-5',
					'name' => 'Claude Opus 4.5',
				),
				array(
					'id'   => 'claude-sonnet-4-5',
					'name' => 'Claude Sonnet 4.5',
				),
				array(
					'id'   => 'claude-haiku-3-5',
					'name' => 'Claude Haiku 3.5',
				),
				array(
					'id'   => 'claude-3-5-haiku-20241022',
					'name' => 'Claude 3.5 Haiku',
				),
				array(
					'id'   => 'claude-opus-4-20250514',
					'name' => 'Claude Opus 4',
				),
				array(
					'id'   => 'claude-sonnet-4-20250514',
					'name' => 'Claude Sonnet 4',
				),
				array(
					'id'   => 'claude-haiku-3-20241022',
					'name' => 'Claude Haiku 3',
				),
			),
		),
		'google'    => array(
			'name'          => 'Google',
			'default_model' => 'gemini-2.0-flash',
			'models'        => array(
				array(
					'id'   => 'gemini-2.5-pro-preview-05-06',
					'name' => 'Gemini 2.5 Pro',
				),
				array(
					'id'   => 'gemini-2.5-flash-preview',
					'name' => 'Gemini 2.5 Flash',
				),
				array(
					'id'   => 'gemini-2.5-flash-lite-preview',
					'name' => 'Gemini 2.5 Flash Lite',
				),
				array(
					'id'   => 'gemini-2.0-flash',
					'name' => 'Gemini 2.0 Flash',
				),
				array(
					'id'   => 'gemini-2.0-flash-lite',
					'name' => 'Gemini 2.0 Flash Lite',
				),
				array(
					'id'   => 'gemini-1.5-pro',
					'name' => 'Gemini 1.5 Pro',
				),
				array(
					'id'   => 'gemini-1.5-flash',
					'name' => 'Gemini 1.5 Flash',
				),
			),
		),
	);

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'default_provider'         => '',
			'default_model'            => '',
			'max_iterations'           => 25,
			'greeting_message'         => '',
			'system_prompt'            => '',
			'auto_memory'              => true,
			'tool_permissions'         => array(),
			'temperature'              => 0.7,
			'max_output_tokens'        => 4096,
			'context_window_default'   => 128000,
			'onboarding_complete'      => false,
			'use_claude_max'           => false,
			'knowledge_enabled'        => true,
			'knowledge_auto_index'     => true,
			'max_history_turns'        => 20,
			'suggestion_count'         => 3,
			'yolo_mode'                => false,
			'site_builder_mode'        => false,
			'show_on_frontend'         => false,
			'keyboard_shortcut'        => 'alt+a',
			'image_generation_size'    => '1024x1024',
			'image_generation_quality' => 'standard',
			'image_generation_style'   => 'vivid',
			// White-label / branding settings (t075).
			'agent_name'               => '',
			'brand_primary_color'      => '',
			'brand_text_color'         => '',
			'brand_logo_url'           => '',
			// Spending limits / budget caps (t110).
			'budget_daily_cap'         => 0.0,
			'budget_monthly_cap'       => 0.0,
			'budget_warning_threshold' => 80,
			'budget_exceeded_action'   => 'pause',
			// Provider trace / debug mode (GH#830).
			'provider_trace_enabled'   => false,
			'provider_trace_max_rows'  => 200,
			// Feedback report receiver settings (t180).
			'feedback_enabled'         => false,
			'feedback_endpoint_url'    => '',
		);
	}

	/**
	 * Get the API key for a directly-configured provider.
	 *
	 * Keys are stored in a dedicated option, never in the general settings blob,
	 * so they are not exposed through GET /settings.
	 *
	 * @param string $provider_id One of 'openai', 'anthropic', 'google'.
	 * @return string Empty string when not configured.
	 */
	public static function get_provider_key( string $provider_id ): string {
		$keys = get_option( self::PROVIDER_KEYS_OPTION, array() );
		// @phpstan-ignore-next-line
		return isset( $keys[ $provider_id ] ) ? (string) $keys[ $provider_id ] : '';
	}

	/**
	 * Persist an API key for a directly-configured provider.
	 *
	 * Pass an empty string to clear the credential.
	 *
	 * @param string $provider_id One of 'openai', 'anthropic', 'google'.
	 * @param string $api_key     The API key value.
	 * @return bool True on success.
	 */
	public static function set_provider_key( string $provider_id, string $api_key ): bool {
		if ( ! array_key_exists( $provider_id, self::DIRECT_PROVIDERS ) ) {
			return false;
		}

		$keys = get_option( self::PROVIDER_KEYS_OPTION, array() );

		if ( '' === $api_key ) {
			// @phpstan-ignore-next-line
			unset( $keys[ $provider_id ] );
		} else {
			// @phpstan-ignore-next-line
			$keys[ $provider_id ] = $api_key;
		}

		return update_option( self::PROVIDER_KEYS_OPTION, $keys );
	}

	/**
	 * Get all configured direct providers (those with a non-empty API key).
	 *
	 * Returns an array of provider metadata arrays, each with:
	 *   - id, name, configured (bool), models (array), has_key (bool)
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_configured_direct_providers(): array {
		$result = array();
		foreach ( self::DIRECT_PROVIDERS as $id => $meta ) {
			$key      = self::get_provider_key( $id );
			$result[] = array(
				'id'         => $id,
				'name'       => $meta['name'],
				'configured' => '' !== $key,
				'has_key'    => '' !== $key,
				'models'     => $meta['models'],
			);
		}
		return $result;
	}

	/**
	 * Get the stored Google Search Console credentials.
	 *
	 * Returns an associative array with the credential type and fields, or an
	 * empty array when not configured. The raw credential values are NEVER
	 * returned through GET /settings — only a boolean presence flag is exposed.
	 *
	 * Supported shapes:
	 *   Service account: ['type' => 'service_account', 'client_email' => '...', 'private_key' => '...', 'default_site_url' => '...']
	 *   Access token:    ['type' => 'access_token', 'access_token' => '...', 'default_site_url' => '...']
	 *
	 * @return array<string, mixed> Credential array or empty array.
	 */
	public static function get_gsc_credentials(): array {
		$creds = get_option( self::GSC_CREDENTIALS_OPTION, array() );
		/** @var array<string, mixed> $result */
		$result = is_array( $creds ) ? $creds : array();
		return $result;
	}

	/**
	 * Persist Google Search Console credentials.
	 *
	 * Pass an empty array to clear the credentials.
	 *
	 * @param array<string, mixed> $credentials Credential array (see get_gsc_credentials() for shape).
	 * @return bool True on success.
	 */
	public static function set_gsc_credentials( array $credentials ): bool {
		if ( empty( $credentials ) ) {
			return delete_option( self::GSC_CREDENTIALS_OPTION );
		}

		return update_option( self::GSC_CREDENTIALS_OPTION, $credentials );
	}

	/**
	 * Check whether GSC credentials are configured.
	 *
	 * @return bool
	 */
	public static function has_gsc_credentials(): bool {
		$creds = self::get_gsc_credentials();
		return ! empty( $creds['type'] );
	}

	/**
	 * Get the stored Claude Max OAuth access token.
	 *
	 * Delegates to {@see CredentialResolver::getClaudeMaxToken()} so that all
	 * credential reads are centralised in one place.
	 *
	 * @return string Empty string when not configured.
	 */
	public static function get_claude_max_token(): string {
		return CredentialResolver::getClaudeMaxToken();
	}

	/**
	 * Persist the Claude Max OAuth access token.
	 *
	 * Delegates to {@see CredentialResolver::setClaudeMaxToken()}.
	 * Pass an empty string to clear the credential.
	 *
	 * @param string $token The OAuth access token (sk-ant-oat01-… or similar).
	 * @return bool True on success.
	 */
	public static function set_claude_max_token( string $token ): bool {
		return CredentialResolver::setClaudeMaxToken( $token );
	}

	/**
	 * Resolve the effective default model ID.
	 *
	 * Resolution order (first non-empty value wins):
	 *   1. `default_model` setting saved by the site administrator.
	 *   2. Value returned by the `gratis_ai_agent_default_model` filter (allows
	 *      developers to override the default programmatically).
	 *   3. The `GRATIS_AI_AGENT_DEFAULT_MODEL` constant defined in the plugin root.
	 *
	 * Example — override the default model from a theme or mu-plugin:
	 *
	 *   add_filter( 'gratis_ai_agent_default_model', function ( string $model ): string {
	 *       return 'gpt-4o';
	 *   } );
	 *
	 * @return string Non-empty model ID.
	 */
	public static function get_default_model(): string {
		$settings = self::get();
		// @phpstan-ignore-next-line
		$model = (string) ( $settings['default_model'] ?? '' );

		if ( '' === $model ) {
			$builtin = defined( 'GRATIS_AI_AGENT_DEFAULT_MODEL' ) ? (string) GRATIS_AI_AGENT_DEFAULT_MODEL : 'claude-sonnet-4';

			/**
			 * Filter the default model ID used when no model is configured in settings.
			 *
			 * @param string $model The built-in fallback model ID (GRATIS_AI_AGENT_DEFAULT_MODEL).
			 */
			$model = (string) apply_filters( 'gratis_ai_agent_default_model', $builtin );
		}

		return $model;
	}

	/**
	 * Get a single setting or all settings merged with defaults.
	 *
	 * @param string|null $key Optional key to retrieve.
	 * @return mixed
	 */
	public static function get( ?string $key = null ) {
		$saved    = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();
		// @phpstan-ignore-next-line
		$merged = wp_parse_args( $saved, $defaults );

		if ( null === $key ) {
			return $merged;
		}

		return $merged[ $key ] ?? ( $defaults[ $key ] ?? null );
	}

	/**
	 * Partial-update settings (merge incoming data with existing).
	 *
	 * @param array<string, mixed> $data Key-value pairs to update.
	 * @return bool
	 */
	public static function update( array $data ): bool {
		$current  = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();

		// Only allow known keys.
		$allowed = array_keys( $defaults );
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		// @phpstan-ignore-next-line
		$merged = array_merge( $current, $data );

		return update_option( self::OPTION_NAME, $merged );
	}

	/**
	 * Get the stored feedback-report receiver API key.
	 *
	 * The key is intentionally stored in a dedicated option so it is never
	 * returned by GET /settings and cannot leak through the JSON response.
	 *
	 * @return string Empty string when not configured.
	 */
	public static function get_feedback_api_key(): string {
		$key = get_option( self::FEEDBACK_API_KEY_OPTION, '' );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Persist the feedback-report receiver API key.
	 *
	 * Pass an empty string to clear the credential.
	 *
	 * @param string $api_key The API key value.
	 * @return bool True on success.
	 */
	public static function set_feedback_api_key( string $api_key ): bool {
		if ( '' === $api_key ) {
			return delete_option( self::FEEDBACK_API_KEY_OPTION );
		}

		return update_option( self::FEEDBACK_API_KEY_OPTION, $api_key );
	}

	/**
	 * Check whether a feedback-report receiver API key is configured.
	 *
	 * @return bool
	 */
	public static function has_feedback_api_key(): bool {
		return '' !== self::get_feedback_api_key();
	}
}
