<?php
/**
 * Test case for Schedule enum.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Enums;

use GratisAiAgent\Enums\Schedule;
use WP_UnitTestCase;

/**
 * Test Schedule enum functionality.
 */
class ScheduleTest extends WP_UnitTestCase {

	/**
	 * Test all schedule cases exist.
	 */
	public function test_schedule_cases_exist() {
		$cases = Schedule::cases();

		$this->assertCount(4, $cases);
		$this->assertContains(Schedule::Hourly, $cases);
		$this->assertContains(Schedule::TwiceDaily, $cases);
		$this->assertContains(Schedule::Daily, $cases);
		$this->assertContains(Schedule::Weekly, $cases);
	}

	/**
	 * Test schedule values.
	 */
	public function test_schedule_values() {
		$this->assertSame('hourly', Schedule::Hourly->value);
		$this->assertSame('twicedaily', Schedule::TwiceDaily->value);
		$this->assertSame('daily', Schedule::Daily->value);
		$this->assertSame('weekly', Schedule::Weekly->value);
	}

	/**
	 * Test values() returns all values as array.
	 */
	public function test_values_returns_array() {
		$values = Schedule::values();

		$this->assertIsArray($values);
		$this->assertCount(4, $values);
		$this->assertContains('hourly', $values);
		$this->assertContains('twicedaily', $values);
		$this->assertContains('daily', $values);
		$this->assertContains('weekly', $values);
	}

	/**
	 * Test isValid() with valid values.
	 */
	public function test_is_valid_with_valid_values() {
		$this->assertTrue(Schedule::isValid('hourly'));
		$this->assertTrue(Schedule::isValid('twicedaily'));
		$this->assertTrue(Schedule::isValid('daily'));
		$this->assertTrue(Schedule::isValid('weekly'));
	}

	/**
	 * Test isValid() with invalid values.
	 */
	public function test_is_valid_with_invalid_values() {
		$this->assertFalse(Schedule::isValid('monthly'));
		$this->assertFalse(Schedule::isValid('yearly'));
		$this->assertFalse(Schedule::isValid(''));
		$this->assertFalse(Schedule::isValid('HOURLY')); // Case sensitive
	}

	/**
	 * Test tryFrom() with valid values.
	 */
	public function test_try_from_with_valid_values() {
		$this->assertSame(Schedule::Hourly, Schedule::tryFrom('hourly'));
		$this->assertSame(Schedule::Daily, Schedule::tryFrom('daily'));
	}

	/**
	 * Test tryFrom() with invalid values.
	 */
	public function test_try_from_with_invalid_values() {
		$this->assertNull(Schedule::tryFrom('invalid'));
		$this->assertNull(Schedule::tryFrom(''));
	}

	/**
	 * Test from() with valid values.
	 */
	public function test_from_with_valid_values() {
		$this->assertSame(Schedule::Hourly, Schedule::from('hourly'));
		$this->assertSame(Schedule::Weekly, Schedule::from('weekly'));
	}

	/**
	 * Test from() throws exception with invalid value.
	 */
	public function test_from_throws_exception_with_invalid_value() {
		$this->expectException(\ValueError::class);
		Schedule::from('invalid');
	}
}
