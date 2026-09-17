<?php

declare(strict_types=1);
/**
 * Tests for Elementor mutation completion, rendering, and publication gating.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\ElementorCompletionGate;
use WP_UnitTestCase;

/** Verifies direct and dispatcher Elementor completion evidence. */
class ElementorCompletionGateTest extends WP_UnitTestCase {

	private const POST_ID = 41;

	private const PREVIEW_URL = 'https://example.test/?elementor-preview=41&preview-token=private-token';

	/** Current mobile and desktop evidence is required before publication. */
	public function test_direct_mutation_requires_current_mobile_and_desktop_preview_evidence_before_publish(): void {
		$gate = $this->gate();
		$this->record_mutation( $gate, self::POST_ID, 101 );

		$blocker = $gate->get_publish_blocker( ElementorCompletionGate::PUBLISH_ABILITY, array( 'post_id' => self::POST_ID ) );
		$this->assertIsArray( $blocker );
		$this->assertSame( 'sd_ai_agent_elementor_publish_render_unverified', $blocker['code'] );

		$this->record_preview( $gate, self::POST_ID, 101, self::PREVIEW_URL );
		$calls = $gate->get_render_validation_calls();
		$this->assertCount( 2, $calls );
		$this->assertSame( self::PREVIEW_URL, $calls[0]['url'] );
		$this->assertSame( array( 375, 1280 ), array_column( $calls, 'width' ) );
		$this->assertSame( array( 812, 800 ), array_column( $calls, 'height' ) );

		$this->record_screenshots( $gate, self::PREVIEW_URL );
		$this->assertNull( $gate->get_publish_blocker( ElementorCompletionGate::PUBLISH_ABILITY, array( 'post_id' => self::POST_ID ) ) );
		$this->assertStringContainsString( 'remains unpublished', $gate->get_terminal_notice() );

		$gate->record_tool_call( ElementorCompletionGate::PUBLISH_ABILITY, array( 'post_id' => self::POST_ID ) );
		$gate->record_tool_response( ElementorCompletionGate::PUBLISH_ABILITY, array( 'success' => true, 'post_id' => self::POST_ID ) );
		$this->assertSame( '', $gate->get_terminal_notice() );
	}

	/** Wrapped dispatcher calls participate in the same state machine. */
	public function test_wrapped_mutation_and_result_use_ability_call_envelope(): void {
		$gate = $this->gate();
		$gate->record_tool_call(
			'wpab__sd-ai-agent__ability-call',
			array(
				'ability'   => 'elementor/build-composition',
				'arguments' => array( 'post_id' => self::POST_ID, 'revision_id' => 101 ),
			)
		);
		$gate->record_tool_response(
			'wpab__sd-ai-agent__ability-call',
			array(
				'ability' => 'elementor/build-composition',
				'success' => true,
				'result'  => array( 'success' => true, 'post_id' => self::POST_ID, 'revision_id' => 101 ),
			)
		);
		$gate->record_tool_call(
			'sd-ai-agent/ability-call',
			array(
				'ability'   => ElementorCompletionGate::PREVIEW_ABILITY,
				'arguments' => array( 'post_id' => self::POST_ID, 'revision_id' => 101 ),
			)
		);
		$gate->record_tool_response(
			'sd-ai-agent/ability-call',
			array(
				'ability' => ElementorCompletionGate::PREVIEW_ABILITY,
				'success' => true,
				'result'  => array(
					'success'     => true,
					'post_id'     => self::POST_ID,
					'revision_id' => 101,
					'preview_url' => self::PREVIEW_URL,
				),
			)
		);

		$this->assertCount( 2, $gate->get_render_validation_calls() );
		$blocker = $gate->get_publish_blocker(
			'wpab__sd-ai-agent__ability-call',
			array(
				'ability'   => ElementorCompletionGate::PUBLISH_ABILITY,
				'arguments' => array( 'document_id' => self::POST_ID ),
			)
		);
		$this->assertIsArray( $blocker );
		$this->assertSame( 'sd_ai_agent_elementor_publish_render_unverified', $blocker['code'] );
	}

	/** A later mutation invalidates even a previously passing preview sequence. */
	public function test_later_mutation_invalidates_preview_and_screenshots(): void {
		$gate = $this->gate();
		$this->record_mutation( $gate, self::POST_ID, 101 );
		$this->record_preview( $gate, self::POST_ID, 101, self::PREVIEW_URL );
		$this->record_screenshots( $gate, self::PREVIEW_URL );
		$this->assertTrue( $gate->get_status()['targets'][0]['current_render_verified'] );

		$this->record_mutation( $gate, self::POST_ID, 102 );
		$status = $gate->get_status();
		$this->assertFalse( $status['targets'][0]['current_preview_available'] );
		$this->assertFalse( $status['targets'][0]['current_render_verified'] );
		$this->assertSame( 2, $status['targets'][0]['mutation_version'] );
		$this->assertSame(
			'sd_ai_agent_elementor_publish_render_unverified',
			$gate->get_publish_blocker( ElementorCompletionGate::PUBLISH_ABILITY, array( 'post_id' => self::POST_ID ) )['code']
		);
	}

	/** Wrong target, stale call order, and mismatched screenshot URLs cannot pass. */
	public function test_wrong_post_stale_token_failed_preview_and_wrong_screenshot_url_do_not_satisfy_gate(): void {
		$gate = $this->gate();
		$this->record_mutation( $gate, self::POST_ID, 101 );

		$gate->record_tool_call( ElementorCompletionGate::PREVIEW_ABILITY, array( 'post_id' => 42, 'revision_id' => 101 ) );
		$gate->record_tool_response(
			ElementorCompletionGate::PREVIEW_ABILITY,
			array( 'success' => true, 'post_id' => 42, 'revision_id' => 101, 'preview_url' => 'https://example.test/?elementor-preview=42' )
		);
		$this->assertFalse( $gate->get_status()['targets'][0]['current_preview_available'] );

		$gate->record_tool_call( ElementorCompletionGate::PREVIEW_ABILITY, array( 'post_id' => self::POST_ID, 'revision_id' => 100 ) );
		$gate->record_tool_response(
			ElementorCompletionGate::PREVIEW_ABILITY,
			array( 'success' => true, 'post_id' => self::POST_ID, 'revision_id' => 100, 'preview_url' => self::PREVIEW_URL )
		);
		$this->assertFalse( $gate->get_status()['targets'][0]['current_preview_available'] );

		$this->record_preview( $gate, self::POST_ID, 101, self::PREVIEW_URL );
		$gate->record_tool_call(
			ElementorCompletionGate::SCREENSHOT_ABILITY,
			array( 'url' => self::PREVIEW_URL, 'width' => 375, 'height' => 812 )
		);
		$gate->record_tool_response(
			ElementorCompletionGate::SCREENSHOT_ABILITY,
			array(
				'success'           => true,
				'url'               => 'https://example.test/?elementor-preview=other',
				'attached_to_model' => true,
			)
		);
		$this->assertFalse( $gate->get_status()['targets'][0]['current_render_verified'] );
		$this->assertStringContainsString( 'exact current Elementor preview URL', $gate->get_status()['last_failure'] );
	}

	/** Viewport evidence must match the dispatched capture rather than its call name alone. */
	public function test_wrong_or_truncated_screenshot_dimensions_do_not_satisfy_viewport_evidence(): void {
		$gate = $this->gate();
		$this->record_mutation( $gate, self::POST_ID, 101 );
		$this->record_preview( $gate, self::POST_ID, 101, self::PREVIEW_URL );
		$mobile = $gate->get_render_validation_calls()[0];
		$gate->record_tool_call( ElementorCompletionGate::SCREENSHOT_ABILITY, $mobile );
		$gate->record_tool_response(
			ElementorCompletionGate::SCREENSHOT_ABILITY,
			array(
				'success'           => true,
				'url'               => self::PREVIEW_URL,
				'attached_to_model' => true,
				'width'             => 375,
				'height'            => 811,
				'truncated'         => false,
			)
		);
		$this->assertFalse( $gate->get_status()['targets'][0]['current_render_verified'] );
		$this->assertStringContainsString( 'dimensions did not match', $gate->get_status()['last_failure'] );

		$truncated_gate = $this->gate();
		$this->record_mutation( $truncated_gate, self::POST_ID, 101 );
		$this->record_preview( $truncated_gate, self::POST_ID, 101, self::PREVIEW_URL );
		$mobile = $truncated_gate->get_render_validation_calls()[0];
		$truncated_gate->record_tool_call( ElementorCompletionGate::SCREENSHOT_ABILITY, $mobile );
		$truncated_gate->record_tool_response(
			ElementorCompletionGate::SCREENSHOT_ABILITY,
			array(
				'success'           => true,
				'url'               => self::PREVIEW_URL,
				'attached_to_model' => true,
				'width'             => 375,
				'height'            => 812,
				'truncated'         => true,
			)
		);
		$this->assertFalse( $truncated_gate->get_status()['targets'][0]['current_render_verified'] );
		$this->assertStringContainsString( 'dimensions did not match', $truncated_gate->get_status()['last_failure'] );
	}

	/** Missing preview, publish, or browser support remains an explicit hard stop. */
	public function test_missing_preview_publish_or_browser_capability_blocks_without_bypass(): void {
		$missing_preview = new ElementorCompletionGate(
			array( ElementorCompletionGate::SCREENSHOT_ABILITY ),
			array( ElementorCompletionGate::PUBLISH_ABILITY )
		);
		$this->record_mutation( $missing_preview, self::POST_ID, 101 );
		$this->assertFalse( $missing_preview->requires_repair() );
		$this->assertStringContainsString( 'elementor/create-preview-link is not registered', $missing_preview->get_terminal_notice() );

		$missing_browser = new ElementorCompletionGate(
			array(),
			array( ElementorCompletionGate::PREVIEW_ABILITY, ElementorCompletionGate::PUBLISH_ABILITY )
		);
		$this->record_mutation( $missing_browser, self::POST_ID, 101 );
		$this->assertFalse( $missing_browser->requires_repair() );
		$this->assertStringContainsString( 'does not provide sd-ai-agent-js/screenshot-url', $missing_browser->get_terminal_notice() );

		$missing_publish = new ElementorCompletionGate(
			array( ElementorCompletionGate::SCREENSHOT_ABILITY ),
			array( ElementorCompletionGate::PREVIEW_ABILITY )
		);
		$this->record_mutation( $missing_publish, self::POST_ID, 101 );
		$blocker = $missing_publish->get_publish_blocker( ElementorCompletionGate::PUBLISH_ABILITY, array( 'post_id' => self::POST_ID ) );
		$this->assertIsArray( $blocker );
		$this->assertSame( 'sd_ai_agent_elementor_publish_unsupported', $blocker['code'] );
		$this->assertStringContainsString( 'elementor/publish-document is not registered', $blocker['error'] );
	}

	/** Logs, histories, status, and replay keep only a one-way preview correlation value. */
	public function test_preview_urls_are_redacted_from_logs_serialized_history_status_and_replay(): void {
		$gate = $this->gate();
		$this->record_mutation( $gate, self::POST_ID, 101 );
		$preview_call     = array( 'post_id' => self::POST_ID, 'revision_id' => 101 );
		$preview_response = array(
			'success'     => true,
			'post_id'     => self::POST_ID,
			'revision_id' => 101,
			'preview_url' => self::PREVIEW_URL,
		);
		$gate->record_tool_call( ElementorCompletionGate::PREVIEW_ABILITY, $preview_call );
		$gate->record_tool_response( ElementorCompletionGate::PREVIEW_ABILITY, $preview_response );
		$redacted_preview = $gate->redact_tool_response( ElementorCompletionGate::PREVIEW_ABILITY, $preview_response );

		$mobile_call = array( 'url' => self::PREVIEW_URL, 'width' => 375, 'height' => 812 );
		$gate->record_tool_call( ElementorCompletionGate::SCREENSHOT_ABILITY, $mobile_call );
		$redacted_mobile_call = $gate->redact_tool_call_args( ElementorCompletionGate::SCREENSHOT_ABILITY, $mobile_call );
		$mobile_response      = array( 'success' => true, 'url' => self::PREVIEW_URL, 'attached_to_model' => true, 'width' => 375, 'height' => 812, 'truncated' => false );
		$gate->record_tool_response( ElementorCompletionGate::SCREENSHOT_ABILITY, $mobile_response );
		$redacted_mobile_response = $gate->redact_tool_response( ElementorCompletionGate::SCREENSHOT_ABILITY, $mobile_response );

		$desktop_call = array( 'url' => self::PREVIEW_URL, 'width' => 1280, 'height' => 800 );
		$gate->record_tool_call( ElementorCompletionGate::SCREENSHOT_ABILITY, $desktop_call );
		$redacted_desktop_call = $gate->redact_tool_call_args( ElementorCompletionGate::SCREENSHOT_ABILITY, $desktop_call );
		$desktop_response      = array( 'success' => true, 'url' => self::PREVIEW_URL, 'attached_to_model' => true, 'width' => 768, 'height' => 480, 'truncated' => false );
		$gate->record_tool_response( ElementorCompletionGate::SCREENSHOT_ABILITY, $desktop_response );
		$redacted_desktop_response = $gate->redact_tool_response( ElementorCompletionGate::SCREENSHOT_ABILITY, $desktop_response );

		$serialized = $gate->redact_serialized_history(
			array(
				array( 'response' => $preview_response ),
				array( 'args' => $mobile_call ),
			)
		);
		$this->assertStringNotContainsString( self::PREVIEW_URL, wp_json_encode( $redacted_preview ) );
		$this->assertStringNotContainsString( self::PREVIEW_URL, wp_json_encode( $redacted_mobile_call ) );
		$this->assertStringNotContainsString( self::PREVIEW_URL, wp_json_encode( $serialized ) );
		$this->assertStringNotContainsString( self::PREVIEW_URL, wp_json_encode( $gate->get_status() ) );
		$this->assertStringNotContainsString( self::PREVIEW_URL, $gate->redact_text( 'Private preview: ' . self::PREVIEW_URL ) );
		$this->assertArrayHasKey( 'elementor_preview_url_hash', $redacted_preview );
		$this->assertArrayHasKey( 'elementor_preview_url_hash', $redacted_mobile_call );

		$replayed = $this->gate();
		$replayed->replay_tool_call_log(
			array(
				array( 'type' => 'call', 'name' => 'elementor/build-composition', 'args' => array( 'post_id' => self::POST_ID, 'revision_id' => 101 ) ),
				array( 'type' => 'response', 'name' => 'elementor/build-composition', 'response' => array( 'success' => true, 'post_id' => self::POST_ID, 'revision_id' => 101 ) ),
				array( 'type' => 'call', 'name' => ElementorCompletionGate::PREVIEW_ABILITY, 'args' => $preview_call ),
				array( 'type' => 'response', 'name' => ElementorCompletionGate::PREVIEW_ABILITY, 'response' => $redacted_preview ),
				array( 'type' => 'call', 'name' => ElementorCompletionGate::SCREENSHOT_ABILITY, 'args' => $redacted_mobile_call ),
				array( 'type' => 'response', 'name' => ElementorCompletionGate::SCREENSHOT_ABILITY, 'response' => $redacted_mobile_response ),
				array( 'type' => 'call', 'name' => ElementorCompletionGate::SCREENSHOT_ABILITY, 'args' => $redacted_desktop_call ),
				array( 'type' => 'response', 'name' => ElementorCompletionGate::SCREENSHOT_ABILITY, 'response' => $redacted_desktop_response ),
			)
		);
		$this->assertTrue( $replayed->get_status()['targets'][0]['current_render_verified'] );
	}

	/** Browser handoff keeps private preview URLs out of paused/job persistence. */
	public function test_pending_preview_call_is_sealed_for_persistence_and_bound_to_its_call_id(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$this->markTestSkipped( 'Sodium is unavailable.' );
		}

		$gate = $this->gate();
		$this->record_mutation( $gate, self::POST_ID, 101 );
		$this->record_preview( $gate, self::POST_ID, 101, self::PREVIEW_URL );
		$capture = $gate->get_render_validation_calls()[0];
		$pending = array(
			array(
				'id'          => 'elementor_preview_test_call',
				'name'        => ElementorCompletionGate::SCREENSHOT_ABILITY,
				'args'        => $capture,
				'annotations' => array( 'readonly' => true ),
			),
		);

		$sealed = $gate->seal_pending_client_tool_calls( $pending );
		$this->assertStringNotContainsString( self::PREVIEW_URL, wp_json_encode( $sealed ) );
		$this->assertArrayNotHasKey( 'url', $sealed[0]['args'] );
		$this->assertArrayHasKey( 'elementor_preview_capability', $sealed[0]['args'] );

		$restored = ElementorCompletionGate::restore_pending_client_tool_calls( $sealed );
		$this->assertSame( self::PREVIEW_URL, $restored[0]['args']['url'] );
		$this->assertArrayNotHasKey( 'elementor_preview_capability', $restored[0]['args'] );

		$sealed[0]['id'] = 'different-call-id';
		$invalid          = ElementorCompletionGate::restore_pending_client_tool_calls( $sealed );
		$this->assertArrayNotHasKey( 'url', $invalid[0]['args'] );
		$this->assertStringContainsString( 'expired or could not be verified', $invalid[0]['args']['elementor_preview_capability_error'] );

		$legacy = $pending;
		$legacy[0]['args']['elementor_preview_url_hash'] = $sealed[0]['args']['elementor_preview_url_hash'];
		$legacy_restored = ElementorCompletionGate::restore_pending_client_tool_calls( $legacy );
		$this->assertArrayNotHasKey( 'url', $legacy_restored[0]['args'] );
		$this->assertStringContainsString( 'cannot be restored safely', $legacy_restored[0]['args']['elementor_preview_capability_error'] );
	}

	private function gate(): ElementorCompletionGate {
		return new ElementorCompletionGate(
			array( ElementorCompletionGate::SCREENSHOT_ABILITY ),
			array( ElementorCompletionGate::PREVIEW_ABILITY, ElementorCompletionGate::PUBLISH_ABILITY )
		);
	}

	private function record_mutation( ElementorCompletionGate $gate, int $post_id, int $revision_id ): void {
		$gate->record_tool_call(
			'elementor/build-composition',
			array( 'post_id' => $post_id, 'revision_id' => $revision_id )
		);
		$gate->record_tool_response(
			'elementor/build-composition',
			array( 'success' => true, 'post_id' => $post_id, 'revision_id' => $revision_id )
		);
	}

	private function record_preview( ElementorCompletionGate $gate, int $post_id, int $revision_id, string $url ): void {
		$gate->record_tool_call(
			ElementorCompletionGate::PREVIEW_ABILITY,
			array( 'post_id' => $post_id, 'revision_id' => $revision_id )
		);
		$gate->record_tool_response(
			ElementorCompletionGate::PREVIEW_ABILITY,
			array(
				'success'     => true,
				'post_id'     => $post_id,
				'revision_id' => $revision_id,
				'preview_url' => $url,
			)
		);
	}

	private function record_screenshots( ElementorCompletionGate $gate, string $url ): void {
		foreach (
			array(
				array( 'width' => 375, 'height' => 812 ),
				array( 'width' => 1280, 'height' => 800 ),
		) as $viewport
		) {
			$args = array_merge( array( 'url' => $url ), $viewport );
			$scale = min( 1, 768 / $viewport['width'] );
			$gate->record_tool_call( ElementorCompletionGate::SCREENSHOT_ABILITY, $args );
			$gate->record_tool_response(
				ElementorCompletionGate::SCREENSHOT_ABILITY,
				array(
					'success'           => true,
					'url'               => $url,
					'attached_to_model' => true,
					'width'             => (int) round( $viewport['width'] * $scale ),
					'height'            => (int) round( $viewport['height'] * $scale ),
					'truncated'         => false,
				)
			);
		}
	}
}
