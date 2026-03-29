<?php

declare(strict_types=1);
/**
 * Unit tests for Skill model.
 *
 * Tests table_name(), get_all(), get(), get_by_slug(), create(), update(),
 * delete(), reset_builtin(), get_index_for_prompt(), seed_builtins(),
 * and get_builtin_definitions().
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Models;

use GratisAiAgent\Models\Skill;
use WP_UnitTestCase;

/**
 * Tests for Skill model.
 *
 * @since 1.1.0
 */
class SkillTest extends WP_UnitTestCase {

	/**
	 * IDs of skills created during tests, for cleanup.
	 *
	 * @var int[]
	 */
	private array $created_ids = [];

	/**
	 * Tear down: delete any skills created during the test.
	 */
	public function tear_down(): void {
		global $wpdb;
		foreach ( $this->created_ids as $id ) {
			// Direct delete to bypass built-in guard.
			$wpdb->delete( Skill::table_name(), [ 'id' => $id ], [ '%d' ] );
		}
		$this->created_ids = [];
		parent::tear_down();
	}

	/**
	 * Helper: create a user skill and track its ID.
	 *
	 * @param array<string,mixed> $overrides
	 * @return int
	 */
	private function create_skill( array $overrides = [] ): int {
		$data = array_merge(
			[
				'slug'        => 'test-skill-' . uniqid(),
				'name'        => 'Test Skill',
				'description' => 'A test skill',
				'content'     => 'Skill content here.',
				'enabled'     => true,
			],
			$overrides
		);

		$id = Skill::create( $data );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		$this->created_ids[] = $id;
		return $id;
	}

	// ─── table_name() ────────────────────────────────────────────────────────

	/**
	 * table_name() returns the correct prefixed table name.
	 */
	public function test_table_name_returns_correct_name(): void {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_skills';
		$this->assertSame( $expected, Skill::table_name() );
	}

	// ─── create() ────────────────────────────────────────────────────────────

	/**
	 * create() returns a positive integer ID on success.
	 */
	public function test_create_returns_positive_id(): void {
		$id = $this->create_skill();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * create() stores all provided fields correctly.
	 */
	public function test_create_stores_fields(): void {
		$id = $this->create_skill( [
			'slug'        => 'stored-skill',
			'name'        => 'Stored Skill',
			'description' => 'A stored description',
			'content'     => 'Stored content',
			'enabled'     => true,
		] );

		$skill = Skill::get( $id );
		$this->assertNotNull( $skill );
		$this->assertSame( 'stored-skill', $skill->slug );
		$this->assertSame( 'Stored Skill', $skill->name );
		$this->assertSame( 'A stored description', $skill->description );
		$this->assertSame( 'Stored content', $skill->content );
		$this->assertSame( '1', (string) $skill->enabled );
		$this->assertSame( '0', (string) $skill->is_builtin );
	}

	/**
	 * create() defaults enabled to 1 when not provided.
	 */
	public function test_create_defaults_enabled_to_true(): void {
		$id    = $this->create_skill( [ 'slug' => 'default-enabled-skill' ] );
		$skill = Skill::get( $id );

		$this->assertNotNull( $skill );
		$this->assertSame( '1', (string) $skill->enabled );
	}

	// ─── get() ───────────────────────────────────────────────────────────────

	/**
	 * get() returns null for a non-existent ID.
	 */
	public function test_get_returns_null_for_missing_id(): void {
		$result = Skill::get( 999999 );
		$this->assertNull( $result );
	}

	/**
	 * get() returns the correct skill for a valid ID.
	 */
	public function test_get_returns_correct_skill(): void {
		$id    = $this->create_skill( [ 'name' => 'Get Test Skill' ] );
		$skill = Skill::get( $id );

		$this->assertNotNull( $skill );
		$this->assertSame( 'Get Test Skill', $skill->name );
	}

	// ─── get_by_slug() ───────────────────────────────────────────────────────

	/**
	 * get_by_slug() returns null for an unknown slug.
	 */
	public function test_get_by_slug_returns_null_for_unknown_slug(): void {
		$result = Skill::get_by_slug( 'nonexistent-skill-xyz' );
		$this->assertNull( $result );
	}

	/**
	 * get_by_slug() returns the correct skill.
	 */
	public function test_get_by_slug_returns_correct_skill(): void {
		$id    = $this->create_skill( [ 'slug' => 'slug-lookup-skill' ] );
		$skill = Skill::get_by_slug( 'slug-lookup-skill' );

		$this->assertNotNull( $skill );
		$this->assertSame( (string) $id, (string) $skill->id );
	}

	// ─── get_all() ───────────────────────────────────────────────────────────

	/**
	 * get_all() returns an array.
	 */
	public function test_get_all_returns_array(): void {
		$result = Skill::get_all();
		$this->assertIsArray( $result );
	}

	/**
	 * get_all() includes newly created skills.
	 */
	public function test_get_all_includes_created_skill(): void {
		$id   = $this->create_skill( [ 'name' => 'Get All Skill' ] );
		$all  = Skill::get_all();
		$ids  = array_map( 'strval', array_column( $all, 'id' ) );

		$this->assertContains( (string) $id, $ids );
	}

	/**
	 * get_all( true ) returns only enabled skills.
	 */
	public function test_get_all_filters_by_enabled(): void {
		$enabled_id  = $this->create_skill( [ 'slug' => 'enabled-skill-filter', 'enabled' => true ] );
		$disabled_id = $this->create_skill( [ 'slug' => 'disabled-skill-filter', 'enabled' => false ] );

		$enabled = Skill::get_all( true );
		$ids     = array_map( 'strval', array_column( $enabled, 'id' ) );

		$this->assertContains( (string) $enabled_id, $ids );
		$this->assertNotContains( (string) $disabled_id, $ids );
	}

	/**
	 * get_all( false ) returns only disabled skills.
	 */
	public function test_get_all_filters_by_disabled(): void {
		$enabled_id  = $this->create_skill( [ 'slug' => 'enabled-skill-filter-2', 'enabled' => true ] );
		$disabled_id = $this->create_skill( [ 'slug' => 'disabled-skill-filter-2', 'enabled' => false ] );

		$disabled = Skill::get_all( false );
		$ids      = array_map( 'strval', array_column( $disabled, 'id' ) );

		$this->assertNotContains( (string) $enabled_id, $ids );
		$this->assertContains( (string) $disabled_id, $ids );
	}

	// ─── update() ────────────────────────────────────────────────────────────

	/**
	 * update() returns true and persists changes.
	 */
	public function test_update_persists_changes(): void {
		$id = $this->create_skill( [ 'name' => 'Original Skill Name' ] );

		$result = Skill::update( $id, [ 'name' => 'Updated Skill Name' ] );
		$this->assertTrue( $result );

		$skill = Skill::get( $id );
		$this->assertNotNull( $skill );
		$this->assertSame( 'Updated Skill Name', $skill->name );
	}

	/**
	 * update() ignores fields not in the allowed list.
	 */
	public function test_update_ignores_disallowed_fields(): void {
		$id = $this->create_skill( [ 'slug' => 'original-slug-skill' ] );

		// 'slug' is not in the allowed update list.
		Skill::update( $id, [ 'slug' => 'changed-slug', 'name' => 'Name Changed' ] );

		$skill = Skill::get( $id );
		$this->assertNotNull( $skill );
		$this->assertSame( 'original-slug-skill', $skill->slug );
		$this->assertSame( 'Name Changed', $skill->name );
	}

	/**
	 * update() can toggle enabled status.
	 */
	public function test_update_toggles_enabled(): void {
		$id = $this->create_skill( [ 'enabled' => true ] );

		Skill::update( $id, [ 'enabled' => false ] );

		$skill = Skill::get( $id );
		$this->assertNotNull( $skill );
		$this->assertSame( '0', (string) $skill->enabled );
	}

	// ─── delete() ────────────────────────────────────────────────────────────

	/**
	 * delete() removes a user-created skill.
	 */
	public function test_delete_removes_user_skill(): void {
		$id = Skill::create( [
			'slug'    => 'delete-me-skill',
			'name'    => 'Delete Me',
			'content' => 'Content',
		] );
		$this->assertIsInt( $id );

		$result = Skill::delete( $id );
		$this->assertTrue( $result );

		$skill = Skill::get( $id );
		$this->assertNull( $skill );
	}

	/**
	 * delete() returns false for a non-existent skill.
	 */
	public function test_delete_returns_false_for_missing_skill(): void {
		$result = Skill::delete( 999999 );
		$this->assertFalse( $result );
	}

	/**
	 * delete() returns 'builtin' string for a built-in skill.
	 */
	public function test_delete_returns_builtin_string_for_builtin_skill(): void {
		// Create a skill flagged as built-in.
		$id = Skill::create( [
			'slug'       => 'builtin-skill-test',
			'name'       => 'Built-in Skill',
			'content'    => 'Content',
			'is_builtin' => true,
		] );
		$this->assertIsInt( $id );
		$this->created_ids[] = $id;

		$result = Skill::delete( $id );
		$this->assertSame( 'builtin', $result );

		// Verify it still exists.
		$skill = Skill::get( $id );
		$this->assertNotNull( $skill );
	}

	// ─── get_builtin_definitions() ───────────────────────────────────────────

	/**
	 * get_builtin_definitions() returns a non-empty array keyed by slug.
	 */
	public function test_get_builtin_definitions_returns_non_empty_array(): void {
		$definitions = Skill::get_builtin_definitions();

		$this->assertIsArray( $definitions );
		$this->assertNotEmpty( $definitions );
	}

	/**
	 * Each built-in definition has required keys.
	 */
	public function test_get_builtin_definitions_each_has_required_keys(): void {
		$definitions = Skill::get_builtin_definitions();

		foreach ( $definitions as $slug => $definition ) {
			$this->assertIsString( $slug );
			$this->assertArrayHasKey( 'name', $definition );
			$this->assertArrayHasKey( 'description', $definition );
			$this->assertArrayHasKey( 'content', $definition );
			$this->assertArrayHasKey( 'enabled', $definition );
		}
	}

	/**
	 * get_builtin_definitions() includes 'wordpress-admin' skill.
	 */
	public function test_get_builtin_definitions_includes_wordpress_admin(): void {
		$definitions = Skill::get_builtin_definitions();

		$this->assertArrayHasKey( 'wordpress-admin', $definitions );
	}

	// ─── seed_builtins() ─────────────────────────────────────────────────────

	/**
	 * seed_builtins() inserts built-in skills into the database.
	 */
	public function test_seed_builtins_inserts_skills(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . Skill::table_name() . " WHERE is_builtin = 1" );

		Skill::seed_builtins();

		$all = Skill::get_all();
		$this->assertNotEmpty( $all );

		$slugs = array_column( $all, 'slug' );
		$this->assertContains( 'wordpress-admin', $slugs );
	}

	/**
	 * seed_builtins() is idempotent — calling twice does not duplicate.
	 */
	public function test_seed_builtins_is_idempotent(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . Skill::table_name() . " WHERE is_builtin = 1" );

		Skill::seed_builtins();
		$count_first = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . Skill::table_name() . " WHERE is_builtin = 1"
		);

		Skill::seed_builtins();
		$count_second = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . Skill::table_name() . " WHERE is_builtin = 1"
		);

		$this->assertSame( $count_first, $count_second );
	}

	// ─── reset_builtin() ─────────────────────────────────────────────────────

	/**
	 * reset_builtin() returns false for a non-existent skill.
	 */
	public function test_reset_builtin_returns_false_for_missing_skill(): void {
		$result = Skill::reset_builtin( 999999 );
		$this->assertFalse( $result );
	}

	/**
	 * reset_builtin() returns false for a user-created skill.
	 */
	public function test_reset_builtin_returns_false_for_user_skill(): void {
		$id     = $this->create_skill( [ 'name' => 'User Skill' ] );
		$result = Skill::reset_builtin( $id );

		$this->assertFalse( $result );
	}

	/**
	 * reset_builtin() restores original content for a built-in skill.
	 */
	public function test_reset_builtin_restores_original_content(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . Skill::table_name() . " WHERE is_builtin = 1" );
		Skill::seed_builtins();

		$skill = Skill::get_by_slug( 'wordpress-admin' );
		$this->assertNotNull( $skill );

		// Modify the content.
		Skill::update( (int) $skill->id, [ 'content' => 'Modified content' ] );

		// Reset it.
		$result = Skill::reset_builtin( (int) $skill->id );
		$this->assertTrue( $result );

		// Verify content was restored.
		$restored = Skill::get( (int) $skill->id );
		$this->assertNotNull( $restored );
		$this->assertNotSame( 'Modified content', $restored->content );
		$this->assertStringContainsString( 'WordPress Administration', $restored->content );
	}

	// ─── get_index_for_prompt() ──────────────────────────────────────────────

	/**
	 * get_index_for_prompt() returns empty string when no skills are enabled.
	 */
	public function test_get_index_for_prompt_returns_empty_when_no_enabled_skills(): void {
		global $wpdb;
		// Disable all skills temporarily.
		$wpdb->query( "UPDATE " . Skill::table_name() . " SET enabled = 0" );

		$result = Skill::get_index_for_prompt();
		$this->assertSame( '', $result );

		// Re-enable all.
		$wpdb->query( "UPDATE " . Skill::table_name() . " SET enabled = 1" );
	}

	/**
	 * get_index_for_prompt() returns a formatted string with enabled skills.
	 */
	public function test_get_index_for_prompt_returns_formatted_string(): void {
		$id = $this->create_skill( [
			'slug'        => 'prompt-index-skill',
			'name'        => 'Prompt Index Skill',
			'description' => 'For prompt index test',
			'enabled'     => true,
		] );

		$result = Skill::get_index_for_prompt();

		$this->assertIsString( $result );
		$this->assertStringContainsString( '## Available Skills', $result );
		$this->assertStringContainsString( 'prompt-index-skill', $result );
	}
}
