<?php
/**
 * Test case for AiImageAbilities class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\AiImageAbilities;
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
		$this->assertSame( 'missing_prompt', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with missing prompt returns WP_Error.
	 */
	public function test_handle_generate_missing_prompt() {
		$result = AiImageAbilities::handle_generate( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_prompt', $result->get_error_code() );
	}

	/**
	 * Test handle_generate with valid prompt but no provider configured.
	 *
	 * The handler now routes through the WP AI Client SDK. When no image-capable
	 * provider is configured it returns an array with an 'error' key (not a
	 * WP_Error) so the agent loop can surface a human-readable message.
	 */
	public function test_handle_generate_no_api_key() {
		// Ensure no settings are stored.
		delete_option( 'sd_ai_agent_settings' );

		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A beautiful sunset over the ocean.',
		] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result must be an array or WP_Error.'
		);
		if ( is_array( $result ) ) {
			$this->assertArrayHasKey( 'error', $result );
		}
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
	 * Test handle_generate with unknown size does not error on the size param.
	 *
	 * The current implementation ignores unknown size/quality/style values and
	 * either returns an array (provider unavailable) or falls through to the SDK.
	 */
	public function test_handle_generate_invalid_size_falls_back() {
		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A forest path.',
			'size'   => 'invalid_size',
		] );

		// Should not fail specifically on the size parameter.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result must be an array or WP_Error.'
		);
		if ( is_wp_error( $result ) ) {
			$this->assertNotSame( 'invalid_size', $result->get_error_code() );
		}
	}

	/**
	 * Test handle_generate with unknown quality does not error on the quality param.
	 */
	public function test_handle_generate_invalid_quality_falls_back() {
		$result = AiImageAbilities::handle_generate( [
			'prompt'  => 'A city skyline.',
			'quality' => 'ultra',
		] );

		// Should not fail specifically on the quality parameter.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result must be an array or WP_Error.'
		);
		if ( is_wp_error( $result ) ) {
			$this->assertNotSame( 'invalid_quality', $result->get_error_code() );
		}
	}

	/**
	 * Test handle_generate with unknown style does not error on the style param.
	 */
	public function test_handle_generate_invalid_style_falls_back() {
		$result = AiImageAbilities::handle_generate( [
			'prompt' => 'A beach at sunset.',
			'style'  => 'cartoon',
		] );

		// Should not fail specifically on the style parameter.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result must be an array or WP_Error.'
		);
		if ( is_wp_error( $result ) ) {
			$this->assertNotSame( 'invalid_style', $result->get_error_code() );
		}
	}

}
