<?php
/**
 * Test case for ToolProfiles class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Tools;

use GratisAiAgent\Tools\ToolProfiles;
use WP_UnitTestCase;

/**
 * Test ToolProfiles functionality.
 */
class ToolProfilesTest extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();
		delete_option( ToolProfiles::OPTION_NAME );
	}

	/**
	 * Test list returns array.
	 */
	public function test_list_returns_array() {
		$profiles = ToolProfiles::list();

		$this->assertIsArray( $profiles );
	}

	/**
	 * Test list returns built-in profiles.
	 */
	public function test_list_includes_builtin_profiles() {
		$profiles = ToolProfiles::list();
		$slugs = array_column( $profiles, 'slug' );

		$this->assertContains( 'wp-read-only', $slugs );
		$this->assertContains( 'wp-full-management', $slugs );
		$this->assertContains( 'content-management', $slugs );
		$this->assertContains( 'developer', $slugs );
	}

	/**
	 * Test get returns profile by slug.
	 */
	public function test_get_returns_profile_by_slug() {
		$profile = ToolProfiles::get( 'developer' );

		$this->assertIsArray( $profile );
		$this->assertSame( 'developer', $profile['slug'] );
		$this->assertArrayHasKey( 'name', $profile );
		$this->assertArrayHasKey( 'description', $profile );
		$this->assertArrayHasKey( 'tool_names', $profile );
	}

	/**
	 * Test get returns null for unknown slug.
	 */
	public function test_get_returns_null_for_unknown_slug() {
		$profile = ToolProfiles::get( 'nonexistent-profile' );

		$this->assertNull( $profile );
	}

	/**
	 * Test save creates custom profile.
	 */
	public function test_save_creates_custom_profile() {
		$result = ToolProfiles::save( [
			'slug'        => 'my-custom-profile',
			'name'        => 'My Custom Profile',
			'description' => 'A test profile',
			'tool_names'  => [ 'site/get-posts', 'site/get-pages' ],
		] );

		$this->assertTrue( $result );

		$profile = ToolProfiles::get( 'my-custom-profile' );
		$this->assertSame( 'My Custom Profile', $profile['name'] );
		$this->assertSame( 'A test profile', $profile['description'] );
	}

	/**
	 * Test save fails without slug.
	 */
	public function test_save_fails_without_slug() {
		$result = ToolProfiles::save( [
			'name' => 'Test Profile',
		] );

		$this->assertFalse( $result );
	}

	/**
	 * Test save fails without name.
	 */
	public function test_save_fails_without_name() {
		$result = ToolProfiles::save( [
			'slug' => 'test-profile',
		] );

		$this->assertFalse( $result );
	}

	/**
	 * Test save updates existing profile.
	 */
	public function test_save_updates_existing_profile() {
		ToolProfiles::save( [
			'slug' => 'test-profile',
			'name' => 'Test Profile',
		] );

		ToolProfiles::save( [
			'slug' => 'test-profile',
			'name' => 'Updated Name',
		] );

		$profile = ToolProfiles::get( 'test-profile' );
		$this->assertSame( 'Updated Name', $profile['name'] );
	}

	/**
	 * Test delete removes custom profile.
	 */
	public function test_delete_removes_custom_profile() {
		ToolProfiles::save( [
			'slug' => 'to-delete',
			'name' => 'To Delete',
		] );

		$result = ToolProfiles::delete( 'to-delete' );

		$this->assertTrue( $result );
		$this->assertNull( ToolProfiles::get( 'to-delete' ) );
	}

	/**
	 * Test filter_abilities returns all when profile is empty.
	 */
	public function test_filter_abilities_returns_all_when_empty() {
		$abilities = [ 'ability1', 'ability2' ];
		$filtered = ToolProfiles::filter_abilities( $abilities, '' );

		$this->assertSame( $abilities, $filtered );
	}

	/**
	 * Test filter_abilities returns all for 'all' profile.
	 */
	public function test_filter_abilities_returns_all_for_all_profile() {
		$abilities = [ 'ability1', 'ability2' ];
		$filtered = ToolProfiles::filter_abilities( $abilities, 'all' );

		$this->assertSame( $abilities, $filtered );
	}

	/**
	 * Test export returns JSON.
	 */
	public function test_export_returns_json() {
		$json = ToolProfiles::export();

		$this->assertIsString( $json );
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
	}

	/**
	 * Test import parses JSON and saves profiles.
	 */
	public function test_import_parses_json() {
		$json = json_encode( [
			[
				'slug'        => 'imported-profile',
				'name'        => 'Imported Profile',
				'description' => 'Imported from JSON',
				'tool_names'  => [],
			],
		] );

		$count = ToolProfiles::import( $json );

		$this->assertSame( 1, $count );
		$profile = ToolProfiles::get( 'imported-profile' );
		$this->assertNotNull( $profile );
	}

	/**
	 * Test import returns 0 for invalid JSON.
	 */
	public function test_import_returns_zero_for_invalid_json() {
		$count = ToolProfiles::import( 'not valid json' );

		$this->assertSame( 0, $count );
	}

	/**
	 * Test custom profiles override built-in with same slug.
	 */
	public function test_custom_profiles_override_builtin() {
		ToolProfiles::save( [
			'slug' => 'developer',
			'name' => 'Custom Developer',
		] );

		$profile = ToolProfiles::get( 'developer' );
		$this->assertSame( 'Custom Developer', $profile['name'] );
		$this->assertFalse( $profile['is_builtin'] );
	}

	/**
	 * Test builtin profiles have is_builtin true.
	 */
	public function test_builtin_profiles_have_is_builtin_true() {
		$profile = ToolProfiles::get( 'wp-read-only' );

		$this->assertTrue( $profile['is_builtin'] );
	}

	/**
	 * Test marketing profile exists.
	 */
	public function test_marketing_profile_exists() {
		$profile = ToolProfiles::get( 'marketing' );

		$this->assertNotNull( $profile );
		$this->assertSame( 'marketing', $profile['slug'] );
	}

	/**
	 * Test content-creator profile exists.
	 */
	public function test_content_creator_profile_exists() {
		$profile = ToolProfiles::get( 'content-creator' );

		$this->assertNotNull( $profile );
		$this->assertSame( 'content-creator', $profile['slug'] );
	}
}
