<?php
/**
 * Test case for filesystem path canonicalisation helpers.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core\Filesystem;

use SdAiAgent\Core\Filesystem\PathCanonicalizer;
use WP_UnitTestCase;

/**
 * Tests PathCanonicalizer.
 */
class PathCanonicalizerTest extends WP_UnitTestCase {

	/**
	 * Temporary base directory.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Set up temporary directories.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->base_dir = trailingslashit( sys_get_temp_dir() ) . 'sd-ai-agent-path-canonicalizer';
		$this->remove_dir( $this->base_dir );
		wp_mkdir_p( $this->base_dir . '/content/plugins' );
		wp_mkdir_p( $this->base_dir . '/outside' );
	}

	/**
	 * Tear down temporary directories.
	 */
	public function tearDown(): void {
		$this->remove_dir( $this->base_dir );
		parent::tearDown();
	}

	/**
	 * Paths with parent-dir references are canonicalised before mkdir use.
	 */
	public function test_canonicalize_missing_path_resolves_parent_segments(): void {
		$path = $this->base_dir . '/content/../content/plugins/probe-plugin/includes';

		$result = PathCanonicalizer::canonicalize_missing_path( $path );

		$this->assertIsString( $result );
		$this->assertSame( $this->base_dir . '/content/plugins/probe-plugin/includes', $result );
	}

	/**
	 * Missing child suffixes are appended after resolving the nearest ancestor.
	 */
	public function test_canonicalize_missing_path_inside_appends_missing_suffix(): void {
		$root = $this->base_dir . '/content/plugins';
		$path = $this->base_dir . '/content/../content/plugins/probe-plugin/deep/dir';

		$result = PathCanonicalizer::canonicalize_missing_path_inside( $path, $root );

		$this->assertIsString( $result );
		$this->assertSame( $root . '/probe-plugin/deep/dir', $result );
	}

	/**
	 * Canonicalisation rejects paths that resolve outside the allowed root.
	 */
	public function test_canonicalize_missing_path_inside_rejects_traversal_outside_root(): void {
		$root = $this->base_dir . '/content/plugins';
		$path = $this->base_dir . '/content/plugins/../../outside/probe-plugin';

		$result = PathCanonicalizer::canonicalize_missing_path_inside( $path, $root );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * Write destinations must not follow an existing leaf symbolic link.
	 */
	public function test_canonicalize_write_path_inside_rejects_existing_leaf_symlink(): void {
		$root     = $this->base_dir . '/content/plugins';
		$outside  = $this->base_dir . '/outside/sentinel.php';
		$link     = $root . '/sentinel.php';
		$contents = '<?php /* sentinel */';
		file_put_contents( $outside, $contents );
		$this->assertTrue( symlink( $outside, $link ) );

		$result = PathCanonicalizer::canonicalize_write_path_inside( $link, $root );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
		$this->assertSame( $contents, file_get_contents( $outside ) );
	}

	/**
	 * Dangling leaf links are entries, not missing destinations.
	 */
	public function test_canonicalize_write_path_inside_rejects_dangling_leaf_symlink(): void {
		$root = $this->base_dir . '/content/plugins';
		$link = $root . '/dangling.php';
		$this->assertTrue( symlink( $this->base_dir . '/outside/missing.php', $link ) );

		$result = PathCanonicalizer::canonicalize_write_path_inside( $link, $root );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * Dangling links are rejected when they occur before the final component.
	 */
	public function test_canonicalize_write_path_inside_rejects_dangling_parent_symlink(): void {
		$root = $this->base_dir . '/content/plugins';
		$link = $root . '/dangling-parent';
		$this->assertTrue( symlink( $this->base_dir . '/outside/missing-directory', $link ) );

		$result = PathCanonicalizer::canonicalize_write_path_inside( $link . '/file.php', $root );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * Existing parent links cannot redirect a destination outside the root.
	 */
	public function test_canonicalize_write_path_inside_rejects_parent_symlink_outside_root(): void {
		$root = $this->base_dir . '/content/plugins';
		$link = $root . '/linked-parent';
		$this->assertTrue( symlink( $this->base_dir . '/outside', $link ) );

		$result = PathCanonicalizer::canonicalize_write_path_inside( $link . '/file.php', $root );

		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * Ordinary existing files remain valid write destinations.
	 */
	public function test_canonicalize_write_path_inside_accepts_ordinary_file(): void {
		$root = $this->base_dir . '/content/plugins';
		$file = $root . '/ordinary.php';
		file_put_contents( $file, '<?php /* ordinary */' );

		$result = PathCanonicalizer::canonicalize_write_path_inside( $file, $root );

		$this->assertIsString( $result );
		$this->assertSame( $file, $result );
	}

	/**
	 * Recursively remove a directory using WP_Filesystem_Direct.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		$fs = new \WP_Filesystem_Direct( [] );
		$fs->rmdir( $dir, true );
	}
}
