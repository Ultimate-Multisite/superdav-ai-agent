<?php
/**
 * Test case for Database class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\Database;
use WP_UnitTestCase;

/**
 * Test Database functionality.
 */
class DatabaseTest extends WP_UnitTestCase {

	/**
	 * Test table_name returns correct table name.
	 */
	public function test_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_sessions';
		$this->assertSame( $expected, Database::table_name() );
	}

	/**
	 * Test usage_table_name returns correct table name.
	 */
	public function test_usage_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_usage';
		$this->assertSame( $expected, Database::usage_table_name() );
	}

	/**
	 * Test memories_table_name returns correct table name.
	 */
	public function test_memories_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_memories';
		$this->assertSame( $expected, Database::memories_table_name() );
	}

	/**
	 * Test skills_table_name returns correct table name.
	 */
	public function test_skills_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_skills';
		$this->assertSame( $expected, Database::skills_table_name() );
	}

	/**
	 * Test custom_tools_table_name returns correct table name.
	 */
	public function test_custom_tools_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_custom_tools';
		$this->assertSame( $expected, Database::custom_tools_table_name() );
	}

	/**
	 * Test automations_table_name returns correct table name.
	 */
	public function test_automations_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_automations';
		$this->assertSame( $expected, Database::automations_table_name() );
	}

	/**
	 * Test automation_logs_table_name returns correct table name.
	 */
	public function test_automation_logs_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_automation_logs';
		$this->assertSame( $expected, Database::automation_logs_table_name() );
	}

	/**
	 * Test event_automations_table_name returns correct table name.
	 */
	public function test_event_automations_table_name() {
		global $wpdb;
		$expected = $wpdb->prefix . 'gratis_ai_agent_event_automations';
		$this->assertSame( $expected, Database::event_automations_table_name() );
	}

	/**
	 * Test DB_VERSION constant exists.
	 */
	public function test_db_version_constant() {
		$this->assertNotEmpty( Database::DB_VERSION );
		$this->assertIsString( Database::DB_VERSION );
	}

	/**
	 * Test DB_VERSION_OPTION constant exists.
	 */
	public function test_db_version_option_constant() {
		$this->assertSame( 'gratis_ai_agent_db_version', Database::DB_VERSION_OPTION );
	}

	/**
	 * Test create_session creates a session and returns ID.
	 */
	public function test_create_session() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id'     => $user_id,
			'title'       => 'Test Session',
			'provider_id' => 'anthropic',
			'model_id'    => 'claude-sonnet-4',
		] );

		$this->assertIsInt( $session_id );
		$this->assertGreaterThan( 0, $session_id );
	}

	/**
	 * Test get_session returns session data.
	 */
	public function test_get_session() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id'     => $user_id,
			'title'       => 'Get Test Session',
			'provider_id' => 'openai',
			'model_id'    => 'gpt-4o',
		] );

		$session = Database::get_session( $session_id );

		$this->assertNotNull( $session );
		$this->assertSame( 'Get Test Session', $session->title );
		$this->assertSame( 'openai', $session->provider_id );
		$this->assertSame( 'gpt-4o', $session->model_id );
	}

	/**
	 * Test get_session returns null for non-existent session.
	 */
	public function test_get_session_not_found() {
		$session = Database::get_session( 999999 );
		$this->assertNull( $session );
	}

	/**
	 * Test update_session updates fields.
	 */
	public function test_update_session() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'Original Title',
		] );

		$result = Database::update_session( $session_id, [
			'title' => 'Updated Title',
		] );

		$this->assertTrue( $result );

		$session = Database::get_session( $session_id );
		$this->assertSame( 'Updated Title', $session->title );
	}

	/**
	 * Test delete_session removes session.
	 */
	public function test_delete_session() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'To Delete',
		] );

		$result = Database::delete_session( $session_id );
		$this->assertTrue( $result );

		$session = Database::get_session( $session_id );
		$this->assertNull( $session );
	}

	/**
	 * Test list_sessions returns sessions for user.
	 */
	public function test_list_sessions() {
		$user_id = self::factory()->user->create();

		Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'Session 1',
		] );

		Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'Session 2',
		] );

		$sessions = Database::list_sessions( $user_id );

		$this->assertIsArray( $sessions );
		$this->assertGreaterThanOrEqual( 2, count( $sessions ) );
	}

	/**
	 * Test list_sessions filters by status.
	 */
	public function test_list_sessions_status_filter() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'Active Session',
		] );

		$this->assertNotFalse( $session_id, 'Session should be created successfully.' );

		// Move to trash.
		$updated = Database::update_session( $session_id, [ 'status' => 'trash' ] );
		$this->assertTrue( $updated, 'Session should be updated successfully.' );

		$active = Database::list_sessions( $user_id, [ 'status' => 'active' ] );
		$trashed = Database::list_sessions( $user_id, [ 'status' => 'trash' ] );

		// The trashed session should appear in trash list.
		$trashed_ids = array_column( $trashed, 'id' );
		$this->assertContains( (int) $session_id, array_map( 'intval', $trashed_ids ) );
	}

	/**
	 * Test log_usage records usage data.
	 */
	public function test_log_usage() {
		$user_id = self::factory()->user->create();

		$usage_id = Database::log_usage( [
			'user_id'           => $user_id,
			'session_id'        => 0,
			'provider_id'       => 'anthropic',
			'model_id'          => 'claude-sonnet-4',
			'prompt_tokens'     => 1000,
			'completion_tokens' => 500,
			'cost_usd'          => 0.015,
		] );

		$this->assertIsInt( $usage_id );
		$this->assertGreaterThan( 0, $usage_id );
	}

	/**
	 * Test get_usage_summary returns summary data.
	 */
	public function test_get_usage_summary() {
		$summary = Database::get_usage_summary();

		$this->assertIsArray( $summary );
		$this->assertArrayHasKey( 'totals', $summary );
		$this->assertArrayHasKey( 'by_model', $summary );
	}

	/**
	 * Test update_session_tokens accumulates tokens.
	 */
	public function test_update_session_tokens() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'Token Test',
		] );

		Database::update_session_tokens( $session_id, 100, 50 );
		$session = Database::get_session( $session_id );
		$this->assertSame( '100', $session->prompt_tokens );
		$this->assertSame( '50', $session->completion_tokens );

		Database::update_session_tokens( $session_id, 200, 100 );
		$session = Database::get_session( $session_id );
		$this->assertSame( '300', $session->prompt_tokens );
		$this->assertSame( '150', $session->completion_tokens );
	}

	/**
	 * Test append_to_session adds messages.
	 */
	public function test_append_to_session() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'Append Test',
		] );

		$result = Database::append_to_session( $session_id, [
			[ 'role' => 'user', 'content' => 'Hello' ],
		] );

		$this->assertTrue( $result );

		$session = Database::get_session( $session_id );
		$messages = json_decode( $session->messages, true );

		$this->assertCount( 1, $messages );
		$this->assertSame( 'user', $messages[0]['role'] );
	}

	/**
	 * Test bulk_update_sessions updates multiple sessions.
	 */
	public function test_bulk_update_sessions() {
		$user_id = self::factory()->user->create();

		$session1 = Database::create_session( [ 'user_id' => $user_id, 'title' => 'Bulk 1' ] );
		$session2 = Database::create_session( [ 'user_id' => $user_id, 'title' => 'Bulk 2' ] );

		$count = Database::bulk_update_sessions(
			[ $session1, $session2 ],
			$user_id,
			[ 'status' => 'trash' ]
		);

		$this->assertSame( 2, $count );

		$s1 = Database::get_session( $session1 );
		$s2 = Database::get_session( $session2 );

		$this->assertSame( 'trash', $s1->status );
		$this->assertSame( 'trash', $s2->status );
	}

	/**
	 * Test empty_trash deletes trashed sessions.
	 */
	public function test_empty_trash() {
		$user_id = self::factory()->user->create();

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'To Trash',
		] );

		Database::update_session( $session_id, [ 'status' => 'trash' ] );

		$deleted = Database::empty_trash( $user_id );

		$this->assertGreaterThanOrEqual( 1, $deleted );

		$session = Database::get_session( $session_id );
		$this->assertNull( $session );
	}

	/**
	 * Test list_folders returns distinct folders.
	 */
	public function test_list_folders() {
		$user_id = self::factory()->user->create();

		Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'In Folder',
		] );

		$session_id = Database::create_session( [
			'user_id' => $user_id,
			'title'   => 'In Work Folder',
		] );

		Database::update_session( $session_id, [ 'folder' => 'work' ] );

		$folders = Database::list_folders( $user_id );

		$this->assertIsArray( $folders );
		$this->assertContains( 'work', $folders );
	}
}
