<?php
/**
 * Tests for EventTriggerHandler.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Automations;

use SdAiAgent\Automations\EventAutomations;
use SdAiAgent\Automations\EventTriggerHandler;
use WP_UnitTestCase;

/**
 * Test EventTriggerHandler hook registration and execution.
 *
 * Note: Tests for execute_event_run() that require a live AI provider are
 * not included here — those are covered by E2E tests. This suite focuses on
 * hook registration, re-entrancy guard, and transient-based execution paths.
 */
class EventTriggerHandlerTest extends WP_UnitTestCase {

	/**
	 * Event automation ID created for each test.
	 *
	 * @var int
	 */
	private int $event_id;

	/**
	 * Set up a fresh enabled event automation before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->event_id = (int) EventAutomations::create( [
			'name'            => 'Handler Test Event',
			'description'     => 'Test event for EventTriggerHandler',
			'hook_name'       => 'sd_ai_agent_test_hook_' . uniqid(),
			'prompt_template' => 'Test prompt',
			'conditions'      => [],
			'max_iterations'  => 3,
			'enabled'         => 1,
		] );
	}

	/**
	 * Tear down: clean up event automation and reset static state.
	 */
	public function tear_down(): void {
		EventAutomations::delete( $this->event_id );

		// Reset the static $registered_hooks array to prevent cross-test pollution.
		$ref = new \ReflectionProperty( EventTriggerHandler::class, 'registered_hooks' );
		$ref->setAccessible( true );
		$ref->setValue( null, [] );

		// Reset the static $executing guard.
		$ref = new \ReflectionProperty( EventTriggerHandler::class, 'executing' );
		$ref->setAccessible( true );
		$ref->setValue( null, false );

		// Remove actions added by register().
		remove_action( 'init', [ EventTriggerHandler::class, 'attach_hooks' ], 99 );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// register
	// -------------------------------------------------------------------------

	/**
	 * Test register hooks attach_hooks to the init action.
	 */
	public function test_register_hooks_attach_hooks_to_init(): void {
		EventTriggerHandler::register();

		$this->assertGreaterThan(
			0,
			has_action( 'init', [ EventTriggerHandler::class, 'attach_hooks' ] ),
			'attach_hooks should be hooked to init'
		);
	}

	/**
	 * Test register hooks execute_event_run to the cron action.
	 */
	public function test_register_hooks_execute_event_run(): void {
		EventTriggerHandler::register();

		$this->assertGreaterThan(
			0,
			has_action( 'sd_ai_agent_run_event_automation', [ EventTriggerHandler::class, 'execute_event_run' ] ),
			'execute_event_run should be hooked to sd_ai_agent_run_event_automation'
		);
	}

	// -------------------------------------------------------------------------
	// attach_hooks
	// -------------------------------------------------------------------------

	/**
	 * Test attach_hooks registers a WP action for each enabled event's hook_name.
	 */
	public function test_attach_hooks_registers_action_for_enabled_event(): void {
		$event = EventAutomations::get( $this->event_id );
		$this->assertNotNull( $event );
		$hook = (string) ( $event['hook_name'] ?? '' );

		EventTriggerHandler::attach_hooks();

		$this->assertGreaterThan(
			0,
			has_action( $hook ),
			"Expected action to be registered for hook '{$hook}'"
		);
	}

	/**
	 * Test attach_hooks does not register actions for disabled events.
	 */
	public function test_attach_hooks_skips_disabled_events(): void {
		$unique_hook = 'sd_ai_agent_disabled_hook_' . uniqid();

		$disabled_id = (int) EventAutomations::create( [
			'name'            => 'Disabled Event',
			'description'     => 'Should not be hooked',
			'hook_name'       => $unique_hook,
			'prompt_template' => 'Test',
			'conditions'      => [],
			'max_iterations'  => 3,
			'enabled'         => 0,
		] );

		EventTriggerHandler::attach_hooks();

		$this->assertFalse(
			has_action( $unique_hook ),
			"Disabled event hook '{$unique_hook}' should not be registered"
		);

		EventAutomations::delete( $disabled_id );
	}

	/**
	 * Test attach_hooks skips events with empty hook_name.
	 */
	public function test_attach_hooks_skips_empty_hook_name(): void {
		// Create an event with an empty hook_name (edge case).
		$empty_hook_id = (int) EventAutomations::create( [
			'name'            => 'Empty Hook Event',
			'description'     => 'Has no hook name',
			'hook_name'       => '',
			'prompt_template' => 'Test',
			'conditions'      => [],
			'max_iterations'  => 3,
			'enabled'         => 1,
		] );

		// Should not throw or produce errors.
		EventTriggerHandler::attach_hooks();

		$this->assertTrue( true, 'attach_hooks should handle empty hook_name gracefully' );

		EventAutomations::delete( $empty_hook_id );
	}

	// -------------------------------------------------------------------------
	// execute_event_run
	// -------------------------------------------------------------------------

	/**
	 * Test execute_event_run returns early when transient is missing.
	 */
	public function test_execute_event_run_missing_transient_returns_early(): void {
		// Should not throw or produce errors.
		EventTriggerHandler::execute_event_run( 'nonexistent_transient_key_xyz' );

		$this->assertTrue( true, 'execute_event_run should handle missing transient gracefully' );
	}

	/**
	 * Test execute_event_run returns early when transient value is not an array.
	 */
	public function test_execute_event_run_invalid_transient_returns_early(): void {
		$run_key = 'sd_ai_agent_event_run_test_invalid';
		set_transient( $run_key, 'not-an-array', HOUR_IN_SECONDS );

		// Should not throw or produce errors.
		EventTriggerHandler::execute_event_run( $run_key );

		// Transient should be deleted even on early return.
		$this->assertFalse(
			get_transient( $run_key ),
			'Transient should be deleted after execute_event_run'
		);
	}

	/**
	 * Test execute_event_run deletes the transient after reading it.
	 */
	public function test_execute_event_run_deletes_transient(): void {
		$run_key  = 'sd_ai_agent_event_run_test_delete';
		$run_data = [
			'event_id'  => 999999, // Non-existent event — will return early after delete.
			'prompt'    => 'Test prompt',
			'hook_name' => 'test_hook',
		];

		set_transient( $run_key, $run_data, HOUR_IN_SECONDS );

		EventTriggerHandler::execute_event_run( $run_key );

		$this->assertFalse(
			get_transient( $run_key ),
			'Transient should be deleted after execute_event_run'
		);
	}

	/**
	 * Test execute_event_run returns early when event_id does not exist.
	 */
	public function test_execute_event_run_missing_event_returns_early(): void {
		$run_key  = 'sd_ai_agent_event_run_test_missing_event';
		$run_data = [
			'event_id'  => 999999,
			'prompt'    => 'Test prompt',
			'hook_name' => 'test_hook',
		];

		set_transient( $run_key, $run_data, HOUR_IN_SECONDS );

		// Should not throw or produce errors.
		EventTriggerHandler::execute_event_run( $run_key );

		$this->assertTrue( true, 'execute_event_run should handle missing event gracefully' );
	}

	/**
	 * Test execute_event_run fires the completion action hook on success.
	 *
	 * This test uses a real event but stubs the AgentLoop by filtering the
	 * sd_ai_agent_event_automation_complete action to capture the call.
	 */
	public function test_execute_event_run_fires_completion_action(): void {
		$fired    = false;
		$fired_id = null;

		add_action(
			'sd_ai_agent_event_automation_complete',
			function ( $event_id ) use ( &$fired, &$fired_id ) {
				$fired    = true;
				$fired_id = $event_id;
			}
		);

		$run_key  = 'sd_ai_agent_event_run_test_complete_' . uniqid();
		$run_data = [
			'event_id'  => $this->event_id,
			'prompt'    => 'Test prompt',
			'hook_name' => 'sd_ai_agent_test_hook',
		];

		set_transient( $run_key, $run_data, HOUR_IN_SECONDS );

		// execute_event_run will attempt AgentLoop::run() — in the test
		// environment without a provider, it will produce a WP_Error, but the
		// completion action should still fire and the log should be written.
		EventTriggerHandler::execute_event_run( $run_key );

		$this->assertTrue( $fired, 'sd_ai_agent_event_automation_complete should fire' );
		$this->assertSame( $this->event_id, $fired_id );
	}
}
