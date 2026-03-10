<?php
/**
 * Test case for ConversationTrimmer class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Core;

use AiAgent\Core\ConversationTrimmer;
use AiAgent\Core\Settings;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\DTO\AssistantMessage;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WP_UnitTestCase;

/**
 * Test ConversationTrimmer functionality.
 *
 * @group ai-client
 */
class ConversationTrimmerTest extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Skip tests if AI Client SDK is not available.
		if ( ! class_exists( AssistantMessage::class ) ) {
			$this->markTestSkipped( 'AI Client SDK not available.' );
		}

		// Reset settings before each test.
		delete_option( 'ai_agent_settings' );
	}

	/**
	 * Create a mock user message.
	 *
	 * @param string $text Message text.
	 * @return UserMessage
	 */
	private function create_user_message( string $text ): UserMessage {
		return new UserMessage( [ new MessagePart( $text ) ] );
	}

	/**
	 * Create a mock assistant message.
	 *
	 * @param string $text Message text.
	 * @return AssistantMessage
	 */
	private function create_assistant_message( string $text ): AssistantMessage {
		return new AssistantMessage( [ new MessagePart( $text ) ] );
	}

	/**
	 * Test trim with empty history returns empty array.
	 */
	public function test_trim_empty_history() {
		$result = ConversationTrimmer::trim( [], 10 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test trim with max_turns = 0 disables trimming.
	 */
	public function test_trim_disabled_with_zero_max_turns() {
		$history = [
			$this->create_user_message( 'First message' ),
			$this->create_assistant_message( 'First response' ),
			$this->create_user_message( 'Second message' ),
			$this->create_assistant_message( 'Second response' ),
		];

		// With max_turns = 0, should return unchanged.
		$result = ConversationTrimmer::trim( $history, 0 );

		// Since settings also have no max_history_turns, should return all.
		$this->assertCount( 4, $result );
	}

	/**
	 * Test trim keeps all messages when under limit.
	 */
	public function test_trim_keeps_all_when_under_limit() {
		$history = [
			$this->create_user_message( 'Message 1' ),
			$this->create_assistant_message( 'Response 1' ),
			$this->create_user_message( 'Message 2' ),
			$this->create_assistant_message( 'Response 2' ),
		];

		// 2 turns, limit is 10.
		$result = ConversationTrimmer::trim( $history, 10 );

		$this->assertCount( 4, $result );
	}

	/**
	 * Test DEFAULT_MAX_TURNS constant value.
	 */
	public function test_default_max_turns_constant() {
		$this->assertSame( 20, ConversationTrimmer::DEFAULT_MAX_TURNS );
	}

	/**
	 * Test estimate_tokens returns integer.
	 */
	public function test_estimate_tokens_returns_integer() {
		$message = $this->create_user_message( 'Hello, this is a test message.' );
		$tokens = ConversationTrimmer::estimate_tokens( $message );

		$this->assertIsInt( $tokens );
		$this->assertGreaterThan( 0, $tokens );
	}

	/**
	 * Test estimate_tokens with longer text.
	 */
	public function test_estimate_tokens_longer_text() {
		$short = $this->create_user_message( 'Hi' );
		$long = $this->create_user_message( str_repeat( 'word ', 100 ) );

		$short_tokens = ConversationTrimmer::estimate_tokens( $short );
		$long_tokens = ConversationTrimmer::estimate_tokens( $long );

		$this->assertGreaterThan( $short_tokens, $long_tokens );
	}

	/**
	 * Test estimate_total_tokens sums correctly.
	 */
	public function test_estimate_total_tokens() {
		$history = [
			$this->create_user_message( 'Message one' ),
			$this->create_assistant_message( 'Response one' ),
		];

		$total = ConversationTrimmer::estimate_total_tokens( $history );

		$this->assertIsInt( $total );
		$this->assertGreaterThan( 0, $total );
	}

	/**
	 * Test estimate_total_tokens with empty array.
	 */
	public function test_estimate_total_tokens_empty() {
		$total = ConversationTrimmer::estimate_total_tokens( [] );
		$this->assertSame( 0, $total );
	}

	/**
	 * Test trim with single turn is not trimmed.
	 */
	public function test_trim_single_turn_not_trimmed() {
		$history = [
			$this->create_user_message( 'Only message' ),
			$this->create_assistant_message( 'Only response' ),
		];

		$result = ConversationTrimmer::trim( $history, 1 );

		// Single turn, should not be trimmed even with max_turns = 1.
		$this->assertCount( 2, $result );
	}

	/**
	 * Test token estimation uses ~4 characters per token.
	 */
	public function test_token_estimation_ratio() {
		// "test" = 4 characters = ~1 token.
		$message = $this->create_user_message( 'test' );
		$tokens = ConversationTrimmer::estimate_tokens( $message );

		// Should be at least 1 token.
		$this->assertGreaterThanOrEqual( 1, $tokens );

		// 40 characters should be ~10 tokens.
		$longer = $this->create_user_message( str_repeat( 'a', 40 ) );
		$longer_tokens = ConversationTrimmer::estimate_tokens( $longer );

		$this->assertGreaterThanOrEqual( 10, $longer_tokens );
	}

	/**
	 * Test trim preserves message order.
	 */
	public function test_trim_preserves_order() {
		$history = [
			$this->create_user_message( 'First' ),
			$this->create_assistant_message( 'First response' ),
		];

		$result = ConversationTrimmer::trim( $history, 5 );

		// Order should be preserved.
		$this->assertCount( 2, $result );
	}
}
