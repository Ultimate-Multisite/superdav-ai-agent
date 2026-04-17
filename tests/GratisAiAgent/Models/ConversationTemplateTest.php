<?php

declare(strict_types=1);
/**
 * Unit tests for ConversationTemplate model.
 *
 * Tests table_name(), get_builtins(), seed_builtins(), get_all(),
 * get(), create(), update(), delete(), and get_categories().
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Models;

use GratisAiAgent\Models\ConversationTemplate;
use WP_UnitTestCase;

/**
 * Tests for ConversationTemplate model.
 *
 * @since 1.1.0
 */
class ConversationTemplateTest extends WP_UnitTestCase {

	/**
	 * IDs of templates created during tests, for cleanup.
	 *
	 * @var int[]
	 */
	private array $created_ids = [];

	/**
	 * Tear down: delete any templates created during the test.
	 */
	public function tear_down(): void {
		foreach ( $this->created_ids as $id ) {
			global $wpdb;
			// Direct delete to bypass built-in guard.
			$wpdb->delete( ConversationTemplate::table_name(), [ 'id' => $id ], [ '%d' ] );
		}
		$this->created_ids = [];
		parent::tear_down();
	}

	/**
	 * Helper: create a user template and track its ID.
	 *
	 * @param array<string,mixed> $overrides
	 * @return int
	 */
	private function create_template( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'     => 'Test Template',
				'prompt'   => 'Test prompt content',
				'category' => 'general',
			],
			$overrides
		);

		$id = ConversationTemplate::create( $data );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		$this->created_ids[] = (int) $id;
		return (int) $id;
	}

	// ─── table_name() ────────────────────────────────────────────────────────

	/**
	 * table_name() returns the correct prefixed table name.
	 */
	public function test_table_name_returns_correct_name(): void {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_conversation_templates';
		$this->assertSame( $expected, ConversationTemplate::table_name() );
	}

	// ─── get_builtins() ──────────────────────────────────────────────────────

	/**
	 * get_builtins() returns a non-empty array.
	 */
	public function test_get_builtins_returns_non_empty_array(): void {
		$builtins = ConversationTemplate::get_builtins();

		$this->assertIsArray( $builtins );
		$this->assertNotEmpty( $builtins );
	}

	/**
	 * Each built-in has required keys.
	 */
	public function test_get_builtins_each_has_required_keys(): void {
		$builtins = ConversationTemplate::get_builtins();

		foreach ( $builtins as $template ) {
			$this->assertArrayHasKey( 'slug', $template );
			$this->assertArrayHasKey( 'name', $template );
			$this->assertArrayHasKey( 'prompt', $template );
			$this->assertArrayHasKey( 'category', $template );
		}
	}

	/**
	 * get_builtins() includes the 'summarise-page' template.
	 */
	public function test_get_builtins_includes_summarise_page(): void {
		$builtins = ConversationTemplate::get_builtins();
		$slugs    = array_column( $builtins, 'slug' );

		$this->assertContains( 'summarise-page', $slugs );
	}

	// ─── seed_builtins() ─────────────────────────────────────────────────────

	/**
	 * seed_builtins() inserts built-in templates into the database.
	 */
	public function test_seed_builtins_inserts_templates(): void {
		// Clear any existing built-ins first.
		global $wpdb;
		$wpdb->query( "DELETE FROM " . ConversationTemplate::table_name() . " WHERE is_builtin = 1" );

		ConversationTemplate::seed_builtins();

		$all = ConversationTemplate::get_all();
		$this->assertNotEmpty( $all );

		$slugs = array_column( $all, 'slug' );
		$this->assertContains( 'summarise-page', $slugs );
	}

	/**
	 * seed_builtins() is idempotent — calling twice does not duplicate.
	 */
	public function test_seed_builtins_is_idempotent(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . ConversationTemplate::table_name() . " WHERE is_builtin = 1" );

		ConversationTemplate::seed_builtins();
		$count_after_first = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . ConversationTemplate::table_name() . " WHERE is_builtin = 1"
		);

		ConversationTemplate::seed_builtins();
		$count_after_second = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . ConversationTemplate::table_name() . " WHERE is_builtin = 1"
		);

		$this->assertSame( $count_after_first, $count_after_second );
	}

	// ─── create() ────────────────────────────────────────────────────────────

	/**
	 * create() returns a positive integer ID.
	 */
	public function test_create_returns_positive_id(): void {
		$id = $this->create_template();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * create() stores all provided fields correctly.
	 */
	public function test_create_stores_fields(): void {
		$id = $this->create_template( [
			'slug'        => 'my-custom-template',
			'name'        => 'My Custom Template',
			'description' => 'A custom description',
			'prompt'      => 'Custom prompt text',
			'category'    => 'writing',
			'icon'        => 'edit',
			'sort_order'  => 5,
		] );

		$template = ConversationTemplate::get( $id );
		$this->assertNotNull( $template );
		$this->assertSame( 'My Custom Template', $template->name );
		$this->assertSame( 'A custom description', $template->description );
		$this->assertSame( 'Custom prompt text', $template->prompt );
		$this->assertSame( 'writing', $template->category );
		$this->assertSame( 'edit', $template->icon );
		$this->assertFalse( $template->is_builtin );
	}

	/**
	 * create() auto-generates a slug when not provided.
	 */
	public function test_create_auto_generates_slug(): void {
		$id = $this->create_template( [ 'name' => 'Auto Slug Template' ] );

		$template = ConversationTemplate::get( $id );
		$this->assertNotNull( $template );
		$this->assertNotEmpty( $template->slug );
		$this->assertStringContainsString( 'auto-slug-template', $template->slug );
	}

	// ─── get() ───────────────────────────────────────────────────────────────

	/**
	 * get() returns null for a non-existent ID.
	 */
	public function test_get_returns_null_for_missing_id(): void {
		$result = ConversationTemplate::get( 999999 );
		$this->assertNull( $result );
	}

	/**
	 * get() returns the correct template for a valid ID.
	 */
	public function test_get_returns_correct_template(): void {
		$id       = $this->create_template( [ 'name' => 'Get Test Template' ] );
		$template = ConversationTemplate::get( $id );

		$this->assertNotNull( $template );
		$this->assertSame( 'Get Test Template', $template->name );
	}

	// ─── get_all() ───────────────────────────────────────────────────────────

	/**
	 * get_all() returns an array.
	 */
	public function test_get_all_returns_array(): void {
		$result = ConversationTemplate::get_all();
		$this->assertIsArray( $result );
	}

	/**
	 * get_all() includes newly created templates.
	 */
	public function test_get_all_includes_created_template(): void {
		$id = $this->create_template( [ 'name' => 'Get All Test' ] );

		$all = ConversationTemplate::get_all();
		$ids = array_column( $all, 'id' );

		$this->assertContains( (string) $id, array_map( 'strval', $ids ) );
	}

	/**
	 * get_all() filters by category when provided.
	 */
	public function test_get_all_filters_by_category(): void {
		$writing_id = $this->create_template( [ 'name' => 'Writing Template', 'category' => 'writing' ] );
		$seo_id     = $this->create_template( [ 'name' => 'SEO Template', 'category' => 'seo' ] );

		$writing = ConversationTemplate::get_all( 'writing' );
		$ids     = array_map( 'strval', array_column( $writing, 'id' ) );

		$this->assertContains( (string) $writing_id, $ids );
		$this->assertNotContains( (string) $seo_id, $ids );

		foreach ( $writing as $template ) {
			$this->assertSame( 'writing', $template->category );
		}
	}

	// ─── update() ────────────────────────────────────────────────────────────

	/**
	 * update() returns true and persists changes.
	 */
	public function test_update_persists_changes(): void {
		$id = $this->create_template( [ 'name' => 'Original Name' ] );

		$result = ConversationTemplate::update( $id, [ 'name' => 'Updated Name' ] );
		$this->assertTrue( $result );

		$template = ConversationTemplate::get( $id );
		$this->assertNotNull( $template );
		$this->assertSame( 'Updated Name', $template->name );
	}

	/**
	 * update() returns false when no allowed fields are provided.
	 */
	public function test_update_returns_false_for_empty_data(): void {
		$id     = $this->create_template();
		$result = ConversationTemplate::update( $id, [] );

		$this->assertFalse( $result );
	}

	/**
	 * update() can update multiple fields at once.
	 */
	public function test_update_multiple_fields(): void {
		$id = $this->create_template( [ 'name' => 'Multi Update', 'category' => 'general' ] );

		ConversationTemplate::update( $id, [
			'name'        => 'Updated Multi',
			'description' => 'New description',
			'category'    => 'writing',
		] );

		$template = ConversationTemplate::get( $id );
		$this->assertNotNull( $template );
		$this->assertSame( 'Updated Multi', $template->name );
		$this->assertSame( 'New description', $template->description );
		$this->assertSame( 'writing', $template->category );
	}

	// ─── delete() ────────────────────────────────────────────────────────────

	/**
	 * delete() removes a user-created template.
	 */
	public function test_delete_removes_user_template(): void {
		$id = ConversationTemplate::create( [
			'name'     => 'To Delete',
			'prompt'   => 'Delete me',
			'category' => 'general',
		] );
		$this->assertIsInt( $id );

		$result = ConversationTemplate::delete( (int) $id );
		$this->assertTrue( $result );

		$template = ConversationTemplate::get( (int) $id );
		$this->assertNull( $template );
	}

	/**
	 * delete() returns false for a non-existent template.
	 */
	public function test_delete_returns_false_for_missing_template(): void {
		$result = ConversationTemplate::delete( 999999 );
		$this->assertFalse( $result );
	}

	/**
	 * delete() refuses to delete built-in templates.
	 */
	public function test_delete_refuses_builtin_templates(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . ConversationTemplate::table_name() . " WHERE is_builtin = 1" );
		ConversationTemplate::seed_builtins();

		$builtins = $wpdb->get_results(
			"SELECT id FROM " . ConversationTemplate::table_name() . " WHERE is_builtin = 1 LIMIT 1"
		);
		$this->assertNotEmpty( $builtins );

		$builtin_id = (int) $builtins[0]->id;
		$result     = ConversationTemplate::delete( $builtin_id );

		$this->assertFalse( $result );

		// Verify it still exists.
		$template = ConversationTemplate::get( $builtin_id );
		$this->assertNotNull( $template );
	}

	// ─── get_categories() ────────────────────────────────────────────────────

	/**
	 * get_categories() returns an array of strings.
	 */
	public function test_get_categories_returns_array(): void {
		$this->create_template( [ 'category' => 'content' ] );

		$categories = ConversationTemplate::get_categories();

		$this->assertIsArray( $categories );
		$this->assertContains( 'content', $categories );
	}

	/**
	 * get_categories() returns distinct values only.
	 */
	public function test_get_categories_returns_distinct_values(): void {
		$this->create_template( [ 'category' => 'unique-cat-test' ] );
		$this->create_template( [ 'category' => 'unique-cat-test' ] );

		$categories = ConversationTemplate::get_categories();
		$count      = count( array_filter( $categories, fn( $c ) => $c === 'unique-cat-test' ) );

		$this->assertSame( 1, $count );
	}
}
