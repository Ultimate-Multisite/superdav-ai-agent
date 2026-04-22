<?php
/**
 * Test case for the rewritten ToolDiscovery auto-discovery layer.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Tools;

use GratisAiAgent\Core\IdenticalFailureTracker;
use GratisAiAgent\Tools\AbilityUsageTracker;
use GratisAiAgent\Tools\ToolDiscovery;
use WP_UnitTestCase;

class ToolDiscoveryTest extends WP_UnitTestCase {

	private int $admin_id = 0;

	public function set_up(): void {
		parent::set_up();
		AbilityUsageTracker::reset();
		ToolDiscovery::reset_schema_cache();
		IdenticalFailureTracker::reset();

		// Most abilities require admin caps in their permission callbacks.
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
		grant_super_admin( $this->admin_id );
	}

	public function tear_down(): void {
		parent::tear_down();
		remove_all_filters( 'gratis_ai_agent_ability_usage_instructions' );
		AbilityUsageTracker::reset();
		ToolDiscovery::reset_schema_cache();
		IdenticalFailureTracker::reset();
	}

	// ── tier_1_for_run ────────────────────────────────────────────────

	public function test_tier_1_always_includes_meta_tools(): void {
		$tier_1 = ToolDiscovery::tier_1_for_run();

		$this->assertContains( 'gratis-ai-agent/ability-search', $tier_1 );
		$this->assertContains( 'gratis-ai-agent/ability-call', $tier_1 );
	}

	public function test_tier_1_promotes_recently_used_abilities(): void {
		// Pick an ability that exists in this install.
		AbilityUsageTracker::record( 'gratis-ai-agent/get-plugins' );
		AbilityUsageTracker::record( 'gratis-ai-agent/get-plugins' );
		AbilityUsageTracker::record( 'gratis-ai-agent/get-plugins' );

		$tier_1 = ToolDiscovery::tier_1_for_run();

		$this->assertContains( 'gratis-ai-agent/get-plugins', $tier_1 );
	}

	public function test_tier_1_size_is_capped(): void {
		$tier_1 = ToolDiscovery::tier_1_for_run();

		// Cap is MAX_TIER_1 (15) plus the two meta-tools always added on top.
		$this->assertLessThanOrEqual( ToolDiscovery::MAX_TIER_1 + 2, count( $tier_1 ) );
	}

	// ── ability-search ────────────────────────────────────────────────

	public function test_ability_search_returns_inline_schemas(): void {
		$result = ToolDiscovery::handle_ability_search( [ 'query' => 'plugins' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'results', $result );
		$this->assertNotEmpty( $result['results'] );

		$first = $result['results'][0];
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'input_schema', $first );
		$this->assertArrayHasKey( 'output_schema', $first );
	}

	public function test_ability_search_select_form_returns_exact_matches(): void {
		$result = ToolDiscovery::handle_ability_search(
			[ 'query' => 'select:gratis-ai-agent/get-plugins,gratis-ai-agent/get-themes' ]
		);

		$ids = array_map(
			static function ( $r ) {
				return $r['id'];
			},
			$result['results']
		);

		$this->assertContains( 'gratis-ai-agent/get-plugins', $ids );
		$this->assertContains( 'gratis-ai-agent/get-themes', $ids );
	}

	public function test_ability_search_respects_max_results(): void {
		$result = ToolDiscovery::handle_ability_search(
			[
				'query'       => 'a',
				'max_results' => 3,
			]
		);

		$this->assertLessThanOrEqual( 3, count( $result['results'] ) );
	}

	public function test_ability_search_caches_schemas_for_recently_fetched_section(): void {
		ToolDiscovery::handle_ability_search(
			[ 'query' => 'select:gratis-ai-agent/get-plugins' ]
		);

		$section = ToolDiscovery::recently_fetched_section();
		$this->assertStringContainsString( 'get-plugins', $section );
	}

	// ── ability-call ──────────────────────────────────────────────────

	public function test_ability_call_executes_a_known_ability(): void {
		$result = ToolDiscovery::handle_ability_call(
			[
				'ability'   => 'gratis-ai-agent/get-plugins',
				'arguments' => [],
			]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'result', $result );
	}

	public function test_ability_call_records_usage(): void {
		ToolDiscovery::handle_ability_call(
			[
				'ability'   => 'gratis-ai-agent/get-plugins',
				'arguments' => [],
			]
		);

		$top = AbilityUsageTracker::top( 5 );
		$this->assertContains( 'gratis-ai-agent/get-plugins', $top );
	}

	public function test_ability_call_returns_error_for_unknown_ability(): void {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$result = ToolDiscovery::handle_ability_call(
			[ 'ability' => 'no-such/ability' ]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_ability_call_returns_error_for_malformed_json_arguments(): void {
		$result = ToolDiscovery::handle_ability_call(
			[
				'ability'   => 'gratis-ai-agent/get-plugins',
				'arguments' => '{"title":', // Truncated / malformed JSON.
			]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_ability_arguments', $result->get_error_code() );
	}

	public function test_ability_call_returns_error_for_non_object_json_arguments(): void {
		$result = ToolDiscovery::handle_ability_call(
			[
				'ability'   => 'gratis-ai-agent/get-plugins',
				'arguments' => '"just a string"', // Valid JSON but not an object/array.
			]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_ability_arguments', $result->get_error_code() );
	}

	// ── manifest ──────────────────────────────────────────────────────

	public function test_manifest_lists_tier_2_abilities(): void {
		$manifest = ToolDiscovery::build_manifest_section();

		$this->assertNotEmpty( $manifest );
		$this->assertStringContainsString( '## Available Abilities', $manifest );
	}

	public function test_manifest_uses_usage_instructions_filter(): void {
		add_filter(
			'gratis_ai_agent_ability_usage_instructions',
			static function ( $blocks ) {
				$blocks['gratis-ai-agent'] = 'CUSTOM-INSTRUCTION-MARKER';
				return $blocks;
			}
		);

		$manifest = ToolDiscovery::build_manifest_section();

		$this->assertStringContainsString( 'CUSTOM-INSTRUCTION-MARKER', $manifest );
	}

	public function test_manifest_inlines_required_fields_for_abilities(): void {
		// memory-delete requires `id`. It's not in DEFAULT_TIER_1 so it
		// appears in the manifest, and the line should include "Required: id".
		$manifest = ToolDiscovery::build_manifest_section();

		$this->assertMatchesRegularExpression(
			'/`ai-agent\/memory-delete`.*Required:.*id/',
			$manifest,
			'Manifest line for memory-delete should include "Required: id".'
		);
	}

	// ── validation error self-correction ──────────────────────────────

	public function test_ability_call_inlines_schema_on_validation_error(): void {
		// memory-save requires `category` and `content`. Calling with empty
		// args should produce ability_invalid_input + the input_schema +
		// example_arguments + missing_required_fields.
		$result = ToolDiscovery::handle_ability_call(
			array(
				'ability'   => 'ai-agent/memory-save',
				'arguments' => array(),
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'ability_invalid_input', $result['code'] );
		$this->assertArrayHasKey( 'input_schema', $result );
		$this->assertArrayHasKey( 'hint', $result );
		$this->assertArrayHasKey( 'missing_required_fields', $result );
		$this->assertArrayHasKey( 'example_arguments', $result );

		// example_arguments should contain at least one of the required
		// fields (whichever the validator complains about first).
		$this->assertNotEmpty( $result['example_arguments'] );
	}

	public function test_ability_call_injects_nudge_after_two_identical_failures(): void {
		$args = array();

		// First call: gets the schema/hint but no nudge yet.
		$first = ToolDiscovery::handle_ability_call(
			array(
				'ability'   => 'ai-agent/memory-save',
				'arguments' => $args,
			)
		);
		$this->assertArrayNotHasKey( 'nudge', $first );

		// Second identical call: nudge appears.
		$second = ToolDiscovery::handle_ability_call(
			array(
				'ability'   => 'ai-agent/memory-save',
				'arguments' => $args,
			)
		);
		$this->assertArrayHasKey( 'nudge', $second );
		$this->assertStringContainsString( 'STOP', $second['nudge'] );
		$this->assertStringContainsString( 'ai-agent/memory-save', $second['nudge'] );
	}
}
