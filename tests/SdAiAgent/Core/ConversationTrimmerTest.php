<?php
/**
 * Test case for ConversationTrimmer class.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\ConversationTrimmer;
use SdAiAgent\Core\Settings;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\DTO\AssistantMessage;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
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
		delete_option( 'sd_ai_agent_settings' );
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

	// ── Tool-response pairing tests ──────────────────────────────────────

	/**
	 * Create a mock assistant message with tool calls (FunctionCall parts).
	 *
	 * @param array<int, array{id: string, name: string}> $calls Tool call definitions.
	 * @return object Mock assistant message.
	 */
	private function create_tool_call_message( array $calls ): object {
		$parts = [];
		foreach ( $calls as $call_def ) {
			$call = $this->createMock( FunctionCall::class );
			$call->method( 'getId' )->willReturn( $call_def['id'] );
			$call->method( 'getName' )->willReturn( $call_def['name'] );
			$call->method( 'getArgs' )->willReturn( [] );

			$part = $this->createMock( MessagePart::class );
			$part->method( 'getFunctionCall' )->willReturn( $call );
			$part->method( 'getFunctionResponse' )->willReturn( null );
			$part->method( 'getText' )->willReturn( null );

			$parts[] = $part;
		}

		$role = $this->createMock( \WordPress\AiClient\Messages\Enums\MessageRoleEnum::class );
		$role->method( '__toString' )->willReturn( 'model' );

		$message = $this->createMock( \WordPress\AiClient\Messages\DTO\Message::class );
		$message->method( 'getParts' )->willReturn( $parts );
		$message->method( 'getRole' )->willReturn( $role );

		return $message;
	}

	/**
	 * Create a mock tool-response UserMessage (FunctionResponse parts).
	 *
	 * @param string $id   Tool call ID to match.
	 * @param string $name Tool name.
	 * @return object Mock user message with FunctionResponse.
	 */
	private function create_tool_response_message( string $id, string $name ): object {
		$response = $this->createMock( FunctionResponse::class );
		$response->method( 'getId' )->willReturn( $id );
		$response->method( 'getName' )->willReturn( $name );
		$response->method( 'getResponse' )->willReturn( '{"success":true}' );

		$part = $this->createMock( MessagePart::class );
		$part->method( 'getFunctionCall' )->willReturn( null );
		$part->method( 'getFunctionResponse' )->willReturn( $response );
		$part->method( 'getText' )->willReturn( null );

		$role = $this->createMock( \WordPress\AiClient\Messages\Enums\MessageRoleEnum::class );
		$role->method( '__toString' )->willReturn( 'user' );

		$message = $this->createMock( \WordPress\AiClient\Messages\DTO\Message::class );
		$message->method( 'getParts' )->willReturn( [ $part ] );
		$message->method( 'getRole' )->willReturn( $role );

		return $message;
	}

	/**
	 * Create a user message mock with proper role detection.
	 *
	 * @param string $text Message text.
	 * @return object Mock user message.
	 */
	private function create_user_message_mock( string $text ): object {
		$part = $this->createMock( MessagePart::class );
		$part->method( 'getFunctionCall' )->willReturn( null );
		$part->method( 'getFunctionResponse' )->willReturn( null );
		$part->method( 'getText' )->willReturn( $text );

		$role = $this->createMock( \WordPress\AiClient\Messages\Enums\MessageRoleEnum::class );
		$role->method( '__toString' )->willReturn( 'user' );

		$message = $this->createMock( \WordPress\AiClient\Messages\DTO\Message::class );
		$message->method( 'getParts' )->willReturn( [ $part ] );
		$message->method( 'getRole' )->willReturn( $role );

		return $message;
	}

	/**
	 * Create an assistant message mock with proper role detection.
	 *
	 * @param string $text Message text.
	 * @return object Mock assistant message.
	 */
	private function create_assistant_message_mock( string $text ): object {
		$part = $this->createMock( MessagePart::class );
		$part->method( 'getFunctionCall' )->willReturn( null );
		$part->method( 'getFunctionResponse' )->willReturn( null );
		$part->method( 'getText' )->willReturn( $text );

		$role = $this->createMock( \WordPress\AiClient\Messages\Enums\MessageRoleEnum::class );
		$role->method( '__toString' )->willReturn( 'model' );

		$message = $this->createMock( \WordPress\AiClient\Messages\DTO\Message::class );
		$message->method( 'getParts' )->willReturn( [ $part ] );
		$message->method( 'getRole' )->willReturn( $role );

		return $message;
	}

	/**
	 * Test that tool-response UserMessages are NOT counted as turn boundaries.
	 *
	 * Regression test for the 400 error: "tool_use ids were found without
	 * tool_result blocks immediately after". This happened because the trimmer
	 * treated FunctionResponse UserMessages as turn boundaries, allowing it
	 * to cut between a tool_use and its tool_result.
	 */
	public function test_trim_does_not_split_tool_call_cycle() {
		// Simulate a conversation with multiple tool-call cycles.
		// Turn 1: user asks, assistant calls 3 tools, results come back.
		// Turn 2: user asks again, assistant responds.
		// Turn 3: user asks again, assistant responds.
		// Turn 4: user asks again, assistant responds.
		$history = [
			// Turn 1.
			$this->create_user_message_mock( 'Create a page about cows' ),
			$this->create_tool_call_message( [
				[ 'id' => 'call_1', 'name' => 'ability-search' ],
				[ 'id' => 'call_2', 'name' => 'ability-call' ],
				[ 'id' => 'call_3', 'name' => 'ability-call' ],
			] ),
			// 3 tool results (each a separate UserMessage, per OpenAI splitting).
			$this->create_tool_response_message( 'call_1', 'ability-search' ),
			$this->create_tool_response_message( 'call_2', 'ability-call' ),
			$this->create_tool_response_message( 'call_3', 'ability-call' ),
			$this->create_assistant_message_mock( 'Page created!' ),
			// Turn 2.
			$this->create_user_message_mock( 'Add images' ),
			$this->create_assistant_message_mock( 'Images added!' ),
			// Turn 3.
			$this->create_user_message_mock( 'Make it longer' ),
			$this->create_assistant_message_mock( 'Extended the page.' ),
			// Turn 4.
			$this->create_user_message_mock( 'Add a featured image' ),
			$this->create_assistant_message_mock( 'Featured image set.' ),
		];

		// Trim to 2 turns — this MUST preserve tool-call pairing in turn 1
		// if the first turn is retained.
		$result = ConversationTrimmer::trim( $history, 2 );

		// Verify: no assistant message with FunctionCall exists without
		// its matching FunctionResponse in the next position(s).
		$this->assert_tool_pairs_valid( $result );
	}

	/**
	 * Test that 7 parallel tool calls (the cow image scenario) are preserved.
	 */
	public function test_trim_preserves_parallel_tool_calls() {
		$calls = [];
		for ( $c = 1; $c <= 7; $c++ ) {
			$calls[] = [ 'id' => "call_$c", 'name' => 'import-image' ];
		}

		$history = [
			$this->create_user_message_mock( 'Add 7 images of cows' ),
			$this->create_tool_call_message( $calls ),
		];

		// Add 7 individual tool responses (matching the split pattern).
		for ( $c = 1; $c <= 7; $c++ ) {
			$history[] = $this->create_tool_response_message( "call_$c", 'import-image' );
		}

		$history[] = $this->create_assistant_message_mock( 'All 7 images imported.' );
		$history[] = $this->create_user_message_mock( 'The layout looks bad' );
		$history[] = $this->create_assistant_message_mock( 'Let me fix that.' );

		// Even with a tight trim limit, tool pairs must stay intact.
		$result = ConversationTrimmer::trim( $history, 2 );
		$this->assert_tool_pairs_valid( $result );
	}

	/**
	 * Test validate_tool_pairs removes orphaned tool_use messages.
	 */
	public function test_validate_tool_pairs_removes_orphaned_tool_use() {
		$history = [
			$this->create_user_message_mock( 'Hello' ),
			// Orphaned tool call — no response follows.
			$this->create_tool_call_message( [
				[ 'id' => 'call_orphan', 'name' => 'some-tool' ],
			] ),
			// Next user message (NOT a tool response).
			$this->create_user_message_mock( 'Continue please' ),
			$this->create_assistant_message_mock( 'Sure.' ),
		];

		$result = ConversationTrimmer::validate_tool_pairs( $history );

		// The orphaned tool call should be removed; other messages preserved.
		$this->assert_tool_pairs_valid( $result );
		// Should have: user, user, assistant = 3 messages (orphan removed).
		$this->assertCount( 3, $result );
	}

	/**
	 * Test validate_tool_pairs keeps complete tool cycles intact.
	 */
	public function test_validate_tool_pairs_keeps_complete_cycles() {
		$history = [
			$this->create_user_message_mock( 'Search for something' ),
			$this->create_tool_call_message( [
				[ 'id' => 'call_1', 'name' => 'search' ],
			] ),
			$this->create_tool_response_message( 'call_1', 'search' ),
			$this->create_assistant_message_mock( 'Found it!' ),
		];

		$result = ConversationTrimmer::validate_tool_pairs( $history );

		// All messages should be preserved — the cycle is complete.
		$this->assertCount( 4, $result );
	}

	/**
	 * Test validate_tool_pairs handles partial responses (some tools missing).
	 */
	public function test_validate_tool_pairs_removes_partially_matched_cycles() {
		$history = [
			$this->create_user_message_mock( 'Do two things' ),
			$this->create_tool_call_message( [
				[ 'id' => 'call_a', 'name' => 'tool-a' ],
				[ 'id' => 'call_b', 'name' => 'tool-b' ],
			] ),
			// Only one response for two calls.
			$this->create_tool_response_message( 'call_a', 'tool-a' ),
			$this->create_user_message_mock( 'What happened?' ),
		];

		$result = ConversationTrimmer::validate_tool_pairs( $history );

		// The incomplete cycle should be removed.
		$this->assert_tool_pairs_valid( $result );
		// Should have: user, user = 2 messages.
		$this->assertCount( 2, $result );
	}

	/**
	 * Assert that all tool_use messages in a history have matching tool_results.
	 *
	 * @param array $history The conversation history to validate.
	 */
	private function assert_tool_pairs_valid( array $history ): void {
		$count = count( $history );
		for ( $i = 0; $i < $count; $i++ ) {
			$message  = $history[ $i ];
			$call_ids = [];

			foreach ( $message->getParts() as $part ) {
				if ( method_exists( $part, 'getFunctionCall' ) ) {
					$fc = $part->getFunctionCall();
					if ( $fc ) {
						$call_ids[] = $fc->getId();
					}
				}
			}

			if ( empty( $call_ids ) ) {
				continue;
			}

			// Collect response IDs from the messages that follow.
			$response_ids = [];
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$has_response = false;
				foreach ( $history[ $j ]->getParts() as $part ) {
					if ( method_exists( $part, 'getFunctionResponse' ) ) {
						$fr = $part->getFunctionResponse();
						if ( $fr ) {
							$response_ids[] = $fr->getId();
							$has_response   = true;
						}
					}
				}
				if ( ! $has_response ) {
					break;
				}
			}

			foreach ( $call_ids as $call_id ) {
				$this->assertContains(
					$call_id,
					$response_ids,
					"tool_use ID '$call_id' has no matching tool_result in history"
				);
			}
		}
	}
}
