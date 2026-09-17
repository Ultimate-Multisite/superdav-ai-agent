<?php

declare(strict_types=1);
/**
 * Server-owned completion evidence for Elementor document mutations.
 *
 * Elementor ability callbacks can report a successful write without proving the
 * current document rendered. This request-scoped gate binds preview evidence to
 * the post and mutation that produced it, then keeps publication behind the
 * ordinary user-confirmation boundary.
 *
 * Preview links are short-lived capabilities. The gate keeps them only in
	 * request memory so it can bind a screenshot response, and provides redactors
	 * for persisted history and activity logs. They are never returned by gate
	 * status or guidance methods.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

/**
 * Tracks preview and rendering evidence for Elementor documents in one agent run.
 */
final class ElementorCompletionGate {

	public const PREVIEW_ABILITY = 'elementor/create-preview-link';

	public const PUBLISH_ABILITY = 'elementor/publish-document';

	public const SCREENSHOT_ABILITY = 'sd-ai-agent-js/screenshot-url';

	private const REDACTED_PREVIEW_URL = '[redacted_elementor_preview_url]';

	private const PREVIEW_URL_HASH_KEY = 'elementor_preview_url_hash';

	private const PREVIEW_CAPABILITY_KEY = 'elementor_preview_capability';

	private const PREVIEW_CAPABILITY_VERSION = 1;

	private const PREVIEW_CAPABILITY_PURPOSE = 'sd-ai-agent/elementor-preview-browser-capture';

	/** Must match screenshot.js's MAX_IMAGE_WIDTH after browser downscaling. */
	private const SCREENSHOT_MAX_IMAGE_WIDTH = 768;

	/**
	 * Existing browser support can bind a private Elementor preview URL to a
	 * rendered image at these responsive viewports. It cannot independently
	 * certify interaction flows or third-party widgets beyond those captures.
	 *
	 * @var array<string,array{width:int,height:int}>
	 */
	private const REQUIRED_VIEWPORTS = array(
		'mobile'  => array(
			'width'  => 375,
			'height' => 812,
		),
		'desktop' => array(
			'width'  => 1280,
			'height' => 800,
		),
	);

	/** @var array<string,list<array<string,mixed>>> */
	private array $pending_calls = array();

	/**
	 * Active document state keyed by WordPress post ID.
	 *
	 * The private preview URL is held only until serialized history and activity
	 * log values have been redacted. `get_status()` intentionally excludes it.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $targets = array();

	private bool $preview_ability_available;

	private bool $publish_ability_available;

	private bool $browser_renderer_available;

	private bool $unbound_mutation = false;

	private string $last_failure = '';

	/**
	 * @param array<string> $client_ability_names Browser ability names available in this run.
	 * @param array<string> $server_ability_names Registered server ability names available in this run.
	 */
	public function __construct( array $client_ability_names = array(), array $server_ability_names = array() ) {
		if ( empty( $server_ability_names ) && function_exists( 'wp_get_abilities' ) ) {
			$server_ability_names = array_keys( wp_get_abilities() );
		}

		$this->preview_ability_available  = in_array( self::PREVIEW_ABILITY, $server_ability_names, true );
		$this->publish_ability_available  = in_array( self::PUBLISH_ABILITY, $server_ability_names, true );
		$this->browser_renderer_available = in_array( self::SCREENSHOT_ABILITY, $client_ability_names, true );
	}

	/**
	 * Rebuild conservative state from persisted tool activity after a pause.
	 *
	 * Preview URLs are deliberately redacted before persistence. A resumed gate
	 * therefore never treats a historical preview screenshot as reusable proof;
	 * it asks for a fresh preview and current render instead.
	 *
	 * @param list<array<string,mixed>> $tool_call_log Ordered activity log.
	 */
	public function replay_tool_call_log( array $tool_call_log ): void {
		foreach ( $tool_call_log as $entry ) {
			if ( 'call' === ( $entry['type'] ?? '' ) ) {
				$args = $entry['args'] ?? array();
				$this->record_tool_call(
					(string) ( $entry['name'] ?? '' ),
					is_array( $args ) ? $args : array()
				);
				continue;
			}

			if ( 'response' === ( $entry['type'] ?? '' ) ) {
				$this->record_tool_response(
					(string) ( $entry['name'] ?? '' ),
					$entry['response'] ?? array()
				);
			}
		}
	}

	/**
	 * Record a dispatched ability call before its response is available.
	 *
	 * @param string              $tool_name Ability name as sent to the provider.
	 * @param array<string,mixed> $args      Normalized tool arguments.
	 */
	public function record_tool_call( string $tool_name, array $args ): void {
		$name = self::normalize_tool_name( $tool_name );
		if ( '' === $name ) {
			return;
		}

		$pending = $this->build_pending_call( $args );
		if ( 'sd-ai-agent/ability-call' === $name ) {
			$target      = self::normalize_tool_name( (string) ( $args['ability'] ?? '' ) );
			$nested_args = is_array( $args['arguments'] ?? null ) ? $args['arguments'] : array();
			if ( '' !== $target && 'sd-ai-agent/ability-call' !== $target ) {
				$pending['nested_target'] = $target;
				$pending['nested_call']   = $this->build_pending_call( $nested_args );
			}
		}

		$this->pending_calls[ $name ][] = $pending;
		if ( self::SCREENSHOT_ABILITY === $name ) {
			$post_id = $this->find_target_by_preview_hash( (string) ( $pending['url_hash'] ?? '' ) );
			if ( $post_id > 0 && isset( $this->targets[ $post_id ] ) ) {
				$this->targets[ $post_id ]['render_validation_dispatched'] = true;
			}
		}
	}

	/**
	 * Record a tool response and update only evidence that matches the current target.
	 *
	 * @param string $tool_name Ability name as returned by the provider/client.
	 * @param mixed  $response  Raw response payload.
	 */
	public function record_tool_response( string $tool_name, $response ): void {
		$name    = self::normalize_tool_name( $tool_name );
		$pending = $this->consume_pending_call( $name );
		$payload = self::normalize_response( $response );
		if ( '' === $name ) {
			return;
		}

		if ( 'sd-ai-agent/ability-call' === $name ) {
			$target = self::normalize_tool_name(
				(string) ( $payload['ability'] ?? $pending['nested_target'] ?? $pending['args']['ability'] ?? '' )
			);
			$nested = is_array( $pending['nested_call'] ?? null ) ? $pending['nested_call'] : array();
			if ( '' === $target || 'sd-ai-agent/ability-call' === $target ) {
				return;
			}

			$result = array_key_exists( 'result', $payload ) ? $payload['result'] : $payload;
			$this->record_operation_result(
				$target,
				is_array( $nested['args'] ?? null ) ? $nested['args'] : array(),
				self::normalize_response( $result ),
				$nested
			);
			return;
		}

		$this->record_operation_result(
			$name,
			is_array( $pending['args'] ?? null ) ? $pending['args'] : array(),
			$payload,
			$pending
		);
	}

	/**
	 * Whether this run has an Elementor mutation that needs current render evidence.
	 */
	public function is_required(): bool {
		return $this->unbound_mutation || ! empty( $this->targets );
	}

	/**
	 * Whether a repair turn can advance the current Elementor completion state.
	 */
	public function requires_repair(): bool {
		return $this->is_required()
			&& '' === $this->get_render_capability_limitation()
			&& ! $this->has_current_render_evidence();
	}

	/** Whether AgentLoop should dispatch the exact current private-preview captures. */
	public function should_dispatch_render_validation(): bool {
		return '' === $this->get_render_capability_limitation()
			&& ! empty( $this->get_render_validation_calls() );
	}

	/**
	 * Return gate-owned browser calls for previews that have not yet been captured.
	 *
	 * Preview URLs stay request-scoped in this method. They are never included in
	 * serializable status, repair guidance, or activity log entries.
	 *
	 * @return list<array{url:string,width:int,height:int,fullPage:bool}>
	 */
	public function get_render_validation_calls(): array {
		if ( '' !== $this->get_render_capability_limitation() ) {
			return array();
		}

		$calls = array();
		foreach ( $this->targets as $target ) {
			if (
				! $this->target_has_current_preview( $target )
				|| '' === (string) ( $target['preview_url'] ?? '' )
				|| true === ( $target['render_validation_dispatched'] ?? false )
			) {
				continue;
			}

			foreach ( $this->get_missing_viewports( $target ) as $viewport ) {
				$dimensions = self::REQUIRED_VIEWPORTS[ $viewport ];
				$calls[]    = array(
					'url'      => (string) $target['preview_url'],
					'width'    => $dimensions['width'],
					'height'   => $dimensions['height'],
					'fullPage' => false,
				);
			}
		}

		return $calls;
	}

	/**
	 * Return a blocked tool result when a tracked document would publish without proof.
	 *
	 * `null` means the call is not Elementor publication or it is safe to defer
	 * to the official ability and normal confirmation/permission flow.
	 *
	 * @param string              $tool_name Ability name as sent to the provider.
	 * @param array<string,mixed> $args      Normalized call arguments.
	 * @return array<string,mixed>|null
	 */
	public function get_publish_blocker( string $tool_name, array $args ): ?array {
		$target       = self::normalize_tool_name( $tool_name );
		$publish_args = $args;
		if ( 'sd-ai-agent/ability-call' === $target ) {
			$target       = self::normalize_tool_name( (string) ( $args['ability'] ?? '' ) );
			$publish_args = is_array( $args['arguments'] ?? null ) ? $args['arguments'] : array();
		}

		if ( self::PUBLISH_ABILITY !== $target || ! $this->is_required() ) {
			return null;
		}

		$limitation = $this->get_capability_limitation();
		if ( '' !== $limitation ) {
			return $this->blocked_publish_response( 'sd_ai_agent_elementor_publish_unsupported', $limitation );
		}

		if ( $this->unbound_mutation ) {
			return $this->blocked_publish_response(
				'sd_ai_agent_elementor_publish_unbound_mutation',
				'Elementor publication is blocked because a successful mutation did not identify its WordPress post. Obtain a current document-specific mutation result and preview before publishing.'
			);
		}

		$post_id = self::extract_post_id( $publish_args );
		if ( $post_id <= 0 || ! isset( $this->targets[ $post_id ] ) ) {
			return $this->blocked_publish_response(
				'sd_ai_agent_elementor_publish_target_mismatch',
				'Elementor publication is blocked because the publish request does not identify a document with current server-owned render evidence. Supply the matching post or document ID after verifying its latest preview.'
			);
		}

		if ( ! $this->target_has_current_render_evidence( $this->targets[ $post_id ] ) ) {
			return $this->blocked_publish_response(
				'sd_ai_agent_elementor_publish_render_unverified',
				'Elementor publication is blocked until this document has a successful current preview render at the required mobile and desktop viewports. Create a fresh preview link and capture it with screenshot-url after the latest mutation.'
			);
		}

		return null;
	}

	/**
	 * Return an actionable model-facing recovery instruction without preview URLs.
	 */
	public function get_repair_guidance(): string {
		if ( ! $this->is_required() ) {
			return '';
		}

		$limitation = $this->get_render_capability_limitation();
		if ( '' !== $limitation ) {
			return $limitation;
		}

		if ( $this->unbound_mutation ) {
			return 'Elementor completion is blocked because a successful mutation did not return a post_id, document_id, or page_id. Do not publish or claim completion. Repeat the document-specific operation only when it can identify the affected WordPress post, then create a fresh preview and verify its rendered output.';
		}

		foreach ( $this->targets as $target ) {
			$post_id = (int) ( $target['post_id'] ?? 0 );
			if ( ! $this->target_has_current_preview( $target ) ) {
				return sprintf(
					'Elementor completion is incomplete for post ID %d. Call elementor/create-preview-link for that exact document after its latest mutation. Treat the returned preview link as a short-lived private capability: use it only for the immediate browser check, do not repeat it in chat, logs, memory, or a user-facing reply.',
					$post_id
				);
			}

			$missing = $this->get_missing_viewports( $target );
			if ( ! empty( $missing ) ) {
				return sprintf(
					'Elementor completion is incomplete for post ID %1$d. Create a fresh elementor/create-preview-link for the current document; AgentLoop will direct the private %2$s captures at 375x812 and 1280x800 without exposing the link to the model. Do not substitute link creation, a cached image, refresh, or prose for this render evidence.',
					$post_id,
					self::SCREENSHOT_ABILITY
				);
			}
		}

		if ( ! $this->publish_ability_available ) {
			return $this->get_capability_limitation();
		}

		return '';
	}

	/**
	 * Return a terminal disclosure that suppresses unsupported completion claims.
	 */
	public function get_terminal_notice(): string {
		if ( ! $this->is_required() ) {
			return '';
		}

		$limitation = $this->get_render_capability_limitation();
		if ( '' !== $limitation ) {
			return $limitation;
		}

		if ( $this->unbound_mutation || ! $this->has_current_render_evidence() ) {
			$reason = '' !== $this->last_failure ? ' ' . $this->last_failure : '';
			return 'Elementor document changes were saved, but the latest mutation has not passed current private-preview rendering at the required mobile and desktop viewports. I cannot claim the document is complete or publish it.' . $reason;
		}

		if ( ! $this->publish_ability_available ) {
			return $this->get_capability_limitation();
		}

		foreach ( $this->targets as $target ) {
			if ( true !== ( $target['published'] ?? false ) ) {
				return 'The current Elementor preview rendered successfully, but the document remains unpublished until the user explicitly confirms elementor/publish-document through the normal permission flow. I cannot claim it is published.';
			}
		}

		return '';
	}

	/**
	 * Return serializable state with no preview URLs, raw mutation tokens, or images.
	 *
	 * @return array<string,mixed>
	 */
	public function get_status(): array {
		$targets = array();
		foreach ( $this->targets as $target ) {
			$targets[] = array(
				'post_id'                      => (int) ( $target['post_id'] ?? 0 ),
				'mutation_version'             => (int) ( $target['mutation_version'] ?? 0 ),
				'mutation_token_present'       => '' !== (string) ( $target['mutation_token'] ?? '' ),
				'current_preview_available'    => $this->target_has_current_preview( $target ),
				'render_validation_dispatched' => true === ( $target['render_validation_dispatched'] ?? false ),
				'passed_viewports'             => array_keys(
					array_filter(
						is_array( $target['viewport_evidence'] ?? null ) ? $target['viewport_evidence'] : array()
					)
				),
				'current_render_verified'      => $this->target_has_current_render_evidence( $target ),
				'published'                    => true === ( $target['published'] ?? false ),
			);
		}

		return array(
			'required'                   => $this->is_required(),
			'preview_ability_available'  => $this->preview_ability_available,
			'publish_ability_available'  => $this->publish_ability_available,
			'browser_renderer_available' => $this->browser_renderer_available,
			'unbound_mutation'           => $this->unbound_mutation,
			'targets'                    => $targets,
			'last_failure'               => $this->last_failure,
		);
	}

	/**
	 * Redact known preview URLs from a persisted tool-call argument value.
	 *
	 * @param string $tool_name Ability name as sent to the provider.
	 * @param mixed  $args      Raw tool arguments.
	 * @return mixed
	 */
	public function redact_tool_call_args( string $tool_name, $args ) {
		$name     = self::normalize_tool_name( $tool_name );
		$redacted = $this->redact_preview_urls_from_value( $args, $name );

		if ( self::SCREENSHOT_ABILITY === $name ) {
			$hash = self::extract_preview_url_hash( self::normalize_response( $args ) );
			if ( $this->find_target_by_preview_hash( $hash ) > 0 ) {
				$redacted = self::add_preview_url_hash( $redacted, $hash, $name );
			}
		}

		return $redacted;
	}

	/**
	 * Redact preview URLs from an activity response after the gate has consumed it.
	 *
	 * @param string $tool_name Ability name as returned by the provider/client.
	 * @param mixed  $response  Raw tool response.
	 * @return mixed
	 */
	public function redact_tool_response( string $tool_name, $response ) {
		$name     = self::normalize_tool_name( $tool_name );
		$hash     = self::extract_preview_url_hash( self::normalize_response( $response ) );
		$redacted = $this->redact_preview_urls_from_value( $response, $name );

		if ( $this->find_target_by_preview_hash( $hash ) > 0 ) {
			$redacted = self::add_preview_url_hash( $redacted, $hash, $name );
		}

		return $redacted;
	}

	/**
	 * Redact known private preview values from serialized history before persistence.
	 *
	 * @param array<int,mixed> $history Serialized conversation history.
	 * @return array<int,mixed>
	 */
	public function redact_serialized_history( array $history ): array {
		$redacted = $this->redact_preview_urls_from_value( $history, '' );
		return is_array( $redacted ) ? array_values( $redacted ) : array();
	}

	/** Redact any current private preview URL from model-facing or user-facing text. */
	public function redact_text( string $text ): string {
		$redacted = $this->redact_preview_urls_from_value( $text, '' );
		return is_string( $redacted ) ? $redacted : $text;
	}

	/**
	 * Replace current private preview URLs with sealed single-call capabilities.
	 *
	 * The generic paused-job transport persists browser calls to the database and
	 * job transient. A raw preview link must never enter either store. The browser
	 * receives the URL only after an authenticated job-status response restores
	 * this encrypted, call-ID-bound value in memory.
	 *
	 * @param list<array<string,mixed>> $calls Pending browser calls.
	 * @return list<array<string,mixed>> Calls safe for paused/job persistence.
	 */
	public function seal_pending_client_tool_calls( array $calls ): array {
		$sealed_calls = array();

		foreach ( $calls as $call ) {
			$name = self::normalize_tool_name( (string) ( $call['name'] ?? '' ) );
			$args = is_array( $call['args'] ?? null ) ? $call['args'] : array();
			$url  = is_string( $args['url'] ?? null ) ? trim( $args['url'] ) : '';
			$hash = self::extract_preview_url_hash( $args );

			if (
				self::SCREENSHOT_ABILITY !== $name
				|| '' === $url
				|| '' === $hash
				|| ! hash_equals( $hash, self::hash_url( $url ) )
				|| $this->find_target_by_preview_hash( $hash ) <= 0
			) {
				$sealed_calls[] = $call;
				continue;
			}

			$capability = self::seal_preview_capability( (string) ( $call['id'] ?? '' ), $url, $hash );
			unset( $args['url'] );
			$args[ self::PREVIEW_URL_HASH_KEY ] = $hash;
			if ( '' !== $capability ) {
				$args[ self::PREVIEW_CAPABILITY_KEY ] = $capability;
			} else {
				$args['elementor_preview_capability_error'] = 'The server could not securely hand off the private Elementor preview link for browser validation.';
			}
			$call['args']   = $args;
			$sealed_calls[] = $call;
		}

		return $sealed_calls;
	}

	/**
	 * Whether a paused browser batch includes an owner-scoped preview capability.
	 *
	 * This lets REST delivery keep a sealed Elementor preview out of shared-session
	 * browser responses while leaving ordinary shared client tools unchanged.
	 *
	 * @param list<array<string,mixed>> $calls Pending browser calls.
	 */
	public static function pending_client_tool_calls_require_owner_delivery( array $calls ): bool {
		foreach ( $calls as $call ) {
			$name = self::normalize_tool_name( (string) ( $call['name'] ?? '' ) );
			$args = is_array( $call['args'] ?? null ) ? $call['args'] : array();
			if ( self::SCREENSHOT_ABILITY !== $name ) {
				continue;
			}

			$capability = $args[ self::PREVIEW_CAPABILITY_KEY ] ?? null;
			$hash       = (string) ( $args[ self::PREVIEW_URL_HASH_KEY ] ?? '' );
			if ( ( is_string( $capability ) && '' !== $capability ) || self::is_preview_url_hash( $hash ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Restore sealed private preview URLs only while constructing an authenticated browser response.
	 *
	 * @param list<array<string,mixed>> $calls Persisted pending browser calls.
	 * @return list<array<string,mixed>> Browser-executable pending calls.
	 */
	public static function restore_pending_client_tool_calls( array $calls ): array {
		$restored_calls = array();

		foreach ( $calls as $call ) {
			$name       = self::normalize_tool_name( (string) ( $call['name'] ?? '' ) );
			$args       = is_array( $call['args'] ?? null ) ? $call['args'] : array();
			$capability = is_string( $args[ self::PREVIEW_CAPABILITY_KEY ] ?? null )
				? $args[ self::PREVIEW_CAPABILITY_KEY ]
				: '';

			if ( self::SCREENSHOT_ABILITY !== $name ) {
				$restored_calls[] = $call;
				continue;
			}

			$hash = self::extract_preview_url_hash( $args );
			if ( '' === $capability ) {
				// Do not revive a pre-sealing paused payload that still carries a
				// marked private preview URL in plaintext.
				if ( self::is_preview_url_hash( $hash ) && is_string( $args['url'] ?? null ) ) {
					unset( $args['url'] );
					$args['elementor_preview_capability_error'] = 'The persisted private Elementor preview link cannot be restored safely. Create a fresh preview link before retrying the browser check.';
					$call['args']                               = $args;
				}
				$restored_calls[] = $call;
				continue;
			}

			$url = self::open_preview_capability( (string) ( $call['id'] ?? '' ), $capability, $hash );
			unset( $args[ self::PREVIEW_CAPABILITY_KEY ] );
			if ( '' !== $url ) {
				$args['url'] = $url;
			} else {
				$args['elementor_preview_capability_error'] = 'The private Elementor preview validation link expired or could not be verified. Create a fresh preview link before retrying the browser check.';
			}
			$call['args']     = $args;
			$restored_calls[] = $call;
		}

		return $restored_calls;
	}

	/**
	 * @param string              $name    Normalized ability name.
	 * @param array<string,mixed> $args    Original arguments.
	 * @param array<string,mixed> $payload Normalized response payload.
	 * @param array<string,mixed> $pending Pending call metadata.
	 */
	private function record_operation_result( string $name, array $args, array $payload, array $pending ): void {
		if ( self::is_mutation_ability( $name ) ) {
			if ( self::is_successful_response( $payload ) ) {
				$this->record_mutation( $args, $payload );
			}
			return;
		}

		if ( self::PREVIEW_ABILITY === $name ) {
			$this->record_preview_result( $args, $payload, $pending );
			return;
		}

		if ( self::SCREENSHOT_ABILITY === $name ) {
			$this->record_screenshot_result( $args, $payload, $pending );
			return;
		}

		if ( self::PUBLISH_ABILITY === $name ) {
			$this->record_publish_result( $args, $payload );
		}
	}

	/**
	 * Register a successful generated or material Elementor document mutation.
	 *
	 * @param array<string,mixed> $args    Original arguments.
	 * @param array<string,mixed> $payload Normalized response payload.
	 */
	private function record_mutation( array $args, array $payload ): void {
		$post_id = self::extract_post_id( $payload, $args );
		if ( $post_id <= 0 ) {
			$this->unbound_mutation = true;
			$this->last_failure     = 'A successful Elementor mutation did not identify its affected WordPress post.';
			return;
		}

		$previous_version          = (int) ( $this->targets[ $post_id ]['mutation_version'] ?? 0 );
		$this->targets[ $post_id ] = array(
			'post_id'                      => $post_id,
			'mutation_version'             => $previous_version + 1,
			'mutation_token'               => self::extract_mutation_token( $payload, $args ),
			'preview_version'              => 0,
			'preview_url'                  => '',
			'preview_url_hash'             => '',
			'viewport_evidence'            => array(),
			'render_validation_dispatched' => false,
			'published'                    => false,
		);
		$this->last_failure        = 'The latest Elementor mutation has no current preview render evidence.';
	}

	/**
	 * Accept only a current preview for the affected post and mutation version.
	 *
	 * @param array<string,mixed> $args    Original arguments.
	 * @param array<string,mixed> $payload Normalized response payload.
	 * @param array<string,mixed> $pending Pending call metadata.
	 */
	private function record_preview_result( array $args, array $payload, array $pending ): void {
		$post_id = self::extract_post_id( $payload, $args );
		if ( ! self::is_successful_response( $payload ) ) {
			$this->last_failure = 'Elementor preview-link creation did not succeed for the current document.';
			return;
		}
		if ( $post_id <= 0 || ! isset( $this->targets[ $post_id ] ) ) {
			$this->last_failure = 'Elementor preview evidence did not identify the currently mutated post.';
			return;
		}

		$target         = $this->targets[ $post_id ];
		$called_post_id = (int) ( $pending['post_id'] ?? 0 );
		$called_version = (int) ( $pending['mutation_version'] ?? 0 );
		if (
			( $called_post_id > 0 && $called_post_id !== $post_id )
			|| $called_version <= 0
			|| $called_version !== (int) ( $target['mutation_version'] ?? 0 )
		) {
			$this->last_failure = 'The Elementor preview was requested before the latest mutation or for another post.';
			return;
		}

		$preview_token = self::extract_mutation_token( $payload, $args );
		if (
			'' !== (string) ( $target['mutation_token'] ?? '' )
			&& '' !== $preview_token
			&& $preview_token !== (string) $target['mutation_token']
		) {
			$this->last_failure = 'The Elementor preview reported a different mutation token from the current document.';
			return;
		}

		$preview_url      = self::extract_preview_url( $payload );
		$preview_url_hash = self::extract_preview_url_hash( $payload );
		if ( '' === $preview_url_hash ) {
			$this->last_failure = 'Elementor preview-link creation succeeded without a usable preview URL for current render evidence.';
			return;
		}

		$target['preview_version']              = (int) $target['mutation_version'];
		$target['preview_url']                  = $preview_url;
		$target['preview_url_hash']             = $preview_url_hash;
		$target['viewport_evidence']            = array();
		$target['render_validation_dispatched'] = false;
		$this->targets[ $post_id ]              = $target;
		$this->last_failure                     = 'The current Elementor preview still needs successful mobile and desktop browser screenshots.';
	}

	/**
	 * Bind a browser result only to the exact current private preview URL.
	 *
	 * @param array<string,mixed> $args    Original arguments.
	 * @param array<string,mixed> $payload Normalized response payload.
	 * @param array<string,mixed> $pending Pending call metadata.
	 */
	private function record_screenshot_result( array $args, array $payload, array $pending ): void {
		$url_hash = (string) ( $pending['url_hash'] ?? self::extract_preview_url_hash( $args ) );
		$post_id  = $this->find_target_by_preview_hash( $url_hash );
		if ( $post_id <= 0 || ! isset( $this->targets[ $post_id ] ) ) {
			return;
		}

		$target = $this->targets[ $post_id ];
		if ( ! $this->target_has_current_preview( $target ) ) {
			$this->last_failure = 'A browser screenshot arrived for an Elementor preview that was stale after a later mutation.';
			return;
		}
		if ( ! self::is_successful_response( $payload ) || ! self::has_visual_attachment( $payload ) ) {
			$this->last_failure = 'The Elementor preview browser screenshot did not return a successful rendered image.';
			return;
		}

		$reported_hash = self::extract_preview_url_hash( $payload );
		if ( '' === $reported_hash || $reported_hash !== $url_hash ) {
			$this->last_failure = 'The browser screenshot did not report the exact current Elementor preview URL.';
			return;
		}

		$viewport = self::viewport_key( $args );
		if ( null === $viewport ) {
			$this->last_failure = 'The Elementor preview screenshot did not use a required responsive viewport.';
			return;
		}
		if ( ! self::screenshot_result_matches_viewport( $args, $payload ) ) {
			$this->last_failure = 'The Elementor preview screenshot dimensions did not match the requested responsive viewport or was truncated.';
			return;
		}

		$target['viewport_evidence'][ $viewport ] = true;
		$this->targets[ $post_id ]                = $target;
		$this->last_failure                       = $this->target_has_current_render_evidence( $target )
			? ''
			: 'The current Elementor preview still needs the remaining responsive browser screenshot.';
	}

	/**
	 * Record the official publish result without treating publication itself as render proof.
	 *
	 * @param array<string,mixed> $args    Original arguments.
	 * @param array<string,mixed> $payload Normalized response payload.
	 */
	private function record_publish_result( array $args, array $payload ): void {
		$post_id = self::extract_post_id( $payload, $args );
		if ( $post_id <= 0 || ! isset( $this->targets[ $post_id ] ) ) {
			return;
		}

		if ( ! self::is_successful_response( $payload ) ) {
			$this->last_failure = 'The official Elementor publication operation did not succeed.';
			return;
		}

		$this->targets[ $post_id ]['published'] = true;
		$this->last_failure                     = '';
	}

	/**
	 * @param array<string,mixed> $args Tool arguments captured at dispatch time.
	 * @return array<string,mixed>
	 */
	private function build_pending_call( array $args ): array {
		$post_id = self::extract_post_id( $args );
		return array(
			'args'             => $args,
			'post_id'          => $post_id,
			'mutation_version' => $post_id > 0 ? (int) ( $this->targets[ $post_id ]['mutation_version'] ?? 0 ) : 0,
			'url_hash'         => self::extract_preview_url_hash( $args ),
		);
	}

	/** @return array<string,mixed> */
	private function consume_pending_call( string $name ): array {
		if ( '' === $name || empty( $this->pending_calls[ $name ] ) ) {
			return array();
		}

		$pending = array_shift( $this->pending_calls[ $name ] );
		if ( empty( $this->pending_calls[ $name ] ) ) {
			unset( $this->pending_calls[ $name ] );
		}

		return is_array( $pending ) ? $pending : array();
	}

	private function has_current_render_evidence(): bool {
		if ( $this->unbound_mutation || empty( $this->targets ) ) {
			return false;
		}

		foreach ( $this->targets as $target ) {
			if ( ! $this->target_has_current_render_evidence( $target ) ) {
				return false;
			}
		}

		return true;
	}

	/** @param array<string,mixed> $target */
	private function target_has_current_preview( array $target ): bool {
		return (int) ( $target['preview_version'] ?? 0 ) > 0
			&& (int) ( $target['preview_version'] ?? 0 ) === (int) ( $target['mutation_version'] ?? 0 )
			&& '' !== (string) ( $target['preview_url_hash'] ?? '' );
	}

	/** @param array<string,mixed> $target */
	private function target_has_current_render_evidence( array $target ): bool {
		if ( ! $this->target_has_current_preview( $target ) ) {
			return false;
		}

		$evidence = is_array( $target['viewport_evidence'] ?? null ) ? $target['viewport_evidence'] : array();
		foreach ( array_keys( self::REQUIRED_VIEWPORTS ) as $viewport ) {
			if ( true !== ( $evidence[ $viewport ] ?? false ) ) {
				return false;
			}
		}

		return true;
	}

	/** @param array<string,mixed> $target */
	private function get_missing_viewports( array $target ): array {
		$evidence = is_array( $target['viewport_evidence'] ?? null ) ? $target['viewport_evidence'] : array();
		return array_keys(
			array_filter(
				self::REQUIRED_VIEWPORTS,
				static fn( array $dimensions, string $viewport ): bool => true !== ( $evidence[ $viewport ] ?? false ),
				ARRAY_FILTER_USE_BOTH
			)
		);
	}

	private function find_target_by_preview_hash( string $url_hash ): int {
		if ( '' === $url_hash ) {
			return 0;
		}

		foreach ( $this->targets as $post_id => $target ) {
			if ( hash_equals( (string) ( $target['preview_url_hash'] ?? '' ), $url_hash ) ) {
				return (int) $post_id;
			}
		}

		return 0;
	}

	private function get_render_capability_limitation(): string {
		if ( ! $this->preview_ability_available ) {
			return 'Elementor completion is blocked because elementor/create-preview-link is not registered. The latest document mutation remains unverified and must not be published through a fallback writer.';
		}
		if ( ! $this->browser_renderer_available ) {
			return 'Elementor completion is blocked because this client does not provide sd-ai-agent-js/screenshot-url. A preview link alone proves only URL creation, not current responsive rendering; do not publish or claim completion without a browser-capable client.';
		}
		if ( ! self::can_seal_preview_capabilities() ) {
			return 'Elementor completion is blocked because this server cannot securely hand off a private preview link to the browser. Do not persist, share, or bypass the preview URL; restore the required cryptographic capability before retrying.';
		}
		return '';
	}

	private function get_capability_limitation(): string {
		$render_limitation = $this->get_render_capability_limitation();
		if ( '' !== $render_limitation ) {
			return $render_limitation;
		}

		if ( ! $this->publish_ability_available ) {
			return 'Elementor completion cannot publish because elementor/publish-document is not registered. Preserve the verified document state, disclose this limitation, and do not use a legacy writer or direct meta mutation as a workaround.';
		}

		return '';
	}

	/** @return array<string,mixed> */
	private function blocked_publish_response( string $code, string $message ): array {
		return array(
			'success' => false,
			'code'    => $code,
			'error'   => $message,
			'hint'    => $this->get_repair_guidance(),
		);
	}

	/**
	 * @param mixed  $value     Value that may contain a known private preview URL.
	 * @param string $tool_name Normalized tool name for response-envelope handling.
	 * @return mixed
	 */
	private function redact_preview_urls_from_value( $value, string $tool_name ): mixed {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$redacted = $this->redact_preview_urls_from_value( $decoded, $tool_name );
				$encoded  = wp_json_encode( $redacted );
				return is_string( $encoded ) ? $encoded : $value;
			}

			foreach ( $this->get_private_preview_urls() as $preview_url ) {
				$value = str_replace( $preview_url, self::REDACTED_PREVIEW_URL, $value );
			}
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$redacted = array();
		foreach ( $value as $key => $item ) {
			if (
				self::is_preview_url_key( (string) $key )
				&& self::is_preview_response_value( $tool_name, $value )
				&& is_string( $item )
			) {
				$redacted[ $key ] = self::REDACTED_PREVIEW_URL;
				continue;
			}
			$nested_tool_name = $tool_name;
			if (
				'sd-ai-agent/ability-call' === $tool_name
				&& 'result' === $key
				&& self::PREVIEW_ABILITY === self::normalize_tool_name( (string) ( $value['ability'] ?? '' ) )
			) {
				$nested_tool_name = self::PREVIEW_ABILITY;
			}
			$redacted[ $key ] = $this->redact_preview_urls_from_value( $item, $nested_tool_name );
		}

		return $redacted;
	}

	/**
	 * Keep a one-way correlation value when a private URL is removed from a log.
	 *
	 * @param mixed  $value     Original redacted value.
	 * @param string $url_hash  Hash for a currently tracked preview URL.
	 * @param string $tool_name Normalized tool name.
	 * @return mixed
	 */
	private static function add_preview_url_hash( $value, string $url_hash, string $tool_name ) {
		if ( ! self::is_preview_url_hash( $url_hash ) ) {
			return $value;
		}

		$encoded_input = is_string( $value );
		if ( $encoded_input ) {
			$decoded = json_decode( $value, true );
			if ( ! is_array( $decoded ) ) {
				return $value;
			}
			$value = $decoded;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		if (
			'sd-ai-agent/ability-call' === $tool_name
			&& self::PREVIEW_ABILITY === self::normalize_tool_name( (string) ( $value['ability'] ?? '' ) )
			&& is_array( $value['result'] ?? null )
		) {
			$value['result'][ self::PREVIEW_URL_HASH_KEY ] = $url_hash;
		} else {
			$value[ self::PREVIEW_URL_HASH_KEY ] = $url_hash;
		}

		if ( ! $encoded_input ) {
			return $value;
		}

		$encoded = wp_json_encode( $value );
		return is_string( $encoded ) ? $encoded : '{}';
	}

	/** @return list<string> */
	private function get_private_preview_urls(): array {
		$urls = array();
		foreach ( $this->targets as $target ) {
			$url = (string) ( $target['preview_url'] ?? '' );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * @param string              $tool_name Normalized tool name.
	 * @param array<string,mixed> $value     Response value to classify.
	 */
	private static function is_preview_response_value( string $tool_name, array $value ): bool {
		if ( self::PREVIEW_ABILITY === $tool_name ) {
			return true;
		}

		return 'sd-ai-agent/ability-call' === $tool_name
			&& self::PREVIEW_ABILITY === self::normalize_tool_name( (string) ( $value['ability'] ?? '' ) );
	}

	private static function is_preview_url_key( string $key ): bool {
		return in_array( strtolower( $key ), array( 'url', 'preview_url', 'previewlink', 'preview_link', 'link' ), true );
	}

	private static function is_mutation_ability( string $name ): bool {
		return in_array(
			$name,
			array(
				'elementor/build-composition',
				'elementor/manage-elements',
				'elementor/update-page-settings',
				'elementor/create-page',
			),
			true
		);
	}

	private static function normalize_tool_name( string $tool_name ): string {
		$tool_name = trim( $tool_name );
		if ( str_starts_with( $tool_name, 'wpab__sd-ai-agent__' ) ) {
			return 'sd-ai-agent/' . substr( $tool_name, strlen( 'wpab__sd-ai-agent__' ) );
		}
		if ( str_starts_with( $tool_name, 'wpab__sd-ai-agent-js__' ) ) {
			return 'sd-ai-agent-js/' . substr( $tool_name, strlen( 'wpab__sd-ai-agent-js__' ) );
		}
		if ( str_starts_with( $tool_name, 'wpab__elementor__' ) ) {
			return 'elementor/' . str_replace( '_', '-', substr( $tool_name, strlen( 'wpab__elementor__' ) ) );
		}

		return $tool_name;
	}

	/**
	 * @param array<string,mixed> ...$sources Sources to inspect for a post ID.
	 */
	private static function extract_post_id( array ...$sources ): int {
		foreach ( $sources as $source ) {
			$post_id = self::extract_scalar_by_keys( $source, array( 'post_id', 'document_id', 'page_id' ) );
			if ( $post_id > 0 ) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Return a hash of an upstream mutation version/fingerprint without retaining it.
	 *
	 * @param array<string,mixed> ...$sources Sources to inspect for a mutation token.
	 */
	private static function extract_mutation_token( array ...$sources ): string {
		foreach ( $sources as $source ) {
			foreach ( array( 'mutation_token', 'fingerprint', 'revision_id', 'document_version', 'version', 'updated_at' ) as $key ) {
				$value = self::extract_scalar_by_keys( $source, array( $key ) );
				if ( $value > 0 || ( is_scalar( $source[ $key ] ?? null ) && '' !== trim( (string) $source[ $key ] ) ) ) {
					$raw = (string) ( $source[ $key ] ?? $value );
					return hash( 'sha256', $key . "\0" . $raw );
				}
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $source Source value to inspect.
	 * @param array<string>       $keys   Candidate scalar keys.
	 */
	private static function extract_scalar_by_keys( array $source, array $keys ): int {
		foreach ( $keys as $key ) {
			if ( isset( $source[ $key ] ) && is_numeric( $source[ $key ] ) && (int) $source[ $key ] > 0 ) {
				return (int) $source[ $key ];
			}
		}

		foreach ( array( 'document', 'post', 'page', 'affected', 'data', 'result' ) as $container ) {
			if ( ! is_array( $source[ $container ] ?? null ) ) {
				continue;
			}
			$value = self::extract_scalar_by_keys( $source[ $container ], $keys );
			if ( $value > 0 ) {
				return $value;
			}
		}

		return 0;
	}

	/** @param array<string,mixed> $payload */
	private static function extract_preview_url( array $payload ): string {
		foreach ( array( 'preview_url', 'url', 'preview_link', 'link' ) as $key ) {
			if ( is_string( $payload[ $key ] ?? null ) && '' !== trim( $payload[ $key ] ) ) {
				$url = trim( $payload[ $key ] );
				if ( ! self::is_redacted_preview_url( $url ) ) {
					return $url;
				}
			}
		}

		foreach ( array( 'preview', 'data', 'result' ) as $container ) {
			if ( is_array( $payload[ $container ] ?? null ) ) {
				$url = self::extract_preview_url( $payload[ $container ] );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/** @param array<string,mixed> $payload */
	private static function extract_preview_url_hash( array $payload ): string {
		foreach ( array( self::PREVIEW_URL_HASH_KEY, 'preview_url_hash' ) as $key ) {
			$hash = (string) ( $payload[ $key ] ?? '' );
			if ( self::is_preview_url_hash( $hash ) ) {
				return $hash;
			}
		}

		foreach ( array( 'preview', 'data', 'result' ) as $container ) {
			if ( is_array( $payload[ $container ] ?? null ) ) {
				$hash = self::extract_preview_url_hash( $payload[ $container ] );
				if ( '' !== $hash ) {
					return $hash;
				}
			}
		}

		return self::hash_url( self::extract_preview_url( $payload ) );
	}

	/** @param array<string,mixed> $payload */
	private static function has_visual_attachment( array $payload ): bool {
		return ( is_string( $payload['image'] ?? null ) && '' !== $payload['image'] )
			|| true === ( $payload['attached_to_model'] ?? false );
	}

	/** @param array<string,mixed> $args */
	private static function viewport_key( array $args ): ?string {
		$width  = (int) ( $args['width'] ?? self::REQUIRED_VIEWPORTS['desktop']['width'] );
		$height = (int) ( $args['height'] ?? self::REQUIRED_VIEWPORTS['desktop']['height'] );
		foreach ( self::REQUIRED_VIEWPORTS as $label => $dimensions ) {
			if ( $width === $dimensions['width'] && $height === $dimensions['height'] ) {
				return $label;
			}
		}

		return null;
	}

	/**
	 * Verify that the browser returned the bounded image dimensions produced for
	 * this exact viewport, rather than accepting a mobile/desktop result swap.
	 *
	 * Screenshot-url downscales images wider than SCREENSHOT_MAX_IMAGE_WIDTH, so
	 * its returned dimensions differ from the requested desktop viewport.
	 *
	 * @param array<string,mixed> $args    Server-created screenshot request arguments.
	 * @param array<string,mixed> $payload Browser screenshot result.
	 */
	private static function screenshot_result_matches_viewport( array $args, array $payload ): bool {
		$viewport = self::viewport_key( $args );
		if ( null === $viewport || ! empty( $args['fullPage'] ) || ! empty( $payload['truncated'] ) ) {
			return false;
		}

		$width  = $payload['width'] ?? null;
		$height = $payload['height'] ?? null;
		if ( ! is_numeric( $width ) || ! is_numeric( $height ) ) {
			return false;
		}

		$requested = self::REQUIRED_VIEWPORTS[ $viewport ];
		$scale     = min( 1, self::SCREENSHOT_MAX_IMAGE_WIDTH / $requested['width'] );
		return (int) $width === (int) round( $requested['width'] * $scale )
			&& (int) $height === (int) round( $requested['height'] * $scale );
	}

	private static function hash_url( string $url ): string {
		$url = trim( $url );
		return '' === $url || self::is_redacted_preview_url( $url ) ? '' : hash( 'sha256', $url );
	}

	private static function can_seal_preview_capabilities(): bool {
		return function_exists( 'wp_salt' )
			&& function_exists( 'sodium_crypto_secretbox' )
			&& function_exists( 'sodium_crypto_secretbox_open' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' );
	}

	private static function preview_capability_key(): string {
		return hash(
			'sha256',
			self::PREVIEW_CAPABILITY_PURPOSE . "\0" . wp_salt( 'auth' ),
			true
		);
	}

	private static function seal_preview_capability( string $call_id, string $url, string $url_hash ): string {
		if ( '' === $call_id || '' === $url || ! self::is_preview_url_hash( $url_hash ) || ! self::can_seal_preview_capabilities() ) {
			return '';
		}

		$payload = wp_json_encode(
			array(
				'version'  => self::PREVIEW_CAPABILITY_VERSION,
				'call_id'  => $call_id,
				'url'      => $url,
				'url_hash' => $url_hash,
			)
		);
		if ( ! is_string( $payload ) || '' === $payload ) {
			return '';
		}

		try {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $payload, $nonce, self::preview_capability_key() );
		} catch ( \Throwable $e ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Serializes an authenticated encrypted capability for safe transport.
		return rtrim( strtr( base64_encode( $nonce . $cipher ), '+/', '-_' ), '=' );
	}

	private static function open_preview_capability( string $call_id, string $capability, string $url_hash ): string {
		if ( '' === $call_id || ! self::is_preview_url_hash( $url_hash ) || ! self::can_seal_preview_capabilities() ) {
			return '';
		}

		$encoded = strtr( $capability, '-_', '+/' );
		$padding = strlen( $encoded ) % 4;
		if ( 0 !== $padding ) {
			$encoded .= str_repeat( '=', 4 - $padding );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the authenticated encrypted capability before verification.
		$binary = base64_decode( $encoded, true );
		if ( ! is_string( $binary ) || strlen( $binary ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce  = substr( $binary, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $binary, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		try {
			$plaintext = sodium_crypto_secretbox_open( $cipher, $nonce, self::preview_capability_key() );
		} catch ( \Throwable $e ) {
			return '';
		}
		if ( ! is_string( $plaintext ) || '' === $plaintext ) {
			return '';
		}

		$payload = json_decode( $plaintext, true );
		if ( ! is_array( $payload ) || self::PREVIEW_CAPABILITY_VERSION !== (int) ( $payload['version'] ?? 0 ) ) {
			return '';
		}

		$payload_call_id  = (string) ( $payload['call_id'] ?? '' );
		$payload_url      = is_string( $payload['url'] ?? null ) ? trim( $payload['url'] ) : '';
		$payload_url_hash = (string) ( $payload['url_hash'] ?? '' );
		if (
			'' === $payload_url
			|| ! hash_equals( $call_id, $payload_call_id )
			|| ! hash_equals( $url_hash, $payload_url_hash )
			|| ! hash_equals( $url_hash, self::hash_url( $payload_url ) )
		) {
			return '';
		}

		return $payload_url;
	}

	private static function is_redacted_preview_url( string $url ): bool {
		return self::REDACTED_PREVIEW_URL === trim( $url );
	}

	private static function is_preview_url_hash( string $hash ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/D', $hash );
	}

	/** @param mixed $response */
	private static function normalize_response( $response ): array {
		if ( is_string( $response ) && '' !== $response ) {
			$decoded  = json_decode( $response, true );
			$response = is_array( $decoded ) ? $decoded : array( 'error' => $response );
		}
		if ( ! is_array( $response ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $response as $key => $value ) {
			if ( is_string( $key ) ) {
				$normalized[ $key ] = $value;
			}
		}
		return $normalized;
	}

	/** @param array<string,mixed> $response */
	private static function is_successful_response( array $response ): bool {
		return ! empty( $response )
			&& empty( $response['error'] ?? '' )
			&& false !== ( $response['success'] ?? true )
			&& 'proposal_pending' !== (string) ( $response['status'] ?? '' );
	}
}
