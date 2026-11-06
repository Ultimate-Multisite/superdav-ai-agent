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

	/** User login created by the synthetic loop. */
	public string $last_user_login = '';

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

		if ( 'user-success' === $this->agent_behavior ) {
			if ( 1 !== preg_match( '/login "([^"]+)".*email "([^"]+)"/s', $prompt, $matches ) ) {
				throw new RuntimeException( 'Synthetic benchmark could not resolve the user fixture.' );
			}

			$this->last_user_login = $matches[1];
			$user_id               = wp_insert_user(
				array(
					'user_login' => $matches[1],
					'user_email' => $matches[2],
					'user_pass'  => wp_generate_password( 24, true, true ),
					'role'       => 'subscriber',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				throw new RuntimeException( $user_id->get_error_message() );
			}
			$created_user = get_user_by( 'id', $user_id );
			if ( ! $created_user ) {
				throw new RuntimeException( 'Synthetic benchmark could not reload the user fixture.' );
			}
			$created_user->set_role( 'editor' );

			return array(
				'turns_used'    => 1,
				'token_usage'   => array(),
				'tool_call_log' => array(
					array( 'tool' => 'sd-ai-agent/list-users' ),
					array( 'tool' => 'sd-ai-agent/create-user' ),
					array( 'tool' => 'sd-ai-agent/update-user-role' ),
				),
				'reply'         => 'Synthetic user success.',
			);
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

	/** Credentialed degradation stays distinct from provider unavailability. */
	public function test_provider_error_takes_precedence_over_degradation_status(): void {
		$command = new TestableBenchmarkCommand();
		$method  = new ReflectionMethod( BenchmarkCommand::class, 'summarize_question_result' );
		$summary = $method->invoke(
			$command,
			array(
				'question_id'         => 'credentialed',
				'status'              => 'provider_error',
				'benchmark_dimension' => 'graceful_degradation',
				'provider_error'      => array( 'type' => 'provider_unavailable' ),
				'assertions'          => array(),
			)
		);

		$this->assertSame( 'graceful_degradation', $summary['benchmark_dimension'] );
		$this->assertSame( 'blocked_provider', $summary['functional_status'] );
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

	/** A static benchmark-like login and its authored content are never adopted or changed. */
	public function test_pre_existing_user_and_authored_content_are_preserved(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'as010_user',
				'user_email' => 'as010-user-canary@example.test',
				'role'       => 'subscriber',
			)
		);
		update_user_meta( $user_id, 'benchmark_canary', 'unchanged' );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'draft',
			)
		);

		$command = new TestableBenchmarkCommand();
		$result  = $command->run_test_question( $this->user_question() );
		$user    = get_user_by( 'id', $user_id );

		$this->assertSame( 'provider_error', $result['status'] );
		$this->assertNotFalse( $user );
		$this->assertSame( array( 'subscriber' ), $user->roles );
		$this->assertSame( 'unchanged', get_user_meta( $user_id, 'benchmark_canary', true ) );
		$this->assertSame( $user_id, (int) get_post_field( 'post_author', $post_id ) );
	}

	/** A generated user can satisfy assertions and is removed after the question. */
	public function test_owned_user_fixture_completes_and_is_cleaned(): void {
		$command                 = new TestableBenchmarkCommand();
		$command->agent_behavior = 'user-success';
		$result                  = $command->run_test_question( $this->user_question() );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( 4, $result['assertions']['passed'] );
		$this->assertStringStartsWith( 'as010-user-', $command->last_user_login );
		$this->assertFalse( get_user_by( 'login', $command->last_user_login ) );
	}

	/** Cleanup fails closed when a generated user's ownership marker is changed. */
	public function test_user_cleanup_preserves_fixture_with_tampered_ownership_marker(): void {
		$command = new TestableBenchmarkCommand();
		$prepare = new ReflectionMethod( BenchmarkCommand::class, 'prepare_question_fixtures' );
		$setup   = $prepare->invoke( $command, $this->user_question() );
		$login   = $setup['variables']['{{user_login}}'];
		$user_id = self::factory()->user->create(
			array(
				'user_login' => $login,
				'user_email' => $setup['variables']['{{user_email}}'],
			)
		);

		$claim = new ReflectionMethod( BenchmarkCommand::class, 'claim_created_user_fixtures' );
		$claim->invoke( $command );
		update_user_meta( $user_id, '_sd_ai_agent_benchmark_owner', 'different-owner' );

		$cleanup = new ReflectionMethod( BenchmarkCommand::class, 'cleanup_owned_user_fixtures' );
		$cleanup->invoke( $command );

		$this->assertNotFalse( get_user_by( 'id', $user_id ) );
		$this->assertSame( 'different-owner', get_user_meta( $user_id, '_sd_ai_agent_benchmark_owner', true ) );
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

	/** Build a user-mutation benchmark question with run-scoped fixture placeholders. */
	private function user_question(): array {
		return array(
			'id'         => 'user-fixture-test',
			'category'   => 'users',
			'prompt'     => 'Use list-users, then create-user with login "{{user_login}}", email "{{user_email}}", role "subscriber". Then use update-user-role to change them to "editor".',
			'max_turns'  => 1,
			'setup'      => array(
				'user' => array(
					'login_prefix' => 'as010-user',
					'email_domain' => 'example.test',
				),
			),
			'assertions' => array(
				array( 'type' => 'tool_called', 'tools' => array( 'sd-ai-agent/list-users' ) ),
				array( 'type' => 'tool_called_or_user_exists', 'tools' => array( 'sd-ai-agent/create-user' ), 'login' => '{{user_login}}', 'role' => 'editor' ),
				array( 'type' => 'tool_called_or_user_exists', 'tools' => array( 'sd-ai-agent/update-user-role' ), 'login' => '{{user_login}}', 'role' => 'editor' ),
				array( 'type' => 'user_exists', 'login' => '{{user_login}}', 'role' => 'editor' ),
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
