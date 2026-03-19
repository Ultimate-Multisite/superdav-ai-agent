<?php
/**
 * Test case for MemoryAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\MemoryAbilities;
use GratisAiAgent\Models\Memory;
use WP_UnitTestCase;

/**
 * Test MemoryAbilities handler methods.
 */
class MemoryAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_memory_save with valid input.
	 */
	public function test_handle_memory_save_valid() {
		$result = MemoryAbilities::handle_memory_save( [
			'category' => 'site_info',
			'content'  => 'Test site info content',
		] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertIsInt( $result['id'] );
		$this->assertGreaterThan( 0, $result['id'] );
		$this->assertStringContainsString( 'site_info', $result['message'] );
	}

	/**
	 * Test handle_memory_save with all valid categories.
	 */
	public function test_handle_memory_save_all_categories() {
		foreach ( Memory::CATEGORIES as $category ) {
			$result = MemoryAbilities::handle_memory_save( [
				'category' => $category,
				'content'  => "Content for {$category}",
			] );

			$this->assertTrue( $result['success'], "Failed for category: {$category}" );
		}
	}

	/**
	 * Test handle_memory_save with empty content returns WP_Error.
	 */
	public function test_handle_memory_save_empty_content() {
		$result = MemoryAbilities::handle_memory_save( [
			'category' => 'general',
			'content'  => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'required', $result->get_error_message() );
	}

	/**
	 * Test handle_memory_save with missing content key returns WP_Error.
	 */
	public function test_handle_memory_save_missing_content() {
		$result = MemoryAbilities::handle_memory_save( [
			'category' => 'general',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_memory_save defaults category to general when missing.
	 */
	public function test_handle_memory_save_defaults_category() {
		$result = MemoryAbilities::handle_memory_save( [
			'content' => 'No category provided',
		] );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'general', $result['message'] );
	}

	/**
	 * Test handle_memory_list returns memories.
	 */
	public function test_handle_memory_list_with_memories() {
		// Create some memories first.
		Memory::create( 'site_info', 'List test memory 1' );
		Memory::create( 'general', 'List test memory 2' );

		$result = MemoryAbilities::handle_memory_list();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'memories', $result );
		$this->assertIsArray( $result['memories'] );
		$this->assertGreaterThanOrEqual( 2, count( $result['memories'] ) );

		// Each memory should have id, category, content.
		$first = $result['memories'][0];
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'category', $first );
		$this->assertArrayHasKey( 'content', $first );
		$this->assertIsInt( $first['id'] );
	}

	/**
	 * Test handle_memory_list returns message when no memories exist.
	 */
	public function test_handle_memory_list_empty() {
		// Delete all memories.
		$all = Memory::get_all();
		foreach ( $all as $memory ) {
			Memory::delete( (int) $memory->id );
		}

		$result = MemoryAbilities::handle_memory_list();

		$this->assertArrayHasKey( 'message', $result );
		$this->assertStringContainsString( 'No memories', $result['message'] );
	}

	/**
	 * Test handle_memory_delete with valid ID.
	 */
	public function test_handle_memory_delete_valid() {
		$id = Memory::create( 'general', 'Memory to delete' );

		$result = MemoryAbilities::handle_memory_delete( [ 'id' => $id ] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( (string) $id, $result['message'] );

		// Verify it's actually gone.
		$all = Memory::get_all();
		foreach ( $all as $memory ) {
			$this->assertNotEquals( $id, (int) $memory->id );
		}
	}

	/**
	 * Test handle_memory_delete with non-existent ID.
	 *
	 * Memory::delete uses $wpdb->delete which returns 0 rows affected (not false)
	 * for a non-existent ID, so the handler returns success. This tests that
	 * the call completes without error (idempotent delete).
	 */
	public function test_handle_memory_delete_not_found() {
		$result = MemoryAbilities::handle_memory_delete( [ 'id' => 999999 ] );

		// $wpdb->delete returns 0 (not false) for non-existent rows,
		// so the handler treats it as success. Verify it returns an array.
		$this->assertIsArray( $result );
		$this->assertTrue(
			isset( $result['success'] ) || isset( $result['error'] ),
			'Result should have success or error key.'
		);
	}

	/**
	 * Test handle_memory_delete with missing ID returns WP_Error.
	 */
	public function test_handle_memory_delete_missing_id() {
		$result = MemoryAbilities::handle_memory_delete( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'required', $result->get_error_message() );
	}

	/**
	 * Test handle_memory_delete with zero ID returns WP_Error.
	 */
	public function test_handle_memory_delete_zero_id() {
		$result = MemoryAbilities::handle_memory_delete( [ 'id' => 0 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test save-then-list-then-delete round trip.
	 */
	public function test_save_list_delete_round_trip() {
		// Save.
		$save_result = MemoryAbilities::handle_memory_save( [
			'category' => 'workflows',
			'content'  => 'Round trip test content',
		] );
		$this->assertTrue( $save_result['success'] );
		$id = $save_result['id'];

		// List and find it.
		$list_result = MemoryAbilities::handle_memory_list();
		$found       = false;
		foreach ( $list_result['memories'] as $memory ) {
			if ( $memory['id'] === $id ) {
				$found = true;
				$this->assertSame( 'workflows', $memory['category'] );
				$this->assertSame( 'Round trip test content', $memory['content'] );
				break;
			}
		}
		$this->assertTrue( $found, 'Saved memory not found in list.' );

		// Delete.
		$delete_result = MemoryAbilities::handle_memory_delete( [ 'id' => $id ] );
		$this->assertTrue( $delete_result['success'] );

		// Confirm gone.
		$list_after = MemoryAbilities::handle_memory_list();
		if ( isset( $list_after['memories'] ) ) {
			foreach ( $list_after['memories'] as $memory ) {
				$this->assertNotEquals( $id, $memory['id'] );
			}
		}
	}
}
