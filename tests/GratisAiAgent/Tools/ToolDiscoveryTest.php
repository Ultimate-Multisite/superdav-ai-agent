<?php
/**
 * Test case for ToolDiscovery class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Tools;

use GratisAiAgent\Tools\ToolDiscovery;
use WP_UnitTestCase;

/**
 * Test ToolDiscovery functionality.
 */
class ToolDiscoveryTest extends WP_UnitTestCase {

	/**
	 * Clean up filters after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();
		remove_all_filters( 'gratis_ai_agent_priority_categories' );
		remove_all_filters( 'gratis_ai_agent_priority_tools' );
	}

	// ── get_priority_categories ───────────────────────────────────────────

	/**
	 * Test get_priority_categories returns an array.
	 */
	public function test_get_priority_categories_returns_array(): void {
		$categories = ToolDiscovery::get_priority_categories();
		$this->assertIsArray( $categories );
	}

	/**
	 * Test get_priority_categories includes default categories.
	 */
	public function test_get_priority_categories_includes_defaults(): void {
		$categories = ToolDiscovery::get_priority_categories();

		$this->assertContains( 'gratis-ai-agent', $categories );
		$this->assertContains( 'site', $categories );
		$this->assertContains( 'user', $categories );
	}

	/**
	 * Test get_priority_categories is filterable.
	 */
	public function test_get_priority_categories_is_filterable(): void {
		add_filter(
			'gratis_ai_agent_priority_categories',
			function ( array $cats ): array {
				$cats[] = 'custom-category';
				return $cats;
			}
		);

		$categories = ToolDiscovery::get_priority_categories();

		$this->assertContains( 'custom-category', $categories );
	}

	// ── get_priority_tools ────────────────────────────────────────────────

	/**
	 * Test get_priority_tools returns an array.
	 */
	public function test_get_priority_tools_returns_array(): void {
		$tools = ToolDiscovery::get_priority_tools();
		$this->assertIsArray( $tools );
	}

	/**
	 * Test get_priority_tools includes expected default tools.
	 */
	public function test_get_priority_tools_includes_defaults(): void {
		$tools = ToolDiscovery::get_priority_tools();

		$this->assertContains( 'wpcli/post/create', $tools );
		$this->assertContains( 'wpcli/post/list', $tools );
		$this->assertContains( 'wpcli/media/import', $tools );
	}

	/**
	 * Test get_priority_tools is filterable.
	 */
	public function test_get_priority_tools_is_filterable(): void {
		add_filter(
			'gratis_ai_agent_priority_tools',
			function ( array $tools ): array {
				$tools[] = 'custom/my-priority-tool';
				return $tools;
			}
		);

		$tools = ToolDiscovery::get_priority_tools();

		$this->assertContains( 'custom/my-priority-tool', $tools );
	}

	// ── should_use_discovery_mode ─────────────────────────────────────────

	/**
	 * Test should_use_discovery_mode returns bool.
	 */
	public function test_should_use_discovery_mode_returns_bool(): void {
		$result = ToolDiscovery::should_use_discovery_mode();
		$this->assertIsBool( $result );
	}

	/**
	 * Test should_use_discovery_mode returns false when Abilities API unavailable.
	 */
	public function test_should_use_discovery_mode_false_without_abilities_api(): void {
		// If wp_get_abilities doesn't exist, should return false.
		if ( function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API is available; cannot test unavailable path.' );
		}

		$result = ToolDiscovery::should_use_discovery_mode();
		$this->assertFalse( $result );
	}

	// ── get_discoverable_category_counts ──────────────────────────────────

	/**
	 * Test get_discoverable_category_counts returns array.
	 */
	public function test_get_discoverable_category_counts_returns_array(): void {
		$counts = ToolDiscovery::get_discoverable_category_counts();
		$this->assertIsArray( $counts );
	}

	/**
	 * Test get_discoverable_category_counts returns empty array when Abilities API unavailable.
	 */
	public function test_get_discoverable_category_counts_empty_without_abilities_api(): void {
		if ( function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API is available; cannot test unavailable path.' );
		}

		$counts = ToolDiscovery::get_discoverable_category_counts();
		$this->assertEmpty( $counts );
	}

	// ── get_system_prompt_section ─────────────────────────────────────────

	/**
	 * Test get_system_prompt_section returns a string.
	 */
	public function test_get_system_prompt_section_returns_string(): void {
		$result = ToolDiscovery::get_system_prompt_section();
		$this->assertIsString( $result );
	}

	/**
	 * Test get_system_prompt_section returns empty string when no discoverable tools.
	 */
	public function test_get_system_prompt_section_empty_when_no_discoverable_tools(): void {
		if ( function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API is available; cannot test unavailable path.' );
		}

		$result = ToolDiscovery::get_system_prompt_section();
		$this->assertSame( '', $result );
	}

	// ── handle_list_tools ─────────────────────────────────────────────────

	/**
	 * Test handle_list_tools returns WP_Error when Abilities API unavailable.
	 */
	public function test_handle_list_tools_returns_wp_error_without_abilities_api(): void {
		if ( function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API is available; cannot test unavailable path.' );
		}

		$result = ToolDiscovery::handle_list_tools( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'api_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_list_tools treats sequential array input as empty (no filters).
	 */
	public function test_handle_list_tools_treats_sequential_array_as_empty(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API not available.' );
		}

		// Sequential array (e.g. []) should be treated as no filters.
		$result = ToolDiscovery::handle_list_tools( [] );

		// Should return either category_overview or tools list, not an error.
		$this->assertIsArray( $result );
	}

	// ── handle_execute_tool ───────────────────────────────────────────────

	/**
	 * Test handle_execute_tool returns WP_Error when tool_name is empty.
	 */
	public function test_handle_execute_tool_empty_tool_name(): void {
		$result = ToolDiscovery::handle_execute_tool( [ 'tool_name' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_param', $result->get_error_code() );
	}

	/**
	 * Test handle_execute_tool returns WP_Error when tool_name is missing.
	 */
	public function test_handle_execute_tool_missing_tool_name(): void {
		$result = ToolDiscovery::handle_execute_tool( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_param', $result->get_error_code() );
	}

	/**
	 * Test handle_execute_tool returns WP_Error when Abilities API unavailable.
	 */
	public function test_handle_execute_tool_returns_wp_error_without_abilities_api(): void {
		if ( function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available; cannot test unavailable path.' );
		}

		$result = ToolDiscovery::handle_execute_tool( [ 'tool_name' => 'some/tool' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'api_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_execute_tool normalises wpab__ prefixed tool names.
	 */
	public function test_handle_execute_tool_normalises_wpab_prefix(): void {
		if ( function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available; normalisation path leads to actual lookup.' );
		}

		// With wpab__ prefix, the tool name should be normalised before lookup.
		// Since the API is unavailable, we expect api_unavailable error (not missing_param).
		$result = ToolDiscovery::handle_execute_tool( [
			'tool_name' => 'wpab__gratis-ai-agent__check-security',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		// Should reach the API check, not the empty-name check.
		$this->assertNotSame( 'missing_param', $result->get_error_code() );
	}

	// ── register ──────────────────────────────────────────────────────────

	/**
	 * Test register adds action hook.
	 */
	public function test_register_adds_action_hook(): void {
		ToolDiscovery::register();

		$this->assertGreaterThan(
			0,
			has_action( 'wp_abilities_api_init', [ ToolDiscovery::class, 'register_abilities' ] )
		);
	}
}
