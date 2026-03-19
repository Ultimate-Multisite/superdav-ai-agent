<?php

declare(strict_types=1);
/**
 * Credential resolution for AI provider API keys and connection settings.
 *
 * Centralises all reads and writes of credential-related WordPress options so
 * that the rest of the plugin never calls get_option() / update_option()
 * directly for secrets.  Handles:
 *
 *  - OpenAI-compatible connector (endpoint URL, API key, timeout)
 *  - AI Experiments plugin provider credentials array
 *  - Claude Max OAuth token
 *  - WordPress 7.0 Connectors API (read-only, delegated to WP functions)
 *
 * @package GratisAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CredentialResolver {

	// ── Option names ──────────────────────────────────────────────────────────

	/**
	 * WordPress option that stores the OpenAI-compatible endpoint URL.
	 */
	const OPENAI_COMPAT_ENDPOINT_OPTION = 'openai_compat_endpoint_url';

	/**
	 * WordPress option that stores the OpenAI-compatible API key.
	 */
	const OPENAI_COMPAT_API_KEY_OPTION = 'openai_compat_api_key';

	/**
	 * WordPress option that stores the OpenAI-compatible request timeout (seconds).
	 */
	const OPENAI_COMPAT_TIMEOUT_OPTION = 'openai_compat_timeout';

	/**
	 * WordPress option that stores the AI Experiments plugin provider credentials.
	 * Shape: array<string, string>  (provider_id => api_key)
	 */
	const AI_EXPERIMENTS_CREDENTIALS_OPTION = 'wp_ai_client_provider_credentials';

	/**
	 * WordPress option that stores the Claude Max OAuth access token.
	 */
	const CLAUDE_MAX_TOKEN_OPTION = 'gratis_ai_agent_claude_max_token';

	/**
	 * Sentinel value used when no real API key is available but a non-empty
	 * string is required by the HTTP client.
	 */
	const NO_KEY_SENTINEL = 'no-key';

	// ── OpenAI-compatible connector ───────────────────────────────────────────

	/**
	 * Return the configured OpenAI-compatible endpoint URL, trailing slash stripped.
	 *
	 * @return string Empty string when not configured.
	 */
	public static function getOpenAiCompatEndpointUrl(): string {
		return rtrim( (string) get_option( self::OPENAI_COMPAT_ENDPOINT_OPTION, '' ), '/' );
	}

	/**
	 * Return the OpenAI-compatible API key.
	 *
	 * Falls back to {@see NO_KEY_SENTINEL} when the option is empty so that
	 * callers can always pass a non-empty Authorization header.
	 *
	 * @param bool $allow_sentinel When true (default) returns the sentinel
	 *                             instead of an empty string.  Pass false to
	 *                             get the raw stored value (empty string when
	 *                             not configured).
	 * @return string
	 */
	public static function getOpenAiCompatApiKey( bool $allow_sentinel = true ): string {
		$key = (string) get_option( self::OPENAI_COMPAT_API_KEY_OPTION, '' );

		if ( '' === $key && $allow_sentinel ) {
			return self::NO_KEY_SENTINEL;
		}

		return $key;
	}

	/**
	 * Return the OpenAI-compatible request timeout in seconds.
	 *
	 * @return int Default 600 seconds.
	 */
	public static function getOpenAiCompatTimeout(): int {
		return (int) get_option( self::OPENAI_COMPAT_TIMEOUT_OPTION, 600 );
	}

	// ── AI Experiments plugin credentials ─────────────────────────────────────

	/**
	 * Return the full provider-credentials array stored by the AI Experiments plugin.
	 *
	 * @return array<string, string>  Keys are provider IDs, values are API keys.
	 */
	public static function getAiExperimentsCredentials(): array {
		$raw = get_option( self::AI_EXPERIMENTS_CREDENTIALS_OPTION, [] );
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Return the API key for a specific provider from the AI Experiments store.
	 *
	 * @param string $provider_id The provider ID (e.g. 'openai', 'anthropic', 'google').
	 * @return string Empty string when not configured.
	 */
	public static function getAiExperimentsApiKey( string $provider_id ): string {
		$credentials = self::getAiExperimentsCredentials();
		$key         = $credentials[ $provider_id ] ?? '';
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Persist an API key for a specific provider in the AI Experiments store.
	 *
	 * Pass an empty string to remove the entry for that provider.
	 *
	 * @param string $provider_id The provider ID.
	 * @param string $api_key     The API key value.
	 * @return bool True on success.
	 */
	public static function setAiExperimentsApiKey( string $provider_id, string $api_key ): bool {
		$credentials = self::getAiExperimentsCredentials();

		if ( '' === $api_key ) {
			unset( $credentials[ $provider_id ] );
		} else {
			$credentials[ $provider_id ] = $api_key;
		}

		return (bool) update_option( self::AI_EXPERIMENTS_CREDENTIALS_OPTION, $credentials );
	}

	// ── Claude Max OAuth token ────────────────────────────────────────────────

	/**
	 * Return the stored Claude Max OAuth access token.
	 *
	 * @return string Empty string when not configured.
	 */
	public static function getClaudeMaxToken(): string {
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
	public static function setClaudeMaxToken( string $token ): bool {
		if ( '' === $token ) {
			return (bool) delete_option( self::CLAUDE_MAX_TOKEN_OPTION );
		}
		return (bool) update_option( self::CLAUDE_MAX_TOKEN_OPTION, $token );
	}

	// ── Validation helpers ────────────────────────────────────────────────────

	/**
	 * Return true when the OpenAI-compatible connector is fully configured
	 * (endpoint URL is non-empty).
	 *
	 * @return bool
	 */
	public static function isOpenAiCompatConfigured(): bool {
		return '' !== self::getOpenAiCompatEndpointUrl();
	}

	/**
	 * Return true when a Claude Max token is stored.
	 *
	 * @return bool
	 */
	public static function hasClaudeMaxToken(): bool {
		return '' !== self::getClaudeMaxToken();
	}

	/**
	 * Return true when the given API key is a real key (not empty and not the
	 * sentinel placeholder).
	 *
	 * @param string $api_key The key to test.
	 * @return bool
	 */
	public static function isValidApiKey( string $api_key ): bool {
		return '' !== $api_key && self::NO_KEY_SENTINEL !== $api_key;
	}
}
