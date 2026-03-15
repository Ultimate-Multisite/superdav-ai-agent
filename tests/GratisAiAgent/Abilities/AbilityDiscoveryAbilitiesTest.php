<?php
/**
 * Test case for AbilityDiscoveryAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\AbilityDiscoveryAbilities;
use WP_UnitTestCase;

/**
 * Test AbilityDiscoveryAbilities handler methods.
 */
class AbilityDiscoveryAbilitiesTest extends WP_UnitTestCase {

	// ─── handle_list_abilities ────────────────────────────────────

	/**
	 * Test handle_list_abilities returns array.
	 */
	public function test_handle_list_abilities_returns_array() {
		$result = AbilityDiscoveryAbilities::handle_list_abilities( [] );

		$this->assertIsArray( $result );
	}

	/**
	 * Test handle_list_abilities when Abilities API is unavailable returns WP_Error.
	 *
	 * In the test environment, wp_get_abilities() may not be available
	 * (requires WordPress 6.9+). The handler should return WP_Error gracefully.
	 */
	public function test_handle_list_abilities_api_unavailable_or_available() {
		$result = AbilityDiscoveryAbilities::handle_list_abilities( [] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be array or WP_Error.'
		);

		if ( is_array( $result ) ) {
			// If API is available, should have abilities and count.
			$this->assertArrayHasKey( 'abilities', $result );
			$this->assertArrayHasKey( 'count', $result );
			$this->assertIsArray( $result['abilities'] );
			$this->assertIsInt( $result['count'] );
		} else {
			// If API unavailable, should have specific error code.
			$this->assertSame( 'abilities_api_unavailable', $result->get_error_code() );
		}
	}

	/**
	 * Test handle_list_abilities with category filter.
	 */
	public function test_handle_list_abilities_with_category_filter() {
		$result = AbilityDiscoveryAbilities::handle_list_abilities( [
			'category' => 'gratis-ai-agent',
		] );

		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be array or WP_Error.'
		);

		if ( is_array( $result ) && isset( $result['abilities'] ) ) {
			// All returned abilities should be in the requested category.
			foreach ( $result['abilities'] as $ability ) {
				$this->assertSame( 'gratis-ai-agent', $ability['category'] );
			}
		}
	}

	/**
	 * Test handle_list_abilities count matches abilities array length.
	 */
	public function test_handle_list_abilities_count_matches() {
		$result = AbilityDiscoveryAbilities::handle_list_abilities( [] );

		if ( is_array( $result ) && isset( $result['abilities'] ) ) {
			$this->assertSame( count( $result['abilities'] ), $result['count'] );
		}
	}

	// ─── handle_get_ability ───────────────────────────────────────

	/**
	 * Test handle_get_ability with empty ability ID returns WP_Error.
	 */
	public function test_handle_get_ability_empty_id() {
		$result = AbilityDiscoveryAbilities::handle_get_ability( [
			'ability' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test handle_get_ability with missing ability ID returns WP_Error.
	 */
	public function test_handle_get_ability_missing_id() {
		$result = AbilityDiscoveryAbilities::handle_get_ability( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_get_ability with non-existent ability returns WP_Error.
	 *
	 * WordPress 6.9 fires _doing_it_wrong when looking up a non-existent ability.
	 * We suppress this expected notice so the test can assert the WP_Error result.
	 */
	public function test_handle_get_ability_not_found() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$result = AbilityDiscoveryAbilities::handle_get_ability( [
			'ability' => 'nonexistent/ability-xyz-12345',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		// Either ability_not_found or abilities_api_unavailable.
		$this->assertContains(
			$result->get_error_code(),
			[ 'ability_not_found', 'abilities_api_unavailable' ]
		);
	}

	// ─── handle_execute_ability ───────────────────────────────────

	/**
	 * Test handle_execute_ability with empty ability ID returns WP_Error.
	 */
	public function test_handle_execute_ability_empty_id() {
		$result = AbilityDiscoveryAbilities::handle_execute_ability( [
			'ability' => '',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test handle_execute_ability with missing ability ID returns WP_Error.
	 */
	public function test_handle_execute_ability_missing_id() {
		$result = AbilityDiscoveryAbilities::handle_execute_ability( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_execute_ability with non-existent ability returns WP_Error.
	 *
	 * WordPress 6.9 fires _doing_it_wrong when looking up a non-existent ability.
	 * We suppress this expected notice so the test can assert the WP_Error result.
	 */
	public function test_handle_execute_ability_not_found() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$result = AbilityDiscoveryAbilities::handle_execute_ability( [
			'ability' => 'nonexistent/ability-xyz-12345',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertContains(
			$result->get_error_code(),
			[ 'ability_not_found', 'abilities_api_unavailable' ]
		);
	}
}
