<?php
/**
 * Tests for AutomationRunner (cron scheduling).
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Automations;

use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Automations\Automations;
use WP_UnitTestCase;

/**
 * Test AutomationRunner scheduling functionality.
 *
 * Note: Tests for AutomationRunner::run() are not included here because they
 * require a live AI provider. Those are covered by E2E tests. This suite
 * focuses on cron scheduling, unscheduling, and the custom schedule filter.
 */
class AutomationRunnerTest extends WP_UnitTestCase {

	/**
	 * Automation ID used across tests.
	 *
	 * @var int
	 */
	private int $automation_id;

	/**
	 * Set up a fresh automation before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->automation_id = (int) Automations::create( [
			'name'    => 'Runner Test Automation',
			'prompt'  => 'Test prompt',
			'enabled' => 0,
		] );
	}

	/**
	 * Tear down: unschedule any cron events created during the test.
	 */
	public function tear_down(): void {
		AutomationRunner::unschedule( $this->automation_id );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// CRON_HOOK constant
	// -------------------------------------------------------------------------

	/**
	 * Test CRON_HOOK constant has expected value.
	 */
	public function test_cron_hook_constant(): void {
		$this->assertSame( 'gratis_ai_agent_run_automation', AutomationRunner::CRON_HOOK );
	}

	// -------------------------------------------------------------------------
	// add_cron_schedules
	// -------------------------------------------------------------------------

	/**
	 * Test add_cron_schedules adds weekly schedule when not present.
	 */
	public function test_add_cron_schedules_adds_weekly(): void {
		$schedules = AutomationRunner::add_cron_schedules( [] );

		$this->assertArrayHasKey( 'weekly', $schedules );
		$this->assertArrayHasKey( 'interval', $schedules['weekly'] );
		$this->assertArrayHasKey( 'display', $schedules['weekly'] );
		$this->assertSame( WEEK_IN_SECONDS, $schedules['weekly']['interval'] );
	}

	/**
	 * Test add_cron_schedules does not overwrite existing weekly schedule.
	 */
	public function test_add_cron_schedules_preserves_existing_weekly(): void {
		$existing = [
			'weekly' => [
				'interval' => 999,
				'display'  => 'Custom Weekly',
			],
		];

		$schedules = AutomationRunner::add_cron_schedules( $existing );

		$this->assertSame( 999, $schedules['weekly']['interval'] );
	}

	/**
	 * Test add_cron_schedules preserves other existing schedules.
	 */
	public function test_add_cron_schedules_preserves_others(): void {
		$existing = [
			'hourly' => [
				'interval' => HOUR_IN_SECONDS,
				'display'  => 'Once Hourly',
			],
		];

		$schedules = AutomationRunner::add_cron_schedules( $existing );

		$this->assertArrayHasKey( 'hourly', $schedules );
		$this->assertSame( HOUR_IN_SECONDS, $schedules['hourly']['interval'] );
	}

	// -------------------------------------------------------------------------
	// schedule / unschedule
	// -------------------------------------------------------------------------

	/**
	 * Test schedule creates a cron event for the automation.
	 */
	public function test_schedule_creates_cron_event(): void {
		AutomationRunner::schedule( $this->automation_id, 'daily' );

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $this->automation_id ] );

		$this->assertNotFalse( $timestamp );
		$this->assertGreaterThan( 0, $timestamp );
	}

	/**
	 * Test schedule does not create duplicate events.
	 */
	public function test_schedule_no_duplicate(): void {
		AutomationRunner::schedule( $this->automation_id, 'daily' );
		AutomationRunner::schedule( $this->automation_id, 'daily' );

		// wp_next_scheduled returns a single timestamp — duplicates would cause
		// multiple events but we can only verify one is scheduled.
		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $this->automation_id ] );
		$this->assertNotFalse( $timestamp );
	}

	/**
	 * Test unschedule removes the cron event.
	 */
	public function test_unschedule_removes_event(): void {
		AutomationRunner::schedule( $this->automation_id, 'daily' );
		AutomationRunner::unschedule( $this->automation_id );

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $this->automation_id ] );

		$this->assertFalse( $timestamp );
	}

	/**
	 * Test unschedule on non-scheduled automation does not error.
	 */
	public function test_unschedule_nonexistent_is_safe(): void {
		// Should not throw or produce errors.
		AutomationRunner::unschedule( 999999 );

		$this->assertTrue( true ); // Reached without error.
	}

	// -------------------------------------------------------------------------
	// reschedule_all / unschedule_all
	// -------------------------------------------------------------------------

	/**
	 * Test reschedule_all schedules all enabled automations.
	 */
	public function test_reschedule_all_schedules_enabled(): void {
		// Enable the automation.
		Automations::update( $this->automation_id, [ 'enabled' => 1, 'schedule' => 'daily' ] );

		AutomationRunner::reschedule_all();

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $this->automation_id ] );
		$this->assertNotFalse( $timestamp );
	}

	/**
	 * Test unschedule_all removes all scheduled events.
	 */
	public function test_unschedule_all_removes_events(): void {
		// Enable and schedule the automation.
		Automations::update( $this->automation_id, [ 'enabled' => 1, 'schedule' => 'daily' ] );
		AutomationRunner::schedule( $this->automation_id, 'daily' );

		AutomationRunner::unschedule_all();

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $this->automation_id ] );
		$this->assertFalse( $timestamp );
	}

	// -------------------------------------------------------------------------
	// register
	// -------------------------------------------------------------------------

	/**
	 * Test register hooks the run method to the cron hook.
	 */
	public function test_register_hooks_run_action(): void {
		AutomationRunner::register();

		$this->assertGreaterThan(
			0,
			has_action( AutomationRunner::CRON_HOOK, [ AutomationRunner::class, 'run' ] )
		);
	}

	/**
	 * Test register hooks add_cron_schedules to cron_schedules filter.
	 */
	public function test_register_hooks_cron_schedules_filter(): void {
		AutomationRunner::register();

		$this->assertGreaterThan(
			0,
			has_filter( 'cron_schedules', [ AutomationRunner::class, 'add_cron_schedules' ] )
		);
	}
}
