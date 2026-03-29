<?php
/**
 * Test case for CustomToolExecutor class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Tools;

use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\Tools\CustomTools;
use WP_UnitTestCase;

/**
 * Test CustomToolExecutor execution logic.
 */
class CustomToolExecutorTest extends WP_UnitTestCase {

	// ── replace_placeholders ──────────────────────────────────────────────

	/**
	 * Test replace_placeholders substitutes a simple key.
	 */
	public function test_replace_placeholders_simple_key(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Hello {{name}}!',
			[ 'name' => 'World' ]
		);

		$this->assertSame( 'Hello World!', $result );
	}

	/**
	 * Test replace_placeholders leaves unknown placeholder intact.
	 */
	public function test_replace_placeholders_unknown_key_left_intact(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Value: {{unknown}}',
			[ 'other' => 'something' ]
		);

		$this->assertSame( 'Value: {{unknown}}', $result );
	}

	/**
	 * Test replace_placeholders substitutes multiple placeholders.
	 */
	public function test_replace_placeholders_multiple_keys(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'{{greeting}}, {{name}}!',
			[ 'greeting' => 'Hello', 'name' => 'Alice' ]
		);

		$this->assertSame( 'Hello, Alice!', $result );
	}

	/**
	 * Test replace_placeholders handles integer values.
	 */
	public function test_replace_placeholders_integer_value(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Count: {{count}}',
			[ 'count' => 42 ]
		);

		$this->assertSame( 'Count: 42', $result );
	}

	/**
	 * Test replace_placeholders handles float values.
	 */
	public function test_replace_placeholders_float_value(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Price: {{price}}',
			[ 'price' => 9.99 ]
		);

		$this->assertSame( 'Price: 9.99', $result );
	}

	/**
	 * Test replace_placeholders handles boolean values.
	 */
	public function test_replace_placeholders_boolean_value(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Active: {{active}}',
			[ 'active' => true ]
		);

		$this->assertSame( 'Active: 1', $result );
	}

	/**
	 * Test replace_placeholders handles dot-notation for nested arrays.
	 */
	public function test_replace_placeholders_dot_notation(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Order ID: {{order.id}}',
			[ 'order' => [ 'id' => '12345' ] ]
		);

		$this->assertSame( 'Order ID: 12345', $result );
	}

	/**
	 * Test replace_placeholders leaves dot-notation intact when nested key missing.
	 */
	public function test_replace_placeholders_dot_notation_missing_nested_key(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Value: {{order.missing}}',
			[ 'order' => [ 'id' => '123' ] ]
		);

		$this->assertSame( 'Value: {{order.missing}}', $result );
	}

	/**
	 * Test replace_placeholders encodes array values as JSON.
	 */
	public function test_replace_placeholders_array_value_encoded_as_json(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Data: {{items}}',
			[ 'items' => [ 'a', 'b', 'c' ] ]
		);

		$this->assertStringContainsString( '"a"', $result );
		$this->assertStringContainsString( '"b"', $result );
	}

	/**
	 * Test replace_placeholders with empty template returns empty string.
	 */
	public function test_replace_placeholders_empty_template(): void {
		$result = CustomToolExecutor::replace_placeholders( '', [ 'key' => 'value' ] );
		$this->assertSame( '', $result );
	}

	/**
	 * Test replace_placeholders with no placeholders returns template unchanged.
	 */
	public function test_replace_placeholders_no_placeholders(): void {
		$template = 'No placeholders here.';
		$result   = CustomToolExecutor::replace_placeholders( $template, [ 'key' => 'value' ] );
		$this->assertSame( $template, $result );
	}

	/**
	 * Test replace_placeholders with empty input leaves all placeholders intact.
	 */
	public function test_replace_placeholders_empty_input(): void {
		$result = CustomToolExecutor::replace_placeholders(
			'Hello {{name}}!',
			[]
		);

		$this->assertSame( 'Hello {{name}}!', $result );
	}

	// ── execute — unknown type ────────────────────────────────────────────

	/**
	 * Test execute returns WP_Error for unknown tool type.
	 */
	public function test_execute_unknown_type_returns_wp_error(): void {
		$tool = [
			'type'   => 'unknown_type',
			'config' => [],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'unknown_tool_type', $result->get_error_code() );
	}

	// ── execute — ACTION type ─────────────────────────────────────────────

	/**
	 * Test execute ACTION returns WP_Error when hook_name is missing.
	 */
	public function test_execute_action_missing_hook_name(): void {
		$tool = [
			'type'   => CustomTools::TYPE_ACTION,
			'config' => [],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_config', $result->get_error_code() );
	}

	/**
	 * Test execute ACTION returns WP_Error for invalid hook name characters.
	 */
	public function test_execute_action_invalid_hook_name_characters(): void {
		$tool = [
			'type'   => CustomTools::TYPE_ACTION,
			'config' => [ 'hook_name' => 'invalid-hook-name!' ],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_hook_name', $result->get_error_code() );
	}

	/**
	 * Test execute ACTION fires the hook and returns success.
	 */
	public function test_execute_action_fires_hook(): void {
		$fired = false;

		add_action(
			'gratis_ai_agent_test_executor_hook',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$tool = [
			'type'   => CustomTools::TYPE_ACTION,
			'config' => [ 'hook_name' => 'gratis_ai_agent_test_executor_hook' ],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertTrue( $fired );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'gratis_ai_agent_test_executor_hook', $result['hook_name'] );
	}

	/**
	 * Test execute ACTION auto-prefixes hook name with gratis_ai_agent_.
	 */
	public function test_execute_action_auto_prefixes_hook_name(): void {
		$fired_hook = null;

		add_action(
			'gratis_ai_agent_my_custom_hook',
			function () use ( &$fired_hook ) {
				$fired_hook = 'gratis_ai_agent_my_custom_hook';
			}
		);

		$tool = [
			'type'   => CustomTools::TYPE_ACTION,
			'config' => [ 'hook_name' => 'my_custom_hook' ],
		];

		CustomToolExecutor::execute( $tool, [] );

		$this->assertSame( 'gratis_ai_agent_my_custom_hook', $fired_hook );
	}

	/**
	 * Test execute ACTION passes input as first argument when no arg_defs.
	 */
	public function test_execute_action_passes_input_as_first_arg(): void {
		$received = null;

		add_action(
			'gratis_ai_agent_test_args_hook',
			function ( $arg ) use ( &$received ) {
				$received = $arg;
			}
		);

		$tool = [
			'type'   => CustomTools::TYPE_ACTION,
			'config' => [ 'hook_name' => 'gratis_ai_agent_test_args_hook' ],
		];

		CustomToolExecutor::execute( $tool, [ 'key' => 'value' ] );

		$this->assertIsArray( $received );
		$this->assertSame( 'value', $received['key'] );
	}

	/**
	 * Test execute ACTION uses arg_defs to build arguments.
	 */
	public function test_execute_action_uses_arg_defs(): void {
		$received = [];

		add_action(
			'gratis_ai_agent_test_argdefs_hook',
			function ( $a, $b ) use ( &$received ) {
				$received = [ $a, $b ];
			},
			10,
			2
		);

		$tool = [
			'type'   => CustomTools::TYPE_ACTION,
			'config' => [
				'hook_name' => 'gratis_ai_agent_test_argdefs_hook',
				'args'      => [
					'first'  => 'default_a',
					'second' => 'default_b',
				],
			],
		];

		CustomToolExecutor::execute( $tool, [ 'first' => 'custom_a' ] );

		$this->assertSame( 'custom_a', $received[0] );
		$this->assertSame( 'default_b', $received[1] );
	}

	// ── execute — CLI type ────────────────────────────────────────────────

	/**
	 * Test execute CLI returns WP_Error when command is missing.
	 */
	public function test_execute_cli_missing_command(): void {
		$tool = [
			'type'   => CustomTools::TYPE_CLI,
			'config' => [],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_config', $result->get_error_code() );
	}

	/**
	 * Test execute CLI returns array with expected keys.
	 */
	public function test_execute_cli_returns_expected_structure(): void {
		$tool = [
			'type'   => CustomTools::TYPE_CLI,
			'config' => [ 'command' => 'eval "echo test;"' ],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'return_code', $result );
		$this->assertArrayHasKey( 'output', $result );
		$this->assertArrayHasKey( 'command', $result );
	}

	/**
	 * Test execute CLI replaces placeholders in command.
	 */
	public function test_execute_cli_replaces_placeholders_in_command(): void {
		$tool = [
			'type'   => CustomTools::TYPE_CLI,
			'config' => [ 'command' => 'eval "echo {{message}};"' ],
		];

		$result = CustomToolExecutor::execute( $tool, [ 'message' => 'hello' ] );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'hello', $result['command'] );
	}

	/**
	 * Test execute CLI strips shell injection characters from command.
	 */
	public function test_execute_cli_strips_injection_characters(): void {
		$tool = [
			'type'   => CustomTools::TYPE_CLI,
			'config' => [ 'command' => 'eval "echo test"; rm -rf /' ],
		];

		$result = CustomToolExecutor::execute( $tool, [] );

		$this->assertIsArray( $result );
		// The command key should not contain semicolons or pipes.
		$this->assertStringNotContainsString( ';', $result['command'] );
	}

	// ── register_abilities ────────────────────────────────────────────────

	/**
	 * Test register adds action hook.
	 */
	public function test_register_adds_action_hook(): void {
		CustomToolExecutor::register();

		$this->assertGreaterThan( 0, has_action( 'wp_abilities_api_init', [ CustomToolExecutor::class, 'register_abilities' ] ) );
	}
}
