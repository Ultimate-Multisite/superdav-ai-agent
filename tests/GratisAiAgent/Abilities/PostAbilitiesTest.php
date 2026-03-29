<?php
/**
 * Test case for PostAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\PostAbilities;
use WP_UnitTestCase;

/**
 * Test PostAbilities handler methods.
 */
class PostAbilitiesTest extends WP_UnitTestCase {

	// ─── handle_get_post ──────────────────────────────────────────

	/**
	 * Test handle_get_post with missing post_id returns WP_Error.
	 */
	public function test_handle_get_post_missing_post_id() {
		$result = PostAbilities::handle_get_post( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_post_id', $result->get_error_code() );
	}

	/**
	 * Test handle_get_post with zero post_id returns WP_Error.
	 */
	public function test_handle_get_post_zero_post_id() {
		$result = PostAbilities::handle_get_post( [ 'post_id' => 0 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_post_id', $result->get_error_code() );
	}

	/**
	 * Test handle_get_post with non-existent post_id returns WP_Error.
	 */
	public function test_handle_get_post_not_found() {
		$result = PostAbilities::handle_get_post( [ 'post_id' => 999999 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_post_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_get_post with valid post_id returns expected structure.
	 */
	public function test_handle_get_post_returns_structure() {
		$post_id = $this->factory->post->create( [
			'post_title'   => 'Test Post',
			'post_content' => 'Test content.',
			'post_status'  => 'publish',
		] );

		$result = PostAbilities::handle_get_post( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'post_type', $result );
		$this->assertArrayHasKey( 'author_id', $result );
		$this->assertArrayHasKey( 'categories', $result );
		$this->assertArrayHasKey( 'tags', $result );
		$this->assertArrayHasKey( 'featured_image', $result );
		$this->assertSame( $post_id, $result['id'] );
		$this->assertSame( 'Test Post', $result['title'] );
	}

	/**
	 * Test handle_get_post with post_type mismatch returns WP_Error.
	 */
	public function test_handle_get_post_type_mismatch() {
		$post_id = $this->factory->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
		] );

		$result = PostAbilities::handle_get_post( [
			'post_id'   => $post_id,
			'post_type' => 'page',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_post_type_mismatch', $result->get_error_code() );
	}

	/**
	 * Test handle_get_post with matching post_type succeeds.
	 */
	public function test_handle_get_post_type_match() {
		$post_id = $this->factory->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
		] );

		$result = PostAbilities::handle_get_post( [
			'post_id'   => $post_id,
			'post_type' => 'post',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['id'] );
	}

	/**
	 * Test handle_get_post categories and tags are arrays.
	 */
	public function test_handle_get_post_categories_tags_are_arrays() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish' ] );

		$result = PostAbilities::handle_get_post( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertIsArray( $result['categories'] );
		$this->assertIsArray( $result['tags'] );
	}

	// ─── handle_create_post ───────────────────────────────────────

	/**
	 * Test handle_create_post with empty title returns WP_Error.
	 */
	public function test_handle_create_post_empty_title() {
		$result = PostAbilities::handle_create_post( [ 'title' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_title', $result->get_error_code() );
	}

	/**
	 * Test handle_create_post with missing title returns WP_Error.
	 */
	public function test_handle_create_post_missing_title() {
		$result = PostAbilities::handle_create_post( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_title', $result->get_error_code() );
	}

	/**
	 * Test handle_create_post with valid title creates post and returns structure.
	 */
	public function test_handle_create_post_returns_structure() {
		$result = PostAbilities::handle_create_post( [
			'title'   => 'New Test Post',
			'content' => 'Some content.',
			'status'  => 'draft',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertArrayHasKey( 'permalink', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'post_type', $result );
		$this->assertIsInt( $result['post_id'] );
		$this->assertGreaterThan( 0, $result['post_id'] );
	}

	/**
	 * Test handle_create_post default status is draft.
	 */
	public function test_handle_create_post_default_status_is_draft() {
		$result = PostAbilities::handle_create_post( [ 'title' => 'Draft Post' ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'draft', $result['status'] );
	}

	/**
	 * Test handle_create_post with publish status creates published post.
	 */
	public function test_handle_create_post_publish_status() {
		$result = PostAbilities::handle_create_post( [
			'title'  => 'Published Post',
			'status' => 'publish',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'publish', $result['status'] );
	}

	/**
	 * Test handle_create_post with invalid status falls back to draft.
	 */
	public function test_handle_create_post_invalid_status_falls_back_to_draft() {
		$result = PostAbilities::handle_create_post( [
			'title'  => 'Post With Bad Status',
			'status' => 'invalid_status',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'draft', $result['status'] );
	}

	/**
	 * Test handle_create_post with page post_type creates a page.
	 */
	public function test_handle_create_post_page_post_type() {
		$result = PostAbilities::handle_create_post( [
			'title'     => 'New Page',
			'post_type' => 'page',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'page', $result['post_type'] );
	}

	/**
	 * Test handle_create_post with meta sets post meta.
	 */
	public function test_handle_create_post_sets_meta() {
		$result = PostAbilities::handle_create_post( [
			'title' => 'Post With Meta',
			'meta'  => [ 'custom_key' => 'custom_value' ],
		] );

		$this->assertIsArray( $result );
		$meta_value = get_post_meta( $result['post_id'], 'custom_key', true );
		$this->assertSame( 'custom_value', $meta_value );
	}

	// ─── handle_update_post ───────────────────────────────────────

	/**
	 * Test handle_update_post with missing post_id returns WP_Error.
	 */
	public function test_handle_update_post_missing_post_id() {
		$result = PostAbilities::handle_update_post( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_post_id', $result->get_error_code() );
	}

	/**
	 * Test handle_update_post with non-existent post_id returns WP_Error.
	 */
	public function test_handle_update_post_not_found() {
		$result = PostAbilities::handle_update_post( [ 'post_id' => 999999 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_post_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_update_post updates title.
	 */
	public function test_handle_update_post_updates_title() {
		$post_id = $this->factory->post->create( [
			'post_title'  => 'Original Title',
			'post_status' => 'publish',
		] );

		$result = PostAbilities::handle_update_post( [
			'post_id' => $post_id,
			'title'   => 'Updated Title',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['post_id'] );

		$updated_post = get_post( $post_id );
		$this->assertSame( 'Updated Title', $updated_post->post_title );
	}

	/**
	 * Test handle_update_post updates status.
	 */
	public function test_handle_update_post_updates_status() {
		$post_id = $this->factory->post->create( [
			'post_status' => 'draft',
		] );

		$result = PostAbilities::handle_update_post( [
			'post_id' => $post_id,
			'status'  => 'publish',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'publish', $result['status'] );
	}

	/**
	 * Test handle_update_post returns post_id, permalink, status.
	 */
	public function test_handle_update_post_returns_structure() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'draft' ] );

		$result = PostAbilities::handle_update_post( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertArrayHasKey( 'permalink', $result );
		$this->assertArrayHasKey( 'status', $result );
	}

	// ─── handle_delete_post ───────────────────────────────────────

	/**
	 * Test handle_delete_post with missing post_id returns WP_Error.
	 */
	public function test_handle_delete_post_missing_post_id() {
		$result = PostAbilities::handle_delete_post( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_post_id', $result->get_error_code() );
	}

	/**
	 * Test handle_delete_post with non-existent post_id returns WP_Error.
	 */
	public function test_handle_delete_post_not_found() {
		$result = PostAbilities::handle_delete_post( [ 'post_id' => 999999 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_post_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_delete_post trashes post by default.
	 */
	public function test_handle_delete_post_trashes_by_default() {
		$post_id = $this->factory->post->create( [
			'post_title'  => 'Post To Trash',
			'post_status' => 'publish',
		] );

		$result = PostAbilities::handle_delete_post( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertSame( 'trashed', $result['action'] );
		$this->assertFalse( $result['force_delete'] );
	}

	/**
	 * Test handle_delete_post with force_delete permanently deletes.
	 */
	public function test_handle_delete_post_force_delete() {
		$post_id = $this->factory->post->create( [
			'post_title'  => 'Post To Delete',
			'post_status' => 'publish',
		] );

		$result = PostAbilities::handle_delete_post( [
			'post_id'      => $post_id,
			'force_delete' => true,
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'permanently_deleted', $result['action'] );
		$this->assertTrue( $result['force_delete'] );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * Test handle_delete_post returns title in result.
	 */
	public function test_handle_delete_post_returns_title() {
		$post_id = $this->factory->post->create( [
			'post_title'  => 'My Titled Post',
			'post_status' => 'publish',
		] );

		$result = PostAbilities::handle_delete_post( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'My Titled Post', $result['title'] );
	}
}
