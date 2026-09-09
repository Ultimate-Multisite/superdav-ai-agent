<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Infrastructure\AiClient\Superdav;

use SdAiAgent\Core\AgentLoop;
use SdAiAgent\Core\ConversationSerializer;
use SdAiAgent\Core\Database;
use SdAiAgent\Infrastructure\AiClient\Superdav\ResponsesContinuation;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiProvider;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiResponsesToolSearchTextGenerationModel;
use WordPress\AiClient\Messages\DTO\MessagePart;
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

	/** A fresh PHP object loads the cursor without retaining any old prompt content. */
	public function test_cursor_survives_reconstruction_and_returns_only_new_input(): void {
		$before = array( array( 'role' => 'user', 'content' => 'private fixture' ) );
		( new ResponsesContinuation( 123, $this->owner ) )->acknowledge( 'resp_first', $before, 'scope' );
		$after = array_merge( $before, array( array( 'type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'ok' ) ) );
		$next  = ( new ResponsesContinuation( 123, $this->owner ) )->resume( $after, 'scope' );
		$this->assertSame( 'resp_first', $next['previous_response_id'] );
		$this->assertSame( array( $after[1] ), $next['input'] );
		$stored = get_transient( 'sd_ai_agent_responses_' . get_current_blog_id() . '_123_' . $this->owner );
		$this->assertSame( array( 'response_id', 'count', 'hash', 'scope' ), array_keys( $stored ) );
		$this->assertStringNotContainsString( 'private fixture', wp_json_encode( $stored ) );
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
	}

	/** Model reconstruction and serialized browser/confirmation history preserve server continuity. */
	public function test_three_turns_send_only_tool_results_then_new_user_input(): void {
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
		$this->assertSame( 'resp_first', $requests[1]->getData()['previous_response_id'] );
		$this->assertSame( 'Inspect only.', $requests[1]->getData()['instructions'] );
		$this->assertCount( 1, $requests[1]->getData()['input'] );
		$this->assertSame( 'function_call_output', $requests[1]->getData()['input'][0]['type'] );
		$this->assertSame( 'call_1', $requests[1]->getData()['input'][0]['call_id'] );
		ConversationSerializer::append_assistant_message( $history, $second->toMessage() );
		$history   = ConversationSerializer::deserialize( ConversationSerializer::serialize( $history ) );
		$history[] = new UserMessage( array( new MessagePart( 'Explain the finding.' ) ) );
		$this->model( array( $this->text_response( 'resp_third' ) ), $requests )->generateTextResult( $history );
		$this->assertSame( 'resp_second', $requests[2]->getData()['previous_response_id'] );
		$this->assertSame( array( array( 'role' => 'user', 'content' => 'Explain the finding.' ) ), $requests[2]->getData()['input'] );
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

	/** Storage opt-out cannot accidentally resume an older stored conversation. */
	public function test_storage_opt_out_uses_eager_fallback(): void {
		$requests = array();
		$model    = $this->model( array( $this->chat_response() ), $requests );
		$model->getConfig()->setCustomOption( 'store', false );
		$model->generateTextResult( array( new UserMessage( array( new MessagePart( 'Inspect only.' ) ) ) ) );
		$this->assertStringEndsWith( '/chat/completions', $requests[0]->getUri() );
		$this->assertFalse( $requests[0]->getData()['store'] );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[0]->getData() );
	}

	/** Cache eviction must never send a standalone native function result. */
	public function test_missing_cursor_falls_back_without_trying_responses(): void {
		$requests = array();
		$history  = $this->pending_history( $requests );
		( new ResponsesContinuation( 123, $this->owner ) )->clear();
		$this->model( array( $this->chat_response() ), $requests )->generateTextResult( $history );
		$this->assertCount( 2, $requests );
		$this->assertStringEndsWith( '/chat/completions', $requests[1]->getUri() );
		$this->assertArrayNotHasKey( 'previous_response_id', $requests[1]->getData() );
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
		$this->assertSame( 'resp_first', $requests[2]->getData()['previous_response_id'] );
		$this->assertSame( $requests[1]->getData()['input'], $requests[2]->getData()['input'] );
	}

	/** Real agent loops must bind the session, execute the tool, and resume on a new user turn. */
	public function test_agent_loop_binds_continuation_across_tool_and_user_turns(): void {
		$requests = array();
		$tool     = $this->tool_response()->getData();
		$tool['output'][2]['name'] = \WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( 'sd-ai-agent/list-posts' );
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
			$this->assertSame( 'resp_first', $requests[1]['previous_response_id'] );
			$this->assertSame( array( 'function_call_output' ), array_column( $requests[1]['input'], 'type' ) );
			$history = ConversationSerializer::deserialize( $first['history'] );
			$second = ( new AgentLoop( 'Explain the finding.', array( 'sd-ai-agent/list-posts' ), $history, $options ) )->run();
			$this->assertIsArray( $second );
			$this->assertSame( 'resp_second', $requests[2]['previous_response_id'] );
			$this->assertSame( array( array( 'role' => 'user', 'content' => 'Explain the finding.' ) ), $requests[2]['input'] );
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
				array( 'type' => 'function_call', 'call_id' => 'call_1', 'name' => 'lookup', 'namespace' => 'general', 'arguments' => '{}' ),
			),
		) ) );
	}

	/** Native text-only final response. */
	private function text_response( string $id ): Response {
		return new Response( 200, array(), wp_json_encode( array(
			'id' => $id, 'status' => 'completed', 'output' => array(
				array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => 'Found it.' ) ) ),
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
