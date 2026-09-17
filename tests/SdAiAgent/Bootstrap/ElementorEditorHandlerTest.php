<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Bootstrap;

use SdAiAgent\Bootstrap\ElementorEditorHandler;
use WP_UnitTestCase;

/**
 * Tests for Elementor's optional public editor MCP bridge script registration.
 */
class ElementorEditorHandlerTest extends WP_UnitTestCase {

	private const SCRIPT_HANDLE = 'elementor-v2-sd-ai-agent-elementor-mcp';
	private const ELEMENTOR_MCP_HANDLE = 'elementor-v2-editor-mcp';

	private int $admin_id;
	private int $subscriber_id;
	private string $build_dir;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->build_dir     = trailingslashit( sys_get_temp_dir() ) . 'sd-ai-agent-elementor-handler-' . uniqid();
		wp_mkdir_p( $this->build_dir );
		add_filter( 'sd_ai_agent_build_dir', array( $this, 'filter_build_dir' ) );
	}

	public function tear_down(): void {
		remove_filter( 'sd_ai_agent_build_dir', array( $this, 'filter_build_dir' ) );
		wp_dequeue_script( self::SCRIPT_HANDLE );
		wp_deregister_script( self::SCRIPT_HANDLE );
		wp_deregister_script( self::ELEMENTOR_MCP_HANDLE );
		wp_set_current_user( 0 );

		$asset_path = $this->build_dir . '/elementor-editor-mcp.asset.php';
		if ( file_exists( $asset_path ) ) {
			unlink( $asset_path );
		}
		if ( is_dir( $this->build_dir ) ) {
			rmdir( $this->build_dir );
		}

		parent::tear_down();
	}

	/**
	 * Return the isolated build directory used to test generated asset metadata.
	 *
	 * @return string
	 */
	public function filter_build_dir(): string {
		return $this->build_dir;
	}

	public function test_registers_the_bridge_after_elementor_registers_editor_mcp(): void {
		wp_set_current_user( $this->admin_id );
		wp_register_script( self::ELEMENTOR_MCP_HANDLE, 'https://example.test/editor-mcp.js' );
		$this->write_asset_fixture();

		$handler = new ElementorEditorHandler();
		$handler->register_editor_package();

		$this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) );
		$registered = wp_scripts()->registered[ self::SCRIPT_HANDLE ];
		$this->assertContains( 'wp-data', $registered->deps );
		$this->assertContains( self::ELEMENTOR_MCP_HANDLE, $registered->deps );
		$this->assertSame( 'fixture-version', $registered->ver );

		$handler->enqueue_editor_package();
		$this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
	}

	public function test_skips_registration_without_elementor_or_administrator_access(): void {
		$this->write_asset_fixture();
		$handler = new ElementorEditorHandler();

		wp_set_current_user( $this->admin_id );
		$handler->register_editor_package();
		$this->assertFalse( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) );

		wp_register_script( self::ELEMENTOR_MCP_HANDLE, 'https://example.test/editor-mcp.js' );
		wp_set_current_user( $this->subscriber_id );
		$handler->register_editor_package();
		$this->assertFalse( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) );
	}

	private function write_asset_fixture(): void {
		$contents = <<<'PHP'
<?php
return array(
	'dependencies' => array( 'wp-data' ),
	'version'      => 'fixture-version',
);
PHP;

		$this->assertNotFalse(
			file_put_contents( $this->build_dir . '/elementor-editor-mcp.asset.php', $contents )
		);
	}
}
