<?php

declare(strict_types=1);
/**
 * Test case for benchmark command fixture cleanup.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Benchmark;

use ReflectionMethod;
use RuntimeException;
use SdAiAgent\CLI\BenchmarkCommand;
use WP_UnitTestCase;

/**
 * Minimal WP-CLI command base used when PHPUnit is not bootstrapped by WP-CLI.
 */
class BenchmarkWpCliCommandStub {
}

/**
 * Minimal WP-CLI output facade for cleanup warnings.
 */
class BenchmarkWpCliStub {

	/**
	 * Accept a warning emitted by a fail-closed cleanup path.
	 *
	 * @param string $message Warning message.
	 */
	public static function warning( string $message ): void {
		unset( $message );
	}
}

if ( ! class_exists( '\WP_CLI_Command' ) ) {
	class_alias( BenchmarkWpCliCommandStub::class, '\WP_CLI_Command' );
}

if ( ! class_exists( '\WP_CLI' ) ) {
	class_alias( BenchmarkWpCliStub::class, '\WP_CLI' );
}

require_once dirname( __DIR__, 3 ) . '/advanced-plugin/includes/CLI/BenchmarkCommand.php';

/**
 * Testable command with deterministic agent-loop outcomes.
 */
class TestableBenchmarkCommand extends BenchmarkCommand {

	/**
	 * Agent-loop behavior for the next question.
	 *
	 * @var string
	 */
	public string $agent_behavior = 'provider-error';

	/**
	 * Number of agent-loop invocations.
	 *
	 * @var int
	 */
	public int $agent_calls = 0;

	/** Prompt passed to the synthetic loop. */
	public string $last_prompt = '';

	/**
	 * Run one question through the protected command method.
	 *
	 * @param array<string, mixed> $question Question definition.
	 * @param bool                 $keep     Whether to keep the plugin fixture.
	 * @return array<string, mixed>
	 */
	public function run_test_question( array $question, bool $keep = false ): array {
		return $this->run_question( $question, 'test-provider', 'test-model', $keep );
	}

	/**
	 * Return a deterministic provider error or throw before assertions.
	 *
	 * @param string $prompt      Prompt text.
	 * @param string $provider_id Provider ID.
	 * @param string $model_id    Model ID.
	 * @param int    $max_turns   Maximum turns.
	 * @return array<string, mixed>
	 */
	protected function run_agent_loop( string $prompt, string $provider_id, string $model_id, int $max_turns ): array {
		unset( $provider_id, $model_id, $max_turns );
		++$this->agent_calls;
		$this->last_prompt = $prompt;

		if ( 'exception' === $this->agent_behavior ) {
			throw new RuntimeException( 'Synthetic benchmark failure.' );
		}

		if ( 'success' === $this->agent_behavior ) {
			if ( 1 !== preg_match( '/directory name must be exactly "([^"]+)"/', $prompt, $matches ) ) {
				throw new RuntimeException( 'Synthetic benchmark could not resolve the fixture slug.' );
			}

			$slug = $matches[1];
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test-owned generated plugin.
			file_put_contents( WP_PLUGIN_DIR . "/{$slug}/{$slug}.php", "<?php\n", LOCK_EX );

			return array(
				'turns_used'    => 1,
				'token_usage'   => array(),
				'tool_call_log' => array(),
				'reply'         => 'Synthetic success.',
			);
		}

		return array(
			'error'         => 'Provider unavailable for synthetic benchmark.',
			'turns_used'    => 0,
			'token_usage'   => array(),
			'tool_call_log' => array(),
			'reply'         => '',
		);
	}
}

/**
 * Test benchmark plugin fixture ownership and cleanup boundaries.
 */
class BenchmarkCommandTest extends WP_UnitTestCase {

	/**
	 * Slugs created by the current test.
	 *
	 * @var array<int, string>
	 */
	private array $fixture_slugs = array();

	/**
	 * External canary paths created by the current test.
	 *
	 * @var array<int, string>
	 */
	private array $external_canaries = array();

	/** Invocation-only summaries cannot claim functional ranking eligibility. */
	public function test_invocation_only_summary_is_not_ranking_eligible(): void {
		$command = new TestableBenchmarkCommand();
		$method  = new ReflectionMethod( BenchmarkCommand::class, 'summarize_question_result' );
		$summary = $method->invoke(
			$command,
			array(
				'question_id' => 'invocation-only',
				'status'      => 'passed',
				'assertions'  => array(
					'passed'   => 1,
					'failed'   => 0,
					'total'    => 1,
					'coverage' => array(
						'classification'          => 'invocation_only',
						'postcondition_total'     => 0,
						'postcondition_passed'    => 0,
						'postcondition_failed'    => 0,
						'functionally_verifiable' => false,
						'functionally_verified'   => false,
					),
				),
			)
		);

		$this->assertSame( 'invocation_only', $summary['coverage_classification'] );
		$this->assertSame( 'not_assessed', $summary['functional_status'] );
		$this->assertFalse( $summary['ranking_eligible'] );
	}

	/** Suite ranking requires functional evidence for every included question. */
	public function test_suite_ranking_rejects_invocation_only_questions(): void {
		$command = new TestableBenchmarkCommand();
		$method  = new ReflectionMethod( BenchmarkCommand::class, 'is_ranking_eligible' );
		$totals  = array(
			'questions'                      => 2,
			'questions_functionally_assessed' => 1,
			'postconditions_passed'           => 1,
			'postconditions_failed'           => 0,
			'provider_errors'                 => 0,
		);

		$this->assertFalse( $method->invoke( $command, $totals, 'complete' ) );

		$totals['questions_functionally_assessed'] = 2;
		$this->assertTrue( $method->invoke( $command, $totals, 'complete' ) );
	}

	/**
	 * Clean up synthetic fixtures after each test.
	 */
	public function tear_down(): void {
		foreach ( $this->fixture_slugs as $slug ) {
			$plugin_file = "{$slug}/{$slug}.php";
			if ( is_plugin_active( $plugin_file ) ) {
				deactivate_plugins( $plugin_file, true );
			}

			$this->remove_literal_tree( WP_PLUGIN_DIR . '/' . $slug );
		}

		foreach ( $this->external_canaries as $canary ) {
			if ( is_file( $canary ) || is_link( $canary ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-owned canary cleanup.
				unlink( $canary );
			}
		}

		parent::tear_down();
	}

	/**
	 * A pre-existing plugin must be preserved and the agent must not run.
	 */
	public function test_pre_existing_plugin_is_preserved_with_activation_state(): void {
		$slug        = $this->new_slug( 'collision' );
		$plugin_file = $this->write_plugin( $slug );
		$before      = hash_file( 'sha256', $plugin_file );

		$this->assertNull( activate_plugin( "{$slug}/{$slug}.php" ) );
		$this->assertTrue( is_plugin_active( "{$slug}/{$slug}.php" ) );

		$command = new TestableBenchmarkCommand();
		$result  = $command->run_test_question( $this->question( $slug ) );

		$this->assertSame( 'fixture_collision', $result['status'] );
		$this->assertSame( 0, $command->agent_calls );
		$this->assertSame( $before, hash_file( 'sha256', $plugin_file ) );
		$this->assertTrue( is_plugin_active( "{$slug}/{$slug}.php" ) );
	}

	/**
	 * Provider errors still remove a fixture owned by this run.
	 */
	public function test_provider_error_cleans_owned_plugin_fixture(): void {
		$slug    = $this->new_slug( 'provider-error' );
		$command = new TestableBenchmarkCommand();
		$result  = $command->run_test_question( $this->question( $slug ) );

		$this->assertSame( 'provider_error', $result['status'] );
		$this->assertSame( 1, $command->agent_calls );
		$this->assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . $slug );
	}

	/**
	 * A normal successful run removes its owned fixture after assertions.
	 */
	public function test_successful_run_cleans_owned_plugin_fixture(): void {
		$slug                    = $this->new_slug( 'success' );
		$command                 = new TestableBenchmarkCommand();
		$command->agent_behavior = 'success';
		$result                  = $command->run_test_question( $this->question( $slug ) );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( 1, $result['assertions']['passed'] );
		$this->assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . $slug );
	}

	/**
	 * Exceptions still remove a fixture owned by this run.
	 */
	public function test_exception_cleans_owned_plugin_fixture(): void {
		$slug                    = $this->new_slug( 'exception' );
		$command                 = new TestableBenchmarkCommand();
		$command->agent_behavior = 'exception';

		try {
			$command->run_test_question( $this->question( $slug ) );
			$this->fail( 'Expected the synthetic agent exception.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Synthetic benchmark failure.', $exception->getMessage() );
		}

		$this->assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . $slug );
	}

	/**
	 * The keep-plugins option leaves the owned fixture available for inspection.
	 */
	public function test_keep_plugins_preserves_owned_plugin_fixture(): void {
		$slug    = $this->new_slug( 'keep' );
		$command = new TestableBenchmarkCommand();
		$result  = $command->run_test_question( $this->question( $slug ), true );

		$this->assertSame( 'provider_error', $result['status'] );
		$this->assertFileExists( WP_PLUGIN_DIR . '/' . $slug . '/.sd-ai-agent-benchmark-owner' );
	}

	/** Draft post fixtures are substituted into prompts and removed after execution. */
	public function test_owned_draft_post_fixture_is_substituted_and_cleaned(): void {
		$command  = new TestableBenchmarkCommand();
		$question = array(
			'id'         => 'draft-fixture-test',
			'category'   => 'seo',
			'prompt'     => 'Analyze post_id {{post_id}}.',
			'max_turns'  => 1,
			'setup'      => array(
				'draft_post' => array(
					'title'   => 'Command-owned draft fixture',
					'content' => 'Fixture content.',
				),
			),
			'assertions' => array(),
		);

		$result = $command->run_test_question( $question );

		$this->assertSame( 'provider_error', $result['status'] );
		$this->assertMatchesRegularExpression( '/Analyze post_id [1-9][0-9]*\./', $command->last_prompt );
		$this->assertSame( array(), get_posts( array( 'title' => 'Command-owned draft fixture', 'post_status' => 'any' ) ) );
	}

	/** Deferred questions never invoke the model and retain the machine reason. */
	public function test_deferred_question_skips_agent_execution(): void {
		$command  = new TestableBenchmarkCommand();
		$question = array(
			'id'         => 'deferred-test',
			'category'   => 'files',
			'prompt'     => 'Mutate a file.',
			'max_turns'  => 1,
			'execution'  => array(
				'status' => 'deferred',
				'reason' => 'explicit_approval_profile_required',
			),
			'assertions' => array(),
		);

		$result = $command->run_test_question( $question );

		$this->assertSame( 'deferred', $result['status'] );
		$this->assertSame( 'explicit_approval_profile_required', $result['deferred_reason'] );
		$this->assertSame( 0, $command->agent_calls );
	}

	/**
	 * Cleanup unlinks a leaf symlink without deleting its external target.
	 */
	public function test_cleanup_unlinks_symlink_without_following_target(): void {
		$slug    = $this->new_slug( 'symlink' );
		$command = new TestableBenchmarkCommand();
		$command->run_test_question( $this->question( $slug ), true );

		$canary = WP_CONTENT_DIR . '/sd-ai-agent-benchmark-canary-' . wp_generate_uuid4() . '.txt';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test-owned canary.
		file_put_contents( $canary, 'external-canary', LOCK_EX );
		$this->external_canaries[] = $canary;

		$link = WP_PLUGIN_DIR . '/' . $slug . '/external-link.txt';
		$this->assertTrue( symlink( $canary, $link ) );

		$cleanup = new ReflectionMethod( BenchmarkCommand::class, 'cleanup_plugin' );
		$cleanup->invoke( $command, $slug );

		$this->assertFileExists( $canary );
		$this->assertSame( 'external-canary', file_get_contents( $canary ) );
		$this->assertFileDoesNotExist( $link );
		$this->assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . $slug );
	}

	/**
	 * Cleanup fails closed when the ownership marker no longer matches.
	 */
	public function test_cleanup_preserves_fixture_with_tampered_ownership_marker(): void {
		$slug    = $this->new_slug( 'tampered' );
		$command = new TestableBenchmarkCommand();
		$command->run_test_question( $this->question( $slug ), true );

		$marker = WP_PLUGIN_DIR . '/' . $slug . '/.sd-ai-agent-benchmark-owner';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test-owned marker mutation.
		file_put_contents( $marker, 'different-owner', LOCK_EX );

		$cleanup = new ReflectionMethod( BenchmarkCommand::class, 'cleanup_plugin' );
		$cleanup->invoke( $command, $slug );

		$this->assertDirectoryExists( WP_PLUGIN_DIR . '/' . $slug );
		$this->assertSame( 'different-owner', file_get_contents( $marker ) );
	}

	/**
	 * Build a plugin-building benchmark question.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string, mixed>
	 */
	private function question( string $slug ): array {
		return array(
			'id'         => 'fixture-test',
			'category'   => 'fixture-safety',
			'prompt'     => sprintf( 'Create a plugin called "%s".', $slug ),
			'max_turns'  => 1,
			'assertions' => array(
				array(
					'type' => 'file_exists_in_plugin',
					'path' => $slug . '.php',
				),
			),
		);
	}

	/**
	 * Create a unique test slug and register it for teardown.
	 *
	 * @param string $suffix Descriptive suffix.
	 * @return string
	 */
	private function new_slug( string $suffix ): string {
		$slug                  = 'sd-ai-agent-benchmark-' . $suffix . '-' . strtolower( wp_generate_password( 8, false, false ) );
		$this->fixture_slugs[] = $slug;

		return $slug;
	}

	/**
	 * Write a minimal synthetic plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Main plugin file path.
	 */
	private function write_plugin( string $slug ): string {
		$directory = WP_PLUGIN_DIR . '/' . $slug;
		wp_mkdir_p( $directory );
		$file = $directory . '/' . $slug . '.php';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test-owned plugin fixture.
		file_put_contents( $file, "<?php\n/**\n * Plugin Name: Benchmark fixture\n */\n", LOCK_EX );

		return $file;
	}

	/**
	 * Remove a test-owned tree without resolving symlink targets.
	 *
	 * @param string $path Root path.
	 */
	private function remove_literal_tree( string $path ): void {
		if ( is_link( $path ) || is_file( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-owned fixture cleanup.
			unlink( $path );
			return;
		}

		if ( ! is_dir( $path ) ) {
			return;
		}

		$items = new \FilesystemIterator( $path, \FilesystemIterator::SKIP_DOTS );
		foreach ( $items as $item ) {
			$this->remove_literal_tree( $item->getPathname() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test-owned fixture cleanup.
		rmdir( $path );
	}
}
