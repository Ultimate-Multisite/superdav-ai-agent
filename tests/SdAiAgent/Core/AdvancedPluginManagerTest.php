<?php
/**
 * Tests for the Advanced companion version status.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\AdvancedPluginManager;
use WP_Error;
use WP_UnitTestCase;

final class AdvancedPluginManagerTest extends WP_UnitTestCase {

	private string $plugin_file;

	public function set_up(): void {
		parent::set_up();

		$this->plugin_file = WP_PLUGIN_DIR . '/' . AdvancedPluginManager::PLUGIN_BASENAME;
		wp_mkdir_p( dirname( $this->plugin_file ) );
		$this->write_plugin( '1.20.0' );
		add_filter( 'sd_ai_agent_advanced_plugin_bundled_copy', '__return_false' );
	}

	public function tear_down(): void {
		remove_all_filters( 'sd_ai_agent_advanced_plugin_bundled_copy' );
		delete_option( AdvancedPluginManager::DIAGNOSTIC_OPTION );
		delete_site_transient( 'update_plugins' );
		wp_delete_file( $this->plugin_file );
		rmdir( dirname( $this->plugin_file ) );
		parent::tear_down();
	}

	public function test_status_reports_version_drift_from_wordpress_update_metadata(): void {
		$this->set_update_metadata( '1.23.0' );

		$status = ( new AdvancedPluginManager() )->get_status();

		$this->assertSame( '1.20.0', $status['version'] );
		$this->assertSame( '1.23.0', $status['latest_version'] );
		$this->assertFalse( $status['compatible'] );
		$this->assertTrue( $status['update_available'] );
		$this->assertSame( 'incompatible', $status['status'] );
		$this->assertNull( $status['last_error_code'] );
	}

	public function test_status_reports_unavailable_update_metadata(): void {
		$this->write_plugin( (string) SD_AI_AGENT_VERSION );

		$status = ( new AdvancedPluginManager() )->get_status();

		$this->assertSame( 'metadata_unavailable', $status['status'] );
		$this->assertNull( $status['latest_version'] );
	}

	public function test_status_reports_incompatible_without_update_metadata(): void {
		$status = ( new AdvancedPluginManager() )->get_status();

		$this->assertFalse( $status['compatible'] );
		$this->assertSame( 'incompatible', $status['status'] );
	}

	public function test_status_reports_a_previous_automatic_update_failure(): void {
		$this->set_update_metadata( '1.23.0' );
		update_option( AdvancedPluginManager::DIAGNOSTIC_OPTION, 'download_failed', false );

		$status = ( new AdvancedPluginManager() )->get_status();

		$this->assertSame( 'update_failed', $status['status'] );
		$this->assertSame( 'download_failed', $status['last_error_code'] );
	}

	public function test_status_reports_current_when_versions_match(): void {
		$core_version = (string) SD_AI_AGENT_VERSION;
		$this->write_plugin( $core_version );
		$this->set_update_metadata( $core_version );

		$status = ( new AdvancedPluginManager() )->get_status();

		$this->assertTrue( $status['compatible'] );
		$this->assertFalse( $status['update_available'] );
		$this->assertSame( 'current', $status['status'] );
	}

	public function test_status_reports_an_available_compatible_update(): void {
		$core_version = (string) SD_AI_AGENT_VERSION;
		$this->write_plugin( $core_version );
		$this->set_update_metadata( $core_version . '.1' );

		$status = ( new AdvancedPluginManager() )->get_status();

		$this->assertTrue( $status['compatible'] );
		$this->assertTrue( $status['update_available'] );
		$this->assertSame( 'update_available', $status['status'] );
	}

	public function test_automatic_update_failure_persists_only_its_error_code(): void {
		$manager = new AdvancedPluginManager();
		$manager->record_automatic_update_result(
			true,
			'plugin',
			(object) array( 'plugin' => AdvancedPluginManager::PLUGIN_BASENAME ),
			new WP_Error( 'download_failed', 'Raw update failure details must not be exposed.' )
		);

		$diagnostic = get_option( AdvancedPluginManager::DIAGNOSTIC_OPTION, '' );

		$this->assertSame( 'download_failed', $diagnostic );
		$this->assertStringNotContainsString( 'Raw update failure details', (string) $diagnostic );
	}

	public function test_install_command_is_rejected_for_the_repository_bundled_copy(): void {
		add_filter( 'sd_ai_agent_advanced_plugin_bundled_copy', '__return_true' );

		$result = ( new AdvancedPluginManager() )->install_from_command( $this->command( 'bundled-nonce-0000000001' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_advanced_bundled', $result->get_error_code() );
	}

	public function test_install_command_nonce_is_single_use(): void {
		$nonce = 'replayed-nonce-0000000001';
		set_transient( 'sd_ai_agent_adv_nonce_' . substr( hash( 'sha256', $nonce ), 0, 40 ), 1, 300 );

		$result = ( new AdvancedPluginManager() )->install_from_command( $this->command( $nonce ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_install_command_replayed', $result->get_error_code() );
	}

	public function test_status_reports_whether_the_current_user_can_install(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site administrators can install plugins; multisite needs a super admin.' );
		}

		wp_set_current_user( 0 );
		$this->assertFalse( ( new AdvancedPluginManager() )->get_status()['can_install'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( ( new AdvancedPluginManager() )->get_status()['can_install'] );
	}

	/**
	 * @return array{version:string,download_url:string,package_sha256:string,nonce:string,expires_at:int}
	 */
	private function command( string $nonce ): array {
		return array(
			'version'        => '1.25.0',
			'download_url'   => 'https://sdaiagent.com/?sdai_update_action=download&sdai_update_slug=superdav-ai-agent-advanced',
			'package_sha256' => str_repeat( 'a', 64 ),
			'nonce'          => $nonce,
			'expires_at'     => time() + 300,
		);
	}

	private function set_update_metadata( string $version ): void {
		$updates           = new \stdClass();
		$updates->last_checked = time();
		$updates->response = array(
			AdvancedPluginManager::PLUGIN_BASENAME => (object) array( 'new_version' => $version ),
		);
		set_site_transient( 'update_plugins', $updates );
	}

	private function write_plugin( string $version ): void {
		file_put_contents(
			$this->plugin_file,
			"<?php\n/*\nPlugin Name: SD AI Agent Advanced\nVersion: {$version}\n*/\n"
		);
	}
}
