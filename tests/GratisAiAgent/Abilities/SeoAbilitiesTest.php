<?php
/**
 * Test case for SeoAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\SeoAbilities;
use WP_UnitTestCase;

/**
 * Test SeoAbilities handler methods.
 */
class SeoAbilitiesTest extends WP_UnitTestCase {

	// ─── seo-audit-url ────────────────────────────────────────────

	/**
	 * Test handle_audit_url with empty URL returns WP_Error.
	 */
	public function test_handle_audit_url_empty_url() {
		$result = SeoAbilities::handle_audit_url( [
			'url' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_audit_url with missing URL returns WP_Error.
	 */
	public function test_handle_audit_url_missing_url() {
		$result = SeoAbilities::handle_audit_url( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_audit_url with valid URL returns array or WP_Error.
	 *
	 * In test environment, the HTTP request may fail, but the handler
	 * should return an array or WP_Error (not throw an exception).
	 */
	public function test_handle_audit_url_valid_url_returns_array() {
		$result = SeoAbilities::handle_audit_url( [
			'url' => home_url( '/' ),
		] );

		// Should return either audit data (array) or a WP_Error on HTTP failure.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			$this->assertArrayHasKey( 'url', $result );
		}
	}

	// ─── seo-analyze-content ──────────────────────────────────────

	/**
	 * Test handle_analyze_content with missing post_id returns WP_Error.
	 */
	public function test_handle_analyze_content_missing_post_id() {
		$result = SeoAbilities::handle_analyze_content( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'required', $result->get_error_message() );
	}

	/**
	 * Test handle_analyze_content with zero post_id returns WP_Error.
	 */
	public function test_handle_analyze_content_zero_post_id() {
		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => 0,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_analyze_content with non-existent post returns WP_Error.
	 */
	public function test_handle_analyze_content_post_not_found() {
		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => 999999,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( '999999', $result->get_error_message() );
	}

	/**
	 * Test handle_analyze_content with valid post returns analysis.
	 */
	public function test_handle_analyze_content_valid_post() {
		$post_id = $this->factory->post->create( [
			'post_status'  => 'publish',
			'post_title'   => 'SEO Test Post Title for Analysis',
			'post_content' => 'This is the content of the SEO test post. It has enough words to analyze properly for keyword density and other SEO metrics. WordPress is a great platform.',
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => $post_id,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertSame( $post_id, $result['post_id'] );
	}

	/**
	 * Test handle_analyze_content result has expected SEO fields.
	 */
	public function test_handle_analyze_content_result_structure() {
		$post_id = $this->factory->post->create( [
			'post_status'  => 'publish',
			'post_title'   => 'SEO Structure Test Post',
			'post_content' => 'Content for SEO structure test with multiple words and sentences.',
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => $post_id,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		// Should have word count or content analysis.
		$this->assertTrue(
			isset( $result['word_count'] ) || isset( $result['title'] ) || isset( $result['recommendations'] ),
			'Result should have word_count, title, or recommendations.'
		);
	}

	/**
	 * Test handle_analyze_content with focus_keyword.
	 */
	public function test_handle_analyze_content_with_focus_keyword() {
		$post_id = $this->factory->post->create( [
			'post_status'  => 'publish',
			'post_title'   => 'WordPress SEO Guide',
			'post_content' => 'WordPress is a popular CMS. WordPress powers many websites. WordPress SEO is important.',
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id'       => $post_id,
			'focus_keyword' => 'WordPress',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
	}
}
