<?php
declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\ConversationSerializer;
use SdAiAgent\Core\SkillContextDelivery;
use SdAiAgent\Core\ToolResultTruncator;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WP_UnitTestCase;

/** Recoverable skill pages must survive the actual provider-history boundary. */
final class SkillContextDeliveryTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		SkillContextDelivery::reset();
	}

	public function test_pages_are_bounded_utf8_and_reconstruct_the_entire_guide(): void {
		$source = "# Guide\n\n## Rules\n" . str_repeat( "Never publish without approval. ☕\n", 1000 );
		$input = [];
		$combined = '';
		for ( $page = 0; $page < 100; ++$page ) {
			$result = SkillContextDelivery::build( 'guide', 'Guide', $source, $input );
			$this->assertIsArray( $result );
			$this->assertLessThanOrEqual( SkillContextDelivery::MAX_BYTES, strlen( wp_json_encode( $result ) ) );
			$this->assertSame( 1, preg_match( '//u', $result['content'] ) );
			$this->assertFalse( $result['already_loaded'] );
			$repeat = SkillContextDelivery::build( 'guide', 'Guide', $source, $input );
			$this->assertTrue( $repeat['already_loaded'] );
			$this->assertSame( $result['next_offset'], $repeat['next_offset'] );
			$combined .= $result['content'];
			if ( null === $result['next_offset'] ) {
				break;
			}
			$this->assertGreaterThan( $result['offset'], $result['next_offset'] );
			$input = [ 'offset' => $repeat['next_offset'], 'content_version' => $result['content_version'] ];
		}
		$this->assertSame( $source, $combined );
	}

	public function test_explicit_reload_recovers_guidance_without_disabling_deduplication(): void {
		$source = "# Guide\n☕" . str_repeat( 'Retain approved facts. ', 1000 );
		$first = SkillContextDelivery::build( 'guide', 'Guide', $source );
		$this->assertSame( '', SkillContextDelivery::build( 'guide', 'Guide', $source )['content'] );
		$reload = SkillContextDelivery::build( 'guide', 'Guide', $source, [ 'reload' => true ] );
		$this->assertFalse( $reload['already_loaded'] );
		$this->assertSame( $first['content'], $reload['content'] );
		$this->assertSame( strlen( $reload['content'] ), $reload['next_offset'] );
		$repeat = SkillContextDelivery::build( 'guide', 'Guide', $source );
		$this->assertTrue( $repeat['already_loaded'] );
		$this->assertSame( $reload['next_offset'], $repeat['next_offset'] );
		$this->assertWPError( SkillContextDelivery::build( 'guide', 'Guide', $source, [ 'offset' => 9, 'reload' => true ] ) );
		$this->assertWPError( SkillContextDelivery::build( 'guide', 'Guide', $source . 'changed', [ 'content_version' => $first['content_version'], 'reload' => true ] ) );
	}

	public function test_exact_sections_versions_and_duplicate_delivery(): void {
		$source = "# Guide\n\n## Intro\n" . str_repeat( 'Overview. ', 1000 ) . "\n## Guardrails\nNever publish without approval.\n";
		$first = SkillContextDelivery::build( 'guide', 'Guide', $source );
		$repeat = SkillContextDelivery::build( 'guide', 'Guide', $source );
		$this->assertTrue( $repeat['already_loaded'] );
		$this->assertSame( '', $repeat['content'] );
		$section = SkillContextDelivery::build( 'guide', 'Guide', $source, [ 'section' => 'Guardrails' ] );
		$this->assertStringContainsString( 'Never publish without approval.', $section['content'] );
		$this->assertFalse( $section['truncated'] );
		$this->assertWPError( SkillContextDelivery::build( 'guide', 'Guide', $source, [ 'section' => 'missing' ] ) );
		$this->assertWPError( SkillContextDelivery::build( 'guide', 'Guide', $source . 'edited', [ 'content_version' => $first['content_version'] ] ) );
		$this->assertFalse( SkillContextDelivery::build( 'guide', 'Guide', $source . 'edited' )['already_loaded'] );
		SkillContextDelivery::reset();
		$this->assertFalse( SkillContextDelivery::build( 'guide', 'Guide', $source )['already_loaded'] );
	}

	public function test_skill_pages_survive_direct_and_wrapped_serializer_paths(): void {
		$page = SkillContextDelivery::build( 'guide', 'Guide', "# Guide\n" . str_repeat( 'Useful rule. ', 2000 ) );
		$this->assertGreaterThan( 1000, strlen( $page['content'] ) );
		foreach ( [
			[ 'wpab__sd-ai-agent__skill-load', $page ],
			[ 'wpab__sd-ai-agent__ability-call', [ 'ability' => 'sd-ai-agent/skill-load', 'success' => true, 'result' => $page ] ],
			[ 'wpab__sd-ai-agent__ability-call', [ 'ability' => 'sd-ai-agent/get-theme-json', 'success' => true, 'result' => [ 'data' => str_repeat( 'x', 10000 ) ], '_skill_context' => $page ] ],
		] as [ $name, $payload ] ) {
			foreach ( [ $payload, wp_json_encode( $payload ) ] as $transport ) {
				$message = new UserMessage( [ new MessagePart( new FunctionResponse( 'skill', $name, $transport ) ) ] );
				$history = ConversationSerializer::truncate_tool_results( $message );
				$result = $history->getParts()[0]->getFunctionResponse()->getResponse();
				$this->assertLessThanOrEqual( ToolResultTruncator::MAX_SKILL_ENVELOPE_BYTES, strlen( wp_json_encode( $result ) ) );
				if ( is_string( $transport ) ) {
					$this->assertIsString( $result );
					$result = json_decode( $result, true );
				}
				$skill = $result['result']['content'] ?? $result['_skill_context']['content'] ?? $result['content'];
				$this->assertSame( $page['content'], $skill );
			}
		}
		$this->assertLessThanOrEqual( ToolResultTruncator::MAX_RESULT_BYTES, strlen( wp_json_encode( ToolResultTruncator::truncate( $page, 'unknown/tool' ) ) ) );
	}
}
