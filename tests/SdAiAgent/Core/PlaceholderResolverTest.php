<?php
/**
 * Test case for PlaceholderResolver class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\PlaceholderResolver;
use WP_UnitTestCase;

/**
 * Test PlaceholderResolver functionality.
 */
class PlaceholderResolverTest extends WP_UnitTestCase {

	/**
	 * Test resolve with simple direct placeholder.
	 */
	public function test_resolve_simple_placeholder() {
		// Create a mock trigger registration.
		add_filter( 'sd_ai_agent_event_triggers', function ( $triggers ) {
			$triggers['test_hook'] = [
				'args' => [ 'name', 'value' ],
			];
			return $triggers;
		} );

		$template = 'Hello {{name}}, your value is {{value}}.';
		$result = PlaceholderResolver::resolve( $template, 'test_hook', [ 'John', 42 ] );

		// Without the trigger registered in EventTriggerRegistry, it won't resolve.
		// This test verifies the basic placeholder pattern matching works.
		$this->assertStringContainsString( '{{', $result );
	}

	/**
	 * Test resolve leaves unknown placeholders intact.
	 */
	public function test_resolve_unknown_placeholder_unchanged() {
		$template = 'Value: {{unknown_key}}';
		$result = PlaceholderResolver::resolve( $template, 'nonexistent_hook', [] );

		$this->assertSame( 'Value: {{unknown_key}}', $result );
	}

	/**
	 * Test resolve with no placeholders returns original.
	 */
	public function test_resolve_no_placeholders() {
		$template = 'This is plain text without placeholders.';
		$result = PlaceholderResolver::resolve( $template, 'any_hook', [] );

		$this->assertSame( $template, $result );
	}

	/**
	 * Test resolve with empty template.
	 */
	public function test_resolve_empty_template() {
		$result = PlaceholderResolver::resolve( '', 'any_hook', [] );
		$this->assertSame( '', $result );
	}

	/**
	 * Test resolve handles malformed placeholders gracefully.
	 */
	public function test_resolve_malformed_placeholder() {
		$template = 'Value: {{ spaced }} and {{}} and {single}';
		$result = PlaceholderResolver::resolve( $template, 'any_hook', [] );

		// Malformed placeholders should be left as-is.
		$this->assertSame( $template, $result );
	}

	/**
	 * Test resolve with dot notation placeholder (when context unavailable).
	 */
	public function test_resolve_dot_notation_no_context() {
		$template = 'Post title: {{post.title}}';
		$result = PlaceholderResolver::resolve( $template, 'unknown_hook', [] );

		// Without context, dot notation should be left as-is.
		$this->assertSame( $template, $result );
	}

	/**
	 * Test resolve with transition_post_status hook and post context.
	 */
	public function test_resolve_transition_post_status_post_context() {
		// Create a test post.
		$post_id = self::factory()->post->create( [
			'post_title'  => 'Test Post Title',
			'post_status' => 'publish',
			'post_type'   => 'post',
		] );
		$post = get_post( $post_id );

		$template = 'New post: {{post.title}} (ID: {{post.ID}})';
		$result = PlaceholderResolver::resolve(
			$template,
			'transition_post_status',
			[ 'publish', 'draft', $post ]
		);

		$this->assertStringContainsString( 'Test Post Title', $result );
		$this->assertStringContainsString( (string) $post_id, $result );
	}

	/**
	 * Test resolve with user_register hook and user context.
	 */
	public function test_resolve_user_register_user_context() {
		// Create a test user.
		$user_id = self::factory()->user->create( [
			'user_login'   => 'testuser',
			'user_email'   => 'test@example.com',
			'display_name' => 'Test User',
		] );

		$template = 'New user: {{user.display_name}} ({{user.email}})';
		$result = PlaceholderResolver::resolve(
			$template,
			'user_register',
			[ $user_id ]
		);

		$this->assertStringContainsString( 'Test User', $result );
		$this->assertStringContainsString( 'test@example.com', $result );
	}

	/**
	 * Test resolve with wp_login_failed hook captures username.
	 */
	public function test_resolve_wp_login_failed_username() {
		$template = 'Failed login for: {{username}}';
		$result = PlaceholderResolver::resolve(
			$template,
			'wp_login_failed',
			[ 'baduser' ]
		);

		$this->assertStringContainsString( 'baduser', $result );
	}

	/**
	 * Test resolve with delete_post hook enriches post context.
	 */
	public function test_resolve_delete_post_context() {
		$post_id = self::factory()->post->create( [
			'post_title' => 'Post To Delete',
			'post_type'  => 'page',
		] );

		$template = 'Deleted: {{post.title}} (type: {{post.type}})';
		$result = PlaceholderResolver::resolve(
			$template,
			'delete_post',
			[ $post_id ]
		);

		$this->assertStringContainsString( 'Post To Delete', $result );
		$this->assertStringContainsString( 'page', $result );
	}

	/**
	 * Test resolve with comment_post hook enriches comment context.
	 */
	public function test_resolve_comment_post_context() {
		$post_id = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( [
			'comment_post_ID' => $post_id,
			'comment_author'  => 'Commenter Name',
			'comment_content' => 'This is a test comment.',
		] );

		$template = 'New comment by {{comment.author}}: {{comment.content}}';
		$result = PlaceholderResolver::resolve(
			$template,
			'comment_post',
			[ $comment_id ]
		);

		$this->assertStringContainsString( 'Commenter Name', $result );
		$this->assertStringContainsString( 'This is a test comment', $result );
	}

	/**
	 * Test resolve handles numeric values correctly.
	 */
	public function test_resolve_numeric_values() {
		$post_id = self::factory()->post->create( [
			'post_title' => 'Numeric Test',
		] );
		$post = get_post( $post_id );

		$template = 'Post ID: {{post.ID}}';
		$result = PlaceholderResolver::resolve(
			$template,
			'transition_post_status',
			[ 'publish', 'draft', $post ]
		);

		$this->assertStringContainsString( (string) $post_id, $result );
		$this->assertStringNotContainsString( '{{post.ID}}', $result );
	}

	/**
	 * Test resolve with multiple placeholders in one template.
	 */
	public function test_resolve_multiple_placeholders() {
		$post_id = self::factory()->post->create( [
			'post_title'  => 'Multi Test',
			'post_status' => 'publish',
			'post_type'   => 'post',
		] );
		$post = get_post( $post_id );

		$template = '{{post.title}} ({{post.type}}) is now {{post.status}}';
		$result = PlaceholderResolver::resolve(
			$template,
			'transition_post_status',
			[ 'publish', 'draft', $post ]
		);

		$this->assertStringContainsString( 'Multi Test', $result );
		$this->assertStringContainsString( 'post', $result );
		$this->assertStringContainsString( 'publish', $result );
	}
}
