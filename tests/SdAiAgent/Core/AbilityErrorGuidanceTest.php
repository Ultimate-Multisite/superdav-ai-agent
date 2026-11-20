<?php
/**
 * Test case for AbilityErrorGuidance.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\AbilityErrorGuidance;
use WP_UnitTestCase;

class AbilityErrorGuidanceTest extends WP_UnitTestCase {

	/**
	 * @dataProvider provide_code_rules
	 */
	public function test_default_steps_follow_code_naming_convention( string $code, string $expected_fragment ): void {
		$steps = AbilityErrorGuidance::for_error( $code );

		$this->assertNotEmpty( $steps );
		$this->assertLessThanOrEqual( AbilityErrorGuidance::MAX_STEPS, count( $steps ) );
		$this->assertStringContainsString( $expected_fragment, implode( ' ', $steps ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function provide_code_rules(): array {
		return array(
			'not configured wins over failed' => array( 'pixabay_not_configured', 'not set up' ),
			'rest forbidden'                  => array( 'rest_forbidden', 'not allowed' ),
			'permission beats invalid'        => array( 'invalid_permissions', 'not allowed' ),
			'not found'                       => array( 'sd_ai_agent_file_not_found', 'not found' ),
			'conflict'                        => array( 'sd_ai_agent_commerce_prerequisite_changed', 'current state' ),
			'empty field'                     => array( 'sd_ai_agent_empty_slug', 'required argument' ),
			'invalid value'                   => array( 'sd_ai_agent_invalid_url', 'input_schema' ),
			'external failure'                => array( 'ga_api_request_failed', 'did not complete' ),
			'hyphenated code'                 => array( 'my-plugin-not-found', 'not found' ),
			'unknown code falls back'         => array( 'zzz', 'Read the error message' ),
		);
	}

	public function test_token_match_does_not_fire_inside_other_words(): void {
		// "knot_foundry" contains the letters of "not_found" only across a word boundary.
		$steps = AbilityErrorGuidance::for_error( 'knot_foundry' );

		$this->assertStringContainsString( 'Read the error message', implode( ' ', $steps ) );
	}

	public function test_explicit_steps_override_defaults_and_are_sanitised(): void {
		$long  = str_repeat( 'a', 500 );
		$steps = AbilityErrorGuidance::for_error(
			'sd_ai_agent_file_not_found',
			array(
				'status'     => 404,
				'next_steps' => array( '  Call <b>list-files</b>   first. ', '', 42, $long, 'two', 'three', 'four' ),
			)
		);

		$this->assertSame( 'Call list-files first.', $steps[0] );
		$this->assertCount( AbilityErrorGuidance::MAX_STEPS, $steps );
		$this->assertSame( AbilityErrorGuidance::MAX_STEP_LENGTH, strlen( $steps[1] ) );
	}

	public function test_empty_explicit_steps_fall_back_to_defaults(): void {
		$steps = AbilityErrorGuidance::for_error( 'sd_ai_agent_file_not_found', array( 'next_steps' => array( '', '  ' ) ) );

		$this->assertStringContainsString( 'not found', implode( ' ', $steps ) );
	}

	public function test_other_error_data_keys_are_never_copied(): void {
		$payload = AbilityErrorGuidance::enrich(
			array(
				'error' => 'Nope',
				'code'  => 'x_failed',
			),
			'x_failed',
			array( 'api_key' => 'secret-value' )
		);

		$this->assertStringNotContainsString( 'secret-value', (string) wp_json_encode( $payload ) );
		$this->assertArrayHasKey( 'next_steps', $payload );
	}

	public function test_enrich_leaves_payloads_with_a_hint_or_next_steps_unchanged(): void {
		$with_hint = array(
			'error' => 'bad',
			'code'  => 'ability_invalid_input',
			'hint'  => 'Copy example_arguments.',
		);
		$this->assertSame( $with_hint, AbilityErrorGuidance::enrich( $with_hint, 'ability_invalid_input' ) );

		$with_steps = array(
			'error'      => 'bad',
			'code'       => 'x_failed',
			'next_steps' => array( 'Do this.' ),
		);
		$this->assertSame( $with_steps, AbilityErrorGuidance::enrich( $with_steps, 'x_failed' ) );
	}
}
