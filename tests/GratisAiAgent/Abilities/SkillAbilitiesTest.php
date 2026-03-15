<?php
/**
 * Test case for SkillAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\SkillAbilities;
use GratisAiAgent\Models\Skill;
use WP_UnitTestCase;

/**
 * Test SkillAbilities handler methods.
 */
class SkillAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_skill_list returns message when no enabled skills exist.
	 *
	 * Built-in skills cannot be deleted via Skill::delete() (returns 'builtin').
	 * We disable all skills via a direct UPDATE to simulate an empty enabled list,
	 * then restore them after the assertion. TRUNCATE causes an implicit commit in
	 * MariaDB and bypasses WP's transaction-based test isolation.
	 */
	public function test_handle_skill_list_empty() {
		global $wpdb;

		// Disable all skills directly — avoids TRUNCATE's implicit commit.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'UPDATE ' . Skill::table_name() . ' SET enabled = 0' );

		$result = SkillAbilities::handle_skill_list();

		// Re-enable built-in skills so subsequent tests are not affected.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'UPDATE ' . Skill::table_name() . ' SET enabled = 1 WHERE is_builtin = 1' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertStringContainsString( 'No skills', $result['message'] );
	}

	/**
	 * Test handle_skill_list returns skills when they exist.
	 */
	public function test_handle_skill_list_with_skills() {
		// Create a test skill.
		Skill::create( [
			'slug'        => 'test-skill',
			'name'        => 'Test Skill',
			'description' => 'A test skill description',
			'content'     => 'Test skill content',
			'enabled'     => true,
		] );

		$result = SkillAbilities::handle_skill_list();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'skills', $result );
		$this->assertIsArray( $result['skills'] );
		$this->assertNotEmpty( $result['skills'] );

		// Each skill should have slug, name, description.
		$skill = $result['skills'][0];
		$this->assertArrayHasKey( 'slug', $skill );
		$this->assertArrayHasKey( 'name', $skill );
		$this->assertArrayHasKey( 'description', $skill );
	}

	/**
	 * Test handle_skill_list only returns enabled skills.
	 */
	public function test_handle_skill_list_only_enabled() {
		// Create enabled and disabled skills.
		Skill::create( [ 'slug' => 'enabled-skill', 'name' => 'Enabled Skill', 'description' => 'Enabled', 'content' => 'Content', 'enabled' => true ] );
		Skill::create( [ 'slug' => 'disabled-skill', 'name' => 'Disabled Skill', 'description' => 'Disabled', 'content' => 'Content', 'enabled' => false ] );

		$result = SkillAbilities::handle_skill_list();

		if ( isset( $result['skills'] ) ) {
			$slugs = array_column( $result['skills'], 'slug' );
			$this->assertContains( 'enabled-skill', $slugs );
			$this->assertNotContains( 'disabled-skill', $slugs );
		}
	}

	/**
	 * Test handle_skill_load with empty slug returns WP_Error.
	 */
	public function test_handle_skill_load_empty_slug() {
		$result = SkillAbilities::handle_skill_load( [
			'slug' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'required', $result->get_error_message() );
	}

	/**
	 * Test handle_skill_load with missing slug returns WP_Error.
	 */
	public function test_handle_skill_load_missing_slug() {
		$result = SkillAbilities::handle_skill_load( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_skill_load with non-existent slug returns WP_Error.
	 */
	public function test_handle_skill_load_not_found() {
		$result = SkillAbilities::handle_skill_load( [
			'slug' => 'nonexistent-skill-xyz-12345',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'not found', $result->get_error_message() );
	}

	/**
	 * Test handle_skill_load with disabled skill returns WP_Error.
	 */
	public function test_handle_skill_load_disabled_skill() {
		Skill::create( [ 'slug' => 'disabled-load-test', 'name' => 'Disabled Load Test', 'description' => 'Desc', 'content' => 'Content', 'enabled' => false ] );

		$result = SkillAbilities::handle_skill_load( [
			'slug' => 'disabled-load-test',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'disabled', $result->get_error_message() );
	}

	/**
	 * Test handle_skill_load with enabled skill returns content.
	 */
	public function test_handle_skill_load_enabled_skill() {
		Skill::create( [ 'slug' => 'enabled-load-test', 'name' => 'Enabled Load Test', 'description' => 'Description', 'content' => 'Skill content here', 'enabled' => true ] );

		$result = SkillAbilities::handle_skill_load( [
			'slug' => 'enabled-load-test',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'slug', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertSame( 'enabled-load-test', $result['slug'] );
		$this->assertSame( 'Skill content here', $result['content'] );
	}
}
