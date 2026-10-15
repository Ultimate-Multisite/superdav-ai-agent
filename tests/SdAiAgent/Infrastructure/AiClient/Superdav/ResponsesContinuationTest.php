<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Infrastructure\AiClient\Superdav;

use SdAiAgent\Core\AgentLoop;
use SdAiAgent\Core\CompletedTurnCompactor;
use SdAiAgent\Core\ConversationSerializer;
use SdAiAgent\Core\Database;
use SdAiAgent\Core\ElementorCompletionGate;
use SdAiAgent\Core\ModelCapabilityRegistry;
use SdAiAgent\Core\ProviderTraceLogger;
use SdAiAgent\Infrastructure\AiClient\Superdav\ResponsesContinuation;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiProvider;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiResponsesToolSearchTextGenerationModel;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WP_UnitTestCase;

/** Tests persisted server cursors and the actual provider transport boundary. */
final class ResponsesContinuationTest extends WP_UnitTestCase {

	/** @var int Owner of the isolated cursor fixtures. */
	private int $owner;

	/** Establish an authenticated request context. */
	public function set_up(): void {
		parent::set_up();
		$this->owner = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->owner );
	}

	/** Remove only fixture cursors. */
	public function tear_down(): void {
		( new ResponsesContinuation( 123, $this->owner ) )->clear();
		parent::tear_down();
	}

	/** A fresh PHP object loads encrypted native history without storing plaintext. */
	public function test_cursor_survives_reconstruction_and_replays_native_input(): void {
		$before = array( array( 'role' => 'user', 'content' => 'private fixture' ) );
		( new ResponsesContinuation( 123, $this->owner ) )->acknowledge( 'resp_first', $before, 'scope' );
		$after = array_merge( $before, array( array( 'type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'ok' ) ) );
		$next  = ( new ResponsesContinuation( 123, $this->owner ) )->resume( $after, 'scope' );
		$this->assertArrayNotHasKey( 'previous_response_id', $next );
		$this->assertSame( $after, $next['input'] );
		$stored = get_transient( 'sd_ai_agent_responses_' . get_current_blog_id() . '_123_' . $this->owner );
		$this->assertSame( array( 'response_id', 'count', 'hash', 'scope', 'native' ), array_keys( $stored ) );
		$this->assertStringNotContainsString( 'private fixture', wp_json_encode( $stored ) );
	}

	/** Completed-turn discovery keeps only called schemas; current-turn discovery is untouched. */
	public function test_replay_prunes_unused_schemas_from_completed_turn_discovery(): void {
		$schema = static fn( string $name ): array => array( 'type' => 'function', 'name' => $name, 'parameters' => array( 'type' => 'object' ) );
		$search = static fn( array $names ): array => array(
			'type'  => 'tool_search_output',
			'tools' => array(
				array(
					'type'        => 'namespace',
					'name'        => 'posts',
					'description' => 'WordPress posts: ' . implode( ', ', $names ) . '.',
					'tools'       => array_map( $schema, $names ),
				),
			),
		);
		$input = array(
			array( 'role' => 'user', 'content' => 'first' ),
			$search( array( 'used', 'unused_a', 'unused_b' ) ),
			$search( array( 'unused_c' ) ),
			array( 'type' => 'function_call', 'call_id' => 'call_1', 'name' => 'used', 'arguments' => '{}' ),
			array( 'role' => 'user', 'content' => 'second' ),
			$search( array( 'current_a', 'current_b' ) ),
		);
		$pruned = ResponsesContinuation::prune_completed_discovery( $input );
		$this->assertSame( array( 'used' ), array_column( $pruned[1]['tools'][0]['tools'], 'name' ) );
		$this->assertSame( 'WordPress posts: used.', $pruned[1]['tools'][0]['description'] );
		$this->assertSame( array(), $pruned[2]['tools'] );
		$this->assertSame( 'tool_search_output', $pruned[2]['type'] );
		$this->assertSame( $input[3], $pruned[3] );
		$this->assertSame( $input[5], $pruned[5] );
	}

	/** Completed reasoning becomes client-supplied history while the active turn is byte-identical. */
	public function test_replay_prunes_completed_reasoning_and_provider_item_ids(): void {
		$input = array(
			array( 'role' => 'user', 'content' => 'first' ),
			array( 'type' => 'reasoning', 'id' => 'rs_completed', 'encrypted_content' => 'opaque-completed' ),
			array( 'type' => 'function_call', 'id' => 'fc_completed', 'call_id' => 'call_completed', 'name' => 'lookup', 'arguments' => '{}' ),
			array( 'type' => 'message', 'id' => 'msg_completed', 'role' => 'assistant', 'content' => array() ),
			array( 'role' => 'user', 'content' => 'second' ),
			array( 'type' => 'reasoning', 'id' => 'rs_current', 'encrypted_content' => 'opaque-current' ),
			array( 'type' => 'function_call', 'id' => 'fc_current', 'call_id' => 'call_current', 'name' => 'lookup', 'arguments' => '{}' ),
			array( 'type' => 'message', 'id' => 'msg_current', 'role' => 'assistant', 'content' => array() ),
		);
		$completed_call    = $input[2];
		$completed_message = $input[3];
		unset( $completed_call['id'], $completed_message['id'] );

		$pruned = ResponsesContinuation::prune_completed_discovery( $input );

		$this->assertSame( array( $input[0], $completed_call, $completed_message ), array_slice( $pruned, 0, 3 ) );
		$this->assertSame( array_slice( $input, 4 ), array_slice( $pruned, 3 ) );
	}

	/** A schema loaded by several completed searches is replayed once, at its earliest load. */
	public function test_replay_keeps_one_copy_of_each_completed_schema(): void {
		$search = static fn( array $names ): array => array(
			'type'  => 'tool_search_output',
			'tools' => array(
				array(
					'type'        => 'namespace',
					'name'        => 'themes',
					'description' => 'WordPress themes: ' . implode( ', ', $names ) . '.',
					'tools'       => array_map( static fn( string $name ): array => array( 'type' => 'function', 'name' => $name ), $names ),
				),
			),
		);
		$call   = static fn( string $id, string $name ): array => array( 'type' => 'function_call', 'call_id' => $id, 'name' => $name, 'arguments' => '{}' );
		$input  = array(
			array( 'role' => 'user', 'content' => 'first' ),
			$search( array( 'styles', 'json' ) ),
			$call( 'call_1', 'styles' ),
			array( 'role' => 'user', 'content' => 'second' ),
			$search( array( 'styles', 'json', 'parts' ) ),
			$call( 'call_2', 'styles' ),
			$call( 'call_3', 'parts' ),
			array( 'role' => 'user', 'content' => 'third' ),
			$search( array( 'styles' ) ),
		);

		$pruned = ResponsesContinuation::prune_completed_discovery( $input );

		$this->assertSame( array( 'styles' ), array_column( $pruned[1]['tools'][0]['tools'], 'name' ) );
		$this->assertSame( array( 'parts' ), array_column( $pruned[4]['tools'][0]['tools'], 'name' ) );
		$this->assertSame( 'WordPress themes: parts.', $pruned[4]['tools'][0]['description'] );
		$this->assertSame( $input[8], $pruned[8], 'Active-turn discovery must stay byte-identical.' );
	}

	/** Completed-turn tool payloads are bounded by call ID; active-turn items are untouched. */
	public function test_completed_tool_items_are_bounded_and_active_items_untouched(): void {
		$large_output = wp_json_encode( array( 'styles' => str_repeat( 'x', 20000 ) ) );
		$large_args   = wp_json_encode(
			array(
				'post_id' => 7,
				'content' => str_repeat( 'y', 9000 ),
			)
		);
		$input        = array(
			array( 'role' => 'user', 'content' => 'first' ),
			array( 'type' => 'function_call', 'call_id' => 'call_old', 'name' => 'update', 'arguments' => $large_args ),
			array( 'type' => 'function_call_output', 'call_id' => 'call_old', 'output' => $large_output ),
			array( 'role' => 'user', 'content' => 'second' ),
			array( 'type' => 'function_call', 'call_id' => 'call_new', 'name' => 'update', 'arguments' => $large_args ),
			array( 'type' => 'function_call_output', 'call_id' => 'call_new', 'output' => $large_output ),
		);

		$compacted = ResponsesContinuation::compact_completed_tool_items( $input, array( 'call_old' => true ) );

		$this->assertLessThan( 1500, strlen( $compacted[2]['output'] ) );
		$this->assertStringStartsWith( CompletedTurnCompactor::MARKER, $compacted[2]['output'] );
		$args = json_decode( $compacted[1]['arguments'], true );
		$this->assertSame( 7, $args['post_id'] );
		$this->assertStringContainsString( '9000 characters omitted', $args['content'] );
		$this->assertSame( array_slice( $input, 3 ), array_slice( $compacted, 3 ) );
		$this->assertSame( $compacted, ResponsesContinuation::compact_completed_tool_items( $compacted, array( 'call_old' => true ) ), 'Compaction must be idempotent.' );
	}

	/** Ciphertext and public metadata cannot be tampered with or moved to another session. */
	public function test_encrypted_snapshot_integrity_and_retention_bound(): void {
		$before = array( array( 'role' => 'user', 'content' => 'private fixture' ) );
		$after = array_merge( $before, array( array( 'role' => 'user', 'content' => 'continue' ) ) );
		$key = 'sd_ai_agent_responses_' . get_current_blog_id() . '_123_' . $this->owner;
		$cursor = new ResponsesContinuation( 123, $this->owner );
		$cursor->acknowledge( 'resp_first', $before, 'scope' );
		$state = get_transient( $key );
		$corrupt = $state;
		$corrupt['native'][0] = '0' === $corrupt['native'][0] ? '1' : '0';
		set_transient( $key, $corrupt, DAY_IN_SECONDS );
		$this->assertNull( $cursor->resume( $after, 'scope' ) );
		$forged = $state;
		$forged['scope'] = 'changed-scope';
		set_transient( $key, $forged, DAY_IN_SECONDS );
		$this->assertNull( $cursor->resume( $after, 'changed-scope' ) );
		$other_key = 'sd_ai_agent_responses_' . get_current_blog_id() . '_124_' . $this->owner;
		set_transient( $other_key, $state, DAY_IN_SECONDS );
		$this->assertNull( ( new ResponsesContinuation( 124, $this->owner ) )->resume( $after, 'scope' ) );
		$cursor->acknowledge( 'resp_large', $before, 'scope', array( array( 'content' => str_repeat( 'x', 1048577 ) ) ) );
		$this->assertFalse( get_transient( $key ) );
	}

	/** Changed history, tools, identity, or expired state must not reuse a response ID. */
	public function test_mismatched_cursors_are_not_reused(): void {
		$before = array( array( 'role' => 'user', 'content' => 'first' ) );
		$after  = array_merge( $before, array( array( 'role' => 'user', 'content' => 'next' ) ) );
		$cursor = new ResponsesContinuation( 123, $this->owner );
		$cursor->acknowledge( 'resp_first', $before, 'scope' );
		$this->assertNull( ( new ResponsesContinuation( 123, $this->owner + 1 ) )->resume( $after, 'scope' ) );
		$this->assertNull( ( new ResponsesContinuation( 124, $this->owner ) )->resume( $after, 'scope' ) );
		$this->assertNull( $cursor->resume( $after, 'different_model_or_catalog' ) );
		$cursor->acknowledge( 'resp_first', $before, 'scope' );
		$after[0]['content'] = 'edited or compacted history';
		$this->assertNull( $cursor->resume( $after, 'scope' ) );
		$this->assertFalse( get_transient( 'sd_ai_agent_responses_' . get_current_blog_id() . '_123_' . $this->owner ) );
		$cursor->acknowledge( 'resp_first', $before, 'scope' );
		$cursor->clear();
		$this->assertNull( $cursor->resume( $after, 'scope' ) );
	}

	/** Invalid IDs, anonymous calls and unpersisted sessions never create reusable cursors. */
	public function test_invalid_or_unscoped_cursors_are_ignored(): void {
		$before = array( array( 'role' => 'user', 'content' => 'first' ) );
		$after  = array_merge( $before, $before );
		foreach ( array( new ResponsesContinuation( 0, $this->owner ), new ResponsesContinuation( 123, 0 ) ) as $cursor ) {
			$cursor->acknowledge( 'resp_first', $before, 'scope' );
			$this->assertNull( $cursor->resume( $after, 'scope' ) );
		}
		$cursor = new ResponsesContinuation( 123, $this->owner );
		$cursor->acknowledge( 'invalid/id', $before, 'scope' );
		$this->assertNull( $cursor->resume( $after, 'scope' ) );
		$opaque = 'resp_' . str_repeat( 'a', 2043 );
		$cursor->acknowledge( $opaque, $before, 'scope' );
		$this->assertSame( $after, $cursor->resume( $after, 'scope' )['input'] );
		$cursor->clear();
		$cursor->acknowledge( $opaque . 'a', $before, 'scope' );
		$this->assertNull( $cursor->resume( $after, 'scope' ) );
	}

	/** Model reconstruction and serialized browser/confirmation history preserve server continuity. */
	public function test_three_turns_replay_complete_native_state_without_duplicate_history(): void {
		$requests = array();
		$history  = array( new UserMessage( array( new MessagePart( 'Find the fixture.' ) ) ) );
		$first    = $this->model( array( $this->tool_response() ), $requests )->generateTextResult( $history );
		ConversationSerializer::append_assistant_message( $history, $first->toMessage() );
		// This is the same serialization boundary used by paused tool/confirmation jobs.
		$history = ConversationSerializer::deserialize( ConversationSerializer::serialize( $history ) );
		ConversationSerializer::append_tool_response(
			$history,
			new UserMessage( array( new MessagePart( new FunctionResponse( 'call_1', 'lookup', array( 'found' => true ) ) ) ) )
		);
		$second = $this->model( array( $this->text_response( 'resp_second' ) ), $requests )->generateTextResult( $history );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[0]->getData() );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[1]->getData() );
		$this->assertFalse( $requests[1]->getData()['store'] );
		$this->assertSame( array( 'reasoning.encrypted_content' ), $requests[1]->getData()['include'] );
		$this->assertSame( 'Inspect only.', $requests[1]->getData()['instructions'] );
		$this->assertCount( 6, $requests[1]->getData()['input'] );
		$this->assertSame( $this->tool_response()->getData()['output'], array_slice( $requests[1]->getData()['input'], 1, 4 ) );
		$this->assertSame( 'function_call_output', $requests[1]->getData()['input'][5]['type'] );
		$this->assertSame( 'call_1', $requests[1]->getData()['input'][5]['call_id'] );
		ConversationSerializer::append_assistant_message( $history, $second->toMessage() );
		$history   = ConversationSerializer::deserialize( ConversationSerializer::serialize( $history ) );
		$history[] = new UserMessage( array( new MessagePart( 'Explain the finding.' ) ) );
		$this->model( array( $this->text_response( 'resp_third' ) ), $requests )->generateTextResult( $history );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[2]->getData() );
		$completed_call    = $requests[1]->getData()['input'][4];
		$completed_message = $this->text_response( 'resp_second' )->getData()['output'][0];
		unset( $completed_call['id'], $completed_message['id'] );
		$this->assertSame(
			array(
				$requests[1]->getData()['input'][0],
				$requests[1]->getData()['input'][1],
				$requests[1]->getData()['input'][2],
				$completed_call,
				$requests[1]->getData()['input'][5],
				$completed_message,
				array( 'role' => 'user', 'content' => 'Explain the finding.' ),
			),
			$requests[2]->getData()['input']
		);
	}

	/** Browser persistence must preserve JSON text and empty schema objects. */
	public function test_paused_browser_history_preserves_json_tool_results_for_native_replay(): void {
		$requests = array();
		$history  = $this->pending_history( $requests );
		$payload  = array( 'schema' => array( 'properties' => new \stdClass() ), 'description' => 'Read /wp-admin/plugins.php' );
		$history[2] = new UserMessage( array( new MessagePart( new FunctionResponse( 'call_1', 'lookup', $payload ) ) ) );
		$browser_output = array( array( 'type' => 'function_call', 'call_id' => 'call_browser', 'name' => 'lookup', 'arguments' => '{}' ) );
		$browser = new Response( 200, array(), wp_json_encode( array( 'id' => 'resp_browser', 'status' => 'completed', 'output' => $browser_output ) ) );
		$second = $this->model( array( $browser ), $requests )->generateTextResult( $history );
		ConversationSerializer::append_assistant_message( $history, $second->toMessage() );
		// Use the same privacy scrubber and serialization boundary as a browser pause.
		$gate = new ElementorCompletionGate();
		$serialized = $gate->redact_serialized_history( ConversationSerializer::serialize( $history, true ) );
		$history = ConversationSerializer::deserialize( json_decode( wp_json_encode( $serialized ), true ) );
		ConversationSerializer::append_tool_response( $history, new UserMessage( array( new MessagePart( new FunctionResponse( 'call_browser', 'lookup', array( 'navigated' => true ) ) ) ) ) );
		$this->model( array( $this->text_response( 'resp_after_browser' ) ), $requests )->generateTextResult( $history );
		$this->assertCount( 3, $requests );
		$this->assertStringEndsWith( '/responses', $requests[2]->getUri() );
		$this->assertSame( array_merge( $requests[1]->getData()['input'], $browser_output ), array_slice( $requests[2]->getData()['input'], 0, -1 ) );
	}

	/** An unavailable server cursor falls back once with full history, without native parameters. */
	public function test_rejected_response_id_falls_back_with_full_history(): void {
		$requests = array();
		$history  = array( new UserMessage( array( new MessagePart( 'Find the fixture.' ) ) ) );
		$first    = $this->model( array( $this->tool_response() ), $requests )->generateTextResult( $history );
		ConversationSerializer::append_assistant_message( $history, $first->toMessage() );
		$history[] = new UserMessage( array( new MessagePart( new FunctionResponse( 'call_1', 'lookup', array( 'ok' => true ) ) ) ) );
		$expired   = new Response( 400, array(), wp_json_encode( array( 'error' => array( 'message' => 'Invalid previous_response_id', 'type' => 'invalid_request_error' ) ) ) );
		$this->model( array( $expired, $this->chat_response() ), $requests )->generateTextResult( $history );
		$this->assertCount( 3, $requests );
		$this->assertStringEndsWith( '/responses', $requests[1]->getUri() );
		$this->assertStringEndsWith( '/chat/completions', $requests[2]->getUri() );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[2]->getData() );
		$this->assertGreaterThanOrEqual( 3, count( $requests[2]->getData()['messages'] ) );
	}

	/** Refusals remain visible while their exact native content survives replay. */
	public function test_refusal_is_visible_and_replayed_without_losing_native_content(): void {
		$output = array( array( 'type' => 'message', 'role' => 'assistant', 'content' => array( array( 'type' => 'refusal', 'refusal' => 'I cannot help with that.' ) ) ) );
		$requests = array();
		$history = array( new UserMessage( array( new MessagePart( 'Inspect the fixture.' ) ) ) );
		$response = new Response( 200, array(), wp_json_encode( array( 'id' => 'resp_refusal', 'status' => 'completed', 'output' => $output ) ) );
		$result = $this->model( array( $response ), $requests )->generateTextResult( $history );
		$this->assertSame( 'I cannot help with that.', $result->toMessage()->getParts()[0]->getText() );
		ConversationSerializer::append_assistant_message( $history, $result->toMessage() );
		$history[] = new UserMessage( array( new MessagePart( 'List my pages instead.' ) ) );
		$this->model( array( $this->text_response( 'resp_after_refusal' ) ), $requests )->generateTextResult( $history );
		$this->assertSame( $output[0], $requests[1]->getData()['input'][1] );
		$this->assertCount( 3, $requests[1]->getData()['input'] );
	}

	/** Storage opt-out cannot accidentally resume an older stored conversation. */
	public function test_storage_opt_out_uses_stateless_native_replay(): void {
		$requests = array();
		$model    = $this->model( array( $this->text_response( 'resp_stateless' ) ), $requests );
		$model->getConfig()->setCustomOption( 'store', false );
		$model->generateTextResult( array( new UserMessage( array( new MessagePart( 'Inspect only.' ) ) ) ) );
		$this->assertStringEndsWith( '/responses', $requests[0]->getUri() );
		$this->assertFalse( $requests[0]->getData()['store'] );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[0]->getData() );
	}

	/** Attachments must retain their compatible payload rather than disappearing. */
	public function test_attachments_use_compatible_transport_without_silently_dropping_them(): void {
		$requests = array();
		$uri = 'data:image/png;base64,AQ==';
		$history = array( new UserMessage( array( new MessagePart( 'Inspect this image.' ), new MessagePart( new File( $uri, 'image/png' ) ) ) ) );
		$this->model( array( $this->chat_response() ), $requests )->generateTextResult( $history );
		$this->assertStringEndsWith( '/chat/completions', $requests[0]->getUri() );
		$this->assertStringContainsString( $uri, wp_json_encode( $requests[0]->getData(), JSON_UNESCAPED_SLASHES ) );
	}

	/** Deferred catalogs respect the HTTP cap independently of Chat context estimates. */
	public function test_native_body_budget_accepts_deferred_catalog_without_relaxing_chat_guard(): void {
		$body = wp_json_encode( array( 'model' => 'superdav-chat-pro', 'tools' => array( array( 'type' => 'function', 'name' => 'fixture', 'defer_loading' => true, 'description' => str_repeat( 'schema ', 28000 ) ) ) ) );
		$limit = static fn(): int => 65536;
		ProviderTraceLogger::set_runtime_context( SuperdavAiProvider::PROVIDER_ID, 'superdav-chat-pro', 123 );
		try {
			$this->assertFalse( ProviderTraceLogger::on_pre_http_request( false, array( 'body' => $body ), SuperdavAiProvider::url( 'responses' ) ) );
			// At the default three bytes per token, the ~196 KB chat body also fits admission.
			$this->assertFalse( ProviderTraceLogger::on_pre_http_request( false, array( 'body' => $body ), SuperdavAiProvider::url( 'chat/completions' ) ) );
			// A published byte-as-token ratio still guards chat without affecting native deferred catalogs.
			ModelCapabilityRegistry::set( 'superdav-chat-pro', 16384, 200000, ModelCapabilityRegistry::SOURCE_PROVIDER, 0, '', array(), 1 );
			$this->assertWPError( ProviderTraceLogger::on_pre_http_request( false, array( 'body' => $body ), SuperdavAiProvider::url( 'chat/completions' ) ) );
			$this->assertFalse( ProviderTraceLogger::on_pre_http_request( false, array( 'body' => $body ), SuperdavAiProvider::url( 'responses' ) ) );
			add_filter( 'sd_ai_agent_provider_request_max_bytes', $limit );
			$this->assertWPError( ProviderTraceLogger::on_pre_http_request( false, array( 'body' => $body ), SuperdavAiProvider::url( 'responses' ) ) );
		} finally {
			remove_filter( 'sd_ai_agent_provider_request_max_bytes', $limit );
			ModelCapabilityRegistry::forget( 'superdav-chat-pro' );
			ProviderTraceLogger::clear_runtime_context();
		}
	}

	/** Reject a larger catalog without erasing the snapshot for a bounded retry. */
	public function test_catalog_preflight_preserves_existing_native_replay(): void {
		$requests = array();
		$history = $this->pending_history( $requests );
		$model = $this->model( array(), $requests );
		$model->getConfig()->setFunctionDeclarations( array( new FunctionDeclaration( 'oversized', str_repeat( 'schema ', 10000 ), array( 'type' => 'object' ) ) ) );
		$limit = static fn(): int => 32768;
		add_filter( 'sd_ai_agent_provider_request_max_bytes', $limit );
		ProviderTraceLogger::set_runtime_context( SuperdavAiProvider::PROVIDER_ID, 'gpt-5.5', 123 );
		try {
			try {
				$model->generateTextResult( $history );
				$this->fail( 'Oversized catalog must fail before transport or cursor invalidation.' );
			} catch ( \RuntimeException $error ) {
				$this->assertSame( 1, ProviderTraceLogger::get_runtime_failure_metadata()['local_rejection'] );
			}
		} finally {
			ProviderTraceLogger::clear_runtime_context();
			remove_filter( 'sd_ai_agent_provider_request_max_bytes', $limit );
		}
		$this->model( array( $this->text_response( 'resp_bounded' ) ), $requests )->generateTextResult( $history );
		$this->assertCount( 2, $requests );
		$this->assertStringEndsWith( '/responses', $requests[1]->getUri() );
		$this->assertSame( $requests[0]->getData()['input'][0], $requests[1]->getData()['input'][0] );
		$this->assertSame( 'tool_search_call', $requests[1]->getData()['input'][1]['type'] );
	}

	/** Cache eviction replays local tool history through Responses without provider item IDs. */
	public function test_missing_cursor_replays_native_history_without_provider_item_ids(): void {
		$requests = array();
		$history  = $this->pending_history( $requests );
		( new ResponsesContinuation( 123, $this->owner ) )->clear();
		$this->model( array( $this->text_response( 'resp_replayed' ) ), $requests )->generateTextResult( $history );
		$this->assertCount( 2, $requests );
		$this->assertStringEndsWith( '/responses', $requests[1]->getUri() );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[1]->getData() );
		$this->assertSame( 'function_call', $requests[1]->getData()['input'][1]['type'] );
		$this->assertArrayNotHasKey( 'id', $requests[1]->getData()['input'][1] );
	}

	/** Changing the account must not reference a response owned by the old account. */
	public function test_rotating_credentials_invalidates_the_cursor(): void {
		$requests = array();
		$history  = $this->pending_history( $requests );
		$model    = $this->model( array( $this->chat_response() ), $requests );
		$model->setRequestAuthentication( new ApiKeyRequestAuthentication( 'different-test-credential' ) );
		$model->generateTextResult( $history );
		$this->assertStringEndsWith( '/chat/completions', $requests[1]->getUri() );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[1]->getData() );
	}

	/** Removed tools cannot remain available through an old stored response. */
	public function test_changed_catalog_invalidates_the_cursor(): void {
		$requests = array();
		$history  = $this->pending_history( $requests );
		$model    = $this->model( array( $this->chat_response() ), $requests );
		$model->getConfig()->setFunctionDeclarations( array() );
		$model->generateTextResult( $history );
		$this->assertStringEndsWith( '/chat/completions', $requests[1]->getUri() );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[1]->getData() );
	}

	/** Failed HTTP calls must neither advance the cursor nor silently switch protocols. */
	public function test_transient_server_failure_preserves_the_previous_acknowledgment(): void {
		$requests = array();
		$history  = $this->pending_history( $requests );
		$failure  = new Response( 500, array(), wp_json_encode( array( 'error' => array( 'message' => 'Temporary failure' ) ) ) );
		try {
			$this->model( array( $failure ), $requests )->generateTextResult( $history );
			$this->fail( 'Expected the server error to propagate.' );
		} catch ( ServerException $error ) {
			$this->assertCount( 2, $requests );
		}
		$this->model( array( $this->text_response( 'resp_retry' ) ), $requests )->generateTextResult( $history );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[2]->getData() );
		$this->assertSame( $requests[1]->getData()['input'], $requests[2]->getData()['input'] );
	}

	/** Real agent loops must bind the session, execute the tool, and resume on a new user turn. */
	public function test_agent_loop_binds_continuation_across_tool_and_user_turns(): void {
		$requests = array();
		$tool     = $this->tool_response()->getData();
		$tool['output'][3]['name'] = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( 'sd-ai-agent/list-posts' );
		$replies = array( $tool, $this->text_response( 'resp_second' )->getData(), $this->text_response( 'resp_third' )->getData() );
		$transport = static function ( $preempt, $args, $url ) use ( &$requests, &$replies ) {
			if ( str_ends_with( $url, '/models' ) ) {
				$data = array( 'data' => array( array( 'id' => 'gpt-5.5', 'capabilities' => array( 'text_generation' ) ) ) );
			} elseif ( str_ends_with( $url, '/responses' ) ) {
				$requests[] = json_decode( $args['body'], true );
				$data = array_shift( $replies );
			} else {
				return new \WP_Error( 'unexpected_test_http', 'Unexpected HTTP request in the continuity regression.' );
			}
			return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'body' => wp_json_encode( $data ) );
		};
		$credential = static fn() => 'fixture-only-credential';
		add_filter( 'pre_http_request', $transport, 1, 3 );
		add_filter( 'pre_option_' . SuperdavAiProvider::CREDENTIAL_OPTION, $credential );
		add_filter( 'sd_ai_agent_openai_tool_search_enabled', '__return_true' );
		$session = Database::create_session( array( 'user_id' => $this->owner, 'title' => 'Continuity regression' ) );
		try {
			$options = array(
				'session_id' => $session, 'provider_id' => SuperdavAiProvider::PROVIDER_ID, 'model_id' => 'gpt-5.5',
				'max_iterations' => 3, 'max_output_tokens' => 1024, 'provider_retry_max_attempts' => 1,
			);
			$first = ( new AgentLoop( 'Find posts.', array( 'sd-ai-agent/list-posts' ), array(), $options ) )->run();
			$this->assertIsArray( $first );
			$this->assertContains( 'tool_search', array_column( $requests[0]['tools'], 'type' ) );
			$this->assertArrayNotHasKey( 'previous_response_id', $requests[1] );
			$this->assertSame( $tool['output'], array_slice( $requests[1]['input'], 1, 4 ) );
			$this->assertSame( array( 'function_call_output' ), array_column( array_slice( $requests[1]['input'], -1 ), 'type' ) );
			// The provider saw the turn context; the saved transcript holds only what the user typed.
			$this->assertStringStartsWith( "Find posts.\n\n<context>", $requests[0]['input'][0]['content'] );
			$this->assertStringNotContainsString( '<context>', (string) wp_json_encode( $first['history'] ) );
			$history = ConversationSerializer::deserialize( $first['history'] );
			$second = ( new AgentLoop( 'Explain the finding.', array( 'sd-ai-agent/list-posts' ), $history, $options ) )->run();
			$this->assertIsArray( $second );
			$this->assertArrayNotHasKey( 'previous_response_id', $requests[2] );
			$completed_turn = array_values( array_filter( $requests[1]['input'], static fn( array $item ): bool => 'reasoning' !== ( $item['type'] ?? '' ) ) );
			// Completed turns replay without their stale provider context.
			$completed_turn[0]['content'] = 'Find posts.';
			unset( $completed_turn[3]['id'] );
			$completed_message = $this->text_response( 'resp_second' )->getData()['output'][0];
			unset( $completed_message['id'] );
			$completed_turn[] = $completed_message;
			$this->assertSame( $completed_turn, array_slice( $requests[2]['input'], 0, count( $completed_turn ) ) );
			$new_turn = array_slice( $requests[2]['input'], -1 );
			$this->assertCount( 1, $new_turn );
			$this->assertSame( 'user', $new_turn[0]['role'] );
			$this->assertStringStartsWith( "Explain the finding.\n\n<context>\n\n## Current Context", $new_turn[0]['content'] );
			$this->assertStringEndsWith( '</context>', $new_turn[0]['content'] );
			$this->assertSame( 1, substr_count( $new_turn[0]['content'], '<context>' ) );
			$this->assertSame( 1, substr_count( (string) wp_json_encode( $requests[2]['input'] ), '<context>' ), 'Only the newest turn carries context.' );
			$this->assertStringNotContainsString( '<context>', (string) wp_json_encode( $second['history'] ) );
			$this->assertSame( $requests[0]['instructions'], $requests[1]['instructions'] );
			$this->assertSame( $requests[0]['instructions'], $requests[2]['instructions'] );
			$this->assertCount( 3, $requests );
		} finally {
			remove_filter( 'pre_http_request', $transport, 1 );
			remove_filter( 'pre_option_' . SuperdavAiProvider::CREDENTIAL_OPTION, $credential );
			remove_filter( 'sd_ai_agent_openai_tool_search_enabled', '__return_true' );
			( new ResponsesContinuation( $session, $this->owner ) )->clear();
		}
	}

	/** Build a tool-result boundary shared by invalidation and retry cases. */
	private function pending_history( array &$requests ): array {
		$history = array( new UserMessage( array( new MessagePart( 'Find the fixture.' ) ) ) );
		$first   = $this->model( array( $this->tool_response() ), $requests )->generateTextResult( $history );
		ConversationSerializer::append_assistant_message( $history, $first->toMessage() );
		ConversationSerializer::append_tool_response(
			$history,
			new UserMessage( array( new MessagePart( new FunctionResponse( 'call_1', 'lookup', array( 'found' => true ) ) ) ) )
		);
		return $history;
	}

	/** Construct the real model with a recording SDK transporter and fixture responses. */
	private function model( array $responses, array &$requests ): SuperdavAiResponsesToolSearchTextGenerationModel {
		$model = new SuperdavAiResponsesToolSearchTextGenerationModel(
			new ModelMetadata( 'gpt-5.5', 'GPT', array( CapabilityEnum::textGeneration() ), array() ),
			SuperdavAiProvider::metadata()
		);
		$model->set_continuation_session_id( 123 );
		$config = new ModelConfig();
		$config->setSystemInstruction( 'Inspect only.' );
		$config->setFunctionDeclarations( array( new FunctionDeclaration( 'lookup', 'Read a fixture.', array( 'type' => 'object' ) ) ) );
		$model->setConfig( $config );
		$model->setRequestAuthentication( new ApiKeyRequestAuthentication( 'fixture-only-credential' ) );
		$transport = $this->createMock( HttpTransporterInterface::class );
		$transport->method( 'send' )->willReturnCallback(
			static function ( Request $request ) use ( &$responses, &$requests ): Response {
				$requests[] = $request;
				return array_shift( $responses );
			}
		);
		$model->setHttpTransporter( $transport );
		return $model;
	}

	/** Native discovery followed by a function call (namespace state remains on the server). */
	private function tool_response(): Response {
		return new Response( 200, array(), wp_json_encode( array(
			'id' => 'resp_first', 'status' => 'completed', 'output' => array(
				array( 'type' => 'tool_search_call', 'execution' => 'server', 'call_id' => null, 'arguments' => array( 'paths' => array( 'general' ) ) ),
				array( 'type' => 'tool_search_output', 'execution' => 'server', 'tools' => array() ),
				array( 'type' => 'reasoning', 'id' => 'rs_fixture', 'summary' => array(), 'encrypted_content' => 'opaque-reasoning-fixture' ),
				array( 'type' => 'function_call', 'id' => 'fc_fixture', 'call_id' => 'call_1', 'name' => 'lookup', 'namespace' => 'general', 'arguments' => '{}' ),
			),
		) ) );
	}

	/** Native text-only final response. */
	private function text_response( string $id ): Response {
		return new Response( 200, array(), wp_json_encode( array(
			'id' => $id, 'status' => 'completed', 'output' => array(
				array( 'type' => 'message', 'id' => 'msg_' . $id, 'role' => 'assistant', 'content' => array( array( 'type' => 'output_text', 'text' => 'Found it.' ) ) ),
			),
		) ) );
	}

	/** Compatible eager fallback response. */
	private function chat_response(): Response {
		return new Response( 200, array(), wp_json_encode( array(
			'id' => 'chatcmpl_fixture', 'choices' => array(
				array( 'index' => 0, 'message' => array( 'role' => 'assistant', 'content' => 'Found it.' ), 'finish_reason' => 'stop' ),
			),
		) ) );
	}
}
