<?php
/**
 * Test case for HttpMethod enum.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Enums;

use SdAiAgent\Enums\HttpMethod;
use WP_UnitTestCase;

/**
 * Test HttpMethod enum functionality.
 */
class HttpMethodTest extends WP_UnitTestCase {

	/**
	 * Test all HTTP method cases exist.
	 */
	public function test_http_method_cases_exist() {
		$cases = HttpMethod::cases();

		$this->assertCount(5, $cases);
		$this->assertContains(HttpMethod::Get, $cases);
		$this->assertContains(HttpMethod::Post, $cases);
		$this->assertContains(HttpMethod::Put, $cases);
		$this->assertContains(HttpMethod::Patch, $cases);
		$this->assertContains(HttpMethod::Delete, $cases);
	}

	/**
	 * Test HTTP method values are uppercase.
	 */
	public function test_http_method_values() {
		$this->assertSame('GET', HttpMethod::Get->value);
		$this->assertSame('POST', HttpMethod::Post->value);
		$this->assertSame('PUT', HttpMethod::Put->value);
		$this->assertSame('PATCH', HttpMethod::Patch->value);
		$this->assertSame('DELETE', HttpMethod::Delete->value);
	}

	/**
	 * Test values() returns all values as array.
	 */
	public function test_values_returns_array() {
		$values = HttpMethod::values();

		$this->assertIsArray($values);
		$this->assertCount(5, $values);
		$this->assertContains('GET', $values);
		$this->assertContains('POST', $values);
		$this->assertContains('PUT', $values);
		$this->assertContains('PATCH', $values);
		$this->assertContains('DELETE', $values);
	}

	/**
	 * Test isValid() with valid uppercase values.
	 */
	public function test_is_valid_with_valid_uppercase_values() {
		$this->assertTrue(HttpMethod::isValid('GET'));
		$this->assertTrue(HttpMethod::isValid('POST'));
		$this->assertTrue(HttpMethod::isValid('PUT'));
		$this->assertTrue(HttpMethod::isValid('PATCH'));
		$this->assertTrue(HttpMethod::isValid('DELETE'));
	}

	/**
	 * Test isValid() accepts lowercase and converts to uppercase.
	 */
	public function test_is_valid_with_lowercase_values() {
		$this->assertTrue(HttpMethod::isValid('get'));
		$this->assertTrue(HttpMethod::isValid('post'));
		$this->assertTrue(HttpMethod::isValid('put'));
		$this->assertTrue(HttpMethod::isValid('patch'));
		$this->assertTrue(HttpMethod::isValid('delete'));
	}

	/**
	 * Test isValid() with mixed case values.
	 */
	public function test_is_valid_with_mixed_case_values() {
		$this->assertTrue(HttpMethod::isValid('Get'));
		$this->assertTrue(HttpMethod::isValid('pOsT'));
	}

	/**
	 * Test isValid() with invalid values.
	 */
	public function test_is_valid_with_invalid_values() {
		$this->assertFalse(HttpMethod::isValid('HEAD'));
		$this->assertFalse(HttpMethod::isValid('OPTIONS'));
		$this->assertFalse(HttpMethod::isValid(''));
		$this->assertFalse(HttpMethod::isValid('CONNECT'));
	}

	/**
	 * Test tryFrom() with valid values.
	 */
	public function test_try_from_with_valid_values() {
		$this->assertSame(HttpMethod::Get, HttpMethod::tryFrom('GET'));
		$this->assertSame(HttpMethod::Post, HttpMethod::tryFrom('POST'));
	}

	/**
	 * Test tryFrom() with invalid values.
	 */
	public function test_try_from_with_invalid_values() {
		$this->assertNull(HttpMethod::tryFrom('invalid'));
		$this->assertNull(HttpMethod::tryFrom('get')); // tryFrom is case-sensitive
	}

	/**
	 * Test from() with valid values.
	 */
	public function test_from_with_valid_values() {
		$this->assertSame(HttpMethod::Get, HttpMethod::from('GET'));
		$this->assertSame(HttpMethod::Delete, HttpMethod::from('DELETE'));
	}

	/**
	 * Test from() throws exception with invalid value.
	 */
	public function test_from_throws_exception_with_invalid_value() {
		$this->expectException(\ValueError::class);
		HttpMethod::from('invalid');
	}
}
