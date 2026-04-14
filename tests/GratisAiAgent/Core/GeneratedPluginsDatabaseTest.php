<?php
/**
 * Tests for Database generated_plugins query methods.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\Database;
use WP_UnitTestCase;

/**
 * Test Database::insert_generated_plugin and related CRUD methods.
 */
class GeneratedPluginsDatabaseTest extends WP_UnitTestCase {

	/**
	 * Clean up the generated_plugins table before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		/** @var \wpdb $wpdb */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Test teardown; table name from internal method.
		$wpdb->query( 'TRUNCATE TABLE ' . Database::generated_plugins_table_name() );
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Insert a minimal generated plugin record and return its ID.
	 *
	 * @param string $slug Plugin slug.
	 * @return int Inserted row ID.
	 */
	private function insert_plugin( string $slug ): int {
		$id = Database::insert_generated_plugin(
			[
				'slug'        => $slug,
				'description' => 'Test plugin ' . $slug,
				'plan'        => '{"steps":[]}',
				'plugin_file' => $slug . '/' . $slug . '.php',
				'status'      => 'installed',
			]
		);
		$this->assertIsInt( $id, 'insert_generated_plugin should return an integer ID' );
		return (int) $id;
	}

	// ─── insert_generated_plugin ─────────────────────────────────────────────

	/**
	 * Test insert_generated_plugin returns a positive integer on success.
	 */
	public function test_insert_generated_plugin_returns_id(): void {
		$id = Database::insert_generated_plugin(
			[
				'slug'   => 'test-plugin',
				'status' => 'installed',
			]
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test insert_generated_plugin persists data retrievable via get_generated_plugin.
	 */
	public function test_insert_generated_plugin_persists_data(): void {
		Database::insert_generated_plugin(
			[
				'slug'        => 'persist-test',
				'description' => 'Persistence check',
				'status'      => 'active',
			]
		);

		$row = Database::get_generated_plugin( 'persist-test' );

		$this->assertNotNull( $row );
		$this->assertSame( 'persist-test', $row->slug );
		$this->assertSame( 'Persistence check', $row->description );
		$this->assertSame( 'active', $row->status );
	}

	// ─── get_generated_plugin ─────────────────────────────────────────────────

	/**
	 * Test get_generated_plugin returns null for a non-existent slug.
	 */
	public function test_get_generated_plugin_returns_null_for_missing_slug(): void {
		$row = Database::get_generated_plugin( 'does-not-exist' );
		$this->assertNull( $row );
	}

	/**
	 * Test get_generated_plugin returns the correct row.
	 */
	public function test_get_generated_plugin_returns_correct_row(): void {
		$this->insert_plugin( 'my-plugin' );

		$row = Database::get_generated_plugin( 'my-plugin' );

		$this->assertIsObject( $row );
		$this->assertSame( 'my-plugin', $row->slug );
	}

	// ─── update_generated_plugin ──────────────────────────────────────────────

	/**
	 * Test update_generated_plugin modifies the specified field.
	 */
	public function test_update_generated_plugin_modifies_field(): void {
		$this->insert_plugin( 'update-me' );

		$result = Database::update_generated_plugin( 'update-me', [ 'description' => 'Updated description' ] );

		$this->assertTrue( $result );
		$row = Database::get_generated_plugin( 'update-me' );
		$this->assertNotNull( $row );
		$this->assertSame( 'Updated description', $row->description );
	}

	/**
	 * Test update_generated_plugin returns true even with zero rows affected.
	 *
	 * $wpdb->update() returns 0 (not false) when the data is unchanged.
	 */
	public function test_update_generated_plugin_returns_true_on_no_change(): void {
		$this->insert_plugin( 'no-change' );

		// Update with the same value — $wpdb->update returns 0 (not false).
		$result = Database::update_generated_plugin( 'no-change', [ 'description' => 'Test plugin no-change' ] );

		$this->assertTrue( $result );
	}

	// ─── update_generated_plugin_status ──────────────────────────────────────

	/**
	 * Test update_generated_plugin_status changes the status field.
	 */
	public function test_update_generated_plugin_status_changes_status(): void {
		$this->insert_plugin( 'status-plugin' );

		$result = Database::update_generated_plugin_status( 'status-plugin', 'active' );

		$this->assertTrue( $result );
		$row = Database::get_generated_plugin( 'status-plugin' );
		$this->assertNotNull( $row );
		$this->assertSame( 'active', $row->status );
	}

	// ─── list_generated_plugins ───────────────────────────────────────────────

	/**
	 * Test list_generated_plugins returns all records when no status filter is given.
	 */
	public function test_list_generated_plugins_returns_all(): void {
		$this->insert_plugin( 'plugin-a' );
		$this->insert_plugin( 'plugin-b' );

		Database::update_generated_plugin_status( 'plugin-b', 'active' );

		$results = Database::list_generated_plugins();

		$this->assertCount( 2, $results );
	}

	/**
	 * Test list_generated_plugins filters by status correctly.
	 */
	public function test_list_generated_plugins_filters_by_status(): void {
		$this->insert_plugin( 'installed-only' );
		$this->insert_plugin( 'active-one' );
		Database::update_generated_plugin_status( 'active-one', 'active' );

		$active = Database::list_generated_plugins( 'active' );

		$this->assertCount( 1, $active );
		$this->assertSame( 'active-one', $active[0]->slug );
	}

	/**
	 * Test list_generated_plugins returns an empty array when no records exist.
	 */
	public function test_list_generated_plugins_returns_empty_array_when_none(): void {
		$results = Database::list_generated_plugins();
		$this->assertSame( [], $results );
	}

	// ─── delete_generated_plugin_record ──────────────────────────────────────

	/**
	 * Test delete_generated_plugin_record removes the record.
	 */
	public function test_delete_generated_plugin_record_removes_record(): void {
		$this->insert_plugin( 'delete-me' );

		$result = Database::delete_generated_plugin_record( 'delete-me' );

		$this->assertTrue( $result );
		$this->assertNull( Database::get_generated_plugin( 'delete-me' ) );
	}

	/**
	 * Test delete_generated_plugin_record returns true for a non-existent slug.
	 *
	 * $wpdb->delete() returns 0 (not false) when no rows are matched.
	 */
	public function test_delete_generated_plugin_record_returns_true_for_missing_slug(): void {
		$result = Database::delete_generated_plugin_record( 'ghost-plugin' );
		$this->assertTrue( $result );
	}

	// ─── generated_plugins_table_name ────────────────────────────────────────

	/**
	 * Test generated_plugins_table_name returns the correct table name.
	 */
	public function test_generated_plugins_table_name(): void {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_generated_plugins';
		$this->assertSame( $expected, Database::generated_plugins_table_name() );
	}
}
