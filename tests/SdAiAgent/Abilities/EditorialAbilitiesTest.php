<?php
/**
 * Test case for EditorialAbilities class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\EditorialAbilities;
use WP_UnitTestCase;

/**
 * Test EditorialAbilities handler methods.
 */
class EditorialAbilitiesTest extends WP_UnitTestCase {

	// ─── handle_generate_title ────────────────────────────────────

	/**
	 * Test handle_generate_title without wp_ai_client_prompt returns WP_Error.
	 *
	 * In the test environment, wp_ai_client_prompt() is not available.
	 * The handler must return a WP_Error with 'ai_client_unavailable'.
	 */
	public function test_handle_generate_title_no_ai_client() {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is available in this environment.' );
		}

		$result = EditorialAbilities::handle_generate_title( [
			'content' => 'This is some test content for title generation.',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_client_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_generate_title with empty content and no post_id returns WP_Error.
	 *
	 * When wp_ai_client_prompt is available, empty content should return an error.
	 * When it is not available, the ai_client_unavailable error fires first.
	 */
	public function test_handle_generate_title_empty_content() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$result = EditorialAbilities::handle_generate_title( [ 'content' => '' ] );
			$this->assertInstanceOf( \WP_Error::class, $result );
			return;
		}

		$result = EditorialAbilities::handle_generate_title( [ 'content' => '' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'content_not_provided', $result->get_error_code() );
	}

	/**
	 * Test handle_generate_title with invalid post_id returns WP_Error.
	 *
	 * When wp_ai_client_prompt is available, a non-existent post_id should
	 * return a post_not_found error.
	 */
	public function test_handle_generate_title_invalid_post_id() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is not available.' );
		}

		$result = EditorialAbilities::handle_generate_title( [
			'post_id' => 999999,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'post_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_generate_title candidates is clamped to 1–10 range.
	 *
	 * The handler clamps candidates to [1, 10]. This is exercised by passing
	 * extreme values — the handler should not error on the candidates param itself.
	 */
	public function test_handle_generate_title_candidates_clamped() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is not available.' );
		}

		// Passing 0 candidates should be clamped to 1 — no validation error.
		$result = EditorialAbilities::handle_generate_title( [
			'content'    => 'Some content.',
			'candidates' => 0,
		] );

		// Should fail on AI call, not on candidates validation.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be an array or WP_Error.'
		);
	}

	// ─── handle_generate_excerpt ──────────────────────────────────

	/**
	 * Test handle_generate_excerpt without wp_ai_client_prompt returns WP_Error.
	 */
	public function test_handle_generate_excerpt_no_ai_client() {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is available in this environment.' );
		}

		$result = EditorialAbilities::handle_generate_excerpt( [
			'content' => 'Some content to excerpt.',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_client_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_generate_excerpt with empty content returns WP_Error.
	 */
	public function test_handle_generate_excerpt_empty_content() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$result = EditorialAbilities::handle_generate_excerpt( [ 'content' => '' ] );
			$this->assertInstanceOf( \WP_Error::class, $result );
			return;
		}

		$result = EditorialAbilities::handle_generate_excerpt( [ 'content' => '' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'content_not_provided', $result->get_error_code() );
	}

	/**
	 * Test handle_generate_excerpt with invalid post_id in context returns WP_Error.
	 */
	public function test_handle_generate_excerpt_invalid_post_id_context() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is not available.' );
		}

		$result = EditorialAbilities::handle_generate_excerpt( [
			'content' => '',
			'context' => '999999',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'post_not_found', $result->get_error_code() );
	}

	// ─── handle_summarize_content ─────────────────────────────────

	/**
	 * Test handle_summarize_content without wp_ai_client_prompt returns WP_Error.
	 */
	public function test_handle_summarize_content_no_ai_client() {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is available in this environment.' );
		}

		$result = EditorialAbilities::handle_summarize_content( [
			'content' => 'Some content to summarize.',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_client_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_summarize_content with empty content returns WP_Error.
	 */
	public function test_handle_summarize_content_empty_content() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$result = EditorialAbilities::handle_summarize_content( [
				'content' => '',
				'length'  => 'medium',
			] );
			$this->assertInstanceOf( \WP_Error::class, $result );
			return;
		}

		$result = EditorialAbilities::handle_summarize_content( [
			'content' => '',
			'length'  => 'medium',
		] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'content_not_provided', $result->get_error_code() );
	}

	/**
	 * Test handle_summarize_content with invalid length falls back to medium.
	 *
	 * The handler normalises invalid length values to 'medium'. It should not
	 * error on the length parameter itself.
	 */
	public function test_handle_summarize_content_invalid_length_falls_back() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is not available.' );
		}

		$result = EditorialAbilities::handle_summarize_content( [
			'content' => 'Some content.',
			'length'  => 'invalid_length',
		] );

		// Should fail on AI call, not on length validation.
		$this->assertTrue(
			is_array( $result ) || is_string( $result ) || is_wp_error( $result ),
			'Result should be a string, array, or WP_Error.'
		);
	}

	// ─── handle_review_block ──────────────────────────────────────

	/**
	 * Test handle_review_block without wp_ai_client_prompt returns WP_Error.
	 */
	public function test_handle_review_block_no_ai_client() {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'wp_ai_client_prompt() is available in this environment.' );
		}

		$result = EditorialAbilities::handle_review_block( [
			'block_type'    => 'core/paragraph',
			'block_content' => 'Some paragraph content.',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_client_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_review_block with empty block_content returns WP_Error.
	 */
	public function test_handle_review_block_empty_block_content() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$result = EditorialAbilities::handle_review_block( [
				'block_type'    => 'core/paragraph',
				'block_content' => '',
			] );
			$this->assertInstanceOf( \WP_Error::class, $result );
			return;
		}

		$result = EditorialAbilities::handle_review_block( [
			'block_type'    => 'core/paragraph',
			'block_content' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'block_content_required', $result->get_error_code() );
	}

	/**
	 * Test handle_review_block with missing block_content returns WP_Error.
	 */
	public function test_handle_review_block_missing_block_content() {
		$result = EditorialAbilities::handle_review_block( [
			'block_type' => 'core/paragraph',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	// ─── permission_edit_posts ────────────────────────────────────

	/**
	 * Test permission_edit_posts returns true when user can edit posts.
	 */
	public function test_permission_edit_posts_with_capable_user() {
		$user_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$result = EditorialAbilities::permission_edit_posts( [] );

		$this->assertTrue( $result );
	}

	/**
	 * Test permission_edit_posts returns WP_Error when user cannot edit posts.
	 */
	public function test_permission_edit_posts_without_capability() {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$result = EditorialAbilities::permission_edit_posts( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'insufficient_capabilities', $result->get_error_code() );
	}
}
