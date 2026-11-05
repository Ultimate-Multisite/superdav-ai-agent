<?php

declare(strict_types=1);
/**
 * WP-CLI benchmark command.
 *
 * Drives the full AI agent loop against live WordPress and writes a structured
 * log file for every question run. Result logs live in a private directory
 * outside web-served WordPress paths by default, but benchmark tasks and their
 * fixtures may still mutate the database, filesystem, or site configuration.
 *
 * Usage:
 *   wp sd-ai-agent benchmark run --suite=functional-v1 --provider=anthropic --model=claude-sonnet-4-6
 *   wp sd-ai-agent benchmark run --question=fn-001 --provider=openai --model=gpt-4o
 *   wp sd-ai-agent benchmark suites
 *   wp sd-ai-agent benchmark questions --suite=functional-v1
 *
 * @package SdAiAgent\CLI
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\CLI;

use SdAiAgent\Benchmark\AssertionEngine;
use SdAiAgent\Benchmark\BenchmarkSuite;
use SdAiAgent\Core\AgentLoop;
use SdAiAgent\Core\Filesystem\PathCanonicalizer;
use SdAiAgent\Core\ProviderCredentialLoader;
use SdAiAgent\Tools\ToolDiscovery;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run functional AI agent benchmarks against a live WordPress environment.
 *
 * ## EXAMPLES
 *
 *   # Run all questions in the functional-v1 suite with Claude Sonnet
 *   wp sd-ai-agent benchmark run --suite=functional-v1 --provider=ultimate-ai-connector-anthropic-max --model=claude-sonnet-4-6
 *
 *   # Run a single question
 *   wp sd-ai-agent benchmark run --question=fn-001 --provider=ultimate-ai-connector-anthropic-max --model=claude-sonnet-4-6
 *
 *   # List all suites
 *   wp sd-ai-agent benchmark suites
 *
 *   # List questions in a suite
 *   wp sd-ai-agent benchmark questions --suite=functional-v1
 */
class BenchmarkCommand extends WP_CLI_Command {

	private const PROFILE_FUNCTIONAL = 'functional';

	private const PROFILE_PRODUCTION_PARITY = 'production-parity';

	/**
	 * Prefix for the site-specific private benchmark directory.
	 *
	 * The directory lives in the WP-CLI user's private state directory by
	 * default, not under uploads, wp-content, the document root, or ABSPATH.
	 */
	private const LOG_DIR_PREFIX = 'benchmark-logs-';

	/** Redaction marker used for credential-bearing benchmark values. */
	private const REDACTED = '[redacted]';

	/**
	 * Marker stored only in plugin fixtures created by this command instance.
	 */
	private const PLUGIN_OWNER_MARKER = '.sd-ai-agent-benchmark-owner';

	/**
	 * Plugin fixtures atomically claimed by this command instance.
	 *
	 * @var array<string, array{path: string, token: string}>
	 */
	private array $owned_plugin_fixtures = array();

	/**
	 * Draft posts created by this command for schema-compatible benchmark input.
	 *
	 * @var array<int, string>
	 */
	private array $owned_post_fixtures = array();

	/** @var array<string, string> Generated user identities awaiting agent creation. */
	private array $pending_user_fixtures = array();

	/** @var array<int, array{login: string, owner: string}> Users owned by this run. */
	private array $owned_user_fixtures = array();

	/** @var string Active execution profile. */
	private string $executionProfile = self::PROFILE_FUNCTIONAL;

	/** @var array<int, string> Abilities explicitly approved once for a production-parity run. */
	private array $approvedOnceAbilities = array();

	/** @var array<string, mixed> Safe execution-profile details persisted with every result. */
	private array $executionProfileMetadata = array();

	/**
	 * System prompt injected for every benchmark question.
	 * Tells the agent it is in a live WordPress environment and should act, not describe.
	 */
	private const SYSTEM_PROMPT = 'You are a WordPress AI Agent operating in a real, live WordPress installation. When given a task you MUST complete it immediately using your tools — do not describe what you would do. Write actual working code, create real files, activate plugins, and make real changes. The environment is yours to modify. After completing a task always confirm what was done and that it worked.';

	// ── Subcommands ───────────────────────────────────────────────────────────

	/**
	 * List available benchmark suites.
	 *
	 * ## EXAMPLES
	 *
	 *   wp sd-ai-agent benchmark suites
	 *
	 * @subcommand suites
	 */
	public function suites(): void {
		$suites = BenchmarkSuite::list_suites();

		$rows = array_map(
			fn( $s ) => array(
				'slug'        => $s['slug'],
				'name'        => $s['name'],
				'questions'   => $s['question_count'],
				'description' => $s['description'],
			),
			$suites
		);

		WP_CLI\Utils\format_items( 'table', $rows, array( 'slug', 'name', 'questions', 'description' ) );
	}

	/**
	 * List questions in a suite.
	 *
	 * ## OPTIONS
	 *
	 * [--suite=<slug>]
	 * : Suite slug. Default: functional-v1
	 *
	 * ## EXAMPLES
	 *
	 *   wp sd-ai-agent benchmark questions --suite=functional-v1
	 *
	 * @subcommand questions
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function questions( array $args, array $assoc_args ): void {
		$suite_slug = (string) ( $assoc_args['suite'] ?? 'functional-v1' );
		$questions  = BenchmarkSuite::get_questions( $suite_slug );

		if ( empty( $questions ) ) {
			WP_CLI::error( "Suite '{$suite_slug}' not found." );
		}

		$rows = array_map(
			fn( $q ) => array(
				'id'         => $q['id'],
				'category'   => $q['category'],
				'max_turns'  => $q['max_turns'],
				'assertions' => count( $q['assertions'] ),
				'prompt'     => substr( $q['prompt'], 0, 80 ) . '…',
			),
			$questions
		);

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'category', 'max_turns', 'assertions', 'prompt' ) );
	}

	/**
	 * Run benchmark questions against the AI agent.
	 *
	 * Runs each question through the full AgentLoop with all tools available.
	 * A log file is written for every question under a site-specific private
	 * directory outside web-served WordPress paths. Plugins created during
	 * functional tests are cleaned up after each question.
	 *
	 * ## OPTIONS
	 *
	 * [--suite=<slug>]
	 * : Suite slug to run. Default: functional-v1
	 *
	 * [--question=<id>]
	 * : Run a single question by ID (e.g. fn-001). Overrides --suite.
	 *
	 * [--provider=<id>]
	 * : Provider ID as registered in the WP AI SDK (e.g. ultimate-ai-connector-anthropic-max).
	 *
	 * [--model=<id>]
	 * : Model ID (e.g. claude-sonnet-4-6, gpt-4o).
	 *
	 * [--log-dir=<path>]
	 * : Private absolute path for log output. Paths inside WordPress, wp-content,
	 * uploads, plugins, or the server document root are rejected.
	 *
	 * [--keep-plugins]
	 * : Keep generated sandbox plugins on disk after the run for debugging.
	 *
	 * [--profile=<profile>]
	 * : Execution profile. `functional` preserves the legacy privileged,
	 *   globally-approved smoke profile. `production-parity` honors the current
	 *   WP-CLI actor (select one with the global `--user` option), uses the normal
	 *   prompt and automatic ability catalog, and keeps normal approval checks.
	 *   Default: functional
	 *
	 * [--approve-abilities=<names>]
	 * : Comma-separated ability names explicitly approved once for this run.
	 *   Intended for controlled production-parity mutations; this does not enable
	 *   global write approval.
	 *
	 * ## EXAMPLES
	 *
	 *   wp sd-ai-agent benchmark run --suite=functional-v1 --provider=ultimate-ai-connector-anthropic-max --model=claude-sonnet-4-6
	 *   wp sd-ai-agent benchmark run --question=fn-003 --provider=openai --model=gpt-4o
	 *
	 * @subcommand run
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function run( array $args, array $assoc_args ): void {
		$suite_slug  = (string) ( $assoc_args['suite'] ?? 'functional-v1' );
		$question_id = (string) ( $assoc_args['question'] ?? '' );
		$provider_id = (string) ( $assoc_args['provider'] ?? '' );
		$model_id    = (string) ( $assoc_args['model'] ?? '' );
		$log_dir     = (string) ( $assoc_args['log-dir'] ?? '' );
		$no_cleanup  = isset( $assoc_args['keep-plugins'] );
		$profile     = (string) ( $assoc_args['profile'] ?? self::PROFILE_FUNCTIONAL );

		if ( ! in_array( $profile, array( self::PROFILE_FUNCTIONAL, self::PROFILE_PRODUCTION_PARITY ), true ) ) {
			WP_CLI::error( 'Invalid benchmark profile. Use "functional" or "production-parity".' );
		}

		$this->executionProfile       = $profile;
		$this->approvedOnceAbilities = $this->parse_approved_abilities( (string) ( $assoc_args['approve-abilities'] ?? '' ) );

		// Resolve questions to run.
		if ( '' !== $question_id ) {
			$all       = BenchmarkSuite::get_questions( $suite_slug );
			$questions = array_values( array_filter( $all, fn( $q ) => $q['id'] === $question_id ) );
			if ( empty( $questions ) ) {
				// Try all suites.
				foreach ( BenchmarkSuite::list_suites() as $suite ) {
					$all       = BenchmarkSuite::get_questions( $suite['slug'] );
					$questions = array_values( array_filter( $all, fn( $q ) => $q['id'] === $question_id ) );
					if ( ! empty( $questions ) ) {
						$suite_slug = (string) $suite['slug'];
						break;
					}
				}
			}
			if ( empty( $questions ) ) {
				WP_CLI::error( "Question '{$question_id}' not found in any suite." );
			}
		} else {
			$questions = BenchmarkSuite::get_questions( $suite_slug );
			if ( empty( $questions ) ) {
				WP_CLI::error( "Suite '{$suite_slug}' not found or has no questions." );
			}
		}

		$log_dir = $this->prepare_log_directory( $log_dir );
		if ( is_wp_error( $log_dir ) ) {
			WP_CLI::error( $log_dir->get_error_message() );
		}

		$model_slug = sanitize_file_name( $model_id ?: 'default' );
		$run_id     = gmdate( 'Y-m-d_His' ) . '_' . ( $model_slug ?: 'default' );
		$run_dir    = PathCanonicalizer::canonicalize_missing_path_inside( $log_dir . '/' . $run_id, $log_dir );
		if ( is_wp_error( $run_dir ) ) {
			WP_CLI::error( $run_dir->get_error_message() );
		}

		$run_dir_result = $this->ensure_secure_directory( $run_dir );
		if ( is_wp_error( $run_dir_result ) ) {
			WP_CLI::error( $run_dir_result->get_error_message() );
		}

		// Ensure credentials are loaded for the AI SDK.
		ProviderCredentialLoader::load();

		$this->configure_actor();
		$this->executionProfileMetadata = $this->build_execution_profile_metadata();

		WP_CLI::log( '' );
		WP_CLI::log( '╔══════════════════════════════════════════════════════════════╗' );
		WP_CLI::log( '║  Superdav AI Agent — Functional Benchmark                    ║' );
		WP_CLI::log( '╚══════════════════════════════════════════════════════════════╝' );
		WP_CLI::log( sprintf( '  Provider : %s', $provider_id ?: 'default' ) );
		WP_CLI::log( sprintf( '  Model    : %s', $model_id ?: 'default' ) );
		WP_CLI::log( sprintf( '  Profile  : %s', $profile ) );
		WP_CLI::log( sprintf( '  Actor    : %s', implode( ',', $this->executionProfileMetadata['effective_actor']['roles'] ) ?: 'anonymous' ) );
		WP_CLI::log( sprintf( '  Questions: %d', count( $questions ) ) );
		WP_CLI::log( sprintf( '  Logs     : %s', $run_dir ) );
		WP_CLI::log( '' );

		$totals             = array(
			'passed'                          => 0,
			'failed'                          => 0,
			'questions'                       => 0,
			'question_passed'                 => 0,
			'questions_functionally_assessed' => 0,
			'questions_functionally_verified' => 0,
			'questions_invocation_only'       => 0,
			'questions_composite'             => 0,
			'questions_mixed'                 => 0,
			'postconditions_passed'           => 0,
			'postconditions_failed'           => 0,
			'graceful_degradation_questions'  => 0,
			'errors'                          => 0,
			'provider_errors'                 => 0,
			'fixture_errors'                  => 0,
			'deferred'                        => 0,
			'not_exercised'                   => 0,
		);
		$question_summaries = array();

		foreach ( $questions as $question ) {
			$q_id = (string) $question['id'];
			WP_CLI::log( "┌─ [{$q_id}] " . substr( $question['prompt'], 0, 70 ) . '…' );

			$result            = $this->run_question( $question, $provider_id, $model_id, $no_cleanup );
			$log_file          = $run_dir . "/{$q_id}.json";
			$result['run_id']  = $run_id;
			$result['log_dir'] = $run_dir;

			$status = (string) ( $result['status'] ?? 'unknown' );
			if ( 'not_exercised' === $status ) {
				++$totals['not_exercised'];
			} elseif ( 'deferred' === $status ) {
				++$totals['deferred'];
			}

			// Print summary.
			if ( 'not_exercised' === $status ) {
				WP_CLI::log( '│  Not exercised: ' . implode( ', ', (array) ( $result['missing_prerequisites'] ?? array() ) ) );
			} elseif ( 'deferred' === $status ) {
				WP_CLI::log( '│  Deferred: ' . (string) ( $result['deferred_reason'] ?? 'unsupported execution contract' ) );
			} elseif ( ! empty( $result['agent_error'] ) ) {
				$error_label = ! empty( $result['provider_error'] ) ? 'Provider error' : 'Agent error';
				WP_CLI::log( '│  ✗ ' . $error_label . ': ' . $result['agent_error'] );
				++$totals['errors'];
				if ( ! empty( $result['fixture_error'] ) ) {
					++$totals['fixture_errors'];
				}
				if ( ! empty( $result['provider_error'] ) ) {
					++$totals['provider_errors'];
				}
			} else {
				$turns = $result['turns_used'] ?? 0;
				WP_CLI::log( "│  Agent finished in {$turns} turn(s)" );
			}

			$assertions = $result['assertions'] ?? array();
			if ( ! empty( $assertions['results'] ) ) {
				foreach ( $assertions['results'] as $ar ) {
					$icon = $ar['pass'] ? '✓' : '✗';
					$line = "│  {$icon} {$ar['description']}: {$ar['actual']}";
					if ( $ar['pass'] ) {
						WP_CLI::log( $line );
					} else {
						WP_CLI::warning( $line );
					}
				}
				$p = $assertions['passed'];
				$t = $assertions['total'];
				WP_CLI::log( "│  Score: {$p}/{$t} assertions passed" );
				$totals['passed'] += $p;
				$totals['failed'] += $assertions['failed'];
				if ( 0 === (int) $assertions['failed'] && empty( $result['agent_error'] ) ) {
					$status = 'passed';
					++$totals['question_passed'];
				}
			}

			$coverage = isset( $assertions['coverage'] ) && is_array( $assertions['coverage'] ) ? $assertions['coverage'] : array();
			if ( ! empty( $coverage['functionally_verifiable'] ) ) {
				++$totals['questions_functionally_assessed'];
			}
			if ( ! empty( $coverage['functionally_verified'] ) && empty( $result['agent_error'] ) ) {
				++$totals['questions_functionally_verified'];
			}
			$classification = (string) ( $coverage['classification'] ?? '' );
			if ( 'invocation_only' === $classification ) {
				++$totals['questions_invocation_only'];
			} elseif ( 'composite' === $classification ) {
				++$totals['questions_composite'];
			} elseif ( 'mixed' === $classification ) {
				++$totals['questions_mixed'];
			}
			$totals['postconditions_passed'] += (int) ( $coverage['postcondition_passed'] ?? 0 );
			$totals['postconditions_failed'] += (int) ( $coverage['postcondition_failed'] ?? 0 );
			if ( 'graceful_degradation' === ( $result['benchmark_dimension'] ?? '' ) ) {
				++$totals['graceful_degradation_questions'];
			}

			$result['status'] = $status;
			$write_result     = $this->write_json_log( $log_file, $result );
			if ( is_wp_error( $write_result ) ) {
				WP_CLI::error( $write_result->get_error_message() );
			}

			$question_summaries[] = $this->summarize_question_result( $result );

			WP_CLI::log( '│  Status: ' . $status );
			WP_CLI::log( '└─ Log: ' . $log_file );
			WP_CLI::log( '' );
			++$totals['questions'];
		}

		// Summary.
		$total_assertions     = $totals['passed'] + $totals['failed'];
		$run_status           = $this->determine_run_status( $totals );
		$total_postconditions = $totals['postconditions_passed'] + $totals['postconditions_failed'];
		$summary              = array(
			'summary_schema_version'          => 2,
			'run_id'                          => $run_id,
			'provider_id'                     => $provider_id ?: 'default',
			'model_id'                        => $model_id ?: 'default',
			'suite'                           => $suite_slug,
			'question_count'                  => $totals['questions'],
			'questions_passed'                => $totals['question_passed'],
			'agent_errors'                    => $totals['errors'],
			'provider_errors'                 => $totals['provider_errors'],
			'fixture_errors'                  => $totals['fixture_errors'],
			'questions_deferred'              => $totals['deferred'],
			'questions_not_exercised'         => $totals['not_exercised'],
			'assertions_passed'               => $totals['passed'],
			'assertions_failed'               => $totals['failed'],
			'assertions_total'                => $total_assertions,
			'assertion_score'                 => $total_assertions > 0 ? round( ( $totals['passed'] / $total_assertions ) * 100 ) : null,
			'assertion_score_scope'           => 'all_assertions_including_invocation',
			'postconditions_passed'           => $totals['postconditions_passed'],
			'postconditions_failed'           => $totals['postconditions_failed'],
			'postconditions_total'            => $total_postconditions,
			'functional_score'                => $total_postconditions > 0 ? round( ( $totals['postconditions_passed'] / $total_postconditions ) * 100 ) : null,
			'questions_functionally_assessed' => $totals['questions_functionally_assessed'],
			'questions_functionally_verified' => $totals['questions_functionally_verified'],
			'questions_invocation_only'       => $totals['questions_invocation_only'],
			'questions_composite'             => $totals['questions_composite'],
			'questions_mixed'                 => $totals['questions_mixed'],
			'graceful_degradation_questions'  => $totals['graceful_degradation_questions'],
			'run_status'                      => $run_status,
			'ranking_eligible'                => $this->is_ranking_eligible( $totals, $run_status ),
			'execution_profile'               => $this->executionProfileMetadata,
			'questions'                       => $question_summaries,
		);
		$summary_file         = $run_dir . '/suite-summary.json';
		$write_result         = $this->write_json_log( $summary_file, $summary );
		if ( is_wp_error( $write_result ) ) {
			WP_CLI::error( $write_result->get_error_message() );
		}

		WP_CLI::log( '══════════════════════════════════════════════════════════════' );
		WP_CLI::log( sprintf( '  Questions run    : %d', $totals['questions'] ) );
		WP_CLI::log( sprintf( '  Questions passed : %d', $totals['question_passed'] ) );
		WP_CLI::log( sprintf( '  Agent errors     : %d', $totals['errors'] ) );
		WP_CLI::log( sprintf( '  Provider errors  : %d', $totals['provider_errors'] ) );
		WP_CLI::log( sprintf( '  Deferred         : %d', $totals['deferred'] ) );
		WP_CLI::log( sprintf( '  Not exercised    : %d', $totals['not_exercised'] ) );
		WP_CLI::log( sprintf( '  Assertions       : %d/%d passed', $totals['passed'], $total_assertions ) );
		WP_CLI::log( sprintf( '  Postconditions   : %d/%d passed', $totals['postconditions_passed'], $total_postconditions ) );
		WP_CLI::log( sprintf( '  Invocation only  : %d question(s)', $totals['questions_invocation_only'] ) );
		if ( $total_assertions > 0 ) {
			WP_CLI::log( sprintf( '  Score            : %d%%', $summary['assertion_score'] ) );
		}
		WP_CLI::log( sprintf( '  Run status       : %s', $run_status ) );
		WP_CLI::log( sprintf( '  Ranking eligible : %s', $summary['ranking_eligible'] ? 'yes' : 'no' ) );
		WP_CLI::log( sprintf( '  Summary log      : %s', $summary_file ) );
		WP_CLI::log( '' );

		// Exit with non-zero if any assertions failed (useful for CI).
		if ( $totals['failed'] > 0 || $totals['errors'] > 0 ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Resolve, validate, and secure the benchmark log root.
	 *
	 * @param string $requested_path Explicit path, or an empty string for the default.
	 * @return string|\WP_Error Canonical private directory or an error.
	 */
	private function prepare_log_directory( string $requested_path ): string|\WP_Error {
		$site_path = PathCanonicalizer::canonicalize_missing_path( ABSPATH );
		if ( is_wp_error( $site_path ) ) {
			return $site_path;
		}

		if ( '' === $requested_path ) {
			$default_path = $this->default_log_directory( $site_path );
			if ( is_wp_error( $default_path ) ) {
				return $default_path;
			}

			/**
			 * Filter the private directory used for benchmark logs.
			 *
			 * The selected directory must remain outside all web-served WordPress paths.
			 *
			 * @param string $default_path Site-specific default private path.
			 */
			$requested_path = (string) apply_filters( 'sd_ai_agent_benchmark_private_log_dir', $default_path );
		}

		if ( ! path_is_absolute( $requested_path ) ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_path_relative', 'Benchmark log directory must be an absolute path.' );
		}

		$is_symlink = is_link( $requested_path );
		$canonical  = PathCanonicalizer::canonicalize_missing_path( $requested_path );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}
		if ( $is_symlink ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_path_symlink', 'Benchmark log directory cannot be a symbolic link.' );
		}

		$uploads     = wp_upload_dir( null, false );
		$public_dirs = array( ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR );
		if ( ! empty( $uploads['basedir'] ) ) {
			$public_dirs[] = (string) $uploads['basedir'];
		}
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$public_dirs[] = sanitize_text_field( wp_unslash( (string) $_SERVER['DOCUMENT_ROOT'] ) );
		}
		$inferred_document_root = $this->infer_document_root( $site_path, site_url( '/' ) );
		if ( null !== $inferred_document_root ) {
			$public_dirs[] = $inferred_document_root;
		}

		foreach ( array_unique( $public_dirs ) as $public_dir ) {
			$public_path = PathCanonicalizer::canonicalize_missing_path( $public_dir );
			if ( ! is_wp_error( $public_path ) && PathCanonicalizer::path_is_inside( $canonical, $public_path ) ) {
				return new \WP_Error( 'sd_ai_agent_benchmark_log_path_public', 'Benchmark log directory must be outside web-served WordPress paths.' );
			}
		}

		return $this->ensure_secure_directory( $canonical );
	}

	/**
	 * Build a private default beneath the WP-CLI user's state directory.
	 *
	 * @param string $site_path Canonical WordPress installation path.
	 * @return string|\WP_Error Private default path or an error requiring --log-dir.
	 */
	private function default_log_directory( string $site_path ): string|\WP_Error {
		$state_home = getenv( 'XDG_STATE_HOME' );
		if ( false === $state_home || '' === trim( $state_home ) ) {
			$home = getenv( 'HOME' );
			if ( false === $home || '' === trim( $home ) ) {
				return new \WP_Error(
					'sd_ai_agent_benchmark_log_default_unavailable',
					'Could not resolve private benchmark storage. Pass --log-dir with a private absolute path.'
				);
			}
			$state_home = trailingslashit( $home ) . '.local/state';
		}

		if ( ! path_is_absolute( $state_home ) ) {
			return new \WP_Error(
				'sd_ai_agent_benchmark_log_default_unavailable',
				'Private benchmark storage must be absolute. Pass --log-dir with a private absolute path.'
			);
		}

		return trailingslashit( $state_home ) . 'sd-ai-agent/' . self::LOG_DIR_PREFIX
			. substr( hash_hmac( 'sha256', $site_path, wp_salt( 'auth' ) ), 0, 24 );
	}

	/**
	 * Infer a document root when WordPress core is installed in a URL subdirectory.
	 *
	 * @param string $site_path Canonical ABSPATH.
	 * @param string $site_url  WordPress site URL.
	 * @return string|null Inferred document root when path suffixes agree.
	 */
	private function infer_document_root( string $site_path, string $site_url ): ?string {
		$url_path = wp_parse_url( $site_url, PHP_URL_PATH );
		if ( ! is_string( $url_path ) ) {
			return null;
		}

		$url_path  = trim( rawurldecode( $url_path ), '/\\' );
		$site_path = rtrim( str_replace( '\\', '/', $site_path ), '/' );
		if ( '' === $url_path || ! str_ends_with( $site_path, '/' . $url_path ) ) {
			return null;
		}

		$document_root = substr( $site_path, 0, -strlen( '/' . $url_path ) );
		return '' !== $document_root ? $document_root : '/';
	}

	/**
	 * Create a directory and require owner-only access.
	 *
	 * @param string $directory Absolute directory path.
	 * @return string|\WP_Error Canonical directory or an error.
	 */
	private function ensure_secure_directory( string $directory ): string|\WP_Error {
		$existed = is_dir( $directory );
		if ( ! $existed ) {
			$previous_umask = umask( 0077 );
			try {
				$created = wp_mkdir_p( $directory );
			} finally {
				umask( $previous_umask );
			}
			if ( ! $created ) {
				return new \WP_Error(
					'sd_ai_agent_benchmark_log_unavailable',
					"Could not create benchmark log directory: {$directory}. Pass --log-dir with a writable private absolute path."
				);
			}
		}
		if ( ! is_dir( $directory ) ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_unavailable', "Benchmark log path is not a directory: {$directory}" );
		}

		if ( ! $existed ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Benchmark logs require owner-only filesystem access.
			if ( ! chmod( $directory, 0700 ) ) {
				return new \WP_Error( 'sd_ai_agent_benchmark_log_permissions', 'Could not secure benchmark log directory.' );
			}
		}
		clearstatcache( true, $directory );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fileperms -- Verifying owner-only access.
		$permissions = fileperms( $directory );
		if ( false === $permissions || 0 !== ( $permissions & 0077 ) ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_permissions', 'Benchmark log directory permissions must deny group and public access.' );
		}
		if ( function_exists( 'posix_geteuid' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fileowner -- Preventing shared-host directory substitution.
			$owner = fileowner( $directory );
			if ( false === $owner || $owner !== posix_geteuid() ) {
				return new \WP_Error( 'sd_ai_agent_benchmark_log_owner', 'Benchmark log directory must be owned by the current process user.' );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Validating operator log storage.
		if ( ! is_writable( $directory ) ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_unavailable', 'Benchmark log directory is not writable.' );
		}

		return untrailingslashit( $directory );
	}

	/**
	 * Write a redacted JSON log with owner-only permissions.
	 *
	 * @param string               $path Absolute log file path.
	 * @param array<string, mixed> $data Log payload.
	 * @return true|\WP_Error True on success or an error.
	 */
	private function write_json_log( string $path, array $data ): true|\WP_Error {
		$json = wp_json_encode( $this->scrub_sensitive_log_values( $data ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_encode', 'Could not encode benchmark log data.' );
		}

		$previous_umask = umask( 0077 );
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem is not initialized in CLI context.
			$bytes_written = file_put_contents( $path, $json, LOCK_EX );
		} finally {
			umask( $previous_umask );
		}
		if ( false === $bytes_written ) {
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove a failed partial log write.
				unlink( $path );
			}
			return new \WP_Error( 'sd_ai_agent_benchmark_log_write', "Could not write benchmark log: {$path}" );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Benchmark logs require owner-only filesystem access.
		if ( ! chmod( $path, 0600 ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove a log that could not be secured.
			unlink( $path );
			return new \WP_Error( 'sd_ai_agent_benchmark_log_permissions', "Could not secure benchmark log: {$path}" );
		}

		return true;
	}

	/**
	 * Recursively remove credential-bearing values from durable benchmark logs.
	 *
	 * @param mixed       $value Current value.
	 * @param string|null $key   Parent key when available.
	 * @return mixed Scrubbed value.
	 */
	private function scrub_sensitive_log_values( mixed $value, ?string $key = null ): mixed {
		if ( null !== $key && $this->is_sensitive_log_key( $key ) ) {
			return self::REDACTED;
		}

		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( is_array( $value ) ) {
			$scrubbed = array();
			foreach ( $value as $child_key => $child_value ) {
				$scrubbed[ $child_key ] = $this->scrub_sensitive_log_values( $child_value, is_string( $child_key ) ? $child_key : null );
			}
			return $scrubbed;
		}

		if ( is_string( $value ) ) {
			$patterns = array(
				'/\bBearer\s+[A-Za-z0-9\-._~+\/=]{12,}/i',
				'/\bBasic\s+[A-Za-z0-9+\/=]{12,}/i',
				'/["\']?(?:api[_-]?key|access[_-]?token|refresh[_-]?token|auth[_-]?token|password|passwd|secret|credentials?)["\']?\s*[=:]\s*["\']?[^\s&"\',}]{3,}["\']?/i',
				'/\bsk-(?:proj-|ant-)?[A-Za-z0-9_-]{16,}\b/i',
				'/\b(?:gh[oprsu]_[A-Za-z0-9_]{16,}|xox[a-z]-[A-Za-z0-9-]{16,}|AIza[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16})\b/',
				'/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/',
				'/-----BEGIN(?: [A-Z0-9]+)? PRIVATE KEY-----.*?-----END(?: [A-Z0-9]+)? PRIVATE KEY-----/s',
			);
			return (string) preg_replace( $patterns, self::REDACTED, $value );
		}

		return $value;
	}

	/** Determine whether a structured log key conventionally contains a secret. */
	private function is_sensitive_log_key( string $key ): bool {
		// Split camelCase (apiKey, refreshToken, clientSecret, APIKey) before matching.
		$key = (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '_', $key );
		$key = strtolower( str_replace( array( '-', ' ' ), '_', $key ) );
		if ( 'token_usage' === $key ) {
			return false;
		}

		return in_array( $key, array( 'token', 'authorization', 'auth', 'cookie', 'nonce', 'credentials' ), true )
			|| (bool) preg_match( '/(?:^|_)(?:password|passwd|passphrase|secret|api_key|private_key|client_secret|credentials?)(?:_|$)/', $key )
			|| str_ends_with( $key, '_token' )
			|| str_ends_with( $key, '_nonce' )
			|| str_ends_with( $key, '_salt' );
	}

	// ── Question execution ────────────────────────────────────────────────────

	/**
	 * Run a single benchmark question through the full agent loop.
	 *
	 * Creates a uniquely-named sandbox plugin directory, passes it to the
	 * agent as context, runs the loop, then runs assertions and cleans up.
	 *
	 * @param array<string, mixed> $question    Question definition.
	 * @param string               $provider_id Provider ID.
	 * @param string               $model_id    Model ID.
	 * @param bool                 $no_cleanup  Whether to keep generated plugins.
	 * @return array<string, mixed> Full result including agent output, tool log, and assertions.
	 */
	protected function run_question( array $question, string $provider_id, string $model_id, bool $no_cleanup = false ): array {
		$q_id       = (string) $question['id'];
		$start_time = microtime( true );
		$log        = array(
			'question_id'         => $q_id,
			'category'            => $question['category'],
			'prompt'              => $question['prompt'],
			'benchmark_dimension' => (string) ( $question['benchmark_dimension'] ?? 'capability' ),
			'provider_id'         => $provider_id,
			'model_id'            => $model_id,
			'started_at'          => gmdate( 'c' ),
			'execution_profile'  => $this->get_execution_profile_metadata(),
		);

		$execution = isset( $question['execution'] ) && is_array( $question['execution'] ) ? $question['execution'] : array();
		if ( 'deferred' === ( $execution['status'] ?? '' ) ) {
			$log['status']          = 'deferred';
			$log['deferred_reason'] = (string) ( $execution['reason'] ?? 'unsupported_execution_contract' );
			$log['deferred_detail'] = (string) ( $execution['detail'] ?? '' );
			$log['depends_on']      = array_values( (array) ( $question['depends_on'] ?? array() ) );
			$log['assertions']      = array(
				'passed'  => 0,
				'failed'  => 0,
				'total'   => 0,
				'results' => array(),
			);
			$log['completed_at']    = gmdate( 'c' );
			return $log;
		}

		if ( self::PROFILE_PRODUCTION_PARITY === $this->executionProfile && $this->question_requires_browser_client( $question ) ) {
			$log['status']          = 'deferred';
			$log['deferred_reason'] = 'browser_client_unavailable';
			$log['deferred_detail'] = 'The server-only WP-CLI profile cannot execute or verify browser client tools.';
			$log['assertions']      = array(
				'passed'  => 0,
				'failed'  => 0,
				'total'   => 0,
				'results' => array(),
			);
			$log['completed_at'] = gmdate( 'c' );
			return $log;
		}

		$missing_prerequisites = $this->find_missing_prerequisites( $question );
		if ( ! empty( $missing_prerequisites ) ) {
			$log['status']                = 'not_exercised';
			$log['missing_prerequisites'] = $missing_prerequisites;
			$log['assertions']            = array(
				'passed'  => 0,
				'failed'  => 0,
				'total'   => 0,
				'results' => array(),
			);
			$log['completed_at']          = gmdate( 'c' );
			return $log;
		}

		// For plugin-building questions, create an isolated sandbox plugin slug.
		$plugin_slug   = '';
		$needs_plugin  = $this->question_needs_plugin( $question );
		$assertion_ctx = array();

		if ( $needs_plugin ) {
			// Derive expected slug from the prompt (agent is told this name).
			$plugin_slug                  = $this->extract_plugin_slug( $question['prompt'] );
			$assertion_ctx['plugin_slug'] = $plugin_slug;
			$log['plugin_slug']           = $plugin_slug;

			$fixture_claim = $this->claim_plugin_fixture( $plugin_slug );
			if ( is_wp_error( $fixture_claim ) ) {
				$log['status']        = 'sd_ai_agent_benchmark_fixture_collision' === $fixture_claim->get_error_code()
					? 'fixture_collision'
					: 'fixture_error';
				$log['fixture_error'] = $fixture_claim->get_error_message();
				$log['agent_error']   = $fixture_claim->get_error_message();
				$log['assertions']    = array(
					'passed'  => 0,
					'failed'  => 0,
					'total'   => 0,
					'results' => array(),
				);
				$log['completed_at']  = gmdate( 'c' );
				return $log;
			}
		}

		try {
			$setup = $this->prepare_question_fixtures( $question );
			if ( is_wp_error( $setup ) ) {
				$log['status']        = 'fixture_error';
				$log['fixture_error'] = $setup->get_error_message();
				$log['agent_error']   = $setup->get_error_message();
				$log['assertions']    = array(
					'passed'  => 0,
					'failed'  => 0,
					'total'   => 0,
					'results' => array(),
				);
				$log['completed_at']  = gmdate( 'c' );
				return $log;
			}
			$log['setup']           = $setup['log'];
			$question['assertions'] = $this->substitute_fixture_variables( (array) $question['assertions'], $setup['variables'] );

			// Build the prompt — include plugin name guidance for plugin questions.
			$prompt = strtr( (string) $question['prompt'], $setup['variables'] );
			if ( $needs_plugin && '' !== $plugin_slug ) {
				$prompt .= "\n\nIMPORTANT: The plugin slug and directory name must be exactly \"{$plugin_slug}\". The main plugin file must be \"{$plugin_slug}/{$plugin_slug}.php\".";
			}

			// Run the agent loop.
			$agent_result = $this->run_agent_loop( $prompt, $provider_id, $model_id, (int) ( $question['max_turns'] ?? 20 ) );

			$log['turns_used']    = $agent_result['turns_used'] ?? 0;
			$log['token_usage']   = $agent_result['token_usage'] ?? array();
			$log['tool_call_log'] = $agent_result['tool_call_log'] ?? array();
			$log['agent_reply']   = $agent_result['reply'] ?? '';
			$log['elapsed_ms']    = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$this->claim_created_user_fixtures( (array) $log['tool_call_log'] );

			if ( ! empty( $agent_result['error'] ) ) {
				$provider_error = $this->classify_provider_error( (string) $agent_result['error'] );

				$log['agent_error'] = $agent_result['error'];
				$log['status']      = $provider_error ? 'provider_error' : 'agent_error';
				if ( $provider_error ) {
					$log['provider_error'] = $provider_error;
				}
				$log['assertions']   = array(
					'passed'  => 0,
					'failed'  => 0,
					'total'   => 0,
					'results' => array(),
				);
				$log['completed_at'] = gmdate( 'c' );
				return $log;
			}

			// Run assertions against live WordPress state. `rest_api_init` is
			// a WP core hook — not a plugin hook needing a prefix.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP core hook, not a custom plugin hook.
			do_action( 'rest_api_init' );
			$assertion_ctx['tool_call_log'] = $log['tool_call_log'] ?? array();
			$log['assertions']              = AssertionEngine::run( $question['assertions'], $assertion_ctx );
			$log['status']                  = (int) $log['assertions']['failed'] > 0 ? 'assertion_failed' : 'passed';

			$log['completed_at'] = gmdate( 'c' );
			return $log;
		} finally {
			$this->cleanup_owned_post_fixtures();
			$this->cleanup_owned_user_fixtures();
			$this->pending_user_fixtures = array();
			// Always deactivate and remove the sandbox plugin if we created one,
			// even on agent error or thrown exception, so deterministic slugs
			// (e.g. event-manager) don't leak into later questions.
			if ( $needs_plugin && '' !== $plugin_slug && ! $no_cleanup ) {
				$this->cleanup_plugin( $plugin_slug );
			}
		}
	}

	/**
	 * Run the AgentLoop synchronously and collect the full tool call log.
	 *
	 * @param string $prompt      User message.
	 * @param string $provider_id Provider ID.
	 * @param string $model_id    Model ID.
	 * @param int    $max_turns   Maximum agent iterations.
	 * @return array<string, mixed>
	 */
	protected function run_agent_loop( string $prompt, string $provider_id, string $model_id, int $max_turns ): array {
		$tool_call_log = array();

		// Track how many log entries we've seen so the progress callback
		// can print only the newly-added entry each time it fires.
		$last_log_count = 0;

		$options = array(
			'provider_id'       => $provider_id,
			'model_id'          => $model_id,
			'max_iterations'    => $max_turns,
			'progress_callback' => function ( array $full_log ) use ( &$tool_call_log, &$last_log_count ): void {
				// The callback receives the full log on every fire — diff to get new entries.
				$new_entries = array_slice( $full_log, $last_log_count );
				$last_log_count = count( $full_log );

				foreach ( $new_entries as $entry ) {
					$type = $entry['type'] ?? '';
					if ( 'response' === $type ) {
						$response_id = (string) ( $entry['id'] ?? '' );
						for ( $index = count( $tool_call_log ) - 1; $index >= 0; --$index ) {
							if ( $response_id === (string) ( $tool_call_log[ $index ]['id'] ?? '' ) ) {
								$tool_call_log[ $index ]['output'] = $entry['response'] ?? '';
								break;
							}
						}
						continue;
					}
					if ( 'call' !== $type && '' !== $type ) {
						continue;
					}
					$name = $entry['name'] ?? ( $entry['tool'] ?? '?' );
					$tool_call_log[] = array(
						'id'        => (string) ( $entry['id'] ?? '' ),
						'tool'      => $name,
						'input'     => $entry['args'] ?? $entry['input'] ?? $entry['arguments'] ?? array(),
						'output'    => $entry['output'] ?? $entry['result'] ?? '',
						'timestamp' => gmdate( 'c' ),
					);
					WP_CLI::log( '│  → ' . $name );
				}
			},
		);

		if ( self::PROFILE_FUNCTIONAL === $this->executionProfile ) {
			$options['yolo_mode']           = true;
			$options['agent_system_prompt'] = self::SYSTEM_PROMPT;
			// Preserve the legacy benchmark's explicit Tier-1 override.
			$abilities = ToolDiscovery::tier_1_for_run();
		} else {
			$options['approved_once_abilities'] = $this->approvedOnceAbilities;
			// An empty override selects AgentLoop's normal automatic catalog path.
			$abilities = array();
		}

		$loop   = new AgentLoop( $prompt, $abilities, array(), $options );
		$result = $loop->run();

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array(
			'reply'         => (string) ( $result['reply'] ?? '' ),
			'turns_used'    => (int) ( $result['iterations_used'] ?? 0 ),
			'token_usage'   => $result['token_usage'] ?? array(),
			'tool_call_log' => $tool_call_log,
			'exit_reason'   => $result['exit_reason'] ?? '',
		);
	}

	/**
	 * Build the compact per-question summary persisted in suite-summary.json.
	 *
	 * @param array<string, mixed> $result Full question result.
	 * @return array<string, mixed>
	 */
	private function summarize_question_result( array $result ): array {
		$assertions        = isset( $result['assertions'] ) && is_array( $result['assertions'] ) ? $result['assertions'] : array();
		$coverage          = isset( $assertions['coverage'] ) && is_array( $assertions['coverage'] ) ? $assertions['coverage'] : array();
		$dimension         = (string) ( $result['benchmark_dimension'] ?? 'capability' );
		$assessed          = ! empty( $coverage['functionally_verifiable'] );
		$verified          = ! empty( $coverage['functionally_verified'] ) && empty( $result['agent_error'] );
		$functional_status = $assessed ? ( $verified ? 'verified' : 'failed' ) : 'not_assessed';
		if ( ! empty( $result['provider_error'] ) ) {
			$functional_status = 'blocked_provider';
		} elseif ( 'graceful_degradation' === $dimension ) {
			$functional_status = 'graceful_degradation';
		}

		return array(
			'question_id'             => (string) ( $result['question_id'] ?? '' ),
			'category'                => (string) ( $result['category'] ?? '' ),
			'status'                  => (string) ( $result['status'] ?? 'unknown' ),
			'assertions_passed'       => (int) ( $assertions['passed'] ?? 0 ),
			'assertions_failed'       => (int) ( $assertions['failed'] ?? 0 ),
			'assertions_total'        => (int) ( $assertions['total'] ?? 0 ),
			'coverage_classification' => (string) ( $coverage['classification'] ?? 'unavailable' ),
			'postconditions_passed'   => (int) ( $coverage['postcondition_passed'] ?? 0 ),
			'postconditions_failed'   => (int) ( $coverage['postcondition_failed'] ?? 0 ),
			'postconditions_total'    => (int) ( $coverage['postcondition_total'] ?? 0 ),
			'functional_status'       => $functional_status,
			'benchmark_dimension'     => $dimension,
			'ranking_eligible'        => 'capability' === $dimension && $assessed && empty( $result['provider_error'] ) && 'deferred' !== ( $result['status'] ?? '' ),
			'turns_used'              => (int) ( $result['turns_used'] ?? 0 ),
			'elapsed_ms'              => (int) ( $result['elapsed_ms'] ?? 0 ),
			'agent_error'             => (string) ( $result['agent_error'] ?? '' ),
			'provider_error'          => $result['provider_error'] ?? null,
			'deferred_reason'         => (string) ( $result['deferred_reason'] ?? '' ),
			'missing_prerequisites'   => $result['missing_prerequisites'] ?? array(),
			'execution_profile'       => $result['execution_profile'] ?? array(),
		);
	}

	/** Configure the effective actor for the selected execution profile. */
	protected function configure_actor(): void {
		if ( self::PROFILE_PRODUCTION_PARITY === $this->executionProfile ) {
			if ( 0 === get_current_user_id() ) {
				WP_CLI::warning( 'Production-parity profile is running anonymously. Use the global --user option to select an actor.' );
			}
			return;
		}

		$admin_id = 0;
		if ( is_multisite() ) {
			$super_admins = get_super_admins();
			if ( ! empty( $super_admins ) ) {
				$user = get_user_by( 'login', $super_admins[0] );
				if ( $user ) {
					$admin_id = (int) $user->ID;
				}
			}
		}
		if ( 0 === $admin_id ) {
			$admins = get_users(
				array(
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
				)
			);
			if ( ! empty( $admins ) ) {
				$admin_id = (int) $admins[0]->ID;
			}
		}

		if ( $admin_id > 0 ) {
			wp_set_current_user( $admin_id );
			return;
		}

		WP_CLI::warning( 'No administrator user found — abilities may fail with permission errors.' );
	}

	/** Select a profile in focused command tests. */
	protected function set_execution_profile( string $profile, array $approved_once_abilities = array() ): void {
		$this->executionProfile          = $profile;
		$this->approvedOnceAbilities    = $approved_once_abilities;
		$this->executionProfileMetadata = array();
	}

	/** Return safe, comparable metadata for the active execution profile. */
	protected function get_execution_profile_metadata(): array {
		if ( empty( $this->executionProfileMetadata ) ) {
			$this->executionProfileMetadata = $this->build_execution_profile_metadata();
		}

		return $this->executionProfileMetadata;
	}

	/** Build safe, comparable metadata for the active execution profile. */
	private function build_execution_profile_metadata(): array {
		$user  = wp_get_current_user();
		$roles = $user->exists() ? array_values( array_map( 'sanitize_key', $user->roles ) ) : array();

		return array(
			'name'               => $this->executionProfile,
			'effective_actor'    => array(
				'user_id' => (int) $user->ID,
				'roles'   => $roles,
			),
			'approval_mode'      => self::PROFILE_FUNCTIONAL === $this->executionProfile
				? 'global_write_approval'
				: ( empty( $this->approvedOnceAbilities ) ? 'normal' : 'explicit_once' ),
			'prompt_profile'     => self::PROFILE_FUNCTIONAL === $this->executionProfile ? 'custom_functional' : 'default',
			'catalog_resolution' => self::PROFILE_FUNCTIONAL === $this->executionProfile ? 'explicit_tier_1' : 'automatic',
			'browser_client'     => array(
				'available' => false,
				'mode'      => 'server_only',
			),
		);
	}

	/** Parse a comma-separated explicit approval list. */
	private function parse_approved_abilities( string $value ): array {
		$names = array_map( 'trim', explode( ',', $value ) );
		$names = array_filter( $names, static fn( string $name ): bool => '' !== $name );

		return array_values( array_unique( $names ) );
	}

	/** Whether a question requires a browser client that WP-CLI cannot provide. */
	private function question_requires_browser_client( array $question ): bool {
		if ( ! empty( $question['requires_browser_client'] ) ) {
			return true;
		}

		$client_tools = array( 'sd-ai-agent/navigate', 'sd-ai-agent/get-page-html' );
		foreach ( (array) ( $question['assertions'] ?? array() ) as $assertion ) {
			foreach ( (array) ( $assertion['tools'] ?? array() ) as $tool ) {
				$tool = (string) $tool;
				if ( str_starts_with( $tool, 'sd-ai-agent-js/' ) || in_array( $tool, $client_tools, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Return labels for unavailable question prerequisites.
	 *
	 * Unknown prerequisite types fail closed so malformed benchmark metadata
	 * cannot be scored as a model failure.
	 *
	 * @param array<string, mixed> $question Question definition.
	 * @return array<int, string>
	 */
	private function find_missing_prerequisites( array $question ): array {
		$missing = array();

		foreach ( (array) ( $question['requires'] ?? array() ) as $requirement ) {
			if ( ! is_array( $requirement ) ) {
				$missing[] = 'Invalid prerequisite definition';
				continue;
			}

			$type  = (string) ( $requirement['type'] ?? '' );
			$label = (string) ( $requirement['label'] ?? $requirement['plugin'] ?? $type );
			if ( 'plugin' !== $type || ! is_plugin_active( (string) ( $requirement['plugin'] ?? '' ) ) ) {
				$missing[] = '' !== $label ? $label : 'Unknown prerequisite';
			}
		}

		return $missing;
	}

	/**
	 * Classify provider-level errors so model ranking can distinguish blocked
	 * providers from model/agent failures.
	 *
	 * @param string $message Error message returned by the agent/provider layer.
	 * @return array{type: string, http_status: int|null, message: string}|null
	 */
	private function classify_provider_error( string $message ): ?array {
		$lower       = strtolower( $message );
		$http_status = null;

		if ( preg_match( '/\b(?:client|server) error \((\d{3})\)|\bhttp\s*(\d{3})\b/i', $message, $matches ) ) {
			$http_status = (int) ( ! empty( $matches[1] ) ? $matches[1] : ( $matches[2] ?? 0 ) );
		}

		$type = '';
		if ( 402 === $http_status || str_contains( $lower, 'insufficient quota' ) || str_contains( $lower, 'token balance' ) ) {
			$type = 'provider_quota_exhausted';
		} elseif ( 429 === $http_status || str_contains( $lower, 'rate limit' ) || str_contains( $lower, 'too many requests' ) ) {
			$type = 'provider_rate_limited';
		} elseif ( null !== $http_status && $http_status >= 500 ) {
			$type = 'provider_unavailable';
		} elseif ( str_contains( $lower, 'timeout' ) || str_contains( $lower, 'timed out' ) ) {
			$type = 'provider_timeout';
		} elseif ( str_contains( $lower, 'provider' ) && ( str_contains( $lower, 'unavailable' ) || str_contains( $lower, 'not found' ) ) ) {
			$type = 'provider_unavailable';
		}

		if ( '' === $type ) {
			return null;
		}

		return array(
			'type'        => $type,
			'http_status' => $http_status,
			'message'     => $message,
		);
	}

	/**
	 * Determine normalized suite run status.
	 *
	 * @param array<string, int> $totals Suite counters.
	 * @return string
	 */
	private function determine_run_status( array $totals ): string {
		$questions       = (int) ( $totals['questions'] ?? 0 );
		$errors          = (int) ( $totals['errors'] ?? 0 );
		$provider_errors = (int) ( $totals['provider_errors'] ?? 0 );
		$fixture_errors  = (int) ( $totals['fixture_errors'] ?? 0 );
		$failed          = (int) ( $totals['failed'] ?? 0 );
		$deferred        = (int) ( $totals['deferred'] ?? 0 );
		$not_exercised   = (int) ( $totals['not_exercised'] ?? 0 );

		if ( $questions > 0 && $provider_errors === $questions ) {
			return 'blocked_provider';
		}

		if ( $provider_errors > 0 || $fixture_errors > 0 || $deferred > 0 ) {
			return 'inconclusive';
		}

		if ( $not_exercised > 0 ) {
			return $not_exercised === $questions ? 'not_exercised' : 'inconclusive';
		}

		if ( $errors > 0 || $failed > 0 ) {
			return 'partial';
		}

		return 'complete';
	}

	/**
	 * Whether a suite result should be included in customer model rankings.
	 *
	 * @param array<string, int> $totals Suite counters.
	 * @param string             $run_status Normalized run status.
	 * @return bool
	 */
	private function is_ranking_eligible( array $totals, string $run_status ): bool {
		if ( 'complete' !== $run_status && 'partial' !== $run_status ) {
			return false;
		}

		if ( (int) ( $totals['provider_errors'] ?? 0 ) > 0 ) {
			return false;
		}

		if ( (int) ( $totals['not_exercised'] ?? 0 ) > 0 ) {
			return false;
		}

		$questions = (int) ( $totals['questions'] ?? 0 );

		return $questions > 0
			&& $questions === (int) ( $totals['questions_functionally_assessed'] ?? 0 )
			&& 0 === (int) ( $totals['graceful_degradation_questions'] ?? 0 )
			&& ( (int) ( $totals['postconditions_passed'] ?? 0 ) + (int) ( $totals['postconditions_failed'] ?? 0 ) ) > 0;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Determine whether a question requires a sandbox plugin to be created.
	 *
	 * @param array<string, mixed> $question Question definition.
	 * @return bool
	 */
	private function question_needs_plugin( array $question ): bool {
		$plugin_assertion_types = array(
			'plugin_activates',
			'plugin_no_php_errors',
			'file_exists_in_plugin',
			'file_contains',
		);

		foreach ( (array) $question['assertions'] as $assertion ) {
			if ( in_array( $assertion['type'] ?? '', $plugin_assertion_types, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract the expected plugin slug from the prompt text.
	 *
	 * Looks for patterns like: called "event-manager" or called 'event-manager'
	 *
	 * @param string $prompt Prompt text.
	 * @return string Plugin slug, or a generated fallback.
	 */
	private function extract_plugin_slug( string $prompt ): string {
		if ( preg_match( '/called\s+["\']([a-z0-9-]+)["\']/', $prompt, $matches ) ) {
			return $matches[1];
		}

		// Fallback: derive from first words of prompt.
		return 'bench-' . substr( md5( $prompt ), 0, 8 );
	}

	/**
	 * Atomically create and mark a plugin fixture owned by this command instance.
	 *
	 * A pre-existing path is never adopted. Creating the directory ourselves
	 * closes the check/create race and gives cleanup a verifiable ownership token.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return true|\WP_Error
	 */
	private function claim_plugin_fixture( string $plugin_slug ): true|\WP_Error {
		$plugin_dir = $this->plugin_fixture_path( $plugin_slug );
		if ( null === $plugin_dir ) {
			return new \WP_Error(
				'sd_ai_agent_benchmark_fixture_boundary',
				__( 'Benchmark plugin fixture path is outside the plugin directory.', 'superdav-ai-agent' )
			);
		}

		if ( file_exists( $plugin_dir ) || is_link( $plugin_dir ) ) {
			return new \WP_Error(
				'sd_ai_agent_benchmark_fixture_collision',
				sprintf(
					/* translators: %s: plugin slug. */
					__( 'Benchmark skipped because plugin path "%s" already exists and is not owned by this run.', 'superdav-ai-agent' ),
					$plugin_slug
				)
			);
		}

		$directory_mode = defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.PHP.NoSilencedErrors.Discouraged -- Atomic CLI-only fixture claim; failure is returned below.
		if ( ! @mkdir( $plugin_dir, $directory_mode ) ) {
			$error_code = file_exists( $plugin_dir ) || is_link( $plugin_dir )
				? 'sd_ai_agent_benchmark_fixture_collision'
				: 'sd_ai_agent_benchmark_fixture_create_failed';

			return new \WP_Error(
				$error_code,
				sprintf(
					/* translators: %s: plugin slug. */
					__( 'Benchmark could not claim plugin fixture "%s".', 'superdav-ai-agent' ),
					$plugin_slug
				)
			);
		}

		$token       = wp_generate_uuid4();
		$marker_path = $plugin_dir . DIRECTORY_SEPARATOR . self::PLUGIN_OWNER_MARKER;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI-only ownership marker.
		if ( false === file_put_contents( $marker_path, $token, LOCK_EX ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Directory was atomically created above and is still empty.
			rmdir( $plugin_dir );
			return new \WP_Error(
				'sd_ai_agent_benchmark_fixture_create_failed',
				__( 'Benchmark could not write the plugin fixture ownership marker.', 'superdav-ai-agent' )
			);
		}

		$this->owned_plugin_fixtures[ $plugin_slug ] = array(
			'path'  => $plugin_dir,
			'token' => $token,
		);

		return true;
	}

	/**
	 * Resolve a plugin fixture path while keeping it inside the plugin root.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return string|null
	 */
	private function plugin_fixture_path( string $plugin_slug ): ?string {
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $plugin_slug ) ) {
			return null;
		}

		$plugin_root = realpath( WP_PLUGIN_DIR );
		if ( false === $plugin_root || ! is_dir( $plugin_root ) ) {
			return null;
		}

		return $plugin_root . DIRECTORY_SEPARATOR . $plugin_slug;
	}

	/**
	 * Create command-owned fixtures and return prompt substitutions.
	 *
	 * @param array<string, mixed> $question Question definition.
	 * @return array{variables: array<string, string>, log: array<string, mixed>}|\WP_Error
	 */
	private function prepare_question_fixtures( array $question ): array|\WP_Error {
		$setup      = isset( $question['setup'] ) && is_array( $question['setup'] ) ? $question['setup'] : array();
		$draft_post = isset( $setup['draft_post'] ) && is_array( $setup['draft_post'] ) ? $setup['draft_post'] : array();
		$user       = isset( $setup['user'] ) && is_array( $setup['user'] ) ? $setup['user'] : array();
		$variables  = array();
		$setup_log  = array();

		if ( ! empty( $user ) ) {
			$prefix = sanitize_user( (string) ( $user['login_prefix'] ?? '' ), true );
			$domain = sanitize_text_field( (string) ( $user['email_domain'] ?? '' ) );
			if ( '' === $prefix || '' === $domain || ! is_email( 'fixture@' . $domain ) ) {
				return new \WP_Error( 'sd_ai_agent_benchmark_invalid_user_fixture', 'User fixtures require a login prefix and email domain.' );
			}

			$login = $prefix . '-' . strtolower( wp_generate_password( 12, false, false ) );
			if ( get_user_by( 'login', $login ) ) {
				return new \WP_Error( 'sd_ai_agent_benchmark_user_fixture_collision', 'Generated benchmark user fixture already exists.' );
			}

			$email                                 = $login . '@' . $domain;
			$this->pending_user_fixtures[ $login ] = wp_generate_uuid4();
			$variables['{{user_login}}']           = $login;
			$variables['{{user_email}}']           = $email;
			$setup_log['user']                     = array(
				'login' => $login,
				'email' => $email,
			);
		}

		if ( empty( $draft_post ) ) {
			return array(
				'variables' => $variables,
				'log'       => $setup_log,
			);
		}

		$title   = (string) ( $draft_post['title'] ?? '' );
		$content = (string) ( $draft_post['content'] ?? '' );
		if ( '' === $title || '' === $content ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_invalid_post_fixture', 'Draft post fixtures require title and content.' );
		}

		$token   = wp_generate_uuid4();
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
				'meta_input'   => array( '_sd_ai_agent_benchmark_owner' => $token ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->owned_post_fixtures[ (int) $post_id ] = $token;
		$variables['{{post_id}}']                    = (string) $post_id;
		$setup_log['draft_post']                     = array(
			'post_id' => (int) $post_id,
			'status'  => 'draft',
		);

		return array(
			'variables' => $variables,
			'log'       => $setup_log,
		);
	}

	/**
	 * Substitute fixture placeholders throughout assertion definitions.
	 *
	 * @param array<mixed>         $value     Assertion definitions.
	 * @param array<string,string> $variables Fixture substitutions.
	 * @return array<mixed>
	 */
	private function substitute_fixture_variables( array $value, array $variables ): array {
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = $this->substitute_fixture_variables( $item, $variables );
			} elseif ( is_string( $item ) ) {
				$value[ $key ] = strtr( $item, $variables );
			}
		}

		return $value;
	}

	/** Remove only draft fixtures whose ownership marker still matches. */
	private function cleanup_owned_post_fixtures(): void {
		foreach ( $this->owned_post_fixtures as $post_id => $token ) {
			$owner  = get_post_meta( $post_id, '_sd_ai_agent_benchmark_owner', true );
			$status = get_post_status( $post_id );
			if ( $token !== $owner || 'draft' !== $status ) {
				WP_CLI::warning( sprintf( 'Benchmark post fixture ownership or status changed; cleanup skipped: %d', $post_id ) );
				continue;
			}

			wp_delete_post( $post_id, true );
			unset( $this->owned_post_fixtures[ $post_id ] );
		}
	}

	/**
	 * Mark generated users as owned only when the create-user result proves creation.
	 *
	 * @param array<int, array<string, mixed>> $tool_call_log Agent tool calls.
	 */
	private function claim_created_user_fixtures( array $tool_call_log ): void {
		foreach ( $this->pending_user_fixtures as $login => $owner ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user || ! $this->tool_log_proves_user_creation( $tool_call_log, $login, (int) $user->ID ) || metadata_exists( 'user', (int) $user->ID, '_sd_ai_agent_benchmark_owner' ) ) {
				continue;
			}

			update_user_meta( (int) $user->ID, '_sd_ai_agent_benchmark_owner', $owner );
			if ( $owner !== get_user_meta( (int) $user->ID, '_sd_ai_agent_benchmark_owner', true ) ) {
				WP_CLI::warning( sprintf( 'Could not mark benchmark user fixture as owned: %s', $login ) );
				continue;
			}

			$this->owned_user_fixtures[ (int) $user->ID ] = array(
				'login' => $login,
				'owner' => $owner,
			);
			unset( $this->pending_user_fixtures[ $login ] );
		}
	}

	/**
	 * Verify a successful create-user call returned the exact generated user ID.
	 *
	 * @param array<int, array<string, mixed>> $tool_call_log Agent tool calls.
	 * @param string                           $login         Generated login.
	 * @param int                              $user_id       Created user ID.
	 */
	private function tool_log_proves_user_creation( array $tool_call_log, string $login, int $user_id ): bool {
		foreach ( $tool_call_log as $entry ) {
			$tool  = (string) ( $entry['tool'] ?? '' );
			$input = isset( $entry['input'] ) && is_array( $entry['input'] ) ? $entry['input'] : array();
			if ( str_starts_with( $tool, 'wpab__sd-ai-agent__' ) ) {
				$tool = 'sd-ai-agent/' . substr( $tool, strlen( 'wpab__sd-ai-agent__' ) );
			}

			$output = $entry['output'] ?? array();
			if ( is_string( $output ) ) {
				$decoded = json_decode( $output, true );
				$output  = is_array( $decoded ) ? $decoded : array();
			}

			if ( 'sd-ai-agent/ability-call' === $tool ) {
				if ( 'sd-ai-agent/create-user' !== ( $input['ability'] ?? '' ) ) {
					continue;
				}
				$arguments = $input['arguments'] ?? $input['parameters'] ?? array();
				if ( is_string( $arguments ) ) {
					$decoded   = json_decode( $arguments, true );
					$arguments = is_array( $decoded ) ? $decoded : array();
				}
				$input  = is_array( $arguments ) ? $arguments : array();
				$output = is_array( $output['result'] ?? null ) ? $output['result'] : array();
			} elseif ( 'sd-ai-agent/create-user' !== $tool ) {
				continue;
			}

			if ( $login !== (string) ( $input['username'] ?? '' ) ) {
				continue;
			}

			if ( is_array( $output ) && $user_id === (int) ( $output['user_id'] ?? 0 ) ) {
				return true;
			}
		}

		return false;
	}

	/** Delete only users whose ID, login, and ownership marker still match. */
	private function cleanup_owned_user_fixtures(): void {
		foreach ( $this->owned_user_fixtures as $user_id => $fixture ) {
			$user  = get_user_by( 'id', $user_id );
			$owner = get_user_meta( $user_id, '_sd_ai_agent_benchmark_owner', true );
			if ( ! $user || $fixture['login'] !== $user->user_login || $fixture['owner'] !== $owner ) {
				WP_CLI::warning( sprintf( 'Benchmark user fixture ownership changed; cleanup skipped: %s', $fixture['login'] ) );
				continue;
			}

			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			$reassign_user_id = $this->get_cleanup_reassign_user_id( $user_id );
			if ( ! wp_delete_user( $user_id, $reassign_user_id > 0 ? $reassign_user_id : null ) ) {
				WP_CLI::warning( sprintf( 'Could not delete benchmark user fixture: %s', $fixture['login'] ) );
				continue;
			}

			unset( $this->owned_user_fixtures[ $user_id ] );
		}
	}

	/**
	 * Resolve a safe user ID for reassigning fixture-owned content.
	 *
	 * @param int $deleted_user_id User ID being deleted.
	 * @return int Reassignment user ID, or 0 when none is available.
	 */
	private function get_cleanup_reassign_user_id( int $deleted_user_id ): int {
		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 && $current_user_id !== $deleted_user_id ) {
			return $current_user_id;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 2,
				'orderby' => 'ID',
			)
		);

		foreach ( $admins as $admin ) {
			$admin_id = (int) $admin->ID;
			if ( $admin_id !== $deleted_user_id ) {
				return $admin_id;
			}
		}

		return 0;
	}

	/**
	 * Deactivate and delete the sandbox plugin created during a test.
	 *
	 * @param string $plugin_slug Plugin slug.
	 */
	private function cleanup_plugin( string $plugin_slug ): void {
		$ownership  = $this->owned_plugin_fixtures[ $plugin_slug ] ?? null;
		$plugin_dir = $this->plugin_fixture_path( $plugin_slug );
		if ( null === $ownership || null === $plugin_dir || $plugin_dir !== $ownership['path'] || is_link( $plugin_dir ) ) {
			return;
		}

		$marker_path = $plugin_dir . DIRECTORY_SEPARATOR . self::PLUGIN_OWNER_MARKER;
		if ( is_link( $marker_path ) || ! is_file( $marker_path ) ) {
			WP_CLI::warning( sprintf( 'Benchmark plugin fixture ownership could not be verified: %s', $plugin_slug ) );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI-only ownership marker.
		$marker_token = file_get_contents( $marker_path );
		if ( ! is_string( $marker_token ) || ! hash_equals( $ownership['token'], $marker_token ) ) {
			WP_CLI::warning( sprintf( 'Benchmark plugin fixture ownership changed; cleanup skipped: %s', $plugin_slug ) );
			return;
		}

		$plugin_file = "{$plugin_slug}/{$plugin_slug}.php";

		// Deactivate silently.
		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file, true );
		}

		// Remove the directory.
		if ( is_dir( $plugin_dir ) && $this->rmdir_recursive( $plugin_dir ) ) {
			unset( $this->owned_plugin_fixtures[ $plugin_slug ] );
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function rmdir_recursive( string $dir ): bool {
		$boundary    = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		$marker_path = $dir . DIRECTORY_SEPARATOR . self::PLUGIN_OWNER_MARKER;

		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $items as $item ) {
				$path = $item->getPathname();
				if ( $marker_path === $path ) {
					continue;
				}

				if ( ! str_starts_with( $path, $boundary ) ) {
					WP_CLI::warning( sprintf( 'Benchmark cleanup boundary violation: %s', $path ) );
					return false;
				}

				if ( is_link( $path ) || ! is_dir( $path ) ) {
					// Use the literal iterator path. Resolving it would follow symlinks outside the fixture.
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- CLI-only owned fixture cleanup.
					if ( ! unlink( $path ) ) {
						return false;
					}

					continue;
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- CLI-only owned fixture cleanup.
				if ( ! rmdir( $path ) ) {
					return false;
				}
			}
		} catch ( \UnexpectedValueException $exception ) {
			WP_CLI::warning( sprintf( 'Benchmark cleanup could not inspect fixture: %s', $exception->getMessage() ) );
			return false;
		}

		// Delete the ownership marker last so an interrupted cleanup can be retried safely.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- CLI-only owned fixture cleanup.
		if ( ! unlink( $marker_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- CLI-only owned fixture cleanup.
		return rmdir( $dir );
	}
}
