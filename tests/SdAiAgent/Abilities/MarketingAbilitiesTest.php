<?php
/**
 * Test case for MarketingAbilities class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\MarketingAbilities;
use WP_UnitTestCase;

/**
 * Test MarketingAbilities handler methods.
 */
class MarketingAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 *
	 * Remove any pre_http_request filters added by individual tests so they
	 * do not bleed into the next test in the suite.
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

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
	 * Test handle_fetch_url with valid URL returns expected array shape.
	 *
	 * Uses a pre_http_request filter to intercept the outbound wp_remote_get()
	 * call so the test is deterministic in environments without outbound HTTP
	 * (e.g. wp-env Docker containers).
	 */
	public function test_handle_fetch_url_valid_url_returns_array() {
		$mock_html = '<!DOCTYPE html><html><head>'
			. '<title>Mock Page Title</title>'
			. '<meta name="description" content="Mock meta description.">'
			. '<meta name="generator" content="WordPress 7.0">'
			. '</head><body><h1>Mock Heading</h1></body></html>';

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( $mock_html ) {
				return [
					'headers'  => [
						'content-type' => 'text/html; charset=UTF-8',
						'server'       => 'MockServer/1.0',
					],
					'body'     => $mock_html,
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			3
		);

		$result = MarketingAbilities::handle_fetch_url( [
			'url' => home_url( '/' ),
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'status_code', $result );
		$this->assertSame( 200, $result['status_code'] );
		$this->assertSame( 'Mock Page Title', $result['title'] );
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
	 * Test handle_analyze_headers with valid URL returns expected array shape.
	 *
	 * Uses a pre_http_request filter to intercept the outbound wp_remote_head()
	 * call so the test is deterministic in environments without outbound HTTP
	 * (e.g. wp-env Docker containers).
	 */
	public function test_handle_analyze_headers_valid_url_returns_array() {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				return [
					'headers'  => [
						'content-type'              => 'text/html; charset=UTF-8',
						'cache-control'             => 'max-age=3600, public',
						'strict-transport-security' => 'max-age=31536000; includeSubDomains',
						'x-content-type-options'    => 'nosniff',
					],
					'body'     => '',
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			3
		);

		$result = MarketingAbilities::handle_analyze_headers( [
			'url' => home_url( '/' ),
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'status_code', $result );
		$this->assertSame( 200, $result['status_code'] );
		$this->assertArrayHasKey( 'security', $result );
		$this->assertArrayHasKey( 'performance', $result );
	}
}
