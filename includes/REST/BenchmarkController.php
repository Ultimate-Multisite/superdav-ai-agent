<?php

declare(strict_types=1);
/**
 * REST API controller for Model Benchmarking.
 *
 * Provides endpoints for running benchmarks and retrieving results.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Benchmark\BenchmarkRunner;
use GratisAiAgent\Benchmark\BenchmarkSuite;
use GratisAiAgent\Core\Database;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class BenchmarkController {

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Register REST routes for benchmarking.
	 */
	public static function register_routes(): void {
		$instance = new self();

		// List available test suites.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/suites',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_suites' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Get questions for a suite.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/suites/(?P<slug>[a-z0-9\-_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_get_suite' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// List benchmark runs.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/runs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_runs' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Get a specific benchmark run with results.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/runs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_get_run' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Create a new benchmark run.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/runs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_create_run' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'name'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'test_suite' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'wp-core-v1',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'models'     => array(
						'required' => true,
						'type'     => 'array',
					),
					'question_ids' => array(
						'required' => false,
						'type'     => 'array',
					),
				),
			)
		);

		// Run a single benchmark question (step-by-step execution).
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/runs/(?P<id>\d+)/run-next',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_run_next' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Delete a benchmark run.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/runs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $instance, 'handle_delete_run' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Compare multiple runs.
		register_rest_route(
			self::NAMESPACE,
			'/benchmark/compare',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_compare' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'run_ids' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
	}

	/**
	 * Permission check — admin only.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List available benchmark suites.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_suites(): WP_REST_Response {
		$suites = BenchmarkSuite::list_suites();
		return new WP_REST_Response( $suites );
	}

	/**
	 * Get a specific suite with its questions.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_suite( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );
		$suite = BenchmarkSuite::get_suite( $slug );

		if ( ! $suite ) {
			return new WP_Error(
				'benchmark_suite_not_found',
				__( 'Benchmark suite not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $suite );
	}

	/**
	 * List benchmark runs.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_runs( WP_REST_Request $request ): WP_REST_Response {
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$runs = BenchmarkRunner::list_runs( $per_page, $page );

		return new WP_REST_Response( $runs );
	}

	/**
	 * Get a specific benchmark run with results.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_run( WP_REST_Request $request ) {
		$run_id = absint( $request->get_param( 'id' ) );
		$run    = BenchmarkRunner::get_run( $run_id );

		if ( ! $run ) {
			return new WP_Error(
				'benchmark_run_not_found',
				__( 'Benchmark run not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		$run->results = BenchmarkRunner::get_run_results( $run_id );

		return new WP_REST_Response( $run );
	}

	/**
	 * Create a new benchmark run.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_run( WP_REST_Request $request ) {
		$name        = $request->get_param( 'name' );
		$description = $request->get_param( 'description' );
		$test_suite  = $request->get_param( 'test_suite' );
		$models      = $request->get_param( 'models' );
		$question_ids = $request->get_param( 'question_ids' );

		if ( empty( $models ) || ! is_array( $models ) ) {
			return new WP_Error(
				'benchmark_no_models',
				__( 'At least one model must be selected.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		$run_id = BenchmarkRunner::create_run(
			array(
				'name'         => $name,
				'description'  => $description,
				'test_suite'   => $test_suite,
				'models'       => $models,
				'question_ids' => $question_ids,
			)
		);

		if ( ! $run_id ) {
			return new WP_Error(
				'benchmark_create_failed',
				__( 'Failed to create benchmark run.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$run = BenchmarkRunner::get_run( $run_id );
		return new WP_REST_Response( $run, 201 );
	}

	/**
	 * Run the next pending benchmark question.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_run_next( WP_REST_Request $request ) {
		$run_id = absint( $request->get_param( 'id' ) );

		$run = BenchmarkRunner::get_run( $run_id );
		if ( ! $run ) {
			return new WP_Error(
				'benchmark_run_not_found',
				__( 'Benchmark run not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		if ( $run->status === 'completed' ) {
			return new WP_REST_Response(
				array(
					'status'   => 'completed',
					'progress' => array(
						'completed' => $run->completed_count,
						'total'     => $run->questions_count,
					),
				)
			);
		}

		$result = BenchmarkRunner::run_next_question( $run_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$run = BenchmarkRunner::get_run( $run_id );

		return new WP_REST_Response(
			array(
				'status'   => $run->status,
				'progress' => array(
					'completed' => $run->completed_count,
					'total'     => $run->questions_count,
				),
				'last_result' => $result,
			)
		);
	}

	/**
	 * Delete a benchmark run.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_run( WP_REST_Request $request ) {
		$run_id = absint( $request->get_param( 'id' ) );

		$deleted = BenchmarkRunner::delete_run( $run_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'benchmark_delete_failed',
				__( 'Failed to delete benchmark run.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Compare multiple benchmark runs.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_compare( WP_REST_Request $request ) {
		$run_ids = $request->get_param( 'run_ids' );

		if ( empty( $run_ids ) || ! is_array( $run_ids ) || count( $run_ids ) < 2 ) {
			return new WP_Error(
				'benchmark_compare_invalid',
				__( 'At least two benchmark runs must be selected for comparison.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		$comparison = BenchmarkRunner::compare_runs( $run_ids );

		return new WP_REST_Response( $comparison );
	}
}
