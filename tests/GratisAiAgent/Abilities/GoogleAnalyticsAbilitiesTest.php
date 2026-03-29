<?php
/**
 * Test case for GoogleAnalyticsAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\GoogleAnalyticsAbilities;
use WP_UnitTestCase;

/**
 * Test GoogleAnalyticsAbilities handler methods.
 *
 * All three abilities require valid GA4 credentials (property ID + service
 * account JSON). In the test environment no credentials are configured, so
 * every handler must return a WP_Error describing the missing configuration.
 */
class GoogleAnalyticsAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Ensure no GA credentials are stored before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		GoogleAnalyticsAbilities::clear_credentials();
	}

	/**
	 * Restore any credentials that were present before the test suite ran.
	 */
	public function tearDown(): void {
		GoogleAnalyticsAbilities::clear_credentials();
		parent::tearDown();
	}

	// ─── Credential helpers ───────────────────────────────────────

	/**
	 * Test get_credentials returns empty strings when nothing is stored.
	 */
	public function test_get_credentials_returns_empty_when_not_configured() {
		$creds = GoogleAnalyticsAbilities::get_credentials();

		$this->assertIsArray( $creds );
		$this->assertArrayHasKey( 'property_id', $creds );
		$this->assertArrayHasKey( 'service_account_json', $creds );
		$this->assertSame( '', $creds['property_id'] );
		$this->assertSame( '', $creds['service_account_json'] );
	}

	/**
	 * Test set_credentials persists values retrievable by get_credentials.
	 */
	public function test_set_credentials_persists_values() {
		GoogleAnalyticsAbilities::set_credentials( '123456789', '{"type":"service_account"}' );

		$creds = GoogleAnalyticsAbilities::get_credentials();

		$this->assertSame( '123456789', $creds['property_id'] );
		$this->assertSame( '{"type":"service_account"}', $creds['service_account_json'] );
	}

	/**
	 * Test clear_credentials removes stored values.
	 */
	public function test_clear_credentials_removes_stored_values() {
		GoogleAnalyticsAbilities::set_credentials( '123456789', '{"type":"service_account"}' );
		GoogleAnalyticsAbilities::clear_credentials();

		$creds = GoogleAnalyticsAbilities::get_credentials();

		$this->assertSame( '', $creds['property_id'] );
		$this->assertSame( '', $creds['service_account_json'] );
	}

	// ─── handle_traffic_summary ───────────────────────────────────

	/**
	 * Test handle_traffic_summary returns WP_Error when no credentials configured.
	 */
	public function test_handle_traffic_summary_no_credentials_returns_wp_error() {
		$result = GoogleAnalyticsAbilities::handle_traffic_summary( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_traffic_summary WP_Error code indicates missing property ID.
	 */
	public function test_handle_traffic_summary_error_code_is_no_property_id() {
		$result = GoogleAnalyticsAbilities::handle_traffic_summary( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_no_property_id', $result->get_error_code() );
	}

	/**
	 * Test handle_traffic_summary with property ID but no JSON returns WP_Error.
	 */
	public function test_handle_traffic_summary_missing_json_returns_wp_error() {
		GoogleAnalyticsAbilities::set_credentials( '123456789', '' );

		$result = GoogleAnalyticsAbilities::handle_traffic_summary( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_no_credentials', $result->get_error_code() );
	}

	/**
	 * Test handle_traffic_summary with invalid JSON returns WP_Error.
	 */
	public function test_handle_traffic_summary_invalid_json_returns_wp_error() {
		GoogleAnalyticsAbilities::set_credentials( '123456789', 'not-valid-json' );

		$result = GoogleAnalyticsAbilities::handle_traffic_summary( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_invalid_credentials', $result->get_error_code() );
	}

	// ─── handle_top_pages ─────────────────────────────────────────

	/**
	 * Test handle_top_pages returns WP_Error when no credentials configured.
	 */
	public function test_handle_top_pages_no_credentials_returns_wp_error() {
		$result = GoogleAnalyticsAbilities::handle_top_pages( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_top_pages WP_Error code indicates missing property ID.
	 */
	public function test_handle_top_pages_error_code_is_no_property_id() {
		$result = GoogleAnalyticsAbilities::handle_top_pages( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_no_property_id', $result->get_error_code() );
	}

	/**
	 * Test handle_top_pages with invalid JSON returns WP_Error.
	 */
	public function test_handle_top_pages_invalid_json_returns_wp_error() {
		GoogleAnalyticsAbilities::set_credentials( '123456789', 'not-valid-json' );

		$result = GoogleAnalyticsAbilities::handle_top_pages( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_invalid_credentials', $result->get_error_code() );
	}

	// ─── handle_realtime ──────────────────────────────────────────

	/**
	 * Test handle_realtime returns WP_Error when no credentials configured.
	 */
	public function test_handle_realtime_no_credentials_returns_wp_error() {
		$result = GoogleAnalyticsAbilities::handle_realtime( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_realtime WP_Error code indicates missing property ID.
	 */
	public function test_handle_realtime_error_code_is_no_property_id() {
		$result = GoogleAnalyticsAbilities::handle_realtime( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_no_property_id', $result->get_error_code() );
	}

	/**
	 * Test handle_realtime with invalid JSON returns WP_Error.
	 */
	public function test_handle_realtime_invalid_json_returns_wp_error() {
		GoogleAnalyticsAbilities::set_credentials( '123456789', 'not-valid-json' );

		$result = GoogleAnalyticsAbilities::handle_realtime( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ga_invalid_credentials', $result->get_error_code() );
	}
}
