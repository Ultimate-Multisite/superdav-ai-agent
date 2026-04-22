<?php

declare(strict_types=1);
/**
 * Benchmark runner — manages benchmark execution and results.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Benchmark;

use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\ProviderCredentialLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BenchmarkRunner {

	/**
	 * Create a new benchmark run.
	 *
	 * @param array<string, mixed> $data Run configuration.
	 * @return int|false Run ID or false on failure.
	 */
	public static function create_run( array $data ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$suite = BenchmarkSuite::get_suite( (string) $data['test_suite'] );
		/** @var array<int, array<string, mixed>> $questions */
		$questions = $suite ? $suite['questions'] : array();

		// Filter to specific questions if provided.
		if ( ! empty( $data['question_ids'] ) && is_array( $data['question_ids'] ) ) {
			/** @var array<int, array<string, mixed>> $questions */
			$questions = array_values(
				array_filter(
					$questions,
					function ( array $q ) use ( $data ): bool {
						return in_array( $q['id'], (array) $data['question_ids'], true );
					}
				)
			);
		}

		$questions_count = count( $questions );
		/** @var array<array<string, mixed>> $models */
		$models = (array) $data['models'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write; caching not applicable.
		$result = $wpdb->insert(
			Database::benchmark_runs_table_name(),
			array(
				'user_id'         => get_current_user_id(),
				'name'            => $data['name'],
				'description'     => $data['description'] ?? '',
				'status'          => 'pending',
				'test_suite'      => $data['test_suite'],
				'questions_count' => $questions_count * count( $models ),
				'completed_count' => 0,
				'started_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$run_id = (int) $wpdb->insert_id;

		// Store run configuration in transient for processing.
		set_transient(
			"gratis_ai_benchmark_run_{$run_id}",
			array(
				'run_id'    => $run_id,
				'models'    => $models,
				'questions' => array_values( $questions ),
				'current_q' => 0,
				'total_q'   => $questions_count * count( $models ),
			),
			HOUR_IN_SECONDS
		);

		return $run_id;
	}

	/**
	 * Get a benchmark run by ID.
	 *
	 * @param int $run_id Run ID.
	 * @return object|null
	 */
	public static function get_run( int $run_id ): ?object {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$run = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				Database::benchmark_runs_table_name(),
				$run_id
			)
		);

		return $run ?: null;
	}

	/**
	 * List benchmark runs with pagination.
	 *
	 * @param int $per_page Items per page.
	 * @param int $page     Current page.
	 * @return array
	 */
	public static function list_runs( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table  = Database::benchmark_runs_table_name();
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query; table name comes from internal Database helper.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY started_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable

		return array(
			'runs'  => $rows,
			'total' => $total,
			'pages' => ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Get results for a benchmark run.
	 *
	 * @param int $run_id Run ID.
	 * @return array
	 */
	public static function get_run_results( int $run_id ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::benchmark_results_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query; table name comes from internal Database helper.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE run_id = %d ORDER BY id ASC",
				$run_id
			)
		);
		// phpcs:enable

		return $results ?: array();
	}

	/**
	 * Run the next pending question in a benchmark.
	 *
	 * @param int $run_id Run ID.
	 * @return array|\WP_Error
	 */
	public static function run_next_question( int $run_id ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		/** @var array<string, mixed>|false $run_config */
		$run_config = get_transient( "gratis_ai_benchmark_run_{$run_id}" );

		if ( ! $run_config || ! is_array( $run_config ) ) {
			// Try to reconstruct from database.
			return new \WP_Error(
				'benchmark_expired',
				__( 'Benchmark run configuration has expired. Please create a new run.', 'gratis-ai-agent' ),
				array( 'status' => 410 )
			);
		}

		$current_index = (int) $run_config['current_q'];

		if ( $current_index >= (int) $run_config['total_q'] ) {
			// Mark as completed.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$wpdb->update(
				Database::benchmark_runs_table_name(),
				array(
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $run_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			delete_transient( "gratis_ai_benchmark_run_{$run_id}" );

			return array( 'status' => 'completed' );
		}

		// Calculate which model and question we're on.
		/** @var array<int, array<string, mixed>> $questions */
		$questions = (array) $run_config['questions'];
		/** @var array<int, array<string, mixed>> $models */
		$models              = (array) $run_config['models'];
		$questions_per_model = count( $questions );
		$model_index         = (int) floor( $current_index / $questions_per_model );
		$question_index      = $current_index % $questions_per_model;

		/** @var array<string, mixed> $model */
		$model = $models[ $model_index ];
		/** @var array<string, mixed> $question */
		$question = $questions[ $question_index ];

		// Update status to running.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$wpdb->update(
			Database::benchmark_runs_table_name(),
			array( 'status' => 'running' ),
			array( 'id' => $run_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Run the benchmark question.
		$result = self::benchmark_question( $model, $question );

		// Store result.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
		$wpdb->insert(
			Database::benchmark_results_table_name(),
			array(
				'run_id'            => $run_id,
				'provider_id'       => $model['provider_id'] ?? '',
				'model_id'          => $model['model_id'] ?? '',
				'question_id'       => $question['id'],
				'question_category' => $question['category'],
				'question_type'     => $question['type'],
				'question'          => $question['question'],
				'correct_answer'    => $question['correct_answer'],
				'model_answer'      => $result['answer'],
				'is_correct'        => $result['is_correct'] ? 1 : 0,
				'score'             => $result['score'],
				'prompt_tokens'     => $result['prompt_tokens'],
				'completion_tokens' => $result['completion_tokens'],
				'latency_ms'        => $result['latency_ms'],
				'error_message'     => $result['error'] ?? '',
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%s', '%s' )
		);

		// Update progress.
		$run_config['current_q'] = $current_index + 1;
		set_transient( "gratis_ai_benchmark_run_{$run_id}", $run_config, HOUR_IN_SECONDS );

		// Update completed count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$wpdb->update(
			Database::benchmark_runs_table_name(),
			array( 'completed_count' => $run_config['current_q'] ),
			array( 'id' => $run_id ),
			array( '%d' ),
			array( '%d' )
		);

		return array(
			'question_id' => $question['id'],
			'model_id'    => $model['model_id'],
			'is_correct'  => $result['is_correct'],
			'score'       => $result['score'],
			'latency_ms'  => $result['latency_ms'],
		);
	}

	/**
	 * Benchmark a single question against a model.
	 *
	 * @param array<string, mixed> $model    Model configuration.
	 * @param array<string, mixed> $question Question data.
	 * @return array<string, mixed>
	 */
	private static function benchmark_question( array $model, array $question ): array {
		$start_time = microtime( true );

		$prompt = self::build_prompt( $question );

		// Call the model (token limits are managed by the SDK internally).
		$response = self::call_model( $model, $prompt );

		$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array(
				'answer'            => '',
				'is_correct'        => false,
				'score'             => 0,
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'latency_ms'        => $latency_ms,
				'error'             => $response->get_error_message(),
			);
		}

		$answer     = $response['content'] ?? '';
		$evaluation = self::evaluate_answer( $answer, $question );

		return array(
			'answer'            => $answer,
			'is_correct'        => $evaluation['is_correct'],
			'score'             => $evaluation['score'],
			'prompt_tokens'     => $response['prompt_tokens'] ?? 0,
			'completion_tokens' => $response['completion_tokens'] ?? 0,
			'latency_ms'        => $latency_ms,
		);
	}

	/**
	 * Build the prompt for a question.
	 *
	 * Generates different prompt formats based on question type:
	 * - knowledge: Multiple-choice, expects a single letter answer.
	 * - open_ended: Free-form response for code generation, debugging,
	 *   reasoning, architecture, and multi-step questions.
	 *
	 * @param array<string, mixed> $question Question data.
	 * @return string
	 */
	private static function build_prompt( array $question ): string {
		$question_type = (string) ( $question['type'] ?? 'knowledge' );

		if ( 'open_ended' === $question_type ) {
			return self::build_open_ended_prompt( $question );
		}

		return self::build_multiple_choice_prompt( $question );
	}

	/**
	 * Build a multiple-choice prompt.
	 *
	 * @param array<string, mixed> $question Question data.
	 * @return string
	 */
	private static function build_multiple_choice_prompt( array $question ): string {
		$prompt = 'Question: ' . (string) $question['question'] . "\n\n";

		if ( ! empty( $question['options'] ) ) {
			$prompt .= "Options:\n";
			foreach ( (array) $question['options'] as $key => $option ) {
				$prompt .= (string) $key . '. ' . (string) $option . "\n";
			}
			$prompt .= "\n";
		}

		$prompt .= 'Respond with only the letter of the correct answer (A, B, C, or D). Do not include any explanation.';

		return $prompt;
	}

	/**
	 * Build an open-ended prompt for complex questions.
	 *
	 * Tailors the system instruction based on the question category
	 * (code_generation, debugging, reasoning, architecture, multi_step)
	 * to elicit the most evaluable response from the model.
	 *
	 * @param array<string, mixed> $question Question data.
	 * @return string
	 */
	private static function build_open_ended_prompt( array $question ): string {
		$category = (string) ( $question['category'] ?? 'reasoning' );

		$category_instructions = array(
			'code_generation' => 'You are a senior WordPress developer. Write production-quality PHP code that follows WordPress coding standards. Include all necessary function calls, hooks, and security measures. Respond with code and brief inline comments only.',
			'debugging'       => 'You are a senior WordPress security auditor. Identify every bug, vulnerability, and code quality issue. For each issue, state the problem, the risk, and the specific fix. Be thorough and precise.',
			'reasoning'       => 'You are a senior WordPress consultant. Provide a structured, step-by-step analysis. Be specific about tools, functions, and queries you would use. Explain your reasoning at each step.',
			'architecture'    => 'You are a senior WordPress architect. Design a robust solution considering scalability, performance, failure modes, and WordPress best practices. Discuss trade-offs explicitly.',
			'multi_step'      => 'You are a senior WordPress developer performing a thorough technical analysis. Address every part of the question systematically. Provide specific code examples where appropriate.',
		);

		$instruction = $category_instructions[ $category ] ?? $category_instructions['reasoning'];

		$prompt  = $instruction . "\n\n";
		$prompt .= (string) $question['question'];

		return $prompt;
	}

	/**
	 * Call a model to get a response.
	 *
	 * All models now route through the WordPress AI SDK (wp_ai_client_prompt),
	 * which handles provider authentication, model resolution, and the agent loop.
	 * The SDK connects to all configured providers (direct API keys, WP SDK
	 * connectors, and OpenAI-compatible endpoints) through a unified interface.
	 *
	 * @param array<string, mixed> $model Model configuration.
	 * @param string               $prompt Prompt text.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function call_model( array $model, string $prompt ): array|\WP_Error {
		$provider_id = $model['provider_id'] ?? '';
		$model_id    = $model['model_id'] ?? '';

		// Always route through the WordPress AI SDK.
		return self::call_wp_ai_client( $provider_id, $model_id, $prompt );
	}

	/**
	 * Call WordPress AI Client SDK.
	 *
	 * Routes through the unified WordPress AI SDK which handles all providers
	 * (direct API keys, WP SDK connectors, OpenAI-compatible endpoints).
	 *
	 * @param string $provider_id Provider identifier (empty = use default).
	 * @param string $model_id   Model identifier (empty = use provider default).
	 * @param string $prompt    Prompt text.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function call_wp_ai_client( string $provider_id, string $model_id, string $prompt ): array|\WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'benchmark_no_ai_client',
				__( 'WordPress AI Client not available. WordPress 7.0+ is required.', 'gratis-ai-agent' )
			);
		}

		// Ensure provider credentials are loaded (same logic the chat uses).
		ProviderCredentialLoader::load();

		$builder = wp_ai_client_prompt( $prompt );

		// Set provider and model through the SDK registry.
		if ( ! empty( $provider_id ) ) {
			try {
				$registry = \WordPress\AiClient\AiClient::defaultRegistry();
				if ( ! $registry->hasProvider( $provider_id ) ) {
					return new \WP_Error(
						'benchmark_invalid_provider',
						sprintf(
							/* translators: %s is the provider ID. */
							__( 'Provider "%s" not found.', 'gratis-ai-agent' ),
							$provider_id
						)
					);
				}
				$builder->using_provider( $provider_id );

				// If specific model requested, use it directly.
				if ( ! empty( $model_id ) ) {
					$model = $registry->getProviderModel( $provider_id, $model_id );
					if ( null === $model ) {
						return new \WP_Error(
							'benchmark_invalid_model',
							sprintf(
								/* translators: %1$s is the model ID, %2$s is the provider ID. */
								__( 'Model "%1$s" not found for provider "%2$s".', 'gratis-ai-agent' ),
								$model_id,
								$provider_id
							)
						);
					}
					$builder->using_model( $model );
				}
			} catch ( \Throwable $e ) {
				return new \WP_Error(
					'benchmark_provider_error',
					$e->getMessage()
				);
			}
		} elseif ( ! empty( $model_id ) ) {
			// Empty provider but specific model - try to resolve via preference.
			try {
				$builder->using_model_preference( $model_id );
			} catch ( \Throwable $e ) {
				return new \WP_Error(
					'benchmark_model_error',
					$e->getMessage()
				);
			}
		}

		$result = $builder->generate_text_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$content           = '';
		$prompt_tokens     = 0;
		$completion_tokens = 0;

		if ( $result instanceof \WordPress\AiClient\Results\DTO\GenerativeAiResult ) {
			$content = $result->toText();
			try {
				$usage             = $result->getTokenUsage();
				$prompt_tokens     = $usage->getPromptTokens();
				$completion_tokens = $usage->getCompletionTokens();
			} catch ( \Throwable $e ) {
				// Token usage not available.
			}
		}

		return array(
			'content'           => $content,
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
		);
	}

	/**
	 * Evaluate if the answer is correct.
	 *
	 * Routes to the appropriate evaluator based on question type:
	 * - knowledge: Binary correct/incorrect based on letter match.
	 * - open_ended: Weighted keyword scoring (0-100 partial credit).
	 *
	 * @param string               $answer   Model's answer.
	 * @param array<string, mixed> $question Question data.
	 * @return array<string, mixed>
	 */
	private static function evaluate_answer( string $answer, array $question ): array {
		$question_type = (string) ( $question['type'] ?? 'knowledge' );

		if ( 'open_ended' === $question_type ) {
			return self::evaluate_open_ended_answer( $answer, $question );
		}

		return self::evaluate_multiple_choice_answer( $answer, $question );
	}

	/**
	 * Evaluate a multiple-choice answer.
	 *
	 * @param string               $answer   Model's answer.
	 * @param array<string, mixed> $question Question data.
	 * @return array<string, mixed>
	 */
	private static function evaluate_multiple_choice_answer( string $answer, array $question ): array {
		// Extract the letter answer from the response.
		preg_match( '/[A-D]/i', trim( $answer ), $matches );
		$extracted_answer = strtoupper( $matches[0] ?? '' );
		$correct_answer   = strtoupper( (string) $question['correct_answer'] );

		$is_correct = $extracted_answer === $correct_answer;
		$score      = $is_correct ? 100 : 0;

		return array(
			'is_correct' => $is_correct,
			'score'      => $score,
		);
	}

	/**
	 * Evaluate an open-ended answer using weighted keyword scoring.
	 *
	 * Each question defines scoring_criteria: an array of keyword patterns
	 * with weights. The score is the sum of matched weights, normalized to
	 * 0-100. Keywords can use pipe-separated alternatives (e.g.
	 * "sanitize_text_field|esc_html") and are matched case-insensitively.
	 *
	 * A score >= 60 is considered "correct" (passing) for comparison
	 * purposes, but the numeric score provides granular differentiation.
	 *
	 * @param string               $answer   Model's answer.
	 * @param array<string, mixed> $question Question data.
	 * @return array<string, mixed>
	 */
	private static function evaluate_open_ended_answer( string $answer, array $question ): array {
		/** @var array<int, array<string, mixed>> $criteria */
		$criteria = (array) ( $question['scoring_criteria'] ?? array() );

		if ( empty( $criteria ) ) {
			// No scoring criteria defined — cannot evaluate.
			return array(
				'is_correct' => false,
				'score'      => 0,
			);
		}

		$total_weight   = 0;
		$matched_weight = 0;

		foreach ( $criteria as $criterion ) {
			$keyword = (string) ( $criterion['keyword'] ?? '' );
			$weight  = (int) ( $criterion['weight'] ?? 0 );

			if ( empty( $keyword ) || $weight <= 0 ) {
				continue;
			}

			$total_weight += $weight;

			// Build regex: keywords may contain pipe-separated alternatives.
			// Escape regex special chars except pipe (used as alternation).
			$parts         = explode( '|', $keyword );
			$escaped_parts = array_map(
				function ( string $part ): string {
					// Escape special regex chars but preserve basic patterns.
					// Allow \s, \w, .*, .+ as intentional regex.
					$escaped = preg_quote( $part, '/' );
					// Restore common regex patterns that were escaped.
					$escaped = str_replace(
						array( '\\\\s', '\\\\w', '\\.\\*', '\\.\\+' ),
						array( '\\s', '\\w', '.*', '.+' ),
						$escaped
					);
					return $escaped;
				},
				$parts
			);
			$pattern       = '/' . implode( '|', $escaped_parts ) . '/i';

			if ( preg_match( $pattern, $answer ) ) {
				$matched_weight += $weight;
			}
		}

		// Normalize to 0-100 scale.
		$score = $total_weight > 0
			? (int) round( ( $matched_weight / $total_weight ) * 100 )
			: 0;

		// A score >= 60 is considered "passing" for binary comparison.
		$is_correct = $score >= 60;

		return array(
			'is_correct' => $is_correct,
			'score'      => $score,
		);
	}

	/**
	 * Delete a benchmark run and its results.
	 *
	 * @param int $run_id Run ID.
	 * @return bool
	 */
	public static function delete_run( int $run_id ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// Delete results first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete(
			Database::benchmark_results_table_name(),
			array( 'run_id' => $run_id ),
			array( '%d' )
		);

		// Delete run.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete(
			Database::benchmark_runs_table_name(),
			array( 'id' => $run_id ),
			array( '%d' )
		);

		delete_transient( "gratis_ai_benchmark_run_{$run_id}" );

		return $result !== false;
	}

	/**
	 * Compare multiple benchmark runs.
	 *
	 * @param array<int|string, mixed> $run_ids Array of run IDs.
	 * @return array<string, mixed>
	 */
	public static function compare_runs( array $run_ids ): array {
		$runs = array();

		foreach ( $run_ids as $run_id ) {
			$run = self::get_run( (int) $run_id );
			if ( $run ) {
				$run->results = self::get_run_results( (int) $run_id );
				$runs[]       = $run;
			}
		}

		// Calculate per-model and per-category statistics.
		$comparison = array(
			'runs'        => $runs,
			'summary'     => self::calculate_comparison_summary( $runs ),
			'by_model'    => self::calculate_by_model( $runs ),
			'by_category' => self::calculate_by_category( $runs ),
		);

		return $comparison;
	}

	/**
	 * Calculate comparison summary.
	 *
	 * @param array<int, object> $runs Array of run objects.
	 * @return array<int, array<string, mixed>>
	 */
	private static function calculate_comparison_summary( array $runs ): array {
		$summary = array();

		foreach ( $runs as $run ) {
			/** @var array<int, object> $results */
			$results      = is_array( $run->results ) ? $run->results : array();
			$total        = count( $results );
			$correct      = count(
				array_filter(
					$results,
					function ( object $r ): bool {
						return (bool) $r->is_correct;
					}
				)
			);
			$accuracy     = $total > 0 ? round( ( $correct / $total ) * 100, 2 ) : 0;
			$avg_latency  = $total > 0 ? round( array_sum( array_column( $results, 'latency_ms' ) ) / $total, 2 ) : 0;
			$total_tokens = array_sum( array_column( $results, 'prompt_tokens' ) ) + array_sum( array_column( $results, 'completion_tokens' ) );

			$summary[] = array(
				'run_id'       => $run->id,
				'run_name'     => $run->name,
				'total'        => $total,
				'correct'      => $correct,
				'accuracy'     => $accuracy,
				'avg_latency'  => $avg_latency,
				'total_tokens' => $total_tokens,
			);
		}

		return $summary;
	}

	/**
	 * Calculate stats by model.
	 *
	 * @param array<int, object> $runs Array of run objects.
	 * @return array<int, array<string, mixed>>
	 */
	private static function calculate_by_model( array $runs ): array {
		$by_model = array();

		foreach ( $runs as $run ) {
			foreach ( $run->results as $result ) {
				$model_id = $result->model_id;
				if ( ! isset( $by_model[ $model_id ] ) ) {
					$by_model[ $model_id ] = array(
						'model_id' => $model_id,
						'total'    => 0,
						'correct'  => 0,
					);
				}
				++$by_model[ $model_id ]['total'];
				if ( $result->is_correct ) {
					++$by_model[ $model_id ]['correct'];
				}
			}
		}

		// Calculate accuracy. Total is always >= 1 here (entries are only created on first result).
		foreach ( $by_model as &$model ) {
			$model['accuracy'] = round( ( $model['correct'] / $model['total'] ) * 100, 2 );
		}

		return array_values( $by_model );
	}

	/**
	 * Calculate stats by category.
	 *
	 * @param array<int, object> $runs Array of run objects.
	 * @return array<int, array<string, mixed>>
	 */
	private static function calculate_by_category( array $runs ): array {
		$by_category = array();

		foreach ( $runs as $run ) {
			foreach ( $run->results as $result ) {
				$category = $result->question_category;
				if ( ! isset( $by_category[ $category ] ) ) {
					$by_category[ $category ] = array(
						'category' => $category,
						'total'    => 0,
						'correct'  => 0,
					);
				}
				++$by_category[ $category ]['total'];
				if ( $result->is_correct ) {
					++$by_category[ $category ]['correct'];
				}
			}
		}

		// Calculate accuracy. Total is always >= 1 here (entries are only created on first result).
		foreach ( $by_category as &$category ) {
			$category['accuracy'] = round( ( $category['correct'] / $category['total'] ) * 100, 2 );
		}

		return array_values( $by_category );
	}
}
