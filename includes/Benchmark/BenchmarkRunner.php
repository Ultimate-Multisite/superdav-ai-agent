<?php

declare(strict_types=1);
/**
 * Benchmark runner — manages benchmark execution and results.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Benchmark;

use GratisAiAgent\Core\CredentialResolver;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BenchmarkRunner {

	/**
	 * Create a new benchmark run.
	 *
	 * @param array $data Run configuration.
	 * @return int|false Run ID or false on failure.
	 */
	public static function create_run( array $data ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$suite      = BenchmarkSuite::get_suite( $data['test_suite'] );
		$questions  = $suite ? $suite['questions'] : array();

		// Filter to specific questions if provided.
		if ( ! empty( $data['question_ids'] ) && is_array( $data['question_ids'] ) ) {
			$questions = array_filter(
				$questions,
				function ( $q ) use ( $data ) {
					return in_array( $q['id'], $data['question_ids'], true );
				}
			);
		}

		$questions_count = count( $questions );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write; caching not applicable.
		$result = $wpdb->insert(
			Database::benchmark_runs_table_name(),
			array(
				'user_id'         => get_current_user_id(),
				'name'            => $data['name'],
				'description'     => $data['description'] ?? '',
				'status'          => 'pending',
				'test_suite'      => $data['test_suite'],
				'questions_count' => $questions_count * count( $data['models'] ),
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
				'run_id'     => $run_id,
				'models'     => $data['models'],
				'questions'  => array_values( $questions ),
				'current_q'  => 0,
				'total_q'    => $questions_count * count( $data['models'] ),
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

		$table = Database::benchmark_runs_table_name();
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query.
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query.
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

		$run_config = get_transient( "gratis_ai_benchmark_run_{$run_id}" );

		if ( ! $run_config ) {
			// Try to reconstruct from database.
			return new \WP_Error(
				'benchmark_expired',
				__( 'Benchmark run configuration has expired. Please create a new run.', 'gratis-ai-agent' ),
				array( 'status' => 410 )
			);
		}

		$current_index = $run_config['current_q'];

		if ( $current_index >= $run_config['total_q'] ) {
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
		$questions_per_model = count( $run_config['questions'] );
		$model_index         = (int) floor( $current_index / $questions_per_model );
		$question_index      = $current_index % $questions_per_model;

		$model    = $run_config['models'][ $model_index ];
		$question = $run_config['questions'][ $question_index ];

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
	 * @param array $model    Model configuration.
	 * @param array $question Question data.
	 * @return array
	 */
	private static function benchmark_question( array $model, array $question ): array {
		$start_time = microtime( true );

		$prompt = self::build_prompt( $question );

		// Call the model.
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
	 * @param array $question Question data.
	 * @return string
	 */
	private static function build_prompt( array $question ): string {
	$prompt = "Question: {$question['question']}\n\n";

		if ( ! empty( $question['options'] ) ) {
			$prompt .= "Options:\n";
			foreach ( $question['options'] as $key => $option ) {
				$prompt .= "{$key}. {$option}\n";
			}
			$prompt .= "\n";
		}

		$prompt .= "Respond with only the letter of the correct answer (A, B, C, or D). Do not include any explanation.";

		return $prompt;
	}

	/**
	 * Call a model to get a response.
	 *
	 * @param array  $model  Model configuration.
	 * @param string $prompt Prompt text.
	 * @return array|\WP_Error
	 */
	private static function call_model( array $model, string $prompt ) {
		$provider_id = $model['provider_id'] ?? '';
		$model_id    = $model['model_id'] ?? '';

		// For built-in WordPress AI Client.
		if ( function_exists( 'wp_ai_client_prompt' ) && empty( $provider_id ) ) {
			return self::call_wp_ai_client( $model_id, $prompt );
		}

		// For direct provider calls.
		switch ( $provider_id ) {
			case 'anthropic':
				return self::call_anthropic( $model_id, $prompt );
			case 'openai':
				return self::call_openai( $model_id, $prompt );
			case 'google':
				return self::call_google( $model_id, $prompt );
			default:
				return new \WP_Error(
					'benchmark_unknown_provider',
					__( 'Unknown provider specified.', 'gratis-ai-agent' )
				);
		}
	}

	/**
	 * Call WordPress AI Client.
	 *
	 * @param string $model_id Model identifier.
	 * @param string $prompt   Prompt text.
	 * @return array|\WP_Error
	 */
	private static function call_wp_ai_client( string $model_id, string $prompt ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'benchmark_no_ai_client',
				__( 'WordPress AI Client not available.', 'gratis-ai-agent' )
			);
		}

		$messages = array(
			array( 'role' => 'user', 'content' => $prompt ),
		);

		$response = wp_ai_client_prompt( $messages, array( 'model' => $model_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = '';
		if ( isset( $response['content'] ) ) {
			if ( is_string( $response['content'] ) ) {
				$content = $response['content'];
			} elseif ( is_array( $response['content'] ) && isset( $response['content'][0]['text'] ) ) {
				$content = $response['content'][0]['text'];
			}
		}

		return array(
			'content'           => $content,
			'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
		);
	}

	/**
	 * Call Anthropic API directly.
	 *
	 * @param string $model_id Model identifier.
	 * @param string $prompt   Prompt text.
	 * @return array|\WP_Error
	 */
	private static function call_anthropic( string $model_id, string $prompt ) {
		$api_key = Settings::get_provider_key( 'anthropic' );
		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'benchmark_no_anthropic_key',
				__( 'Anthropic API key not configured.', 'gratis-ai-agent' )
			);
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-API-Key'     => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => (string) wp_json_encode(
					array(
						'model'     => $model_id,
						'max_tokens' => 10,
						'messages'  => array(
							array( 'role' => 'user', 'content' => $prompt ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				'benchmark_anthropic_error',
				$body['error']['message'] ?? 'Unknown error'
			);
		}

		return array(
			'content'           => $body['content'][0]['text'] ?? '',
			'prompt_tokens'     => $body['usage']['input_tokens'] ?? 0,
			'completion_tokens' => $body['usage']['output_tokens'] ?? 0,
		);
	}

	/**
	 * Call OpenAI API directly.
	 *
	 * @param string $model_id Model identifier.
	 * @param string $prompt   Prompt text.
	 * @return array|\WP_Error
	 */
	private static function call_openai( string $model_id, string $prompt ) {
		$api_key = Settings::get_provider_key( 'openai' );
		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'benchmark_no_openai_key',
				__( 'OpenAI API key not configured.', 'gratis-ai-agent' )
			);
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => (string) wp_json_encode(
					array(
						'model'     => $model_id,
						'max_tokens' => 10,
						'messages'  => array(
							array( 'role' => 'user', 'content' => $prompt ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				'benchmark_openai_error',
				$body['error']['message'] ?? 'Unknown error'
			);
		}

		return array(
			'content'           => $body['choices'][0]['message']['content'] ?? '',
			'prompt_tokens'     => $body['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
		);
	}

	/**
	 * Call Google Gemini API directly.
	 *
	 * @param string $model_id Model identifier.
	 * @param string $prompt   Prompt text.
	 * @return array|\WP_Error
	 */
	private static function call_google( string $model_id, string $prompt ) {
		$api_key = Settings::get_provider_key( 'google' );
		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'benchmark_no_google_key',
				__( 'Google API key not configured.', 'gratis-ai-agent' )
			);
		}

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . $model_id . ':generateContent?key=' . $api_key,
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => (string) wp_json_encode(
					array(
						'contents' => array(
							array(
								'parts' => array(
									array( 'text' => $prompt ),
								),
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				'benchmark_google_error',
				$body['error']['message'] ?? 'Unknown error'
			);
		}

		$candidates = $body['candidates'] ?? array();
		$content    = '';
		if ( ! empty( $candidates ) && isset( $candidates[0]['content']['parts'][0]['text'] ) ) {
			$content = $candidates[0]['content']['parts'][0]['text'];
		}

		return array(
			'content'           => $content,
			'prompt_tokens'     => $body['usageMetadata']['promptTokenCount'] ?? 0,
			'completion_tokens' => $body['usageMetadata']['candidatesTokenCount'] ?? 0,
		);
	}

	/**
	 * Evaluate if the answer is correct.
	 *
	 * @param string $answer   Model's answer.
	 * @param array  $question Question data.
	 * @return array
	 */
	private static function evaluate_answer( string $answer, array $question ): array {
		// Extract the letter answer from the response.
		preg_match( '/[A-D]/i', trim( $answer ), $matches );
		$extracted_answer = strtoupper( $matches[0] ?? '' );
		$correct_answer   = strtoupper( $question['correct_answer'] );

		$is_correct = $extracted_answer === $correct_answer;
		$score      = $is_correct ? 100 : 0;

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
	 * @param array $run_ids Array of run IDs.
	 * @return array
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
			'runs'       => $runs,
			'summary'    => self::calculate_comparison_summary( $runs ),
			'by_model'   => self::calculate_by_model( $runs ),
			'by_category'=> self::calculate_by_category( $runs ),
		);

		return $comparison;
	}

	/**
	 * Calculate comparison summary.
	 *
	 * @param array $runs Array of run objects.
	 * @return array
	 */
	private static function calculate_comparison_summary( array $runs ): array {
		$summary = array();

		foreach ( $runs as $run ) {
			$results         = $run->results;
			$total           = count( $results );
			$correct         = count(
				array_filter(
					$results,
					function ( $r ) {
						return $r->is_correct;
					}
				)
			);
			$accuracy        = $total > 0 ? round( ( $correct / $total ) * 100, 2 ) : 0;
			$avg_latency     = $total > 0 ? round( array_sum( array_column( $results, 'latency_ms' ) ) / $total, 2 ) : 0;
			$total_tokens    = array_sum( array_column( $results, 'prompt_tokens' ) ) + array_sum( array_column( $results, 'completion_tokens' ) );

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
	 * @param array $runs Array of run objects.
	 * @return array
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
				$by_model[ $model_id ]['total']++;
				if ( $result->is_correct ) {
					$by_model[ $model_id ]['correct']++;
				}
			}
		}

		// Calculate accuracy.
		foreach ( $by_model as &$model ) {
			$model['accuracy'] = $model['total'] > 0 ? round( ( $model['correct'] / $model['total'] ) * 100, 2 ) : 0;
		}

		return array_values( $by_model );
	}

	/**
	 * Calculate stats by category.
	 *
	 * @param array $runs Array of run objects.
	 * @return array
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
				$by_category[ $category ]['total']++;
				if ( $result->is_correct ) {
					$by_category[ $category ]['correct']++;
				}
			}
		}

		// Calculate accuracy.
		foreach ( $by_category as &$category ) {
			$category['accuracy'] = $category['total'] > 0 ? round( ( $category['correct'] / $category['total'] ) * 100, 2 ) : 0;
		}

		return array_values( $by_category );
	}
}
