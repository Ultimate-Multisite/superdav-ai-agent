<?php
/**
 * Unit tests for {@see SchemaNormalizer}.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Tests\Infrastructure\Schema;

use GratisAiAgent\Infrastructure\Schema\SchemaNormalizer;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Pure-PHP tests — no WordPress bootstrap required.
 *
 * We use the raw PHPUnit `TestCase` rather than `WP_UnitTestCase` because
 * `SchemaNormalizer` has zero WordPress dependencies and the WP test harness
 * adds ~2 seconds of bootstrap per suite run that we don't need here.
 */
final class SchemaNormalizerTest extends TestCase {

	public function test_empty_schema_becomes_empty_object_schema(): void {
		$result = SchemaNormalizer::normalize( array() );

		$this->assertSame( 'object', $result['type'] );
		$this->assertInstanceOf( stdClass::class, $result['properties'] );
	}

	public function test_scalar_is_returned_unchanged(): void {
		$this->assertSame( 'foo', SchemaNormalizer::normalize( 'foo' ) );
		$this->assertSame( 42, SchemaNormalizer::normalize( 42 ) );
		$this->assertNull( SchemaNormalizer::normalize( null ) );
	}

	public function test_properties_presence_infers_object_type(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'properties' => array( 'name' => array( 'type' => 'string' ) ),
			)
		);

		$this->assertSame( 'object', $result['type'] );
	}

	public function test_required_array_presence_infers_object_type(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'required' => array( 'name' ),
			)
		);

		$this->assertSame( 'object', $result['type'] );
	}

	public function test_empty_properties_array_is_coerced_to_stdclass(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'       => 'object',
				'properties' => array(),
			)
		);

		$this->assertInstanceOf( stdClass::class, $result['properties'] );
		$this->assertSame( '{"type":"object","properties":{}}', wp_json_encode( $result ) );
	}

	public function test_object_schema_without_properties_backfills_stdclass(): void {
		$result = SchemaNormalizer::normalize( array( 'type' => 'object' ) );

		$this->assertInstanceOf( stdClass::class, $result['properties'] );
	}

	public function test_array_schema_without_items_backfills_permissive_items(): void {
		$result = SchemaNormalizer::normalize( array( 'type' => 'array' ) );

		$this->assertInstanceOf( stdClass::class, $result['items'] );
	}

	public function test_list_items_array_is_replaced_with_permissive_object(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'  => 'array',
				'items' => array(),
			)
		);

		$this->assertInstanceOf( stdClass::class, $result['items'] );
	}

	public function test_object_items_is_recursively_normalised(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'  => 'array',
				'items' => array( 'properties' => array() ),
			)
		);

		$this->assertSame( 'object', $result['items']['type'] );
		$this->assertInstanceOf( stdClass::class, $result['items']['properties'] );
	}

	public function test_draft_04_boolean_required_is_promoted_to_parent_required(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'       => 'object',
				'properties' => array(
					'name'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'email' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		$this->assertSame( array( 'name' ), $result['required'] );
		$this->assertArrayNotHasKey( 'required', $result['properties']['name'] );
		$this->assertArrayNotHasKey( 'required', $result['properties']['email'] );
	}

	public function test_promoted_required_is_merged_with_existing_required_without_duplicates(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'       => 'object',
				'required'   => array( 'id' ),
				'properties' => array(
					'id'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		$this->assertSame( array( 'id', 'name' ), $result['required'] );
	}

	public function test_empty_array_default_is_stripped(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'    => 'object',
				'default' => array(),
			)
		);

		$this->assertArrayNotHasKey( 'default', $result );
	}

	public function test_non_empty_default_is_preserved(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'type'    => 'string',
				'default' => 'hello',
			)
		);

		$this->assertSame( 'hello', $result['default'] );
	}

	public function test_combinators_are_recursively_normalised(): void {
		$result = SchemaNormalizer::normalize(
			array(
				'anyOf' => array(
					array( 'type' => 'object' ),
					array( 'properties' => array( 'foo' => array( 'type' => 'string' ) ) ),
				),
			)
		);

		$this->assertInstanceOf( stdClass::class, $result['anyOf'][0]['properties'] );
		$this->assertSame( 'object', $result['anyOf'][1]['type'] );
	}

	public function test_properties_encode_to_json_object_not_array(): void {
		// This is the bug that motivated the whole normaliser: Ollama crashes
		// on `"properties":[]` but accepts `"properties":{}`.
		$result = SchemaNormalizer::normalize(
			array(
				'type'       => 'object',
				'properties' => array(),
			)
		);

		$json = wp_json_encode( $result );
		$this->assertStringContainsString( '"properties":{}', $json );
		$this->assertStringNotContainsString( '"properties":[]', $json );
	}
}
