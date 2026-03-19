<?php
/**
 * Test case for MarketingAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\MarketingAbilities;
use WP_UnitTestCase;

/**
 * Test MarketingAbilities handler methods.
 */
class MarketingAbilitiesTest extends WP_UnitTestCase {

	// ─── fetch-url ────────────────────────────────────────────────

	/**
	 * Test handle_fetch_url with empty URL returns WP_Error.
	 */
	public function test_handle_fetch_url_empty_url() {
		$result = MarketingAbilities::handle_fetch_url( [
			'url' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_fetch_url with missing URL returns WP_Error.
	 */
	public function test_handle_fetch_url_missing_url() {
		$result = MarketingAbilities::handle_fetch_url( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_fetch_url with valid URL returns array or WP_Error.
	 *
	 * In test environment, HTTP requests may fail, but the handler
	 * should return an array or WP_Error (not throw an exception).
	 */
	public function test_handle_fetch_url_valid_url_returns_array() {
		$result = MarketingAbilities::handle_fetch_url( [
			'url' => home_url( '/' ),
		] );

		// Should return either fetch data (array) or a WP_Error on HTTP failure.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			$this->assertTrue(
				isset( $result['url'] ) || isset( $result['status_code'] ),
				'Array result should have url or status_code key.'
			);
		}
	}

	// ─── analyze-headers ──────────────────────────────────────────

	/**
	 * Test handle_analyze_headers with empty URL returns WP_Error.
	 */
	public function test_handle_analyze_headers_empty_url() {
		$result = MarketingAbilities::handle_analyze_headers( [
			'url' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_analyze_headers with missing URL returns WP_Error.
	 */
	public function test_handle_analyze_headers_missing_url() {
		$result = MarketingAbilities::handle_analyze_headers( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_analyze_headers with valid URL returns array or WP_Error.
	 */
	public function test_handle_analyze_headers_valid_url_returns_array() {
		$result = MarketingAbilities::handle_analyze_headers( [
			'url' => home_url( '/' ),
		] );

		// Should return either header analysis (array) or a WP_Error on HTTP failure.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			$this->assertTrue(
				isset( $result['url'] ) || isset( $result['headers'] ),
				'Array result should have url or headers key.'
			);
		}
	}
}
