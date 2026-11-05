<?php

declare(strict_types=1);
/**
 * Test case for private benchmark log storage.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Benchmark;

use ReflectionMethod;
use SdAiAgent\CLI\BenchmarkCommand;
use WP_Error;
use WP_UnitTestCase;

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	class BenchmarkWpCliCommandStub {}
	class_alias( BenchmarkWpCliCommandStub::class, 'WP_CLI_Command' );
}

require_once dirname( __DIR__, 3 ) . '/advanced-plugin/includes/CLI/BenchmarkCommand.php';

/** Tests benchmark logs stay private and credentials are scrubbed. */
class BenchmarkCommandTest extends WP_UnitTestCase {

	private BenchmarkCommand $command;

	private string $private_root;

	private string $uploads_root;

	public function setUp(): void {
		parent::setUp();
		$this->private_root = sys_get_temp_dir() . '/sd-ai-agent-benchmark-private-' . wp_generate_uuid4();
		$this->uploads_root = sys_get_temp_dir() . '/sd-ai-agent-benchmark-public-' . wp_generate_uuid4();
		wp_mkdir_p( $this->uploads_root );
		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		add_filter( 'sd_ai_agent_benchmark_private_log_dir', array( $this, 'filter_private_root' ) );
		$this->command = new BenchmarkCommand();
	}

	public function tearDown(): void {
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		remove_filter( 'sd_ai_agent_benchmark_private_log_dir', array( $this, 'filter_private_root' ) );
		$this->remove_directory( $this->private_root );
		$this->remove_directory( $this->uploads_root );
		parent::tearDown();
	}

	/** @param array<string, mixed> $uploads Upload directory data. */
	public function filter_upload_dir( array $uploads ): array {
		$uploads['basedir'] = $this->uploads_root;
		$uploads['path']    = $this->uploads_root;
		return $uploads;
	}

	public function filter_private_root(): string {
		return $this->private_root;
	}

	public function test_default_directory_is_private_and_owner_only(): void {
		$result = $this->invoke( 'prepare_log_directory', array( '' ) );

		$this->assertSame( $this->private_root, $result );
		$this->assertStringNotContainsString( $this->uploads_root, (string) $result );
		$this->assertSame( 0, fileperms( $this->private_root ) & 0077 );
	}

	public function test_unfiltered_default_uses_user_state_storage(): void {
		$result = $this->invoke( 'default_log_directory', array( ABSPATH ) );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '/sd-ai-agent/benchmark-logs-', $result );
		$this->assertStringNotContainsString( untrailingslashit( ABSPATH ) . '/', $result );
	}

	public function test_explicit_private_directory_is_preserved(): void {
		$explicit = sys_get_temp_dir() . '/sd-ai-agent-benchmark-explicit-' . wp_generate_uuid4();
		$result   = $this->invoke( 'prepare_log_directory', array( $explicit ) );

		$this->assertSame( $explicit, $result );
		$this->assertSame( 0, fileperms( $explicit ) & 0077 );
		$this->remove_directory( $explicit );
	}

	public function test_public_upload_directory_is_rejected(): void {
		$result = $this->invoke( 'prepare_log_directory', array( $this->uploads_root . '/benchmark-logs' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_benchmark_log_path_public', $result->get_error_code() );
	}

	public function test_relative_directory_is_rejected(): void {
		$result = $this->invoke( 'prepare_log_directory', array( 'benchmark-logs' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_benchmark_log_path_relative', $result->get_error_code() );
	}

	public function test_document_root_is_inferred_for_subdirectory_install(): void {
		$result = $this->invoke( 'infer_document_root', array( '/var/www/html/wp', 'https://example.test/wp/' ) );

		$this->assertSame( '/var/www/html', $result );
	}

	public function test_symlinked_directory_is_rejected(): void {
		$target = sys_get_temp_dir() . '/sd-ai-agent-benchmark-target-' . wp_generate_uuid4();
		$link   = sys_get_temp_dir() . '/sd-ai-agent-benchmark-link-' . wp_generate_uuid4();
		wp_mkdir_p( $target );
		chmod( $target, 0700 );
		if ( ! symlink( $target, $link ) ) {
			$this->remove_directory( $target );
			$this->markTestSkipped( 'Symlinks are unavailable on this filesystem.' );
		}

		$result = $this->invoke( 'prepare_log_directory', array( $link ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_benchmark_log_path_symlink', $result->get_error_code() );
		unlink( $link );
		$this->remove_directory( $target );
	}

	public function test_existing_directory_with_public_permissions_is_rejected(): void {
		$explicit = sys_get_temp_dir() . '/sd-ai-agent-benchmark-insecure-' . wp_generate_uuid4();
		wp_mkdir_p( $explicit );
		chmod( $explicit, 0755 );

		$result = $this->invoke( 'prepare_log_directory', array( $explicit ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_benchmark_log_permissions', $result->get_error_code() );
		$this->remove_directory( $explicit );
	}

	public function test_json_logs_redact_credentials_and_use_owner_only_permissions(): void {
		$this->invoke( 'prepare_log_directory', array( '' ) );
		$path   = $this->private_root . '/question.json';
		$result = $this->invoke(
			'write_json_log',
			array(
				$path,
				array(
					'api_key'     => 'synthetic-test-secret',
					'token_usage' => array( 'input' => 10 ),
					'tool_output' => 'Authorization: Bearer synthetic-token-value',
					'json_output' => '{"refresh_token":"synthetic-json-token"}',
				),
			)
		);

		$this->assertTrue( $result );
		$contents = file_get_contents( $path );
		$this->assertIsString( $contents );
		$this->assertStringNotContainsString( 'synthetic-test-secret', $contents );
		$this->assertStringNotContainsString( 'synthetic-token-value', $contents );
		$this->assertStringNotContainsString( 'synthetic-json-token', $contents );
		$this->assertStringContainsString( 'token_usage', $contents );
		$this->assertSame( 0, fileperms( $path ) & 0077 );
	}

	/**
	 * @param string             $method Method name.
	 * @param array<int, mixed>  $args   Method arguments.
	 * @return mixed
	 */
	private function invoke( string $method, array $args ): mixed {
		$reflection = new ReflectionMethod( BenchmarkCommand::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->command, $args );
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$files = scandir( $directory );
		if ( false !== $files ) {
			foreach ( $files as $file ) {
				if ( '.' !== $file && '..' !== $file ) {
					$path = $directory . '/' . $file;
					is_dir( $path ) ? $this->remove_directory( $path ) : unlink( $path );
				}
			}
		}
		rmdir( $directory );
	}
}
