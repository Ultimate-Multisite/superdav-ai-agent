<?php

declare(strict_types=1);
/**
 * Test case for benchmark assertion engine.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Benchmark;

use SdAiAgent\Benchmark\AssertionEngine;
use SdAiAgent\Benchmark\BenchmarkSuite;
use ReflectionMethod;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Test benchmark assertion behavior.
 */
class AssertionEngineTest extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;

		$wp_rest_server = null;

		$this->remove_benchmark_fixture_plugin( 'sd-ai-agent-init-cpt-fixture' );
		if ( post_type_exists( 'sd_test_event' ) && function_exists( 'unregister_post_type' ) ) {
			unregister_post_type( 'sd_test_event' );
		}

		parent::tear_down();
	}

	/**
	 * Test activation replays init callbacks added when activate_plugin() includes the plugin.
	 */
	public function test_plugin_activation_replays_init_callbacks_registered_during_activation_include(): void {
		$this->write_benchmark_fixture_plugin(
			'sd-ai-agent-init-cpt-fixture',
			<<<'PHP'
<?php
/**
 * Plugin Name: SD AI Agent Init CPT Fixture
 */

add_action( 'init', 'sd_ai_agent_fixture_register_post_type' );

function sd_ai_agent_fixture_register_post_type(): void {
	register_post_type(
		'sd_test_event',
		array(
			'public' => true,
			'label'  => 'SD Test Events',
		)
	);
}
PHP
		);

		$result = AssertionEngine::run(
			array(
				array(
					'type' => 'plugin_activates',
				),
				array(
					'type'      => 'post_type_registered',
					'post_type' => 'sd_test_event',
				),
			),
			array(
				'plugin_slug' => 'sd-ai-agent-init-cpt-fixture',
			)
		);

		$this->assertSame( 2, $result['passed'], (string) wp_json_encode( $result ) );
		$this->assertSame( 0, $result['failed'], (string) wp_json_encode( $result ) );
		$this->assertTrue( $result['results'][1]['pass'] );
	}

	/**
	 * Test REST endpoint registration assertions require an exact route match.
	 */
	public function test_rest_endpoint_registered_rejects_partial_route_matches(): void {
		add_action(
			'rest_api_init',
			static function (): void {
				\register_rest_route(
					'sd-ai-agent-test-partial/v1',
					'/events-old',
					array(
						'methods'             => 'GET',
						'callback'            => static function (): WP_REST_Response {
							return new WP_REST_Response( array( 'ok' => true ) );
						},
						'permission_callback' => '__return_true',
					)
				);
			}
		);

		$result = AssertionEngine::run(
			array(
				array(
					'type'   => 'rest_endpoint_registered',
					'method' => 'GET',
					'path'   => '/sd-ai-agent-test-partial/v1/events',
				),
			)
		);

		$this->assertSame( 0, $result['passed'] );
		$this->assertSame( 1, $result['failed'] );
		$this->assertFalse( $result['results'][0]['pass'] );
		$this->assertSame( 'route not found in REST server', $result['results'][0]['actual'] );
	}

	/**
	 * Test REST endpoint registration assertions accept the exact normalized route.
	 */
	public function test_rest_endpoint_registered_accepts_exact_normalized_route(): void {
		add_action(
			'rest_api_init',
			static function (): void {
				\register_rest_route(
					'sd-ai-agent-test-exact/v1',
					'/events',
					array(
						'methods'             => 'GET',
						'callback'            => static function (): WP_REST_Response {
							return new WP_REST_Response( array( 'ok' => true ) );
						},
						'permission_callback' => '__return_true',
					)
				);
			}
		);

		$result = AssertionEngine::run(
			array(
				array(
					'type'   => 'rest_endpoint_registered',
					'method' => 'GET',
					'path'   => 'sd-ai-agent-test-exact/v1/events/',
				),
			)
		);

		$this->assertSame( 1, $result['passed'] );
		$this->assertSame( 0, $result['failed'] );
		$this->assertTrue( $result['results'][0]['pass'] );
		$this->assertSame( 'found at route: /sd-ai-agent-test-exact/v1/events', $result['results'][0]['actual'] );
	}

	/**
	 * Test WP-CLI assertions target the active local site instead of old fixture URLs.
	 */
	public function test_wp_cli_benchmark_url_normalizes_historical_fixture_host(): void {
		$method = new ReflectionMethod( AssertionEngine::class, 'normalize_wp_cli_benchmark_url' );
		$method->setAccessible( true );

		$command    = 'post list --post_type=page --url=wp-multisite-waas.test';
		$normalized = (string) $method->invoke( null, $command );
		$site_url   = untrailingslashit( (string) preg_replace( '#^https?://#', '', home_url( '/' ) ) );

		$this->assertStringNotContainsString( 'wp-multisite-waas.test', $normalized );
		$this->assertStringContainsString( '--url=' . $site_url, $normalized );
	}

	/**
	 * Test WP-CLI URL normalization only rewrites --url option values.
	 */
	public function test_wp_cli_benchmark_url_leaves_non_url_occurrences_untouched(): void {
		$method = new ReflectionMethod( AssertionEngine::class, 'normalize_wp_cli_benchmark_url' );
		$method->setAccessible( true );

		$command    = 'option get wp-multisite-waas.test --url=wp-multisite-waas.test';
		$normalized = (string) $method->invoke( null, $command );
		$site_url   = untrailingslashit( (string) preg_replace( '#^https?://#', '', home_url( '/' ) ) );

		$this->assertSame( 'option get wp-multisite-waas.test --url=' . $site_url, $normalized );
	}

	/**
	 * Test WP-CLI URL normalization handles separate quoted --url option values.
	 */
	public function test_wp_cli_benchmark_url_normalizes_separate_quoted_url_values(): void {
		$method = new ReflectionMethod( AssertionEngine::class, 'normalize_wp_cli_benchmark_url' );
		$method->setAccessible( true );

		$command    = 'post list --url "https://wp-multisite-waas.test/subsite" --field=ID';
		$normalized = (string) $method->invoke( null, $command );
		$site_url   = untrailingslashit( (string) preg_replace( '#^https?://#', '', home_url( '/' ) ) );

		$this->assertSame( 'post list --url "https://' . $site_url . '/subsite" --field=ID', $normalized );
	}

	/**
	 * Test WP-CLI URL normalization does not rewrite fixture text in URL paths.
	 */
	public function test_wp_cli_benchmark_url_only_rewrites_fixture_host(): void {
		$method = new ReflectionMethod( AssertionEngine::class, 'normalize_wp_cli_benchmark_url' );
		$method->setAccessible( true );

		$command    = 'post list --url=example.test/wp-multisite-waas.test';
		$normalized = (string) $method->invoke( null, $command );

		$this->assertSame( $command, $normalized );
	}

	/**
	 * Test state-sensitive user benchmarks declare run-scoped fixtures.
	 */
	public function test_as_010_declares_run_scoped_user_fixture(): void {
		$questions = BenchmarkSuite::get_questions( 'abilities-structure-v1' );
		$matches   = array_values(
			array_filter(
				$questions,
				static fn( array $question ): bool => 'as-010' === (string) ( $question['id'] ?? '' )
			)
		);

		$this->assertCount( 1, $matches );
		$this->assertArrayNotHasKey( 'cleanup', $matches[0] );
		$this->assertSame( 'as010-user', $matches[0]['setup']['user']['login_prefix'] ?? '' );
		$this->assertSame( '{{user_login}}', $matches[0]['assertions'][3]['login'] ?? '' );
	}

	/**
	 * Test repeated user benchmarks accept an already-satisfied final state.
	 */
	public function test_tool_called_or_user_exists_accepts_existing_role(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'sd_tool_called_or_user_exists',
				'user_email' => 'sd_tool_called_or_user_exists@example.test',
				'role'       => 'editor',
			)
		);

		$this->assertIsInt( $user_id );

		$result = AssertionEngine::run(
			array(
				array(
					'type'  => 'tool_called_or_user_exists',
					'tools' => array( 'sd-ai-agent/update-user-role' ),
					'login' => 'sd_tool_called_or_user_exists',
					'role'  => 'editor',
				),
			),
			array(
				'tool_call_log' => array(),
			)
		);

		$this->assertSame( 1, $result['passed'], (string) wp_json_encode( $result ) );
		$this->assertSame( 0, $result['failed'], (string) wp_json_encode( $result ) );
		$this->assertStringContainsString( 'final state satisfied', $result['results'][0]['actual'] );
		$this->assertSame( 'composite', $result['coverage']['classification'] );
		$this->assertFalse( $result['coverage']['functionally_verifiable'] );
		$this->assertFalse( $result['coverage']['functionally_verified'] );
	}

	/** Invocation coverage remains successful even when the logged call was denied. */
	public function test_denied_tool_call_is_invocation_coverage_not_functional_verification(): void {
		$result = AssertionEngine::run(
			array(
				array(
					'type'  => 'tool_called',
					'tools' => array( 'sd-ai-agent/create-post' ),
				),
			),
			array(
				'tool_call_log' => array(
					array(
						'tool'   => 'sd-ai-agent/create-post',
						'output' => array(
							'code'    => 'ability_invalid_permissions',
							'message' => 'Denied',
						),
					),
				),
			)
		);

		$this->assertSame( 1, $result['passed'] );
		$this->assertSame( 'invocation', $result['results'][0]['evidence_type'] );
		$this->assertSame( 'invocation_only', $result['coverage']['classification'] );
		$this->assertSame( 0, $result['coverage']['postcondition_total'] );
		$this->assertFalse( $result['coverage']['functionally_verified'] );
	}

	/** Tool output assertions require real supported fields, not invocation alone. */
	public function test_tool_output_contains_checks_required_fields(): void {
		$result = AssertionEngine::run(
			array(
				array(
					'type'          => 'tool_output_contains',
					'tools'         => array( 'sd-ai-agent/seo-analyze-content' ),
					'required_keys' => array( 'post_id', 'recommendations' ),
				),
			),
			array(
				'tool_call_log' => array(
					array(
						'tool'   => 'wpab__sd-ai-agent__seo-analyze-content',
						'output' => array(
							'post_id'         => 42,
							'recommendations' => array( 'Add a meta description.' ),
						),
					),
				),
			)
		);

		$this->assertSame( 1, $result['passed'], (string) wp_json_encode( $result ) );
		$this->assertSame( 0, $result['failed'], (string) wp_json_encode( $result ) );
	}

	/** Successful ability-call envelopes expose the dispatched ability result. */
	public function test_tool_output_contains_checks_successful_dispatcher_result(): void {
		$result = $this->run_tool_output_assertion(
			array(
				array(
					'tool'   => 'wpab__sd-ai-agent__ability-call',
					'input'  => array( 'ability' => 'sd-ai-agent/seo-analyze-content' ),
					'output' => array(
						'ability'       => 'sd-ai-agent/seo-analyze-content',
						'success'       => true,
						'result'        => array(
							'post_id'         => 42,
							'recommendations' => array( 'Add a meta description.' ),
						),
						'_skill_context' => array( 'recommendations' => array( 'Diagnostic only.' ) ),
					),
				),
			)
		);

		$this->assertSame( 1, $result['passed'], (string) wp_json_encode( $result ) );
		$this->assertSame( 0, $result['failed'], (string) wp_json_encode( $result ) );
	}

	/** JSON-encoded ability-call envelopes are decoded before result inspection. */
	public function test_tool_output_contains_checks_json_dispatcher_result(): void {
		$result = $this->run_tool_output_assertion(
			array(
				array(
					'tool'   => 'sd-ai-agent/ability-call',
					'input'  => array( 'ability' => 'sd-ai-agent/seo-analyze-content' ),
					'output' => wp_json_encode(
						array(
							'ability' => 'sd-ai-agent/seo-analyze-content',
							'success' => true,
							'result'  => array(
								'post_id'         => 42,
								'recommendations' => array(),
							),
						)
					),
				),
			)
		);

		$this->assertSame( 1, $result['passed'], (string) wp_json_encode( $result ) );
	}

	/** Denied, unrelated, malformed, and diagnostic-only dispatcher output cannot pass. */
	public function test_tool_output_contains_rejects_invalid_dispatcher_output(): void {
		$outputs = array(
			array(
				'ability' => 'sd-ai-agent/seo-analyze-content',
				'success' => false,
				'result'  => array(
					'post_id'         => 42,
					'recommendations' => array(),
				),
			),
			array(
				'ability' => 'other-plugin/seo-analyze-content',
				'success' => true,
				'result'  => array(
					'post_id'         => 42,
					'recommendations' => array(),
				),
			),
			array(
				'ability'       => 'sd-ai-agent/seo-analyze-content',
				'success'       => true,
				'result'        => array( 'post_id' => 42 ),
				'_skill_context' => array( 'recommendations' => array( 'Diagnostic only.' ) ),
			),
			'{"ability":"sd-ai-agent/seo-analyze-content","success":true,"result":',
		);

		foreach ( $outputs as $output ) {
			$result = $this->run_tool_output_assertion(
				array(
					array(
						'tool'   => 'wpab__sd-ai-agent__ability-call',
						'input'  => array( 'ability' => 'sd-ai-agent/seo-analyze-content' ),
						'output' => $output,
					),
				)
			);

			$this->assertSame( 0, $result['passed'], (string) wp_json_encode( $output ) );
			$this->assertSame( 1, $result['failed'], (string) wp_json_encode( $output ) );
		}

		$wrong_dispatcher = $this->run_tool_output_assertion(
			array(
				array(
					'tool'   => 'wpab__other-plugin__ability-call',
					'input'  => array( 'ability' => 'sd-ai-agent/seo-analyze-content' ),
					'output' => array(
						'ability' => 'sd-ai-agent/seo-analyze-content',
						'success' => true,
						'result'  => array(
							'post_id'         => 42,
							'recommendations' => array(),
						),
					),
				),
			)
		);

		$this->assertSame( 0, $wrong_dispatcher['passed'], (string) wp_json_encode( $wrong_dispatcher ) );
	}

	/** A later complete matching call can satisfy the postcondition. */
	public function test_tool_output_contains_checks_all_matching_dispatcher_calls(): void {
		$result = $this->run_tool_output_assertion(
			array(
				array(
					'tool'   => 'wpab__sd-ai-agent__ability-call',
					'input'  => array( 'ability' => 'sd-ai-agent/seo-analyze-content' ),
					'output' => array(
						'ability' => 'sd-ai-agent/seo-analyze-content',
						'success' => true,
						'result'  => array( 'post_id' => 42 ),
					),
				),
				array(
					'tool'   => 'wpab__sd-ai-agent__ability-call',
					'input'  => array( 'ability' => 'sd-ai-agent/seo-analyze-content' ),
					'output' => array(
						'ability' => 'sd-ai-agent/seo-analyze-content',
						'success' => true,
						'result'  => array(
							'post_id'         => 42,
							'recommendations' => array(),
						),
					),
				),
			)
		);

		$this->assertSame( 1, $result['passed'], (string) wp_json_encode( $result ) );
	}

	/**
	 * Run the SEO output assertion against a supplied tool-call log.
	 *
	 * @param array<int, array<string, mixed>> $tool_call_log Tool-call entries.
	 * @return array<string, mixed>
	 */
	private function run_tool_output_assertion( array $tool_call_log ): array {
		return AssertionEngine::run(
			array(
				array(
					'type'          => 'tool_output_contains',
					'tools'         => array( 'sd-ai-agent/seo-analyze-content' ),
					'required_keys' => array( 'post_id', 'recommendations' ),
				),
			),
			array( 'tool_call_log' => $tool_call_log )
		);
	}

	/**
	 * Write a single-file benchmark fixture plugin.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $content Plugin file contents.
	 */
	private function write_benchmark_fixture_plugin( string $slug, string $content ): void {
		$directory = WP_PLUGIN_DIR . '/' . $slug;
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}

		file_put_contents( $directory . '/' . $slug . '.php', $content );
	}

	/**
	 * Remove a benchmark fixture plugin and clear activation state.
	 *
	 * @param string $slug Plugin slug.
	 */
	private function remove_benchmark_fixture_plugin( string $slug ): void {
		$plugin_file = $slug . '/' . $slug . '.php';
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $plugin_file, true );
		}

		$active_plugins = array_filter(
			(array) get_option( 'active_plugins', array() ),
			static fn( $plugin ): bool => $plugin_file !== $plugin
		);
		update_option( 'active_plugins', array_values( $active_plugins ) );

		$file      = WP_PLUGIN_DIR . '/' . $plugin_file;
		$directory = WP_PLUGIN_DIR . '/' . $slug;
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		if ( is_dir( $directory ) ) {
			rmdir( $directory );
		}
	}
}
