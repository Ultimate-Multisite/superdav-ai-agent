<?php
/**
 * Recovery regressions through the real WordPress AI Client HTTP adapter.
 *
 * @package SdAiAgent\Tests\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\ActiveJobFailureDiagnostic;
use SdAiAgent\Core\AgentLoop;
use SdAiAgent\Core\ProviderTraceLogger;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WP_Error;
use WP_UnitTestCase;

/** Tests must reach the SDK adapter rather than the direct HTTP compatibility path. */
final class AgentLoopSdkRecoveryTest extends WP_UnitTestCase {

	private ProviderRegistry $original_registry;

	/** @var array<string, mixed> Hooks restored after each test. */
	private array $original_hooks = array();

	/** Configure an isolated authenticated registry using WordPress's real transporter. */
	public function set_up(): void {
		parent::set_up();
		global $wp_filter;
		foreach ( array( 'pre_http_request', 'sd_ai_agent_cloud_base_url', 'sd_ai_agent_provider_request_max_bytes', 'sd_ai_agent_provider_request_safety_margin_bytes' ) as $hook ) {
			$this->original_hooks[ $hook ] = isset( $wp_filter[ $hook ] ) ? clone $wp_filter[ $hook ] : null;
		}
		$this->original_registry = AiClient::defaultRegistry();
		$registry                = new ProviderRegistry();
		$registry->setHttpTransporter( $this->original_registry->getHttpTransporter() );
		$registry->registerProvider( SuperdavAiProvider::class );
		$registry->setProviderRequestAuthentication( SuperdavAiProvider::PROVIDER_ID, new ApiKeyRequestAuthentication( 'test-key' ) );
		( new \ReflectionProperty( AiClient::class, 'defaultRegistry' ) )->setValue( null, $registry );
		add_filter( 'sd_ai_agent_cloud_base_url', static fn(): string => 'https://sdk-recovery.example/v1' );
		add_filter( 'sd_ai_agent_provider_request_safety_margin_bytes', static fn(): int => 0 );
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', array( ProviderTraceLogger::class, 'on_pre_http_request' ), 10, 3 );
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( false !== $preempt ) {
					return $preempt;
				}
				if ( str_ends_with( $url, '/models' ) ) {
					return self::response( 200, array( 'data' => array( array( 'id' => 'superdav-chat-pro' ) ) ) );
				}
				// Never allow a test request to escape to a real provider.
				return new WP_Error( 'test_http_unmocked', 'Unmocked test HTTP request.' );
			},
			100,
			3
		);
	}

	/** Restore hooks and registry so test authentication cannot affect other suites. */
	public function tear_down(): void {
		global $wp_filter;
		foreach ( $this->original_hooks as $hook => $original ) {
			if ( null === $original ) {
				unset( $wp_filter[ $hook ] );
			} else {
				$wp_filter[ $hook ] = $original;
			}
		}
		( new \ReflectionProperty( AiClient::class, 'defaultRegistry' ) )->setValue( null, $this->original_registry );
		ProviderTraceLogger::clear_runtime_context();
		parent::tear_down();
	}

	/** Local 413 metadata survives the SDK's NetworkException/503 conversion. */
	public function test_sdk_local_guard_remains_actionable_without_outage_retries(): void {
		add_filter( 'sd_ai_agent_provider_request_max_bytes', static fn(): int => 4096 );
		$result = $this->loop( array(), str_repeat( 'system instructions ', 300 ) )->run();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_provider_payload_budget_exceeded', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 413, $data['status_code'] );
		$this->assertTrue( (bool) $data['local_rejection'] );
		$this->assertSame( 'complete_envelope', $data['request_size_source'] );
		$this->assertGreaterThan( $data['request_budget_bytes'], $data['request_bytes'] );
		$this->assertSame( ActiveJobFailureDiagnostic::REASON_LOCAL_PAYLOAD_GUARD, ActiveJobFailureDiagnostic::reason_from_error( $result ) );
		$this->assertStringNotContainsString( 'system instructions', (string) wp_json_encode( $data ) );
		$this->assertCount( 0, array_filter( $data['messages'], static fn( array $entry ): bool => 'provider_retry' === $entry['type'] ) );
		$this->assertSame( array(), ProviderTraceLogger::get_runtime_envelope_metrics() );
	}

	/** A real temporary upstream failure sends a fresh second HTTP request. */
	public function test_sdk_retry_sends_fresh_request_after_first_builder_error(): void {
		$calls = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$calls ) {
				if ( false !== $preempt || ! str_ends_with( $url, '/chat/completions' ) ) {
					return $preempt;
				}
				++$calls;
				return 1 === $calls ? self::response( 503, array( 'error' => array( 'message' => 'Temporarily unavailable' ) ) ) : self::completion();
			},
			20,
			3
		);
		$result = $this->loop()->run();

		$this->assertIsArray( $result );
		$this->assertSame( 'Recovered.', $result['reply'] );
		$this->assertSame( 2, $calls );
		$this->assertCount( 1, array_filter( $result['messages'], static fn( array $entry ): bool => 'provider_retry' === $entry['type'] ) );
	}

	/** Newest tool output compacts against system overhead without losing durable evidence. */
	public function test_sdk_complete_envelope_compacts_latest_turn_and_preserves_history(): void {
		add_filter( 'sd_ai_agent_provider_request_max_bytes', static fn(): int => 12000 );
		$evidence = str_repeat( 'Completed tool evidence. ', 300 );
		$history  = array(
			new UserMessage( array( new MessagePart( 'Finish the existing task.' ) ) ),
			new ModelMessage( array( new MessagePart( new FunctionCall( 'call-evidence', 'test_evidence', array() ) ) ) ),
			new UserMessage( array( new MessagePart( new FunctionResponse( 'call-evidence', 'test_evidence', array( 'content' => $evidence ) ) ) ) ),
		);
		$forwarded_sizes = array();
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$forwarded_sizes ) {
				if ( false !== $preempt || ! str_ends_with( $url, '/chat/completions' ) ) {
					return $preempt;
				}
				$forwarded_sizes[] = strlen( $args['body'] );
				return self::completion();
			},
			20,
			3
		);
		$result = $this->loop( $history, str_repeat( 'system instructions ', 400 ) )->run();

		$this->assertIsArray( $result );
		$this->assertSame( 'Recovered.', $result['reply'] );
		$this->assertCount( 1, $forwarded_sizes );
		$this->assertLessThanOrEqual( 12000, $forwarded_sizes[0] );
		$this->assertStringContainsString( $evidence, (string) wp_json_encode( $result['history'] ) );
		$this->assertCount( 1, array_filter( $result['messages'], static fn( array $entry ): bool => 'provider_payload_recovery' === $entry['type'] ) );
		$this->assertCount( 0, array_filter( $result['messages'], static fn( array $entry ): bool => 'provider_retry' === $entry['type'] ) );
	}

	/** @param array<\WordPress\AiClient\Messages\DTO\Message> $history Conversation evidence. */
	private function loop( array $history = array(), string $system = 'Reply briefly.' ): AgentLoop {
		return new AgentLoop(
			'Continue.',
			array( 'test/no-tools' ),
			$history,
			array(
				'provider_id'                => SuperdavAiProvider::PROVIDER_ID,
				'model_id'                   => 'superdav-chat-pro',
				'system_instruction'         => $system,
				'provider_retry_max_attempts' => 2,
				'provider_retry_delays'       => array( 0 ),
			)
		);
	}

	/** @return array<string, mixed> Mock WordPress HTTP response. */
	private static function completion(): array {
		return self::response(
			200,
			array(
				'id'      => 'chatcmpl-recovery',
				'choices' => array( array( 'index' => 0, 'message' => array( 'role' => 'assistant', 'content' => 'Recovered.' ), 'finish_reason' => 'stop' ) ),
				'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 2, 'total_tokens' => 12 ),
			)
		);
	}

	/**
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $body Mock JSON body.
	 * @return array<string, mixed>
	 */
	private static function response( int $status, array $body ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array( 'code' => $status, 'message' => 'Test response' ),
			'cookies'  => array(),
			'filename' => '',
		);
	}
}
