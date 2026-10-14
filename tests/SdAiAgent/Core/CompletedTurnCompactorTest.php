<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\CompletedTurnCompactor;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WP_UnitTestCase;

/** Completed-turn tool payloads are bounded in the provider view only. */
final class CompletedTurnCompactorTest extends WP_UnitTestCase {

	/**
	 * Build one user turn with a tool call and result.
	 *
	 * @return list<\WordPress\AiClient\Messages\DTO\Message>
	 */
	private function turn( string $text, string $call_id, array $args, array $result ): array {
		return array(
			new UserMessage( array( new MessagePart( $text ) ) ),
			new ModelMessage( array( new MessagePart( new FunctionCall( $call_id, 'wpab__sd-ai-agent__get-global-styles', $args ) ) ) ),
			new UserMessage( array( new MessagePart( new FunctionResponse( $call_id, 'wpab__sd-ai-agent__get-global-styles', $result ) ) ) ),
		);
	}

	/** Completed-turn payloads shrink; the active turn and identifiers are untouched. */
	public function test_completed_turn_payloads_are_bounded_and_current_turn_is_unchanged(): void {
		$large_result = array( 'styles' => str_repeat( 'a', 30000 ) );
		$large_args   = array(
			'section' => 'all',
			'styles'  => array( 'blocks' => str_repeat( 'b', 5000 ) ),
		);
		$completed    = $this->turn( 'first', 'call_1', $large_args, $large_result );
		$current      = $this->turn( 'second', 'call_2', $large_args, $large_result );
		$history      = array_merge( $completed, $current );

		$compacted = CompletedTurnCompactor::compact_history( $history, count( $completed ) );

		$this->assertSame( $history[0], $compacted[0], 'User text is not rewritten.' );
		$call = $compacted[1]->getParts()[0]->getFunctionCall();
		$this->assertSame( 'call_1', $call->getId() );
		$this->assertSame( 'all', $call->getArgs()['section'] );
		$this->assertStringContainsString( 'omitted from an earlier turn', $call->getArgs()['styles'] );

		$response = $compacted[2]->getParts()[0]->getFunctionResponse();
		$this->assertSame( 'call_1', $response->getId() );
		$this->assertSame( 'wpab__sd-ai-agent__get-global-styles', $response->getName() );
		$this->assertStringStartsWith( CompletedTurnCompactor::MARKER, $response->getResponse() );
		$this->assertLessThan( 1500, strlen( $response->getResponse() ) );

		$this->assertSame( array_slice( $history, 3 ), array_slice( $compacted, 3 ), 'Active-turn messages must be identical objects.' );
	}

	/** Small payloads, a zero boundary, and already-compacted output are unchanged. */
	public function test_small_payloads_and_zero_boundary_are_unchanged(): void {
		$history = $this->turn( 'first', 'call_1', array( 'post_id' => 1 ), array( 'title' => 'Hello' ) );

		$this->assertSame( $history, CompletedTurnCompactor::compact_history( $history, count( $history ) ) );

		$large = $this->turn( 'first', 'call_1', array(), array( 'x' => str_repeat( 'z', 9000 ) ) );
		$this->assertSame( $large, CompletedTurnCompactor::compact_history( $large, 0 ) );

		$once = CompletedTurnCompactor::compact_result_text( str_repeat( 'q', 9000 ) );
		$this->assertSame( $once, CompletedTurnCompactor::compact_result_text( $once ) );
	}

	/** Call IDs are collected only from the completed prefix. */
	public function test_completed_call_ids_stop_at_the_boundary(): void {
		$history = array_merge(
			$this->turn( 'first', 'call_1', array(), array() ),
			$this->turn( 'second', 'call_2', array(), array() )
		);

		$this->assertSame( array( 'call_1' => true ), CompletedTurnCompactor::completed_call_ids( $history, 3 ) );
	}

	/** Excerpts never split a multi-byte character. */
	public function test_excerpt_is_valid_utf8(): void {
		$compacted = CompletedTurnCompactor::compact_result_text( str_repeat( 'é', 5000 ) );

		$this->assertTrue( mb_check_encoding( $compacted, 'UTF-8' ) );
	}
}
