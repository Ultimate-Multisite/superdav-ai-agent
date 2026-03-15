<?php
/**
 * Test case for AbilityHooks class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\AbilityHooks;
use WP_UnitTestCase;

/**
 * Test AbilityHooks functionality.
 */
class AbilityHooksTest extends WP_UnitTestCase {

	/**
	 * Clean up hooks after each test.
	 */
	public function tearDown(): void {
		remove_all_filters( 'gratis_ai_agent_ability_args' );
		remove_all_filters( 'gratis_ai_agent_ability_blocked' );
		remove_all_filters( 'gratis_ai_agent_ability_result' );
		remove_all_actions( 'gratis_ai_agent_before_ability' );
		remove_all_actions( 'gratis_ai_agent_after_ability' );
		remove_all_actions( 'gratis_ai_agent_ability_error' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// before() — action fires + args filter
	// -------------------------------------------------------------------------

	/**
	 * before() fires gratis_ai_agent_before_ability with correct args.
	 */
	public function test_before_fires_action_with_correct_args(): void {
		$captured = [];

		add_action(
			'gratis_ai_agent_before_ability',
			function ( string $name, ?array $args, string $call_id ) use ( &$captured ) {
				$captured = compact( 'name', 'args', 'call_id' );
			},
			10,
			3
		);

		AbilityHooks::before( 'gratis-ai-agent/memory-save', [ 'category' => 'general', 'content' => 'test' ], 'call_001' );

		$this->assertSame( 'gratis-ai-agent/memory-save', $captured['name'] );
		$this->assertSame( [ 'category' => 'general', 'content' => 'test' ], $captured['args'] );
		$this->assertSame( 'call_001', $captured['call_id'] );
	}

	/**
	 * before() returns unmodified args when no filter is registered.
	 */
	public function test_before_returns_original_args_when_no_filter(): void {
		$args   = [ 'category' => 'general', 'content' => 'hello' ];
		$result = AbilityHooks::before( 'gratis-ai-agent/memory-save', $args, 'call_002' );

		$this->assertSame( $args, $result );
	}

	/**
	 * before() returns filtered args when gratis_ai_agent_ability_args filter is registered.
	 */
	public function test_before_returns_filtered_args(): void {
		add_filter(
			'gratis_ai_agent_ability_args',
			function ( ?array $args, string $name, string $call_id ): ?array {
				if ( 'gratis-ai-agent/memory-save' === $name ) {
					$args['content'] = 'overridden';
				}
				return $args;
			},
			10,
			3
		);

		$args   = [ 'category' => 'general', 'content' => 'original' ];
		$result = AbilityHooks::before( 'gratis-ai-agent/memory-save', $args, 'call_003' );

		$this->assertSame( 'overridden', $result['content'] );
	}

	/**
	 * before() handles null args (no-arg abilities).
	 */
	public function test_before_handles_null_args(): void {
		$result = AbilityHooks::before( 'gratis-ai-agent/memory-list', null, 'call_004' );

		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// is_blocked()
	// -------------------------------------------------------------------------

	/**
	 * is_blocked() returns false by default (no filter registered).
	 */
	public function test_is_blocked_returns_false_by_default(): void {
		$blocked = AbilityHooks::is_blocked( 'gratis-ai-agent/memory-save', [], 'call_005' );

		$this->assertFalse( $blocked );
	}

	/**
	 * is_blocked() returns true when a filter returns true.
	 */
	public function test_is_blocked_returns_true_when_filter_blocks(): void {
		add_filter(
			'gratis_ai_agent_ability_blocked',
			function ( bool $blocked, string $name ): bool {
				return 'gratis-ai-agent/memory-save' === $name ? true : $blocked;
			},
			10,
			2
		);

		$blocked = AbilityHooks::is_blocked( 'gratis-ai-agent/memory-save', [], 'call_006' );

		$this->assertTrue( $blocked );
	}

	/**
	 * is_blocked() does not block a different ability when filter targets a specific one.
	 */
	public function test_is_blocked_does_not_block_other_abilities(): void {
		add_filter(
			'gratis_ai_agent_ability_blocked',
			function ( bool $blocked, string $name ): bool {
				return 'gratis-ai-agent/memory-save' === $name ? true : $blocked;
			},
			10,
			2
		);

		$blocked = AbilityHooks::is_blocked( 'gratis-ai-agent/memory-list', [], 'call_007' );

		$this->assertFalse( $blocked );
	}

	/**
	 * is_blocked() passes ability name, args, and call_id to the filter.
	 */
	public function test_is_blocked_passes_all_params_to_filter(): void {
		$captured = [];

		add_filter(
			'gratis_ai_agent_ability_blocked',
			function ( bool $blocked, string $name, ?array $args, string $call_id ) use ( &$captured ): bool {
				$captured = compact( 'name', 'args', 'call_id' );
				return $blocked;
			},
			10,
			4
		);

		AbilityHooks::is_blocked( 'gratis-ai-agent/memory-delete', [ 'id' => 42 ], 'call_008' );

		$this->assertSame( 'gratis-ai-agent/memory-delete', $captured['name'] );
		$this->assertSame( [ 'id' => 42 ], $captured['args'] );
		$this->assertSame( 'call_008', $captured['call_id'] );
	}

	// -------------------------------------------------------------------------
	// after() — actions fire + result filter
	// -------------------------------------------------------------------------

	/**
	 * after() fires gratis_ai_agent_after_ability with correct args.
	 */
	public function test_after_fires_action_with_correct_args(): void {
		$captured = [];

		add_action(
			'gratis_ai_agent_after_ability',
			function ( string $name, ?array $args, mixed $result, string $call_id ) use ( &$captured ) {
				$captured = compact( 'name', 'args', 'result', 'call_id' );
			},
			10,
			4
		);

		$result = [ 'success' => true, 'id' => 1 ];
		AbilityHooks::after( 'gratis-ai-agent/memory-save', [ 'category' => 'general', 'content' => 'test' ], $result, 'call_009' );

		$this->assertSame( 'gratis-ai-agent/memory-save', $captured['name'] );
		$this->assertSame( $result, $captured['result'] );
		$this->assertSame( 'call_009', $captured['call_id'] );
	}

	/**
	 * after() returns unmodified result when no filter is registered.
	 */
	public function test_after_returns_original_result_when_no_filter(): void {
		$result   = [ 'success' => true, 'id' => 5 ];
		$returned = AbilityHooks::after( 'gratis-ai-agent/memory-save', [], $result, 'call_010' );

		$this->assertSame( $result, $returned );
	}

	/**
	 * after() returns filtered result when gratis_ai_agent_ability_result filter is registered.
	 */
	public function test_after_returns_filtered_result(): void {
		add_filter(
			'gratis_ai_agent_ability_result',
			function ( mixed $result, string $name ): mixed {
				if ( 'gratis-ai-agent/memory-save' === $name && is_array( $result ) ) {
					$result['extra'] = 'injected';
				}
				return $result;
			},
			10,
			2
		);

		$result   = [ 'success' => true ];
		$returned = AbilityHooks::after( 'gratis-ai-agent/memory-save', [], $result, 'call_011' );

		$this->assertSame( 'injected', $returned['extra'] );
	}

	/**
	 * after() fires gratis_ai_agent_ability_error when result is WP_Error.
	 */
	public function test_after_fires_error_action_on_wp_error(): void {
		$error_captured = null;

		add_action(
			'gratis_ai_agent_ability_error',
			function ( string $name, ?array $args, \WP_Error $error, string $call_id ) use ( &$error_captured ) {
				$error_captured = $error;
			},
			10,
			4
		);

		$error = new \WP_Error( 'test_error', 'Something went wrong' );
		AbilityHooks::after( 'gratis-ai-agent/memory-save', [], $error, 'call_012' );

		$this->assertInstanceOf( \WP_Error::class, $error_captured );
		$this->assertSame( 'test_error', $error_captured->get_error_code() );
	}

	/**
	 * after() does NOT fire gratis_ai_agent_ability_error on success.
	 */
	public function test_after_does_not_fire_error_action_on_success(): void {
		$error_fired = false;

		add_action(
			'gratis_ai_agent_ability_error',
			function () use ( &$error_fired ) {
				$error_fired = true;
			}
		);

		AbilityHooks::after( 'gratis-ai-agent/memory-save', [], [ 'success' => true ], 'call_013' );

		$this->assertFalse( $error_fired );
	}

	/**
	 * after() fires gratis_ai_agent_after_ability even when result is WP_Error.
	 */
	public function test_after_fires_after_action_even_on_error(): void {
		$after_fired = false;

		add_action(
			'gratis_ai_agent_after_ability',
			function () use ( &$after_fired ) {
				$after_fired = true;
			}
		);

		$error = new \WP_Error( 'test_error', 'Something went wrong' );
		AbilityHooks::after( 'gratis-ai-agent/memory-save', [], $error, 'call_014' );

		$this->assertTrue( $after_fired );
	}

	/**
	 * after() passes all params to the result filter.
	 */
	public function test_after_passes_all_params_to_result_filter(): void {
		$captured = [];

		add_filter(
			'gratis_ai_agent_ability_result',
			function ( mixed $result, string $name, ?array $args, string $call_id ) use ( &$captured ): mixed {
				$captured = compact( 'result', 'name', 'args', 'call_id' );
				return $result;
			},
			10,
			4
		);

		$result = [ 'memories' => [] ];
		$args   = null;
		AbilityHooks::after( 'gratis-ai-agent/memory-list', $args, $result, 'call_015' );

		$this->assertSame( $result, $captured['result'] );
		$this->assertSame( 'gratis-ai-agent/memory-list', $captured['name'] );
		$this->assertNull( $captured['args'] );
		$this->assertSame( 'call_015', $captured['call_id'] );
	}
}
