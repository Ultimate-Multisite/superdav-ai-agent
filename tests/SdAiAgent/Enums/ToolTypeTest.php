<?php
/**
 * Test case for ToolType enum.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Enums;

use SdAiAgent\Enums\ToolType;
use WP_UnitTestCase;

/**
 * Test ToolType enum functionality.
 */
class ToolTypeTest extends WP_UnitTestCase {

	/**
	 * Test all tool type cases exist.
	 */
	public function test_tool_type_cases_exist() {
		$cases = ToolType::cases();

		$this->assertCount(3, $cases);
		$this->assertContains(ToolType::Http, $cases);
		$this->assertContains(ToolType::Action, $cases);
		$this->assertContains(ToolType::Cli, $cases);
	}

	/**
	 * Test tool type values.
	 */
	public function test_tool_type_values() {
		$this->assertSame('http', ToolType::Http->value);
		$this->assertSame('action', ToolType::Action->value);
		$this->assertSame('cli', ToolType::Cli->value);
	}

	/**
	 * Test values() returns all values as array.
	 */
	public function test_values_returns_array() {
		$values = ToolType::values();

		$this->assertIsArray($values);
		$this->assertCount(3, $values);
		$this->assertContains('http', $values);
		$this->assertContains('action', $values);
		$this->assertContains('cli', $values);
	}

	/**
	 * Test isValid() with valid values.
	 */
	public function test_is_valid_with_valid_values() {
		$this->assertTrue(ToolType::isValid('http'));
		$this->assertTrue(ToolType::isValid('action'));
		$this->assertTrue(ToolType::isValid('cli'));
	}

	/**
	 * Test isValid() with invalid values.
	 */
	public function test_is_valid_with_invalid_values() {
		$this->assertFalse(ToolType::isValid('webhook'));
		$this->assertFalse(ToolType::isValid('api'));
		$this->assertFalse(ToolType::isValid(''));
		$this->assertFalse(ToolType::isValid('HTTP')); // Case sensitive
	}

	/**
	 * Test tryFrom() with valid values.
	 */
	public function test_try_from_with_valid_values() {
		$this->assertSame(ToolType::Http, ToolType::tryFrom('http'));
		$this->assertSame(ToolType::Action, ToolType::tryFrom('action'));
		$this->assertSame(ToolType::Cli, ToolType::tryFrom('cli'));
	}

	/**
	 * Test tryFrom() with invalid values.
	 */
	public function test_try_from_with_invalid_values() {
		$this->assertNull(ToolType::tryFrom('invalid'));
		$this->assertNull(ToolType::tryFrom(''));
	}

	/**
	 * Test from() with valid values.
	 */
	public function test_from_with_valid_values() {
		$this->assertSame(ToolType::Http, ToolType::from('http'));
		$this->assertSame(ToolType::Cli, ToolType::from('cli'));
	}

	/**
	 * Test from() throws exception with invalid value.
	 */
	public function test_from_throws_exception_with_invalid_value() {
		$this->expectException(\ValueError::class);
		ToolType::from('invalid');
	}
}
