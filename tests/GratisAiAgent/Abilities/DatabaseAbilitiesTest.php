<?php
/**
 * Test case for DatabaseAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\DatabaseAbilities;
use WP_UnitTestCase;

/**
 * Test DatabaseAbilities handler methods.
 */
class DatabaseAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_db_query with valid SELECT returns results.
	 */
	public function test_handle_db_query_valid_select() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'SELECT 1 AS value',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'query', $result );
		$this->assertArrayHasKey( 'rows', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertIsArray( $result['rows'] );
		$this->assertIsInt( $result['count'] );
	}

	/**
	 * Test handle_db_query returns correct row count.
	 */
	public function test_handle_db_query_row_count() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'SELECT 1 AS value',
		] );

		$this->assertSame( 1, $result['count'] );
		$this->assertCount( 1, $result['rows'] );
	}

	/**
	 * Test handle_db_query replaces {prefix} placeholder.
	 */
	public function test_handle_db_query_prefix_substitution() {
		global $wpdb;

		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'SELECT option_name FROM {prefix}options WHERE option_name = "siteurl" LIMIT 1',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'rows', $result );
		// The query should have been executed (prefix replaced).
		$this->assertStringContainsString( $wpdb->prefix . 'options', $result['query'] );
		$this->assertStringNotContainsString( '{prefix}', $result['query'] );
	}

	/**
	 * Test handle_db_query with real WordPress table.
	 */
	public function test_handle_db_query_real_table() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'SELECT option_name FROM {prefix}options WHERE option_name = "siteurl" LIMIT 1',
		] );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'siteurl', $result['rows'][0]['option_name'] );
	}

	/**
	 * Test handle_db_query rejects non-SELECT queries.
	 */
	public function test_handle_db_query_rejects_insert() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'INSERT INTO wp_options (option_name) VALUES ("test")',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_sql_not_select', $result->get_error_code() );
	}

	/**
	 * Test handle_db_query rejects UPDATE queries.
	 */
	public function test_handle_db_query_rejects_update() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'UPDATE wp_options SET option_value = "x" WHERE option_name = "siteurl"',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_sql_not_select', $result->get_error_code() );
	}

	/**
	 * Test handle_db_query rejects DELETE queries.
	 */
	public function test_handle_db_query_rejects_delete() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'DELETE FROM wp_options WHERE option_name = "test"',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_sql_not_select', $result->get_error_code() );
	}

	/**
	 * Test handle_db_query rejects DROP queries.
	 */
	public function test_handle_db_query_rejects_drop() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'DROP TABLE wp_options',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_sql_not_select', $result->get_error_code() );
	}

	/**
	 * Test handle_db_query with empty SQL returns WP_Error.
	 */
	public function test_handle_db_query_empty_sql() {
		$result = DatabaseAbilities::handle_db_query( [ 'sql' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_empty_sql', $result->get_error_code() );
	}

	/**
	 * Test handle_db_query with missing sql key returns WP_Error.
	 */
	public function test_handle_db_query_missing_sql() {
		$result = DatabaseAbilities::handle_db_query( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_db_query with whitespace-only SQL returns WP_Error.
	 */
	public function test_handle_db_query_whitespace_sql() {
		$result = DatabaseAbilities::handle_db_query( [ 'sql' => '   ' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_empty_sql', $result->get_error_code() );
	}

	/**
	 * Test handle_db_query returns query in result.
	 */
	public function test_handle_db_query_returns_executed_query() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => 'SELECT 1 AS test_col',
		] );

		$this->assertArrayHasKey( 'query', $result );
		$this->assertStringContainsString( 'SELECT', $result['query'] );
	}

	/**
	 * Test handle_db_query with SELECT that returns no rows.
	 */
	public function test_handle_db_query_empty_result() {
		$result = DatabaseAbilities::handle_db_query( [
			'sql' => "SELECT option_name FROM {prefix}options WHERE option_name = 'nonexistent_option_xyz_12345' LIMIT 1",
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['count'] );
		$this->assertIsArray( $result['rows'] );
		$this->assertEmpty( $result['rows'] );
	}
}
