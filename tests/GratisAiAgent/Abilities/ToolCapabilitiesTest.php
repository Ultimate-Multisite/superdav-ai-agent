<?php
/**
 * Test case for ToolCapabilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\ToolCapabilities;
use WP_UnitTestCase;

/**
 * Test ToolCapabilities functionality.
 */
class ToolCapabilitiesTest extends WP_UnitTestCase {

	/**
	 * Test cap_name derives the correct capability name from an ability ID.
	 *
	 * @dataProvider provider_cap_name
	 *
	 * @param string $ability_id   Input ability ID.
	 * @param string $expected_cap Expected capability name.
	 */
	public function test_cap_name( string $ability_id, string $expected_cap ): void {
		$this->assertSame( $expected_cap, ToolCapabilities::cap_name( $ability_id ) );
	}

	/**
	 * Data provider for test_cap_name.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function provider_cap_name(): array {
		return [
			// gratis-ai-agent/ prefix (plugin-specific abilities).
			'memory-save'              => [ 'gratis-ai-agent/memory-save', 'gratis_ai_agent_tool_memory_save' ],
			'memory-list'              => [ 'gratis-ai-agent/memory-list', 'gratis_ai_agent_tool_memory_list' ],
			'memory-delete'            => [ 'gratis-ai-agent/memory-delete', 'gratis_ai_agent_tool_memory_delete' ],
			'db-query'                 => [ 'gratis-ai-agent/db-query', 'gratis_ai_agent_tool_db_query' ],
			'run-php'                  => [ 'gratis-ai-agent/run-php', 'gratis_ai_agent_tool_run_php' ],
			'file-read'                => [ 'gratis-ai-agent/file-read', 'gratis_ai_agent_tool_file_read' ],
			'get-plugins'              => [ 'gratis-ai-agent/get-plugins', 'gratis_ai_agent_tool_get_plugins' ],
			'navigate'                 => [ 'gratis-ai-agent/navigate', 'gratis_ai_agent_tool_navigate' ],
			'seo-audit-url'            => [ 'gratis-ai-agent/seo-audit-url', 'gratis_ai_agent_tool_seo_audit_url' ],
			'content-analyze'          => [ 'gratis-ai-agent/content-analyze', 'gratis_ai_agent_tool_content_analyze' ],
			'markdown-to-blocks'       => [ 'gratis-ai-agent/markdown-to-blocks', 'gratis_ai_agent_tool_markdown_to_blocks' ],
			'stock-image'              => [ 'gratis-ai-agent/stock-image', 'gratis_ai_agent_tool_stock_image' ],
			'generate-image'           => [ 'gratis-ai-agent/generate-image', 'gratis_ai_agent_tool_generate_image' ],
			'custom-tool-with-slashes' => [ 'gratis-ai-agent-custom/my-tool', 'gratis_ai_agent_tool_gratis_ai_agent_custom_my_tool' ],
			// ai-agent/ prefix (WP core built-in abilities — same cap name, different namespace prefix).
			'ai-agent/memory-save'     => [ 'ai-agent/memory-save', 'gratis_ai_agent_tool_memory_save' ],
			'ai-agent/create-post'     => [ 'ai-agent/create-post', 'gratis_ai_agent_tool_create_post' ],
			'ai-agent/update-post'     => [ 'ai-agent/update-post', 'gratis_ai_agent_tool_update_post' ],
			'ai-agent/list-posts'      => [ 'ai-agent/list-posts', 'gratis_ai_agent_tool_list_posts' ],
		];
	}

	/**
	 * Test capability_exists returns false when capability is not in any role.
	 */
	public function test_capability_exists_returns_false_for_unknown_cap(): void {
		$this->assertFalse( ToolCapabilities::capability_exists( 'gratis_ai_agent_tool_nonexistent_xyz_12345' ) );
	}

	/**
	 * Test capability_exists returns true after adding capability to a role.
	 */
	public function test_capability_exists_returns_true_after_adding_to_role(): void {
		$cap  = 'gratis_ai_agent_tool_test_cap_' . uniqid();
		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );

		$role->add_cap( $cap, true );
		$this->assertTrue( ToolCapabilities::capability_exists( $cap ) );

		// Clean up.
		$role->remove_cap( $cap );
	}

	/**
	 * Test current_user_can falls back to manage_options when capability doesn't exist.
	 */
	public function test_current_user_can_falls_back_to_manage_options(): void {
		// Create a user with manage_options.
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Use an ability ID whose capability has never been registered.
		$this->assertTrue( ToolCapabilities::current_user_can( 'gratis-ai-agent/nonexistent-tool-xyz' ) );

		// Create a subscriber (no manage_options).
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( ToolCapabilities::current_user_can( 'gratis-ai-agent/nonexistent-tool-xyz' ) );
	}

	/**
	 * Test current_user_can uses the specific capability when it exists.
	 */
	public function test_current_user_can_uses_specific_cap_when_registered(): void {
		$ability_id = 'gratis-ai-agent/test-specific-tool-' . uniqid();
		$cap        = ToolCapabilities::cap_name( $ability_id );

		// Grant the capability to the editor role.
		$editor_role = get_role( 'editor' );
		$this->assertNotNull( $editor_role );
		$editor_role->add_cap( $cap, true );

		// Editor should now have access.
		$editor_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );
		$this->assertTrue( ToolCapabilities::current_user_can( $ability_id ) );

		// Subscriber should not have access.
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( ToolCapabilities::current_user_can( $ability_id ) );

		// Clean up.
		$editor_role->remove_cap( $cap );
	}

	/**
	 * Test the gratis_ai_agent_tool_capability filter overrides the capability name.
	 */
	public function test_filter_overrides_capability_name(): void {
		$ability_id   = 'gratis-ai-agent/memory-save';
		$override_cap = 'edit_posts';

		add_filter(
			'gratis_ai_agent_tool_capability',
			function ( string $cap, string $id ) use ( $ability_id, $override_cap ): string {
				if ( $id === $ability_id ) {
					return $override_cap;
				}
				return $cap;
			},
			10,
			2
		);

		// Grant edit_posts to editor role (it already has it by default).
		$editor_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		// The capability 'edit_posts' exists in roles, so it should be used directly.
		$this->assertTrue( ToolCapabilities::current_user_can( $ability_id ) );

		remove_all_filters( 'gratis_ai_agent_tool_capability' );
	}

	/**
	 * Test register_capabilities adds capabilities to the administrator role.
	 */
	public function test_register_capabilities_adds_to_admin_role(): void {
		$test_ids = [
			'gratis-ai-agent/test-reg-tool-a-' . uniqid(),
			'gratis-ai-agent/test-reg-tool-b-' . uniqid(),
		];

		ToolCapabilities::register_capabilities( $test_ids );

		$admin_role = get_role( 'administrator' );
		$this->assertNotNull( $admin_role );

		foreach ( $test_ids as $id ) {
			$cap = ToolCapabilities::cap_name( $id );
			$this->assertArrayHasKey( $cap, $admin_role->capabilities );
			$this->assertTrue( $admin_role->capabilities[ $cap ] );

			// Clean up.
			$admin_role->remove_cap( $cap );
		}
	}

	/**
	 * Test all_ability_ids returns a non-empty array of strings.
	 *
	 * Abilities may use either the plugin-specific "gratis-ai-agent/" prefix
	 * or the WP core "ai-agent/" prefix (used by WP 7.0+ built-in abilities
	 * such as memory-save, create-post, etc.).
	 */
	public function test_all_ability_ids_returns_non_empty_array(): void {
		$ids = ToolCapabilities::all_ability_ids();
		$this->assertIsArray( $ids );
		$this->assertNotEmpty( $ids );

		foreach ( $ids as $id ) {
			$this->assertIsString( $id );
			$this->assertTrue(
				str_starts_with( $id, 'gratis-ai-agent/' ) || str_starts_with( $id, 'ai-agent/' ),
				"Ability ID '{$id}' must start with 'gratis-ai-agent/' or 'ai-agent/'"
			);
		}
	}

	/**
	 * Test all_ability_ids contains expected core abilities.
	 *
	 * Memory, skill, knowledge, post, and global-styles abilities are registered
	 * under the WP core "ai-agent/" prefix; plugin-specific abilities use
	 * "gratis-ai-agent/".
	 */
	public function test_all_ability_ids_contains_core_abilities(): void {
		$ids = ToolCapabilities::all_ability_ids();

		$expected = [
			// WP core ai-agent/ prefix abilities.
			'ai-agent/memory-save',
			'ai-agent/memory-list',
			'ai-agent/memory-delete',
			'ai-agent/create-post',
			'ai-agent/update-post',
			'ai-agent/list-posts',
			// Plugin-specific gratis-ai-agent/ prefix abilities.
			'gratis-ai-agent/db-query',
			'gratis-ai-agent/run-php',
			'gratis-ai-agent/file-read',
			'gratis-ai-agent/file-write',
			'gratis-ai-agent/navigate',
		];

		foreach ( $expected as $id ) {
			$this->assertContains( $id, $ids, "Expected ability ID '{$id}' not found in all_ability_ids()" );
		}
	}
}
