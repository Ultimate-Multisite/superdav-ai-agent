<?php
/**
 * Test case for UserAbilities class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\UserAbilities;
use WP_UnitTestCase;

/**
 * Test UserAbilities handler methods.
 */
class UserAbilitiesTest extends WP_UnitTestCase {

	// ─── handle_list_users ────────────────────────────────────────

	/**
	 * Test handle_list_users returns expected structure.
	 */
	public function test_handle_list_users_returns_structure() {
		$result = UserAbilities::handle_list_users( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'users', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsArray( $result['users'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test handle_list_users total matches users array count.
	 */
	public function test_handle_list_users_total_matches_count() {
		$result = UserAbilities::handle_list_users( [] );

		$this->assertSame( count( $result['users'] ), $result['total'] );
	}

	/**
	 * Test handle_list_users each user has required fields.
	 */
	public function test_handle_list_users_user_structure() {
		// Ensure at least one user exists.
		$this->factory->user->create( [ 'role' => 'subscriber' ] );

		$result = UserAbilities::handle_list_users( [] );

		$this->assertNotEmpty( $result['users'] );
		$user = $result['users'][0];
		$this->assertArrayHasKey( 'id', $user );
		$this->assertArrayHasKey( 'username', $user );
		$this->assertArrayHasKey( 'email', $user );
		$this->assertArrayHasKey( 'display_name', $user );
		$this->assertArrayHasKey( 'roles', $user );
		$this->assertArrayHasKey( 'registered', $user );
	}

	/**
	 * Test handle_list_users with role filter returns only that role.
	 */
	public function test_handle_list_users_role_filter() {
		$this->factory->user->create( [ 'role' => 'editor' ] );

		$result = UserAbilities::handle_list_users( [ 'role' => 'editor' ] );

		$this->assertIsArray( $result );
		foreach ( $result['users'] as $user ) {
			$this->assertContains( 'editor', $user['roles'] );
		}
	}

	/**
	 * Test handle_list_users limit is respected.
	 */
	public function test_handle_list_users_limit() {
		$this->factory->user->create_many( 5 );

		$result = UserAbilities::handle_list_users( [ 'limit' => 2 ] );

		$this->assertLessThanOrEqual( 2, $result['total'] );
	}

	/**
	 * Test handle_list_users limit is clamped to 100 maximum.
	 */
	public function test_handle_list_users_limit_clamped_max() {
		$result = UserAbilities::handle_list_users( [ 'limit' => 9999 ] );

		// Should not error — limit is clamped internally.
		$this->assertIsArray( $result );
		$this->assertLessThanOrEqual( 100, $result['total'] );
	}

	/**
	 * Test handle_list_users with search term filters results.
	 */
	public function test_handle_list_users_search() {
		$unique = 'uniqueuser_' . uniqid();
		$this->factory->user->create( [
			'user_login' => $unique,
			'user_email' => $unique . '@example.com',
		] );

		$result = UserAbilities::handle_list_users( [ 'search' => $unique ] );

		$this->assertGreaterThanOrEqual( 1, $result['total'] );
	}

	// ─── handle_create_user ───────────────────────────────────────

	/**
	 * Test handle_create_user with empty username returns WP_Error.
	 */
	public function test_handle_create_user_empty_username() {
		$result = UserAbilities::handle_create_user( [
			'username' => '',
			'email'    => 'test@example.com',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_username', $result->get_error_code() );
	}

	/**
	 * Test handle_create_user with invalid email returns WP_Error.
	 */
	public function test_handle_create_user_invalid_email() {
		$result = UserAbilities::handle_create_user( [
			'username' => 'testuser_' . uniqid(),
			'email'    => 'not-an-email',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_invalid_email', $result->get_error_code() );
	}

	/**
	 * Test handle_create_user with duplicate username returns WP_Error.
	 */
	public function test_handle_create_user_duplicate_username() {
		$username = 'dupuser_' . uniqid();
		$this->factory->user->create( [
			'user_login' => $username,
			'user_email' => $username . '@example.com',
		] );

		$result = UserAbilities::handle_create_user( [
			'username' => $username,
			'email'    => 'other_' . uniqid() . '@example.com',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_username_exists', $result->get_error_code() );
	}

	/**
	 * Test handle_create_user with duplicate email returns WP_Error.
	 */
	public function test_handle_create_user_duplicate_email() {
		$email = 'dupemail_' . uniqid() . '@example.com';
		$this->factory->user->create( [
			'user_login' => 'user_' . uniqid(),
			'user_email' => $email,
		] );

		$result = UserAbilities::handle_create_user( [
			'username' => 'newuser_' . uniqid(),
			'email'    => $email,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_email_exists', $result->get_error_code() );
	}

	/**
	 * Test handle_create_user with invalid role returns WP_Error.
	 */
	public function test_handle_create_user_invalid_role() {
		$result = UserAbilities::handle_create_user( [
			'username' => 'roleuser_' . uniqid(),
			'email'    => 'roleuser_' . uniqid() . '@example.com',
			'role'     => 'nonexistent_role',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_invalid_role', $result->get_error_code() );
	}

	/**
	 * Test handle_create_user with valid data creates user and returns structure.
	 */
	public function test_handle_create_user_returns_structure() {
		$username = 'newuser_' . uniqid();
		$email    = $username . '@example.com';

		$result = UserAbilities::handle_create_user( [
			'username' => $username,
			'email'    => $email,
			'role'     => 'subscriber',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'user_id', $result );
		$this->assertArrayHasKey( 'username', $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertArrayHasKey( 'role', $result );
		$this->assertArrayHasKey( 'display_name', $result );
		$this->assertIsInt( $result['user_id'] );
		$this->assertGreaterThan( 0, $result['user_id'] );
		$this->assertSame( $username, $result['username'] );
		$this->assertSame( $email, $result['email'] );
		$this->assertSame( 'subscriber', $result['role'] );
	}

	// ─── handle_update_user_role ──────────────────────────────────

	/**
	 * Test handle_update_user_role with empty role returns WP_Error.
	 */
	public function test_handle_update_user_role_empty_role() {
		$result = UserAbilities::handle_update_user_role( [ 'role' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_role', $result->get_error_code() );
	}

	/**
	 * Test handle_update_user_role with invalid role returns WP_Error.
	 */
	public function test_handle_update_user_role_invalid_role() {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		$result = UserAbilities::handle_update_user_role( [
			'user_id' => $user_id,
			'role'    => 'nonexistent_role',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_invalid_role', $result->get_error_code() );
	}

	/**
	 * Test handle_update_user_role with no user identifier returns WP_Error.
	 */
	public function test_handle_update_user_role_user_not_found() {
		$result = UserAbilities::handle_update_user_role( [
			'role' => 'editor',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_user_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_update_user_role with valid user_id updates role.
	 */
	public function test_handle_update_user_role_by_user_id() {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		$result = UserAbilities::handle_update_user_role( [
			'user_id' => $user_id,
			'role'    => 'editor',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'user_id', $result );
		$this->assertArrayHasKey( 'username', $result );
		$this->assertArrayHasKey( 'previous_role', $result );
		$this->assertArrayHasKey( 'new_role', $result );
		$this->assertSame( $user_id, $result['user_id'] );
		$this->assertSame( 'subscriber', $result['previous_role'] );
		$this->assertSame( 'editor', $result['new_role'] );
	}

	/**
	 * Test handle_update_user_role can resolve user by email.
	 */
	public function test_handle_update_user_role_by_email() {
		$email   = 'rolebyemail_' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [
			'user_email' => $email,
			'role'       => 'author',
		] );

		$result = UserAbilities::handle_update_user_role( [
			'user_email' => $email,
			'role'       => 'editor',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( $user_id, $result['user_id'] );
		$this->assertSame( 'editor', $result['new_role'] );
	}
}
