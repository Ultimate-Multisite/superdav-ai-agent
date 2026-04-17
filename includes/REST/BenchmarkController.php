<?php

declare(strict_types=1);
/**
 * REST API controller for Model Benchmarking.
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
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;
use XWP_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages model benchmark runs via REST.
 *
 * Endpoints:
 *  GET    /benchmark/suites               — list available test suites
 *  GET    /benchmark/suites/{slug}        — get a specific suite
 *  GET    /benchmark/runs                 — list benchmark runs
 *  GET    /benchmark/runs/{id}            — get a specific run
 *  POST   /benchmark/runs                 — create a new run
 *  POST   /benchmark/runs/{id}/run-next   — run next question
 *  DELETE /benchmark/runs/{id}            — delete a run
 *  POST   /benchmark/compare              — compare multiple runs
 */
#[REST_Handler(
	namespace: RestController::NAMESPACE,
	basename: 'benchmark',
	container: 'gratis-ai-agent',
)]
final class BenchmarkController extends XWP_REST_Controller {

	/**
	 * Permission check — admin only.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List available benchmark suites.
	 *
	 * @return WP_REST_Response
	 */
	#[REST_Route(
		route: 'suites',
		methods: WP_REST_Server::READABLE,
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: 'suites/(?P<slug>[a-z0-9\-_]+)',
		methods: WP_REST_Server::READABLE,
		vars: 'get_slug_args',
		guard: 'check_permission',
	)]
	public function handle_get_suite( WP_REST_Request $request ) {
		$slug  = $request->get_param( 'slug' );
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
	#[REST_Route(
		route: 'runs',
		methods: WP_REST_Server::READABLE,
		vars: 'get_list_runs_args',
		guard: 'check_permission',
	)]
	public function handle_list_runs( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		$runs = BenchmarkRunner::list_runs( $per_page, $page );

		return new WP_REST_Response( $runs );
	}

	/**
	 * Get a specific benchmark run with results.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	#[REST_Route(
		route: 'runs/(?P<id>\d+)',
		methods: WP_REST_Server::READABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: 'runs',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_create_run_args',
		guard: 'check_permission',
	)]
	public function handle_create_run( WP_REST_Request $request ) {
		$name         = $request->get_param( 'name' );
		$description  = $request->get_param( 'description' );
		$test_suite   = $request->get_param( 'test_suite' );
		$models       = $request->get_param( 'models' );
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
	#[REST_Route(
		route: 'runs/(?P<id>\d+)/run-next',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
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

		if ( ! $run ) {
			return new WP_Error(
				'benchmark_run_not_found',
				__( 'Benchmark run not found after execution.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response(
			array(
				'status'      => $run->status,
				'progress'    => array(
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
	#[REST_Route(
		route: 'runs/(?P<id>\d+)',
		methods: WP_REST_Server::DELETABLE,
		vars: 'get_id_args',
		guard: 'check_permission',
	)]
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
	#[REST_Route(
		route: 'compare',
		methods: WP_REST_Server::CREATABLE,
		vars: 'get_compare_args',
		guard: 'check_permission',
	)]
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

	/**
	 * Schema arguments for slug-based routes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_slug_args(): array {
		return array(
			'slug' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Schema arguments for GET /benchmark/runs (list).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_list_runs_args(): array {
		return array(
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
		);
	}

	/**
	 * Schema arguments for ID-based routes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_id_args(): array {
		return array(
			'id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Schema arguments for POST /benchmark/runs (create).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_create_run_args(): array {
		return array(
			'name'         => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'test_suite'   => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'wp-core-v1',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'models'       => array(
				'required' => true,
				'type'     => 'array',
			),
			'question_ids' => array(
				'required' => false,
				'type'     => 'array',
			),
		);
	}

	/**
	 * Schema arguments for POST /benchmark/compare.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_compare_args(): array {
		return array(
			'run_ids' => array(
				'required' => true,
				'type'     => 'array',
			),
		);
	}
}
