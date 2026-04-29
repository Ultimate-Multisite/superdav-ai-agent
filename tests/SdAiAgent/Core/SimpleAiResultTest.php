<?php
/**
 * Test case for SimpleAiResult class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\SimpleAiResult;
use WP_UnitTestCase;

/**
 * Test SimpleAiResult functionality.
 *
 * @group ai-client
 */
class SimpleAiResultTest extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Skip tests if AI Client SDK is not available.
		if ( ! class_exists( 'WordPress\AiClient\Messages\DTO\ModelMessage' ) ) {
			$this->markTestSkipped( 'AI Client SDK not available.' );
		}
	}

	// ── toText ────────────────────────────────────────────────────────────

	/**
	 * Test toText returns the text passed to constructor.
	 */
	public function test_to_text_returns_constructor_text(): void {
		$result = new SimpleAiResult( 'Hello, world!' );
		$this->assertSame( 'Hello, world!', $result->toText() );
	}

	/**
	 * Test toText returns empty string when constructed with empty string.
	 */
	public function test_to_text_returns_empty_string(): void {
		$result = new SimpleAiResult( '' );
		$this->assertSame( '', $result->toText() );
	}

	/**
	 * Test toText returns multiline text unchanged.
	 */
	public function test_to_text_returns_multiline_text(): void {
		$text   = "Line one\nLine two\nLine three";
		$result = new SimpleAiResult( $text );
		$this->assertSame( $text, $result->toText() );
	}

	// ── toMessage ─────────────────────────────────────────────────────────

	/**
	 * Test toMessage returns a Message object.
	 */
	public function test_to_message_returns_message_object(): void {
		$result  = new SimpleAiResult( 'Test response' );
		$message = $result->toMessage();
		$this->assertInstanceOf( 'WordPress\AiClient\Messages\DTO\Message', $message );
	}

	/**
	 * Test toMessage is cached — same instance returned on repeated calls.
	 */
	public function test_to_message_is_cached(): void {
		$result   = new SimpleAiResult( 'Cached response' );
		$message1 = $result->toMessage();
		$message2 = $result->toMessage();
		$this->assertSame( $message1, $message2 );
	}

	/**
	 * Test toMessage with empty text still returns a Message.
	 */
	public function test_to_message_with_empty_text(): void {
		$result  = new SimpleAiResult( '' );
		$message = $result->toMessage();
		$this->assertInstanceOf( 'WordPress\AiClient\Messages\DTO\Message', $message );
	}

	/**
	 * Test toMessage with tool_calls in raw data creates FunctionCall parts.
	 */
	public function test_to_message_with_tool_calls(): void {
		$raw = [
			'choices' => [
				[
					'message' => [
						'content'    => null,
						'tool_calls' => [
							[
								'id'       => 'call_abc123',
								'type'     => 'function',
								'function' => [
									'name'      => 'sd_ai_agent_memory_save',
									'arguments' => '{"content":"test memory"}',
								],
							],
						],
					],
				],
			],
		];

		$result  = new SimpleAiResult( '', $raw );
		$message = $result->toMessage();

		$this->assertInstanceOf( 'WordPress\AiClient\Messages\DTO\Message', $message );

		// The message should have parts — at least one for the function call.
		$parts = $message->getParts();
		$this->assertNotEmpty( $parts );
	}

	/**
	 * Test toMessage with multiple tool_calls.
	 */
	public function test_to_message_with_multiple_tool_calls(): void {
		$raw = [
			'choices' => [
				[
					'message' => [
						'content'    => null,
						'tool_calls' => [
							[
								'id'       => 'call_1',
								'type'     => 'function',
								'function' => [
									'name'      => 'tool_one',
									'arguments' => '{"arg":"value1"}',
								],
							],
							[
								'id'       => 'call_2',
								'type'     => 'function',
								'function' => [
									'name'      => 'tool_two',
									'arguments' => '{"arg":"value2"}',
								],
							],
						],
					],
				],
			],
		];

		$result  = new SimpleAiResult( '', $raw );
		$message = $result->toMessage();

		$parts = $message->getParts();
		// Should have 2 function call parts.
		$this->assertCount( 2, $parts );
	}

	/**
	 * Test toMessage with tool_calls and text content.
	 */
	public function test_to_message_with_text_and_tool_calls(): void {
		$raw = [
			'choices' => [
				[
					'message' => [
						'content'    => 'I will call a tool.',
						'tool_calls' => [
							[
								'id'       => 'call_xyz',
								'type'     => 'function',
								'function' => [
									'name'      => 'some_tool',
									'arguments' => '{}',
								],
							],
						],
					],
				],
			],
		];

		$result  = new SimpleAiResult( 'I will call a tool.', $raw );
		$message = $result->toMessage();

		$parts = $message->getParts();
		// Text part + function call part.
		$this->assertCount( 2, $parts );
	}

	/**
	 * Test toMessage with invalid JSON arguments falls back to empty array.
	 */
	public function test_to_message_with_invalid_json_arguments(): void {
		$raw = [
			'choices' => [
				[
					'message' => [
						'content'    => null,
						'tool_calls' => [
							[
								'id'       => 'call_bad',
								'type'     => 'function',
								'function' => [
									'name'      => 'bad_tool',
									'arguments' => 'not-valid-json',
								],
							],
						],
					],
				],
			],
		];

		$result  = new SimpleAiResult( '', $raw );
		// Should not throw.
		$message = $result->toMessage();
		$this->assertInstanceOf( 'WordPress\AiClient\Messages\DTO\Message', $message );
	}

	// ── getUsage ──────────────────────────────────────────────────────────

	/**
	 * Test getUsage returns null when no usage data.
	 */
	public function test_get_usage_returns_null_without_usage_data(): void {
		$result = new SimpleAiResult( 'text' );
		$this->assertNull( $result->getUsage() );
	}

	/**
	 * Test getUsage returns null when raw has no usage key.
	 */
	public function test_get_usage_returns_null_when_no_usage_key(): void {
		$result = new SimpleAiResult( 'text', [ 'choices' => [] ] );
		$this->assertNull( $result->getUsage() );
	}

	/**
	 * Test getUsage returns object with correct token counts.
	 */
	public function test_get_usage_returns_usage_object(): void {
		$raw = [
			'usage' => [
				'prompt_tokens'     => 150,
				'completion_tokens' => 75,
				'total_tokens'      => 225,
			],
		];

		$result = new SimpleAiResult( 'text', $raw );
		$usage  = $result->getUsage();

		$this->assertNotNull( $usage );
		$this->assertSame( 150, $usage->getPromptTokens() );
		$this->assertSame( 75, $usage->getCompletionTokens() );
	}

	/**
	 * Test getUsage with zero token counts.
	 */
	public function test_get_usage_with_zero_tokens(): void {
		$raw = [
			'usage' => [
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
			],
		];

		$result = new SimpleAiResult( 'text', $raw );
		$usage  = $result->getUsage();

		$this->assertNotNull( $usage );
		$this->assertSame( 0, $usage->getPromptTokens() );
		$this->assertSame( 0, $usage->getCompletionTokens() );
	}

	/**
	 * Test getUsage with missing token keys defaults to zero.
	 */
	public function test_get_usage_with_missing_token_keys(): void {
		$raw = [
			'usage' => [],
		];

		$result = new SimpleAiResult( 'text', $raw );
		$usage  = $result->getUsage();

		$this->assertNotNull( $usage );
		$this->assertSame( 0, $usage->getPromptTokens() );
		$this->assertSame( 0, $usage->getCompletionTokens() );
	}

	/**
	 * Test getUsage returns null when usage is not an array.
	 */
	public function test_get_usage_returns_null_when_usage_not_array(): void {
		$raw = [
			'usage' => 'not-an-array',
		];

		$result = new SimpleAiResult( 'text', $raw );
		$this->assertNull( $result->getUsage() );
	}
}
