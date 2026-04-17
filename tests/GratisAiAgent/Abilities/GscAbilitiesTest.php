<?php
/**
 * Test case for GscAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\GscAbilities;
use GratisAiAgent\Core\Settings;
use WP_UnitTestCase;

/**
 * Test GscAbilities handler methods.
 *
 * All handlers require GSC credentials stored via Settings::instance()->set_gsc_credentials().
 * In the test environment no credentials are configured, so every handler must
 * return a WP_Error describing the missing configuration.
 */
class GscAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Clear GSC credentials before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Settings::instance()->set_gsc_credentials( [] );
	}

	/**
	 * Clear GSC credentials after each test.
	 */
	public function tearDown(): void {
		Settings::instance()->set_gsc_credentials( [] );
		parent::tearDown();
	}

	// ─── handle_top_queries ───────────────────────────────────────

	/**
	 * Test handle_top_queries returns WP_Error when no credentials configured.
	 */
	public function test_handle_top_queries_no_credentials_returns_wp_error() {
		$result = GscAbilities::handle_top_queries( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_configured', $result->get_error_code() );
	}

	/**
	 * Test handle_top_queries with access_token credential type but empty token.
	 */
	public function test_handle_top_queries_empty_access_token_returns_wp_error() {
		Settings::instance()->set_gsc_credentials( [
			'type'         => 'access_token',
			'access_token' => '',
		] );

		$result = GscAbilities::handle_top_queries( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_missing_token', $result->get_error_code() );
	}

	/**
	 * Test handle_top_queries with unknown credential type returns WP_Error.
	 */
	public function test_handle_top_queries_unknown_credential_type_returns_wp_error() {
		Settings::instance()->set_gsc_credentials( [
			'type' => 'unknown_type',
		] );

		$result = GscAbilities::handle_top_queries( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_unknown_type', $result->get_error_code() );
	}

	/**
	 * Test handle_top_queries with service_account but missing private_key.
	 */
	public function test_handle_top_queries_service_account_missing_key_returns_wp_error() {
		Settings::instance()->set_gsc_credentials( [
			'type'         => 'service_account',
			'client_email' => 'test@project.iam.gserviceaccount.com',
			'private_key'  => '',
		] );

		$result = GscAbilities::handle_top_queries( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_invalid_sa', $result->get_error_code() );
	}

	// ─── handle_page_performance ──────────────────────────────────

	/**
	 * Test handle_page_performance returns WP_Error when no credentials configured.
	 */
	public function test_handle_page_performance_no_credentials_returns_wp_error() {
		$result = GscAbilities::handle_page_performance( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_configured', $result->get_error_code() );
	}

	/**
	 * Test handle_page_performance with unknown credential type returns WP_Error.
	 */
	public function test_handle_page_performance_unknown_type_returns_wp_error() {
		Settings::instance()->set_gsc_credentials( [
			'type' => 'oauth2',
		] );

		$result = GscAbilities::handle_page_performance( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_unknown_type', $result->get_error_code() );
	}

	// ─── handle_query_details ─────────────────────────────────────

	/**
	 * Test handle_query_details returns WP_Error for missing query parameter.
	 */
	public function test_handle_query_details_missing_query_returns_wp_error() {
		$result = GscAbilities::handle_query_details( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_param', $result->get_error_code() );
	}

	/**
	 * Test handle_query_details returns WP_Error for empty query string.
	 */
	public function test_handle_query_details_empty_query_returns_wp_error() {
		$result = GscAbilities::handle_query_details( [ 'query' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_param', $result->get_error_code() );
	}

	/**
	 * Test handle_query_details with valid query but no credentials returns WP_Error.
	 */
	public function test_handle_query_details_no_credentials_returns_wp_error() {
		$result = GscAbilities::handle_query_details( [ 'query' => 'wordpress seo' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_configured', $result->get_error_code() );
	}

	// ─── handle_site_summary ──────────────────────────────────────

	/**
	 * Test handle_site_summary returns WP_Error when no credentials configured.
	 */
	public function test_handle_site_summary_no_credentials_returns_wp_error() {
		$result = GscAbilities::handle_site_summary( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_configured', $result->get_error_code() );
	}

	/**
	 * Test handle_site_summary with compare_previous=false and no credentials.
	 */
	public function test_handle_site_summary_no_compare_no_credentials_returns_wp_error() {
		$result = GscAbilities::handle_site_summary( [ 'compare_previous' => false ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_configured', $result->get_error_code() );
	}

	/**
	 * Test handle_site_summary with compare_previous=true and no credentials.
	 */
	public function test_handle_site_summary_with_compare_no_credentials_returns_wp_error() {
		$result = GscAbilities::handle_site_summary( [ 'compare_previous' => true ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_configured', $result->get_error_code() );
	}
}
