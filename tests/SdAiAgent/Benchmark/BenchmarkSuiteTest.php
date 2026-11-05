<?php

declare(strict_types=1);
/**
 * Test case for benchmark suite definitions.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Benchmark;

use SdAiAgent\Benchmark\BenchmarkSuite;
use WP_UnitTestCase;

/**
 * Test benchmark suite question definitions.
 */
class BenchmarkSuiteTest extends WP_UnitTestCase {

	/** Assertion intent distinguishes smoke coverage from verified outcomes. */
	public function test_assertion_intent_classification_is_conservative(): void {
		$invocation = BenchmarkSuite::inspect_assertion_intent(
			array(
				array( 'type' => 'tool_called' ),
			)
		);
		$mixed      = BenchmarkSuite::inspect_assertion_intent(
			array(
				array( 'type' => 'tool_called' ),
				array( 'type' => 'post_exists' ),
			)
		);
		$composite  = BenchmarkSuite::inspect_assertion_intent(
			array(
				array( 'type' => 'tool_called_or_user_exists' ),
			)
		);

		$this->assertSame( 'invocation_only', $invocation['classification'] );
		$this->assertFalse( $invocation['functionally_verifiable'] );
		$this->assertSame( 'mixed', $mixed['classification'] );
		$this->assertTrue( $mixed['functionally_verifiable'] );
		$this->assertSame( 'composite', $composite['classification'] );
		$this->assertFalse( $composite['functionally_verifiable'] );
	}

	/** Credentialed questions explicitly report graceful-degradation coverage. */
	public function test_credentialed_questions_declare_graceful_degradation_dimension(): void {
		$questions = BenchmarkSuite::get_questions( 'abilities-credentialed-v1' );

		$this->assertNotEmpty( $questions );
		foreach ( $questions as $question ) {
			$this->assertSame( 'graceful_degradation', $question['benchmark_dimension'] ?? '' );
		}
	}

	/**
	 * Test adev-005 targets a file available under the file ability wp-content root.
	 */
	public function test_adev_005_uses_accessible_wp_content_root_file(): void {
		$question = $this->find_question( 'abilities-developer-v1', 'adev-005' );

		$this->assertIsArray( $question );

		$prompt = (string) $question['prompt'];
		$this->assertStringContainsString( 'path "."', $prompt );
		$this->assertStringContainsString( 'pattern "index.php"', $prompt );
		$this->assertStringNotContainsString( '*Abilities.php', $prompt );
	}

	/** Deferred contracts must be explicit instead of producing false model failures. */
	public function test_unexecutable_mutation_contracts_are_deferred(): void {
		$approval = $this->find_question( 'abilities-developer-v1', 'adev-006' );
		$staging  = $this->find_question( 'abilities-plugin-mgmt-v1', 'apm-009' );
		$update   = $this->find_question( 'abilities-plugin-mgmt-v1', 'apm-010' );

		$this->assertSame( 'explicit_approval_profile_required', $approval['execution']['reason'] ?? '' );
		$this->assertSame( 'sandbox_staging_operation_unavailable', $staging['execution']['reason'] ?? '' );
		$this->assertSame( array( 'apm-009' ), $update['depends_on'] ?? array() );
		$this->assertSame( 'deferred', $update['execution']['status'] ?? '' );
	}

	/** SEO analysis must use the registered post-ID schema and command-owned draft setup. */
	public function test_au_007_declares_owned_draft_post_fixture(): void {
		$question = $this->find_question( 'abilities-utility-v1', 'au-007' );

		$this->assertIsArray( $question );
		$this->assertStringContainsString( 'post_id {{post_id}}', (string) $question['prompt'] );
		$this->assertSame( 'AU-007 Remote Work Tools Benchmark Fixture', $question['setup']['draft_post']['title'] ?? '' );
		$this->assertSame( 'tool_output_contains', $question['assertions'][1]['type'] ?? '' );
	}

	/**
	 * Test fn-004 requires benchmark-compatible zero-row WP-CLI output.
	 */
	public function test_fn_004_requires_zero_row_wp_cli_summary_output(): void {
		$question = $this->find_question( 'functional-v1', 'fn-004' );

		$this->assertIsArray( $question );

		$prompt = (string) $question['prompt'];
		$this->assertStringContainsString( 'Processed 0/0 posts', $prompt );
		$this->assertStringContainsString( 'Migration complete: 0 posts updated', $prompt );
		$this->assertStringContainsString( 'do not return early', $prompt );
	}

	/**
	 * Find a benchmark question by suite and ID.
	 *
	 * @param string $suite Suite slug.
	 * @param string $id    Question ID.
	 * @return array<string, mixed>|null
	 */
	private function find_question( string $suite, string $id ): ?array {
		$questions = BenchmarkSuite::get_questions( $suite );

		foreach ( $questions as $candidate ) {
			if ( $id === ( $candidate['id'] ?? '' ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
