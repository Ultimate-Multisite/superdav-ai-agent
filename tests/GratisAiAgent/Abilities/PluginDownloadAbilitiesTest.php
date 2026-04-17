<?php

declare(strict_types=1);
/**
 * Test case for PluginDownloadAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\PluginDownloadAbilities;
use WP_UnitTestCase;

/**
 * Test PluginDownloadAbilities functionality.
 */
class PluginDownloadAbilitiesTest extends WP_UnitTestCase {

	// ── handle_list_modified_plugins ──────────────────────────────────────

	/**
	 * handle_list_modified_plugins() returns array with plugins and count keys.
	 */
	public function test_handle_list_modified_plugins_returns_expected_shape(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$result = PluginDownloadAbilities::handle_list_modified_plugins();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'plugins', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertIsArray( $result['plugins'] );
		$this->assertIsInt( $result['count'] );
	}

	/**
	 * handle_list_modified_plugins() returns empty plugins when no modifications.
	 */
	public function test_handle_list_modified_plugins_returns_empty_when_no_modifications(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$result = PluginDownloadAbilities::handle_list_modified_plugins();

		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['count'] );
		$this->assertEmpty( $result['plugins'] );
	}

	// ── handle_get_plugin_download_url ────────────────────────────────────

	/**
	 * handle_get_plugin_download_url() returns WP_Error for empty slug.
	 */
	public function test_handle_get_plugin_download_url_returns_wp_error_for_empty_slug(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$result = PluginDownloadAbilities::handle_get_plugin_download_url( [ 'plugin_slug' => '' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * handle_get_plugin_download_url() returns WP_Error for missing slug.
	 */
	public function test_handle_get_plugin_download_url_returns_wp_error_for_missing_slug(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$result = PluginDownloadAbilities::handle_get_plugin_download_url( [] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * handle_get_plugin_download_url() returns WP_Error when plugin directory does not exist.
	 */
	public function test_handle_get_plugin_download_url_returns_wp_error_for_nonexistent_plugin(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$result = PluginDownloadAbilities::handle_get_plugin_download_url( [
			'plugin_slug' => 'nonexistent-plugin-xyz-12345',
		] );

		$this->assertWPError( $result );
		$this->assertSame( 'gratis_ai_agent_plugin_not_found', $result->get_error_code() );
	}
}
