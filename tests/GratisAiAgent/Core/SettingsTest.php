<?php
/**
 * Test case for Settings class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\Settings;
use WP_UnitTestCase;

/**
 * Test Settings functionality.
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::CLAUDE_MAX_TOKEN_OPTION );
	}

	/**
	 * Test get_defaults returns expected keys.
	 */
	public function test_get_defaults_returns_expected_keys() {
		$defaults = Settings::get_defaults();

		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'default_provider', $defaults );
		$this->assertArrayHasKey( 'default_model', $defaults );
		$this->assertArrayHasKey( 'max_iterations', $defaults );
		$this->assertArrayHasKey( 'greeting_message', $defaults );
		$this->assertArrayHasKey( 'system_prompt', $defaults );
		$this->assertArrayHasKey( 'auto_memory', $defaults );
		$this->assertArrayHasKey( 'temperature', $defaults );
		$this->assertArrayHasKey( 'max_output_tokens', $defaults );
		$this->assertArrayHasKey( 'max_history_turns', $defaults );
	}

	/**
	 * Test get_defaults returns expected default values.
	 */
	public function test_get_defaults_returns_expected_values() {
		$defaults = Settings::get_defaults();

		$this->assertSame( 25, $defaults['max_iterations'] );
		$this->assertSame( true, $defaults['auto_memory'] );
		$this->assertSame( 0.7, $defaults['temperature'] );
		$this->assertSame( 4096, $defaults['max_output_tokens'] );
		$this->assertSame( 20, $defaults['max_history_turns'] );
	}

	/**
	 * Test get returns all settings merged with defaults.
	 */
	public function test_get_returns_merged_settings() {
		$settings = Settings::get();

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'max_iterations', $settings );
		$this->assertArrayHasKey( 'temperature', $settings );
	}

	/**
	 * Test get returns single setting when key provided.
	 */
	public function test_get_returns_single_setting() {
		$max_iterations = Settings::get( 'max_iterations' );

		$this->assertSame( 25, $max_iterations );
	}

	/**
	 * Test get returns null for unknown key.
	 */
	public function test_get_returns_null_for_unknown_key() {
		$result = Settings::get( 'nonexistent_setting' );

		$this->assertNull( $result );
	}

	/**
	 * Test update saves settings.
	 */
	public function test_update_saves_settings() {
		$result = Settings::update( [ 'max_iterations' => 50 ] );

		$this->assertTrue( $result );
		$this->assertSame( 50, Settings::get( 'max_iterations' ) );
	}

	/**
	 * Test update only allows known keys.
	 */
	public function test_update_only_allows_known_keys() {
		Settings::update( [ 'unknown_key' => 'test_value' ] );

		$settings = Settings::get();
		$this->assertArrayNotHasKey( 'unknown_key', $settings );
	}

	/**
	 * Test update merges with existing settings.
	 */
	public function test_update_merges_with_existing_settings() {
		Settings::update( [ 'max_iterations' => 30 ] );
		Settings::update( [ 'temperature' => 0.5 ] );

		$this->assertSame( 30, Settings::get( 'max_iterations' ) );
		$this->assertSame( 0.5, Settings::get( 'temperature' ) );
	}

	/**
	 * Test get_claude_max_token returns empty string when not set.
	 */
	public function test_get_claude_max_token_returns_empty_when_not_set() {
		$token = Settings::get_claude_max_token();

		$this->assertSame( '', $token );
	}

	/**
	 * Test set_claude_max_token stores token.
	 */
	public function test_set_claude_max_token_stores_token() {
		$result = Settings::set_claude_max_token( 'sk-ant-test-token' );

		$this->assertTrue( $result );
		$this->assertSame( 'sk-ant-test-token', Settings::get_claude_max_token() );
	}

	/**
	 * Test set_claude_max_token clears token when empty string.
	 */
	public function test_set_claude_max_token_clears_on_empty() {
		Settings::set_claude_max_token( 'sk-ant-test-token' );
		Settings::set_claude_max_token( '' );

		$this->assertSame( '', Settings::get_claude_max_token() );
	}

	/**
	 * Test PAGE_SLUG constant.
	 */
	public function test_page_slug_constant() {
		$this->assertSame( 'gratis-ai-agent-settings', Settings::PAGE_SLUG );
	}

	/**
	 * Test OPTION_NAME constant.
	 */
	public function test_option_name_constant() {
		$this->assertSame( 'gratis_ai_agent_settings', Settings::OPTION_NAME );
	}

	/**
	 * Test disabled_abilities default is empty array.
	 */
	public function test_disabled_abilities_default_is_empty_array() {
		$defaults = Settings::get_defaults();

		$this->assertIsArray( $defaults['disabled_abilities'] );
		$this->assertEmpty( $defaults['disabled_abilities'] );
	}

	/**
	 * Test tool_permissions default is empty array.
	 */
	public function test_tool_permissions_default_is_empty_array() {
		$defaults = Settings::get_defaults();

		$this->assertIsArray( $defaults['tool_permissions'] );
		$this->assertEmpty( $defaults['tool_permissions'] );
	}

	/**
	 * Test update can save array values.
	 */
	public function test_update_can_save_array_values() {
		$disabled = [ 'tool1', 'tool2' ];
		Settings::update( [ 'disabled_abilities' => $disabled ] );

		$result = Settings::get( 'disabled_abilities' );
		$this->assertSame( $disabled, $result );
	}
}
