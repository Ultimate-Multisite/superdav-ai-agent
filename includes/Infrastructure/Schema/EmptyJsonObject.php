<?php
/**
 * An empty-object placeholder that JSON-encodes as `{}` while supporting
 * PHP array-access syntax required by WordPress core schema validation.
 *
 * Problem: AI providers (Anthropic, OpenAI, Ollama) require `"properties":{}`
 * in tool schemas. PHP's `json_encode([])` produces `[]`, so the old code used
 * `(object)[]` (stdClass). But WP core's `rest_validate_object_value_from_schema()`
 * at rest-api.php:2408 uses `$schema['properties'][$key]` — array bracket access
 * that throws TypeError on stdClass in PHP 8.
 *
 * Solution: this class implements both JsonSerializable (for JSON `{}`) and
 * ArrayAccess + IteratorAggregate + Countable (for WP core compatibility).
 *
 * @package GratisAiAgent\Infrastructure\Schema
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Infrastructure\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Empty-object placeholder for JSON Schema properties/items.
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
final class EmptyJsonObject implements \JsonSerializable, \ArrayAccess, \Countable, \IteratorAggregate {

	/**
	 * JSON-serialize as an empty object `{}`.
	 *
	 * @return \stdClass
	 */
	public function jsonSerialize(): \stdClass {
		return new \stdClass();
	}

	/**
	 * Always false — the object has no keys.
	 *
	 * @param mixed $offset Key to check.
	 * @return bool
	 */
	public function offsetExists( mixed $offset ): bool {
		return false;
	}

	/**
	 * Always null — the object has no values.
	 *
	 * @param mixed $offset Key to read.
	 * @return null
	 */
	public function offsetGet( mixed $offset ): mixed {
		return null;
	}

	/**
	 * No-op — the object is immutable.
	 *
	 * @param mixed $offset Key.
	 * @param mixed $value  Value.
	 */
	public function offsetSet( mixed $offset, mixed $value ): void {
		// Immutable.
	}

	/**
	 * No-op — the object is immutable.
	 *
	 * @param mixed $offset Key.
	 */
	public function offsetUnset( mixed $offset ): void {
		// Immutable.
	}

	/**
	 * Always zero — the object is empty.
	 *
	 * @return int
	 */
	public function count(): int {
		return 0;
	}

	/**
	 * Empty iterator — no properties to walk.
	 *
	 * @return \ArrayIterator<string, mixed>
	 */
	public function getIterator(): \ArrayIterator {
		return new \ArrayIterator( array() );
	}
}
