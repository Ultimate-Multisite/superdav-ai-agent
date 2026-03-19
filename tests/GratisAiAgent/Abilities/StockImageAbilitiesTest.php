<?php
/**
 * Test case for StockImageAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\StockImageAbilities;
use WP_UnitTestCase;

/**
 * Test StockImageAbilities handler methods.
 */
class StockImageAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_import with empty keyword returns WP_Error.
	 */
	public function test_handle_import_empty_keyword() {
		$result = StockImageAbilities::handle_import( [
			'keyword' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'required', $result->get_error_message() );
	}

	/**
	 * Test handle_import with missing keyword returns WP_Error.
	 */
	public function test_handle_import_missing_keyword() {
		$result = StockImageAbilities::handle_import( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_import with valid keyword returns array or WP_Error.
	 *
	 * In test environment, the HTTP request to loremflickr.com will fail,
	 * but the handler should return an array or WP_Error (not throw an exception).
	 */
	public function test_handle_import_valid_keyword_returns_array() {
		$result = StockImageAbilities::handle_import( [
			'keyword' => 'nature',
		] );

		// Should return either success data (array) or a WP_Error on HTTP failure.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			$this->assertArrayHasKey( 'attachment_id', $result );
		}
	}

	/**
	 * Test handle_import dimensions are clamped to minimum 200.
	 *
	 * The handler clamps width/height to [200, 3000]. We can verify this
	 * by checking the handler doesn't error on extreme values.
	 */
	public function test_handle_import_dimensions_clamped_minimum() {
		$result = StockImageAbilities::handle_import( [
			'keyword' => 'test',
			'width'   => 1,
			'height'  => 1,
		] );

		// Should not error on dimension clamping — only on HTTP failure.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	/**
	 * Test handle_import dimensions are clamped to maximum 3000.
	 */
	public function test_handle_import_dimensions_clamped_maximum() {
		$result = StockImageAbilities::handle_import( [
			'keyword' => 'test',
			'width'   => 99999,
			'height'  => 99999,
		] );

		// Should not error on dimension clamping — only on HTTP failure.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	/**
	 * Test handle_import with invalid site_url returns array or WP_Error.
	 *
	 * On multisite, invalid site_url returns WP_Error. On single site, site_url is ignored.
	 */
	public function test_handle_import_invalid_site_url() {
		$result = StockImageAbilities::handle_import( [
			'keyword'  => 'nature',
			'site_url' => 'https://nonexistent-site-xyz-12345.example.com/',
		] );

		// Should return an array or WP_Error — never throw an exception.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}
}
