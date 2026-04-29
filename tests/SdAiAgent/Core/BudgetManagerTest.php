<?php
/**
 * Test case for BudgetManager class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\BudgetManager;
use SdAiAgent\Core\Settings;
use WP_UnitTestCase;

/**
 * Test BudgetManager functionality.
 */
class BudgetManagerTest extends WP_UnitTestCase {

	/**
	 * Reset settings and transients before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( Settings::OPTION_NAME );
		delete_transient( BudgetManager::TRANSIENT_DAILY );
		delete_transient( BudgetManager::TRANSIENT_MONTHLY );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		delete_option( Settings::OPTION_NAME );
		delete_transient( BudgetManager::TRANSIENT_DAILY );
		delete_transient( BudgetManager::TRANSIENT_MONTHLY );
		parent::tearDown();
	}

	// ─── check_budget ────────────────────────────────────────────────

	/**
	 * When no caps are configured, check_budget() returns true.
	 */
	public function test_check_budget_no_caps_returns_true() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'   => 0,
			'budget_monthly_cap' => 0,
		] );

		$result = BudgetManager::check_budget();
		$this->assertTrue( $result );
	}

	/**
	 * When daily cap is set and spend is below cap, check_budget() returns true.
	 */
	public function test_check_budget_below_daily_cap_returns_true() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'        => 5.00,
			'budget_monthly_cap'      => 0,
			'budget_exceeded_action'  => 'pause',
		] );

		// Inject a spend below the cap via transient.
		set_transient( BudgetManager::TRANSIENT_DAILY, 2.50, BudgetManager::CACHE_TTL );

		$result = BudgetManager::check_budget();
		$this->assertTrue( $result );
	}

	/**
	 * When daily cap is exceeded and action is "pause", check_budget() returns WP_Error.
	 */
	public function test_check_budget_daily_exceeded_pause_returns_wp_error() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'        => 5.00,
			'budget_monthly_cap'      => 0,
			'budget_exceeded_action'  => 'pause',
		] );

		// Inject a spend at or above the cap.
		set_transient( BudgetManager::TRANSIENT_DAILY, 5.00, BudgetManager::CACHE_TTL );

		$result = BudgetManager::check_budget();
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_budget_daily_exceeded', $result->get_error_code() );
	}

	/**
	 * When daily cap is exceeded and action is "warn", check_budget() returns true.
	 */
	public function test_check_budget_daily_exceeded_warn_returns_true() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'        => 5.00,
			'budget_monthly_cap'      => 0,
			'budget_exceeded_action'  => 'warn',
		] );

		set_transient( BudgetManager::TRANSIENT_DAILY, 10.00, BudgetManager::CACHE_TTL );

		$result = BudgetManager::check_budget();
		$this->assertTrue( $result );
	}

	/**
	 * When monthly cap is exceeded and action is "pause", check_budget() returns WP_Error.
	 */
	public function test_check_budget_monthly_exceeded_pause_returns_wp_error() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'        => 0,
			'budget_monthly_cap'      => 50.00,
			'budget_exceeded_action'  => 'pause',
		] );

		set_transient( BudgetManager::TRANSIENT_MONTHLY, 50.00, BudgetManager::CACHE_TTL );

		$result = BudgetManager::check_budget();
		$this->assertWPError( $result );
		$this->assertSame( 'sd_ai_agent_budget_monthly_exceeded', $result->get_error_code() );
	}

	/**
	 * When monthly cap is below spend, check_budget() returns true.
	 */
	public function test_check_budget_below_monthly_cap_returns_true() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'        => 0,
			'budget_monthly_cap'      => 50.00,
			'budget_exceeded_action'  => 'pause',
		] );

		set_transient( BudgetManager::TRANSIENT_MONTHLY, 25.00, BudgetManager::CACHE_TTL );

		$result = BudgetManager::check_budget();
		$this->assertTrue( $result );
	}

	// ─── is_exceeded ─────────────────────────────────────────────────

	/**
	 * is_exceeded() returns false when no caps are set.
	 */
	public function test_is_exceeded_no_caps_returns_false() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'   => 0,
			'budget_monthly_cap' => 0,
		] );

		$this->assertFalse( BudgetManager::is_exceeded() );
	}

	/**
	 * is_exceeded() returns true when daily cap is exceeded.
	 */
	public function test_is_exceeded_daily_cap_exceeded_returns_true() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'        => 5.00,
			'budget_monthly_cap'      => 0,
			'budget_exceeded_action'  => 'pause',
		] );

		set_transient( BudgetManager::TRANSIENT_DAILY, 6.00, BudgetManager::CACHE_TTL );

		$this->assertTrue( BudgetManager::is_exceeded() );
	}

	// ─── get_warning_level ───────────────────────────────────────────

	/**
	 * get_warning_level() returns 'ok' when no caps are set.
	 */
	public function test_get_warning_level_no_caps_returns_ok() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'   => 0,
			'budget_monthly_cap' => 0,
		] );

		$this->assertSame( 'ok', BudgetManager::get_warning_level() );
	}

	/**
	 * get_warning_level() returns 'ok' when spend is below warning threshold.
	 */
	public function test_get_warning_level_below_threshold_returns_ok() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'          => 10.00,
			'budget_monthly_cap'        => 0,
			'budget_warning_threshold'  => 80,
		] );

		// 50% of cap — below 80% threshold.
		set_transient( BudgetManager::TRANSIENT_DAILY, 5.00, BudgetManager::CACHE_TTL );

		$this->assertSame( 'ok', BudgetManager::get_warning_level() );
	}

	/**
	 * get_warning_level() returns 'warning' when spend is at or above warning threshold.
	 */
	public function test_get_warning_level_at_threshold_returns_warning() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'          => 10.00,
			'budget_monthly_cap'        => 0,
			'budget_warning_threshold'  => 80,
		] );

		// 80% of cap — exactly at threshold.
		set_transient( BudgetManager::TRANSIENT_DAILY, 8.00, BudgetManager::CACHE_TTL );

		$this->assertSame( 'warning', BudgetManager::get_warning_level() );
	}

	/**
	 * get_warning_level() returns 'exceeded' when spend is at or above cap.
	 */
	public function test_get_warning_level_at_cap_returns_exceeded() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'          => 10.00,
			'budget_monthly_cap'        => 0,
			'budget_warning_threshold'  => 80,
		] );

		set_transient( BudgetManager::TRANSIENT_DAILY, 10.00, BudgetManager::CACHE_TTL );

		$this->assertSame( 'exceeded', BudgetManager::get_warning_level() );
	}

	// ─── get_status ──────────────────────────────────────────────────

	/**
	 * get_status() returns the expected shape.
	 */
	public function test_get_status_returns_expected_shape() {
		update_option( Settings::OPTION_NAME, [
			'budget_daily_cap'   => 5.00,
			'budget_monthly_cap' => 50.00,
		] );

		set_transient( BudgetManager::TRANSIENT_DAILY, 1.00, BudgetManager::CACHE_TTL );
		set_transient( BudgetManager::TRANSIENT_MONTHLY, 10.00, BudgetManager::CACHE_TTL );

		$status = BudgetManager::get_status();

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'daily_spend', $status );
		$this->assertArrayHasKey( 'monthly_spend', $status );
		$this->assertArrayHasKey( 'daily_cap', $status );
		$this->assertArrayHasKey( 'monthly_cap', $status );
		$this->assertArrayHasKey( 'warning_level', $status );
		$this->assertArrayHasKey( 'is_exceeded', $status );

		$this->assertSame( 1.00, $status['daily_spend'] );
		$this->assertSame( 10.00, $status['monthly_spend'] );
		$this->assertSame( 5.00, $status['daily_cap'] );
		$this->assertSame( 50.00, $status['monthly_cap'] );
		$this->assertSame( 'ok', $status['warning_level'] );
		$this->assertFalse( $status['is_exceeded'] );
	}

	// ─── invalidate_cache ────────────────────────────────────────────

	/**
	 * invalidate_cache() removes both transients.
	 */
	public function test_invalidate_cache_removes_transients() {
		set_transient( BudgetManager::TRANSIENT_DAILY, 1.00, BudgetManager::CACHE_TTL );
		set_transient( BudgetManager::TRANSIENT_MONTHLY, 10.00, BudgetManager::CACHE_TTL );

		BudgetManager::invalidate_cache();

		$this->assertFalse( get_transient( BudgetManager::TRANSIENT_DAILY ) );
		$this->assertFalse( get_transient( BudgetManager::TRANSIENT_MONTHLY ) );
	}

	// ─── format_cost ─────────────────────────────────────────────────

	/**
	 * format_cost() formats large values to 2 decimal places.
	 */
	public function test_format_cost_large_value() {
		$this->assertSame( '$2.34', BudgetManager::format_cost( 2.34 ) );
	}

	/**
	 * format_cost() formats small values to 4 decimal places.
	 */
	public function test_format_cost_small_value() {
		$this->assertSame( '$0.0012', BudgetManager::format_cost( 0.0012 ) );
	}

	/**
	 * format_cost() formats zero correctly.
	 */
	public function test_format_cost_zero() {
		$this->assertSame( '$0.00', BudgetManager::format_cost( 0.0 ) );
	}

	/**
	 * format_cost() formats exactly $0.01 to 2 decimal places.
	 */
	public function test_format_cost_boundary_value() {
		$this->assertSame( '$0.01', BudgetManager::format_cost( 0.01 ) );
	}
}
