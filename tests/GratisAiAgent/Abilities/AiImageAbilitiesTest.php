<?php
/**
 * Test case for AiImageAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\AiImageAbilities;
use WP_UnitTestCase;

/**
 * Test AiImageAbilities handler methods.
 */
class AiImageAbilitiesTest extends WP_UnitTestCase {

	// ─── handle_generate ──────────────────────────────────────────

	/**
	 * Test handle_generate with empty prompt returns WP_Error.
	 */
	public function test_handle_generate_empty_prompt() {
		$result = AiImageAbilities::handle_generate( [ 'prompt' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_param', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with missing prompt returns WP_Error.
	 */
	public function test_handle_generate_missing_prompt() {
		$result = AiImageAbilities::handle_generate( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_param', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with valid prompt but no API key returns WP_Error.
	 *
	 * In the test environment, no OpenAI API key is configured, so the handler
	 * should return a WP_Error with 'no_openai_key' code.
	 */
	public function test_handle_generate_no_api_key() {
		// Ensure no OpenAI key is set.
		delete_option( 'gratis_ai_agent_settings' );

		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A beautiful sunset over the ocean.',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'no_openai_key', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with valid prompt returns array or WP_Error.
	 *
	 * In the test environment, the API call will fail (no key), but the handler
	 * must not throw an exception.
	 */
	public function test_handle_generate_returns_array_or_wp_error() {
		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A mountain landscape at dawn.',
		] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	/**
	 * Test handle_generate with invalid size falls back gracefully.
	 *
	 * The handler resolves invalid enum values to the site default or first allowed value.
	 * It should not error on the size parameter itself.
	 */
	public function test_handle_generate_invalid_size_falls_back() {
		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A forest path.',
			'size'   => 'invalid_size',
		] );

		// Should fail on API key, not on size validation.
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotSame( 'invalid_size', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with invalid quality falls back gracefully.
	 */
	public function test_handle_generate_invalid_quality_falls_back() {
		$result = AiImageAbilities::handle_generate( [
			'prompt'  => 'A city skyline.',
			'quality' => 'ultra',
		] );

		// Should fail on API key, not on quality validation.
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotSame( 'ultra', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with invalid style falls back gracefully.
	 */
	public function test_handle_generate_invalid_style_falls_back() {
		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A beach at sunset.',
			'style'  => 'cartoon',
		] );

		// Should fail on API key, not on style validation.
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotSame( 'cartoon', $result->get_error_code() );
	}

}
