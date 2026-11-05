<?php

declare(strict_types=1);
/**
 * WP-CLI benchmark command.
 *
 * Drives the full AI agent loop against live WordPress and writes a structured
 * log file for every question run. No database writes — results live entirely
 * in a private directory outside web-served WordPress paths by default, or in
 * an explicitly supplied private absolute path passed via --log-dir.
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

	/**
	 * Prefix for the site-specific private benchmark directory.
	 *
	 * The directory is a sibling of the WordPress installation by default, not
	 * a child of uploads, wp-content, the document root, or ABSPATH.
	 */
	private const LOG_DIR_PREFIX = '.sd-ai-agent-benchmark-logs-';

	/** Redaction marker used for credential-bearing benchmark values. */
	private const REDACTED = '[redacted]';

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

		$run_id  = gmdate( 'Y-m-d_His' ) . '_' . ( $model_id ?: 'default' );
		$run_dir = $log_dir . '/' . $run_id;

		$run_dir_result = $this->ensure_secure_directory( $run_dir );
		if ( is_wp_error( $run_dir_result ) ) {
			WP_CLI::error( $run_dir_result->get_error_message() );
		}

		// Ensure credentials are loaded for the AI SDK.
		ProviderCredentialLoader::load();

		// Set the current user to an administrator so abilities that gate on
		// current_user_can( 'edit_posts' )/'manage_options' work in CLI context.
		// Without this every ability returns ability_invalid_permissions.
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
		} else {
			WP_CLI::warning( 'No administrator user found — abilities may fail with permission errors.' );
		}

		WP_CLI::log( '' );
		WP_CLI::log( '╔══════════════════════════════════════════════════════════════╗' );
		WP_CLI::log( '║  Superdav AI Agent — Functional Benchmark                    ║' );
		WP_CLI::log( '╚══════════════════════════════════════════════════════════════╝' );
		WP_CLI::log( sprintf( '  Provider : %s', $provider_id ?: 'default' ) );
		WP_CLI::log( sprintf( '  Model    : %s', $model_id ?: 'default' ) );
		WP_CLI::log( sprintf( '  Questions: %d', count( $questions ) ) );
		WP_CLI::log( sprintf( '  Logs     : %s', $run_dir ) );
		WP_CLI::log( '' );

		$totals             = array(
			'passed'          => 0,
			'failed'          => 0,
			'questions'       => 0,
			'question_passed' => 0,
			'errors'          => 0,
			'provider_errors' => 0,
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

			// Print summary.
			if ( ! empty( $result['agent_error'] ) ) {
				$error_label = ! empty( $result['provider_error'] ) ? 'Provider error' : 'Agent error';
				WP_CLI::log( '│  ✗ ' . $error_label . ': ' . $result['agent_error'] );
				++$totals['errors'];
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
		$total_assertions = $totals['passed'] + $totals['failed'];
		$run_status       = $this->determine_run_status( $totals );
		$summary          = array(
			'run_id'            => $run_id,
			'provider_id'       => $provider_id ?: 'default',
			'model_id'          => $model_id ?: 'default',
			'suite'             => $suite_slug,
			'question_count'    => $totals['questions'],
			'questions_passed'  => $totals['question_passed'],
			'agent_errors'      => $totals['errors'],
			'provider_errors'   => $totals['provider_errors'],
			'assertions_passed' => $totals['passed'],
			'assertions_failed' => $totals['failed'],
			'assertions_total'  => $total_assertions,
			'assertion_score'   => $total_assertions > 0 ? round( ( $totals['passed'] / $total_assertions ) * 100 ) : null,
			'run_status'        => $run_status,
			'ranking_eligible'  => $this->is_ranking_eligible( $totals, $run_status ),
			'questions'         => $question_summaries,
		);
		$summary_file     = $run_dir . '/suite-summary.json';
		$write_result     = $this->write_json_log( $summary_file, $summary );
		if ( is_wp_error( $write_result ) ) {
			WP_CLI::error( $write_result->get_error_message() );
		}

		WP_CLI::log( '══════════════════════════════════════════════════════════════' );
		WP_CLI::log( sprintf( '  Questions run    : %d', $totals['questions'] ) );
		WP_CLI::log( sprintf( '  Questions passed : %d', $totals['question_passed'] ) );
		WP_CLI::log( sprintf( '  Agent errors     : %d', $totals['errors'] ) );
		WP_CLI::log( sprintf( '  Provider errors  : %d', $totals['provider_errors'] ) );
		WP_CLI::log( sprintf( '  Assertions       : %d/%d passed', $totals['passed'], $total_assertions ) );
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
			$default_path = trailingslashit( dirname( rtrim( $site_path, '/\\' ) ) )
				. self::LOG_DIR_PREFIX
				. substr( hash_hmac( 'sha256', $site_path, wp_salt( 'auth' ) ), 0, 24 );

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

		foreach ( array_unique( $public_dirs ) as $public_dir ) {
			$public_path = PathCanonicalizer::canonicalize_missing_path( $public_dir );
			if ( ! is_wp_error( $public_path ) && PathCanonicalizer::path_is_inside( $canonical, $public_path ) ) {
				return new \WP_Error( 'sd_ai_agent_benchmark_log_path_public', 'Benchmark log directory must be outside web-served WordPress paths.' );
			}
		}

		return $this->ensure_secure_directory( $canonical );
	}

	/**
	 * Create a directory and require owner-only access.
	 *
	 * @param string $directory Absolute directory path.
	 * @return string|\WP_Error Canonical directory or an error.
	 */
	private function ensure_secure_directory( string $directory ): string|\WP_Error {
		$existed = is_dir( $directory );
		if ( ! $existed && ! wp_mkdir_p( $directory ) ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_unavailable', "Could not create benchmark log directory: {$directory}" );
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem is not initialized in CLI context.
		if ( false === file_put_contents( $path, $json, LOCK_EX ) ) {
			return new \WP_Error( 'sd_ai_agent_benchmark_log_write', "Could not write benchmark log: {$path}" );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Benchmark logs require owner-only filesystem access.
		if ( ! chmod( $path, 0600 ) ) {
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
		if ( null !== $key && preg_match( '/(?:password|passwd|passphrase|secret|api[_-]?key|access[_-]?token|refresh[_-]?token|auth(?:orization)?|cookie|private[_-]?key|client[_-]?secret)/i', $key ) ) {
			return self::REDACTED;
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
				'/\b(?:api[_-]?key|access[_-]?token|refresh[_-]?token|password|secret)\s*[=:]\s*[^\s&"\']{3,}/i',
				'/\bsk-(?:proj-|ant-)?[A-Za-z0-9_-]{16,}\b/i',
				'/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/',
			);
			return (string) preg_replace( $patterns, self::REDACTED, $value );
		}

		return $value;
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
	private function run_question( array $question, string $provider_id, string $model_id, bool $no_cleanup = false ): array {
		$q_id       = (string) $question['id'];
		$start_time = microtime( true );
		$log        = array(
			'question_id' => $q_id,
			'category'    => $question['category'],
			'prompt'      => $question['prompt'],
			'provider_id' => $provider_id,
			'model_id'    => $model_id,
			'started_at'  => gmdate( 'c' ),
		);

		// For plugin-building questions, create an isolated sandbox plugin slug.
		$plugin_slug   = '';
		$needs_plugin  = $this->question_needs_plugin( $question );
		$assertion_ctx = array();

		if ( $needs_plugin ) {
			// Derive expected slug from the prompt (agent is told this name).
			$plugin_slug                  = $this->extract_plugin_slug( $question['prompt'] );
			$assertion_ctx['plugin_slug'] = $plugin_slug;
			$log['plugin_slug']           = $plugin_slug;
		}

		$log['cleanup'] = $this->cleanup_question_fixtures( $question );

		// Build the prompt — include plugin name guidance for plugin questions.
		$prompt = $question['prompt'];
		if ( $needs_plugin && '' !== $plugin_slug ) {
			$prompt .= "\n\nIMPORTANT: The plugin slug and directory name must be exactly \"{$plugin_slug}\". The main plugin file must be \"{$plugin_slug}/{$plugin_slug}.php\".";
		}

		try {
			// Run the agent loop.
			$agent_result = $this->run_agent_loop( $prompt, $provider_id, $model_id, (int) ( $question['max_turns'] ?? 20 ) );

			$log['turns_used']    = $agent_result['turns_used'] ?? 0;
			$log['token_usage']   = $agent_result['token_usage'] ?? array();
			$log['tool_call_log'] = $agent_result['tool_call_log'] ?? array();
			$log['agent_reply']   = $agent_result['reply'] ?? '';
			$log['elapsed_ms']    = (int) ( ( microtime( true ) - $start_time ) * 1000 );

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
	private function run_agent_loop( string $prompt, string $provider_id, string $model_id, int $max_turns ): array {
		$tool_call_log = array();

		// Track how many log entries we've seen so the progress callback
		// can print only the newly-added entry each time it fires.
		$last_log_count = 0;

		$options = array(
			'provider_id'         => $provider_id,
			'model_id'            => $model_id,
			'max_iterations'      => $max_turns,
			'yolo_mode'           => true,  // No confirmation prompts in benchmark.
			'agent_system_prompt' => self::SYSTEM_PROMPT,
			'progress_callback'   => function ( array $full_log ) use ( &$tool_call_log, &$last_log_count ): void {
				// The callback receives the full log on every fire — diff to get new entries.
				$new_entries = array_slice( $full_log, $last_log_count );
				$last_log_count = count( $full_log );

				foreach ( $new_entries as $entry ) {
					$type = $entry['type'] ?? '';
					if ( 'call' !== $type && '' !== $type ) {
						// Skip 'response' rows — we only log calls so the
						// matcher can introspect what the agent invoked.
						continue;
					}
					$name = $entry['name'] ?? ( $entry['tool'] ?? '?' );
					$tool_call_log[] = array(
						'tool'      => $name,
						'input'     => $entry['args'] ?? $entry['input'] ?? $entry['arguments'] ?? array(),
						'output'    => $entry['output'] ?? $entry['result'] ?? '',
						'timestamp' => gmdate( 'c' ),
					);
					WP_CLI::log( '│  → ' . $name );
				}
			},
		);

		// Load all server-side abilities (same as a normal agent run).
		$abilities = ToolDiscovery::tier_1_for_run();

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
		$assertions = isset( $result['assertions'] ) && is_array( $result['assertions'] ) ? $result['assertions'] : array();

		return array(
			'question_id'       => (string) ( $result['question_id'] ?? '' ),
			'category'          => (string) ( $result['category'] ?? '' ),
			'status'            => (string) ( $result['status'] ?? 'unknown' ),
			'assertions_passed' => (int) ( $assertions['passed'] ?? 0 ),
			'assertions_failed' => (int) ( $assertions['failed'] ?? 0 ),
			'assertions_total'  => (int) ( $assertions['total'] ?? 0 ),
			'turns_used'        => (int) ( $result['turns_used'] ?? 0 ),
			'elapsed_ms'        => (int) ( $result['elapsed_ms'] ?? 0 ),
			'agent_error'       => (string) ( $result['agent_error'] ?? '' ),
			'provider_error'    => $result['provider_error'] ?? null,
		);
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
		$failed          = (int) ( $totals['failed'] ?? 0 );

		if ( $questions > 0 && $provider_errors === $questions ) {
			return 'blocked_provider';
		}

		if ( $provider_errors > 0 ) {
			return 'inconclusive';
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

		return (int) ( $totals['questions'] ?? 0 ) > 0 && ( (int) ( $totals['passed'] ?? 0 ) + (int) ( $totals['failed'] ?? 0 ) ) > 0;
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
	 * Remove benchmark-owned fixtures before an agent run.
	 *
	 * Question definitions may declare cleanup fixtures that must not leak from
	 * a previous run into the current attempt. This keeps state-sensitive
	 * ability benchmarks deterministic while restricting destructive cleanup to
	 * explicit, benchmark-owned identifiers.
	 *
	 * @param array<string, mixed> $question Question definition.
	 * @return array{users: array<int, array{login: string, status: string}>}
	 */
	private function cleanup_question_fixtures( array $question ): array {
		$cleanup = isset( $question['cleanup'] ) && is_array( $question['cleanup'] ) ? $question['cleanup'] : array();
		$users   = isset( $cleanup['users'] ) && is_array( $cleanup['users'] ) ? $cleanup['users'] : array();
		$result  = array(
			'users' => array(),
		);

		foreach ( $users as $login ) {
			if ( ! is_scalar( $login ) || '' === (string) $login ) {
				continue;
			}

			$result['users'][] = array(
				'login'  => (string) $login,
				'status' => $this->cleanup_user_fixture( (string) $login ),
			);
		}

		return $result;
	}

	/**
	 * Delete a single benchmark-owned user fixture by login.
	 *
	 * @param string $login User login.
	 * @return string Cleanup status.
	 */
	private function cleanup_user_fixture( string $login ): string {
		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			return 'not-found';
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$reassign_user_id = $this->get_cleanup_reassign_user_id( (int) $user->ID );
		$deleted          = wp_delete_user( (int) $user->ID, $reassign_user_id > 0 ? $reassign_user_id : null );

		if ( ! $deleted ) {
			WP_CLI::warning( sprintf( 'Could not delete benchmark user fixture: %s', $login ) );
			return 'delete-failed';
		}

		return 'deleted';
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
		$plugin_file = "{$plugin_slug}/{$plugin_slug}.php";
		$plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_slug;

		// Deactivate silently.
		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file, true );
		}

		// Remove the directory.
		if ( is_dir( $plugin_dir ) ) {
			$this->rmdir_recursive( $plugin_dir );
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function rmdir_recursive( string $dir ): void {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.unlink_unlink -- CLI-only sandbox cleanup.
			$item->isDir() ? rmdir( $item->getRealPath() ) : unlink( $item->getRealPath() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- CLI-only sandbox cleanup.
		rmdir( $dir );
	}
}
