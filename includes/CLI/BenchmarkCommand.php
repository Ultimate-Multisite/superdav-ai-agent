<?php
/**
 * WP-CLI commands for model benchmarking.
 *
 * Provides `wp ai-agent benchmark run|list|show|suites|delete|compare`
 * commands that use the same BenchmarkRunner/BenchmarkSuite backend
 * as the browser-based benchmark UI.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\CLI;

use GratisAiAgent\Benchmark\BenchmarkRunner;
use GratisAiAgent\Benchmark\BenchmarkSuite;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run AI model benchmarks from the command line.
 *
 * Uses the same backend as the admin benchmark page — creates runs,
 * executes questions one at a time, stores results in the database,
 * and supports comparison across runs.
 *
 * ## EXAMPLES
 *
 *     # List available benchmark suites
 *     wp ai-agent benchmark suites
 *
 *     # Run Opus on the agent capabilities suite
 *     wp ai-agent benchmark run --suite=agent-capabilities-v1 --provider=anthropic --model=claude-opus-4-6
 *
 *     # Run a quick test with Google
 *     wp ai-agent benchmark run --suite=wp-quick --provider=google --model=gemini-2.5-pro-preview-05-06
 *
 *     # Run only specific questions
 *     wp ai-agent benchmark run --suite=agent-capabilities-v1 --provider=anthropic --model=claude-opus-4-6 --questions=ac-001,ac-004,ac-010
 *
 *     # List past benchmark runs
 *     wp ai-agent benchmark list
 *
 *     # Show results for a specific run
 *     wp ai-agent benchmark show 42
 *
 *     # Compare two runs
 *     wp ai-agent benchmark compare 42 43
 *
 * @since 1.5.0
 */
class BenchmarkCommand extends \WP_CLI_Command {

	/**
	 * List available benchmark suites.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent benchmark suites
	 *     wp ai-agent benchmark suites --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function suites( array $args, array $assoc_args ): void {
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$suites = BenchmarkSuite::list_suites();

		if ( empty( $suites ) ) {
			WP_CLI::log( 'No benchmark suites available.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $suites, array( 'slug', 'name', 'question_count', 'description' ) );
	}

	/**
	 * Run a benchmark suite against one or more models.
	 *
	 * Creates a benchmark run, executes every question sequentially,
	 * and prints results as they complete. Results are stored in the
	 * database and visible in the admin benchmark page.
	 *
	 * ## OPTIONS
	 *
	 * [--suite=<slug>]
	 * : Benchmark suite to run.
	 * ---
	 * default: wp-core-v1
	 * ---
	 *
	 * [--provider=<id>]
	 * : Provider ID (anthropic, openai, google). Required unless WP AI Client is configured.
	 *
	 * [--model=<id>]
	 * : Model ID (e.g. claude-opus-4-6, gpt-4o, gemini-2.5-pro-preview-05-06).
	 *
	 * [--name=<name>]
	 * : Human-readable name for this run. Defaults to "CLI: {suite} — {model}".
	 *
	 * [--questions=<ids>]
	 * : Comma-separated list of question IDs to run (e.g. ac-001,ac-004). Runs all if omitted.
	 *
	 * [--format=<format>]
	 * : Output format for results.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent benchmark run --suite=agent-capabilities-v1 --provider=anthropic --model=claude-opus-4-6
	 *     wp ai-agent benchmark run --suite=wp-quick --provider=openai --model=gpt-4o
	 *     wp ai-agent benchmark run --suite=agent-capabilities-v1 --provider=anthropic --model=claude-opus-4-6 --questions=ac-001,ac-010
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function run( array $args, array $assoc_args ): void {
		$suite_slug   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'suite', 'wp-core-v1' );
		$provider_id  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'provider', '' );
		$model_id     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'model', '' );
		$run_name     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'name', '' );
		$question_csv = \WP_CLI\Utils\get_flag_value( $assoc_args, 'questions', '' );
		$format       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// Ensure a user context so permission checks pass.
		self::ensure_admin_user();

		// Validate suite exists.
		$suite = BenchmarkSuite::get_suite( $suite_slug );
		if ( ! $suite ) {
			WP_CLI::error( "Unknown benchmark suite: {$suite_slug}. Run `wp ai-agent benchmark suites` to list available suites." );
		}

		// Build model config.
		$model_config = array(
			'provider_id' => $provider_id,
			'model_id'    => $model_id,
		);

		$model_label = ! empty( $model_id ) ? $model_id : 'default';
		if ( $provider_id ) {
			$model_label = "{$provider_id}/{$model_label}";
		}

		// Build run name.
		if ( empty( $run_name ) ) {
			$run_name = "CLI: {$suite['name']} — {$model_label}";
		}

		// Parse question IDs filter.
		$question_ids = array();
		if ( ! empty( $question_csv ) ) {
			$question_ids = array_map( 'trim', explode( ',', $question_csv ) );

			// Validate question IDs exist in the suite.
			$valid_ids = array_column( $suite['questions'], 'id' );
			$invalid   = array_diff( $question_ids, $valid_ids );
			if ( ! empty( $invalid ) ) {
				WP_CLI::error( 'Unknown question IDs: ' . implode( ', ', $invalid ) . '. Valid IDs for this suite: ' . implode( ', ', $valid_ids ) );
			}
		}

		$question_count = ! empty( $question_ids ) ? count( $question_ids ) : count( $suite['questions'] );

		WP_CLI::log( "Benchmark: {$suite['name']}" );
		WP_CLI::log( "Model:     {$model_label}" );
		WP_CLI::log( "Questions: {$question_count}" );
		WP_CLI::log( '' );

		// Create the run.
		$create_data = array(
			'name'       => $run_name,
			'test_suite' => $suite_slug,
			'models'     => array( $model_config ),
		);
		if ( ! empty( $question_ids ) ) {
			$create_data['question_ids'] = $question_ids;
		}

		$run_id = BenchmarkRunner::create_run( $create_data );
		if ( ! $run_id ) {
			WP_CLI::error( 'Failed to create benchmark run.' );
		}

		WP_CLI::log( "Run #{$run_id} created. Starting..." );
		WP_CLI::log( '' );

		// Execute questions one at a time with a progress bar.
		$progress = \WP_CLI\Utils\make_progress_bar( 'Running benchmark', $question_count );
		$start    = microtime( true );

		for ( $i = 0; $i < $question_count; $i++ ) {
			$result = BenchmarkRunner::run_next_question( $run_id );

			if ( is_wp_error( $result ) ) {
				$progress->finish();
				WP_CLI::error( "Question failed: {$result->get_error_message()}" );
			}

			$progress->tick();

			if ( isset( $result['status'] ) && 'completed' === $result['status'] ) {
				break;
			}
		}

		// Trigger final status update if all questions answered but status not yet set.
		BenchmarkRunner::run_next_question( $run_id );

		$progress->finish();

		$elapsed = round( microtime( true ) - $start, 1 );
		WP_CLI::log( '' );
		WP_CLI::log( "Completed in {$elapsed}s." );
		WP_CLI::log( '' );

		// Display results.
		self::display_run_results( $run_id, $format );
	}

	/**
	 * List past benchmark runs.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Number of runs to show.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent benchmark list
	 *     wp ai-agent benchmark list --limit=5 --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function list( array $args, array $assoc_args ): void {
		$limit  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 20 );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$data = BenchmarkRunner::list_runs( $limit );
		$runs = $data['runs'] ?? array();

		if ( empty( $runs ) ) {
			WP_CLI::log( 'No benchmark runs found.' );
			return;
		}

		$rows = array();
		foreach ( $runs as $run ) {
			$rows[] = array(
				'ID'        => $run->id,
				'Name'      => $run->name,
				'Suite'     => $run->test_suite,
				'Status'    => $run->status,
				'Progress'  => "{$run->completed_count}/{$run->questions_count}",
				'Started'   => $run->started_at,
				'Completed' => $run->completed_at ?? '-',
			);
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Name', 'Suite', 'Status', 'Progress', 'Started', 'Completed' ) );
	}

	/**
	 * Show detailed results for a benchmark run.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The benchmark run ID.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent benchmark show 42
	 *     wp ai-agent benchmark show 42 --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function show( array $args, array $assoc_args ): void {
		$run_id = (int) $args[0];
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		self::display_run_results( $run_id, $format );
	}

	/**
	 * Delete a benchmark run and its results.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The benchmark run ID to delete.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent benchmark delete 42
	 *     wp ai-agent benchmark delete 42 --yes
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function delete( array $args, array $assoc_args ): void {
		$run_id = (int) $args[0];

		$run = BenchmarkRunner::get_run( $run_id );
		if ( ! $run ) {
			WP_CLI::error( "Benchmark run #{$run_id} not found." );
		}

		WP_CLI::confirm( "Delete benchmark run #{$run_id} ({$run->name}) and all its results?", $assoc_args );

		$deleted = BenchmarkRunner::delete_run( $run_id );
		if ( ! $deleted ) {
			WP_CLI::error( "Failed to delete benchmark run #{$run_id}." );
		}

		WP_CLI::success( "Benchmark run #{$run_id} deleted." );
	}

	/**
	 * Compare two or more benchmark runs side by side.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Two or more benchmark run IDs to compare.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent benchmark compare 42 43
	 *     wp ai-agent benchmark compare 42 43 44 --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function compare( array $args, array $assoc_args ): void {
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( count( $args ) < 2 ) {
			WP_CLI::error( 'At least two run IDs are required for comparison.' );
		}

		$run_ids    = array_map( 'intval', $args );
		$comparison = BenchmarkRunner::compare_runs( $run_ids );

		if ( 'json' === $format ) {
			WP_CLI::log( (string) wp_json_encode( $comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// Summary table.
		WP_CLI::log( '## Run Summary' );
		WP_CLI::log( '' );

		$summary_rows = array();
		foreach ( $comparison['summary'] as $entry ) {
			$summary_rows[] = array(
				'Run'      => "#{$entry['run_id']} {$entry['run_name']}",
				'Total'    => $entry['total'],
				'Correct'  => $entry['correct'],
				'Accuracy' => "{$entry['accuracy']}%",
				'Avg Lat.' => "{$entry['avg_latency']}ms",
				'Tokens'   => $entry['total_tokens'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $summary_rows, array( 'Run', 'Total', 'Correct', 'Accuracy', 'Avg Lat.', 'Tokens' ) );

		// By-model breakdown.
		if ( ! empty( $comparison['by_model'] ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( '## By Model' );
			WP_CLI::log( '' );

			$model_rows = array();
			foreach ( $comparison['by_model'] as $entry ) {
				$model_rows[] = array(
					'Model'    => $entry['model_id'],
					'Total'    => $entry['total'],
					'Correct'  => $entry['correct'],
					'Accuracy' => "{$entry['accuracy']}%",
				);
			}

			\WP_CLI\Utils\format_items( 'table', $model_rows, array( 'Model', 'Total', 'Correct', 'Accuracy' ) );
		}

		// By-category breakdown.
		if ( ! empty( $comparison['by_category'] ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( '## By Category' );
			WP_CLI::log( '' );

			$cat_rows = array();
			foreach ( $comparison['by_category'] as $entry ) {
				$cat_rows[] = array(
					'Category' => $entry['category'],
					'Total'    => $entry['total'],
					'Correct'  => $entry['correct'],
					'Accuracy' => "{$entry['accuracy']}%",
				);
			}

			\WP_CLI\Utils\format_items( 'table', $cat_rows, array( 'Category', 'Total', 'Correct', 'Accuracy' ) );
		}
	}

	/**
	 * Display results for a benchmark run.
	 *
	 * @param int    $run_id Run ID.
	 * @param string $format Output format (table or json).
	 */
	private static function display_run_results( int $run_id, string $format ): void {
		$run = BenchmarkRunner::get_run( $run_id );
		if ( ! $run ) {
			WP_CLI::error( "Benchmark run #{$run_id} not found." );
		}

		$results = BenchmarkRunner::get_run_results( $run_id );

		// Run header.
		WP_CLI::log( "Run #{$run->id}: {$run->name}" );
		WP_CLI::log( "Suite: {$run->test_suite} | Status: {$run->status} | {$run->completed_count}/{$run->questions_count} completed" );
		WP_CLI::log( '' );

		if ( empty( $results ) ) {
			WP_CLI::log( 'No results yet.' );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log(
				(string) wp_json_encode(
					array(
						'run'     => $run,
						'results' => $results,
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				)
			);
			return;
		}

		// Results table.
		$rows        = array();
		$total_score = 0;
		$total_count = 0;

		foreach ( $results as $result ) {
			$score_display = (float) $result->score;

			// For open-ended questions, show the 0-100 score.
			// For multiple-choice, show correct/incorrect.
			$correct_display = $result->is_correct ? 'Yes' : 'No';

			$rows[] = array(
				'Q.ID'     => $result->question_id,
				'Category' => $result->question_category,
				'Correct'  => $correct_display,
				'Score'    => $score_display,
				'Latency'  => "{$result->latency_ms}ms",
				'Tokens'   => ( (int) $result->prompt_tokens + (int) $result->completion_tokens ),
				'Error'    => $result->error_message ? substr( $result->error_message, 0, 40 ) : '',
			);

			$total_score += $score_display;
			++$total_count;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'Q.ID', 'Category', 'Correct', 'Score', 'Latency', 'Tokens', 'Error' ) );

		// Summary.
		$avg_score     = $total_count > 0 ? round( $total_score / $total_count, 1 ) : 0;
		$correct       = count(
			array_filter(
				$results,
				function ( object $r ): bool {
					return (bool) $r->is_correct;
				}
			)
		);
		$total_latency = array_sum( array_column( $results, 'latency_ms' ) );
		$total_tokens  = array_sum(
			array_map(
				function ( object $r ): int {
					return (int) $r->prompt_tokens + (int) $r->completion_tokens;
				},
				$results
			)
		);

		WP_CLI::log( '' );
		WP_CLI::log( "Accuracy:      {$correct}/{$total_count} correct" );
		WP_CLI::log( "Average score: {$avg_score}/100" );
		WP_CLI::log( "Total latency: {$total_latency}ms" );
		WP_CLI::log( "Total tokens:  {$total_tokens}" );
	}

	/**
	 * Ensure a logged-in admin user for CLI context.
	 *
	 * WP-CLI doesn't set a current user unless --user is passed.
	 * The benchmark needs manage_options capability.
	 */
	private static function ensure_admin_user(): void {
		if ( get_current_user_id() ) {
			return;
		}

		$admins = get_super_admins();
		if ( ! empty( $admins ) ) {
			$admin = get_user_by( 'login', $admins[0] );
			if ( $admin ) {
				wp_set_current_user( $admin->ID );
				return;
			}
		}

		// Fallback: find any user with manage_options.
		$users = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
			)
		);
		if ( ! empty( $users ) ) {
			wp_set_current_user( $users[0]->ID );
		}
	}
}
