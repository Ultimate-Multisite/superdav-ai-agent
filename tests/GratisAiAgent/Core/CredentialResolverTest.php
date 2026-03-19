<?php

declare(strict_types=1);
/**
 * Test case for CredentialResolver class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\CredentialResolver;
use WP_UnitTestCase;

/**
 * Test CredentialResolver functionality.
 */
class CredentialResolverTest extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();
		delete_option( CredentialResolver::OPENAI_COMPAT_ENDPOINT_OPTION );
		delete_option( CredentialResolver::OPENAI_COMPAT_API_KEY_OPTION );
		delete_option( CredentialResolver::OPENAI_COMPAT_TIMEOUT_OPTION );
		delete_option( CredentialResolver::AI_EXPERIMENTS_CREDENTIALS_OPTION );
		delete_option( CredentialResolver::CLAUDE_MAX_TOKEN_OPTION );
	}

	// ── OpenAI-compatible endpoint ────────────────────────────────────────────

	/**
	 * Test getOpenAiCompatEndpointUrl returns empty string when not configured.
	 */
	public function test_get_openai_compat_endpoint_url_returns_empty_when_not_set(): void {
		$this->assertSame( '', CredentialResolver::getOpenAiCompatEndpointUrl() );
	}

	/**
	 * Test getOpenAiCompatEndpointUrl strips trailing slash.
	 */
	public function test_get_openai_compat_endpoint_url_strips_trailing_slash(): void {
		update_option( CredentialResolver::OPENAI_COMPAT_ENDPOINT_OPTION, 'https://api.example.com/v1/' );

		$this->assertSame( 'https://api.example.com/v1', CredentialResolver::getOpenAiCompatEndpointUrl() );
	}

	/**
	 * Test getOpenAiCompatEndpointUrl returns stored value without trailing slash.
	 */
	public function test_get_openai_compat_endpoint_url_returns_stored_value(): void {
		update_option( CredentialResolver::OPENAI_COMPAT_ENDPOINT_OPTION, 'https://api.example.com/v1' );

		$this->assertSame( 'https://api.example.com/v1', CredentialResolver::getOpenAiCompatEndpointUrl() );
	}

	// ── OpenAI-compatible API key ─────────────────────────────────────────────

	/**
	 * Test getOpenAiCompatApiKey returns sentinel when not configured.
	 */
	public function test_get_openai_compat_api_key_returns_sentinel_when_not_set(): void {
		$this->assertSame( CredentialResolver::NO_KEY_SENTINEL, CredentialResolver::getOpenAiCompatApiKey() );
	}

	/**
	 * Test getOpenAiCompatApiKey returns empty string when not configured and sentinel disabled.
	 */
	public function test_get_openai_compat_api_key_returns_empty_when_sentinel_disabled(): void {
		$this->assertSame( '', CredentialResolver::getOpenAiCompatApiKey( false ) );
	}

	/**
	 * Test getOpenAiCompatApiKey returns stored key.
	 */
	public function test_get_openai_compat_api_key_returns_stored_key(): void {
		update_option( CredentialResolver::OPENAI_COMPAT_API_KEY_OPTION, 'sk-test-key-123' );

		$this->assertSame( 'sk-test-key-123', CredentialResolver::getOpenAiCompatApiKey() );
	}

	// ── OpenAI-compatible timeout ─────────────────────────────────────────────

	/**
	 * Test getOpenAiCompatTimeout returns default 600 when not configured.
	 */
	public function test_get_openai_compat_timeout_returns_default(): void {
		$this->assertSame( 600, CredentialResolver::getOpenAiCompatTimeout() );
	}

	/**
	 * Test getOpenAiCompatTimeout returns stored value.
	 */
	public function test_get_openai_compat_timeout_returns_stored_value(): void {
		update_option( CredentialResolver::OPENAI_COMPAT_TIMEOUT_OPTION, 120 );

		$this->assertSame( 120, CredentialResolver::getOpenAiCompatTimeout() );
	}

	// ── isOpenAiCompatConfigured ──────────────────────────────────────────────

	/**
	 * Test isOpenAiCompatConfigured returns false when not configured.
	 */
	public function test_is_openai_compat_configured_returns_false_when_not_set(): void {
		$this->assertFalse( CredentialResolver::isOpenAiCompatConfigured() );
	}

	/**
	 * Test isOpenAiCompatConfigured returns true when endpoint is set.
	 */
	public function test_is_openai_compat_configured_returns_true_when_endpoint_set(): void {
		update_option( CredentialResolver::OPENAI_COMPAT_ENDPOINT_OPTION, 'https://api.example.com/v1' );

		$this->assertTrue( CredentialResolver::isOpenAiCompatConfigured() );
	}

	// ── AI Experiments credentials ────────────────────────────────────────────

	/**
	 * Test getAiExperimentsCredentials returns empty array when not configured.
	 */
	public function test_get_ai_experiments_credentials_returns_empty_array_when_not_set(): void {
		$this->assertSame( [], CredentialResolver::getAiExperimentsCredentials() );
	}

	/**
	 * Test getAiExperimentsCredentials returns stored array.
	 */
	public function test_get_ai_experiments_credentials_returns_stored_array(): void {
		$credentials = [ 'openai' => 'sk-openai-key', 'anthropic' => 'sk-ant-key' ];
		update_option( CredentialResolver::AI_EXPERIMENTS_CREDENTIALS_OPTION, $credentials );

		$this->assertSame( $credentials, CredentialResolver::getAiExperimentsCredentials() );
	}

	/**
	 * Test getAiExperimentsApiKey returns empty string for unknown provider.
	 */
	public function test_get_ai_experiments_api_key_returns_empty_for_unknown_provider(): void {
		$this->assertSame( '', CredentialResolver::getAiExperimentsApiKey( 'unknown' ) );
	}

	/**
	 * Test getAiExperimentsApiKey returns key for known provider.
	 */
	public function test_get_ai_experiments_api_key_returns_key_for_known_provider(): void {
		update_option( CredentialResolver::AI_EXPERIMENTS_CREDENTIALS_OPTION, [ 'openai' => 'sk-openai-key' ] );

		$this->assertSame( 'sk-openai-key', CredentialResolver::getAiExperimentsApiKey( 'openai' ) );
	}

	/**
	 * Test setAiExperimentsApiKey stores key for provider.
	 */
	public function test_set_ai_experiments_api_key_stores_key(): void {
		$result = CredentialResolver::setAiExperimentsApiKey( 'openai', 'sk-new-key' );

		$this->assertTrue( $result );
		$this->assertSame( 'sk-new-key', CredentialResolver::getAiExperimentsApiKey( 'openai' ) );
	}

	/**
	 * Test setAiExperimentsApiKey removes provider when empty string passed.
	 */
	public function test_set_ai_experiments_api_key_removes_provider_on_empty(): void {
		update_option( CredentialResolver::AI_EXPERIMENTS_CREDENTIALS_OPTION, [ 'openai' => 'sk-openai-key' ] );

		CredentialResolver::setAiExperimentsApiKey( 'openai', '' );

		$this->assertSame( '', CredentialResolver::getAiExperimentsApiKey( 'openai' ) );
		$this->assertArrayNotHasKey( 'openai', CredentialResolver::getAiExperimentsCredentials() );
	}

	// ── Claude Max token ──────────────────────────────────────────────────────

	/**
	 * Test getClaudeMaxToken returns empty string when not configured.
	 */
	public function test_get_claude_max_token_returns_empty_when_not_set(): void {
		$this->assertSame( '', CredentialResolver::getClaudeMaxToken() );
	}

	/**
	 * Test setClaudeMaxToken stores token.
	 */
	public function test_set_claude_max_token_stores_token(): void {
		$result = CredentialResolver::setClaudeMaxToken( 'sk-ant-oat01-test' );

		$this->assertTrue( $result );
		$this->assertSame( 'sk-ant-oat01-test', CredentialResolver::getClaudeMaxToken() );
	}

	/**
	 * Test setClaudeMaxToken clears token when empty string passed.
	 */
	public function test_set_claude_max_token_clears_on_empty(): void {
		CredentialResolver::setClaudeMaxToken( 'sk-ant-oat01-test' );
		CredentialResolver::setClaudeMaxToken( '' );

		$this->assertSame( '', CredentialResolver::getClaudeMaxToken() );
	}

	/**
	 * Test hasClaudeMaxToken returns false when not configured.
	 */
	public function test_has_claude_max_token_returns_false_when_not_set(): void {
		$this->assertFalse( CredentialResolver::hasClaudeMaxToken() );
	}

	/**
	 * Test hasClaudeMaxToken returns true when token is stored.
	 */
	public function test_has_claude_max_token_returns_true_when_set(): void {
		CredentialResolver::setClaudeMaxToken( 'sk-ant-oat01-test' );

		$this->assertTrue( CredentialResolver::hasClaudeMaxToken() );
	}

	// ── isValidApiKey ─────────────────────────────────────────────────────────

	/**
	 * Test isValidApiKey returns false for empty string.
	 */
	public function test_is_valid_api_key_returns_false_for_empty_string(): void {
		$this->assertFalse( CredentialResolver::isValidApiKey( '' ) );
	}

	/**
	 * Test isValidApiKey returns false for sentinel value.
	 */
	public function test_is_valid_api_key_returns_false_for_sentinel(): void {
		$this->assertFalse( CredentialResolver::isValidApiKey( CredentialResolver::NO_KEY_SENTINEL ) );
	}

	/**
	 * Test isValidApiKey returns true for real key.
	 */
	public function test_is_valid_api_key_returns_true_for_real_key(): void {
		$this->assertTrue( CredentialResolver::isValidApiKey( 'sk-real-api-key-123' ) );
	}
}
