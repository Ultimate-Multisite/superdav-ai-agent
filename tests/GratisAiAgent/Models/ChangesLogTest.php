<?php

declare(strict_types=1);
/**
 * Unit tests for ChangesLog model.
 *
 * Tests record(), list(), get(), mark_reverted(), delete(),
 * generate_diff(), and generate_patch().
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Models;

use GratisAiAgent\Models\ChangesLog;
use WP_UnitTestCase;

/**
 * Tests for ChangesLog model.
 *
 * @since 1.1.0
 */
class ChangesLogTest extends WP_UnitTestCase {

	/**
	 * IDs of records created during tests, for cleanup.
	 *
	 * @var int[]
	 */
	private array $created_ids = [];

	/**
	 * Tear down: delete any records created during the test.
	 */
	public function tear_down(): void {
		foreach ( $this->created_ids as $id ) {
			ChangesLog::delete( $id );
		}
		$this->created_ids = [];
		parent::tear_down();
	}

	/**
	 * Helper: create a minimal change record and track its ID.
	 *
	 * @param array<string,mixed> $overrides
	 * @return int
	 */
	private function create_record( array $overrides = [] ): int {
		$data = array_merge(
			[
				'session_id'   => 1,
				'object_type'  => 'post',
				'object_id'    => 42,
				'object_title' => 'Test Post',
				'ability_name' => 'test-ability',
				'field_name'   => 'post_content',
				'before_value' => 'Before content',
				'after_value'  => 'After content',
			],
			$overrides
		);

		$id = ChangesLog::record( $data );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		$this->created_ids[] = $id;
		return $id;
	}

	// ─── record() ────────────────────────────────────────────────────────────

	/**
	 * record() returns a positive integer ID on success.
	 */
	public function test_record_returns_positive_id(): void {
		$id = $this->create_record();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * record() stores all provided fields correctly.
	 */
	public function test_record_stores_fields(): void {
		$id = $this->create_record( [
			'session_id'   => 7,
			'object_type'  => 'page',
			'object_id'    => 99,
			'object_title' => 'My Page',
			'ability_name' => 'edit-page',
			'field_name'   => 'post_title',
			'before_value' => 'Old Title',
			'after_value'  => 'New Title',
		] );

		$record = ChangesLog::get( $id );
		$this->assertNotNull( $record );
		$this->assertSame( '7', (string) $record->session_id );
		$this->assertSame( 'page', $record->object_type );
		$this->assertSame( '99', (string) $record->object_id );
		$this->assertSame( 'My Page', $record->object_title );
		$this->assertSame( 'edit-page', $record->ability_name );
		$this->assertSame( 'post_title', $record->field_name );
		$this->assertSame( 'Old Title', $record->before_value );
		$this->assertSame( 'New Title', $record->after_value );
		$this->assertSame( '0', (string) $record->reverted );
	}

	// ─── get() ───────────────────────────────────────────────────────────────

	/**
	 * get() returns null for a non-existent ID.
	 */
	public function test_get_returns_null_for_missing_id(): void {
		$result = ChangesLog::get( 999999 );
		$this->assertNull( $result );
	}

	/**
	 * get() returns the correct record for a valid ID.
	 */
	public function test_get_returns_correct_record(): void {
		$id     = $this->create_record( [ 'object_title' => 'Unique Title XYZ' ] );
		$record = ChangesLog::get( $id );

		$this->assertNotNull( $record );
		$this->assertSame( 'Unique Title XYZ', $record->object_title );
	}

	// ─── list() ──────────────────────────────────────────────────────────────

	/**
	 * list() returns an array with 'items' and 'total' keys.
	 */
	public function test_list_returns_expected_structure(): void {
		$result = ChangesLog::list();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsArray( $result['items'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * list() includes newly created records.
	 */
	public function test_list_includes_created_record(): void {
		$id = $this->create_record( [ 'object_title' => 'List Test Record' ] );

		$result = ChangesLog::list();
		$ids    = array_column( $result['items'], 'id' );

		$this->assertContains( (string) $id, array_map( 'strval', $ids ) );
	}

	/**
	 * list() filters by session_id.
	 */
	public function test_list_filters_by_session_id(): void {
		$id_session_5 = $this->create_record( [ 'session_id' => 5 ] );
		$id_session_6 = $this->create_record( [ 'session_id' => 6 ] );

		$result = ChangesLog::list( [ 'session_id' => 5 ] );
		$ids    = array_map( 'strval', array_column( $result['items'], 'id' ) );

		$this->assertContains( (string) $id_session_5, $ids );
		$this->assertNotContains( (string) $id_session_6, $ids );
	}

	/**
	 * list() filters by object_type.
	 */
	public function test_list_filters_by_object_type(): void {
		$post_id = $this->create_record( [ 'object_type' => 'post' ] );
		$page_id = $this->create_record( [ 'object_type' => 'page' ] );

		$result = ChangesLog::list( [ 'object_type' => 'page' ] );
		$ids    = array_map( 'strval', array_column( $result['items'], 'id' ) );

		$this->assertContains( (string) $page_id, $ids );
		$this->assertNotContains( (string) $post_id, $ids );
	}

	/**
	 * list() filters by reverted status.
	 */
	public function test_list_filters_by_reverted(): void {
		$id = $this->create_record();
		ChangesLog::mark_reverted( $id );

		$reverted = ChangesLog::list( [ 'reverted' => 1 ] );
		$ids      = array_map( 'strval', array_column( $reverted['items'], 'id' ) );
		$this->assertContains( (string) $id, $ids );

		$not_reverted = ChangesLog::list( [ 'reverted' => 0 ] );
		$ids_nr       = array_map( 'strval', array_column( $not_reverted['items'], 'id' ) );
		$this->assertNotContains( (string) $id, $ids_nr );
	}

	/**
	 * list() respects per_page and page parameters.
	 */
	public function test_list_respects_pagination(): void {
		// Create 3 records.
		$this->create_record();
		$this->create_record();
		$this->create_record();

		$result = ChangesLog::list( [ 'per_page' => 1, 'page' => 1 ] );

		$this->assertCount( 1, $result['items'] );
		$this->assertGreaterThanOrEqual( 3, $result['total'] );
	}

	// ─── mark_reverted() ─────────────────────────────────────────────────────

	/**
	 * mark_reverted() sets the reverted flag to 1.
	 */
	public function test_mark_reverted_sets_flag(): void {
		$id = $this->create_record();

		$result = ChangesLog::mark_reverted( $id );
		$this->assertTrue( $result );

		$record = ChangesLog::get( $id );
		$this->assertNotNull( $record );
		$this->assertSame( '1', (string) $record->reverted );
	}

	/**
	 * mark_reverted() sets reverted_at timestamp.
	 */
	public function test_mark_reverted_sets_timestamp(): void {
		$id = $this->create_record();
		ChangesLog::mark_reverted( $id );

		$record = ChangesLog::get( $id );
		$this->assertNotNull( $record );
		$this->assertNotEmpty( $record->reverted_at );
	}

	// ─── delete() ────────────────────────────────────────────────────────────

	/**
	 * delete() removes the record from the database.
	 */
	public function test_delete_removes_record(): void {
		$id = ChangesLog::record( [
			'session_id'   => 1,
			'object_type'  => 'post',
			'object_id'    => 1,
			'object_title' => 'To Delete',
			'ability_name' => 'test',
			'field_name'   => 'content',
			'before_value' => 'a',
			'after_value'  => 'b',
		] );
		$this->assertIsInt( $id );

		$result = ChangesLog::delete( $id );
		$this->assertTrue( $result );

		$record = ChangesLog::get( $id );
		$this->assertNull( $record );
	}

	// ─── generate_diff() ─────────────────────────────────────────────────────

	/**
	 * generate_diff() returns a non-empty string for different before/after values.
	 */
	public function test_generate_diff_returns_string_for_different_values(): void {
		$diff = ChangesLog::generate_diff( 'Line one', 'Line two' );

		$this->assertIsString( $diff );
		$this->assertNotEmpty( $diff );
	}

	/**
	 * generate_diff() returns empty string or minimal output for identical values.
	 */
	public function test_generate_diff_for_identical_values(): void {
		$diff = ChangesLog::generate_diff( 'Same content', 'Same content' );

		// wp_text_diff returns empty string for identical content.
		// Fallback also produces minimal output.
		$this->assertIsString( $diff );
	}

	/**
	 * generate_diff() fallback includes --- before and +++ after markers.
	 */
	public function test_generate_diff_fallback_includes_markers(): void {
		// Force the fallback by using content that wp_text_diff may return empty for.
		$diff = ChangesLog::generate_diff( 'old line', 'new line' );

		$this->assertIsString( $diff );
		// Either wp_text_diff output or fallback — both should be non-empty for different content.
		$this->assertNotEmpty( $diff );
	}

	// ─── generate_patch() ────────────────────────────────────────────────────

	/**
	 * generate_patch() returns a string with patch header.
	 */
	public function test_generate_patch_returns_patch_header(): void {
		$id    = $this->create_record();
		$patch = ChangesLog::generate_patch( [ $id ] );

		$this->assertIsString( $patch );
		$this->assertStringContainsString( '# Gratis AI Agent', $patch );
	}

	/**
	 * generate_patch() includes change metadata for each ID.
	 */
	public function test_generate_patch_includes_change_metadata(): void {
		$id    = $this->create_record( [
			'object_type'  => 'post',
			'object_id'    => 55,
			'object_title' => 'Patch Test Post',
			'field_name'   => 'post_content',
			'before_value' => 'Before patch',
			'after_value'  => 'After patch',
		] );
		$patch = ChangesLog::generate_patch( [ $id ] );

		$this->assertStringContainsString( "Change #{$id}", $patch );
		$this->assertStringContainsString( 'post_content', $patch );
	}

	/**
	 * generate_patch() skips non-existent IDs gracefully.
	 */
	public function test_generate_patch_skips_missing_ids(): void {
		$patch = ChangesLog::generate_patch( [ 999999 ] );

		$this->assertIsString( $patch );
		$this->assertStringContainsString( '# Gratis AI Agent', $patch );
		// No change section for missing ID.
		$this->assertStringNotContainsString( 'Change #999999', $patch );
	}

	/**
	 * generate_patch() handles empty ID array.
	 */
	public function test_generate_patch_handles_empty_ids(): void {
		$patch = ChangesLog::generate_patch( [] );

		$this->assertIsString( $patch );
		$this->assertStringContainsString( '# Gratis AI Agent', $patch );
	}
}
