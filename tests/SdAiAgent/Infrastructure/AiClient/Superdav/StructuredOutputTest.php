<?php
/**
 * Managed endpoint structured-output request contracts.
 *
 * @package SdAiAgent\Tests\Infrastructure\AiClient\Superdav
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Tests\Infrastructure\AiClient\Superdav;

use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiProvider;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiResponsesToolSearchTextGenerationModel;
use SdAiAgent\Infrastructure\AiClient\Superdav\SuperdavAiTextGenerationModel;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WP_UnitTestCase;

/** Verify actual SDK parameter preparation, without a live provider or site shim. */
final class StructuredOutputTest extends WP_UnitTestCase {

	/** Strict translation schemas remain caller-owned and unmodified. */
	private function schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'translation_html' => array( 'type' => 'string' ),
				'placeholders'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'             => array( 'translation_html', 'placeholders' ),
		);
	}

	/** Prepare the same raw output-schema DTO through both managed adapters. */
	private function prepare( string $class, ?array $schema, ?string $mime = 'application/json' ): array {
		$model = new $class( new ModelMetadata( 'example-model', 'Example', array(), array() ), SuperdavAiProvider::metadata() );
		$config = new ModelConfig();
		if ( null !== $mime ) {
			$config->setOutputMimeType( $mime );
		}
		if ( null !== $schema ) {
			$config->setOutputSchema( $schema );
		}
		$config->setMaxTokens( 4096 );
		$model->setConfig( $config );
		$method = new \ReflectionMethod( $model, SuperdavAiTextGenerationModel::class === $class ? 'prepareGenerateTextParams' : 'prepare_responses_params' );
		return $method->invoke( $model, array( new UserMessage( array( new MessagePart( 'Translate Hello to French.' ) ) ) ) );
	}

	/** Raw SDK schemas acquire the required named envelope in Chat Completions. */
	public function test_chat_completions_wraps_raw_schema_without_rewriting_it(): void {
		$schema = $this->schema();
		$params = $this->prepare( SuperdavAiTextGenerationModel::class, $schema );
		$this->assertSame( 'json_schema', $params['response_format']['type'] );
		$this->assertSame( array( 'name' => 'sd_ai_output', 'schema' => $schema, 'strict' => true ), $params['response_format']['json_schema'] );
		$this->assertSame( 4096, $params['max_tokens'] );
	}

	/** Native Responses forwards the structured contract, instead of dropping it. */
	public function test_responses_forwards_raw_schema_using_native_text_format(): void {
		$schema = $this->schema();
		$params = $this->prepare( SuperdavAiResponsesToolSearchTextGenerationModel::class, $schema );
		$this->assertSame( array( 'type' => 'json_schema', 'name' => 'sd_ai_output', 'schema' => $schema, 'strict' => true ), $params['text']['format'] );
		$this->assertSame( 4096, $params['max_output_tokens'] );
		$this->assertArrayNotHasKey( 'response_format', $params );
	}

	/** Already valid API envelopes retain names, descriptions and explicit strictness. */
	public function test_wrapped_schemas_are_preserved_on_both_paths(): void {
		$envelope = array( 'name' => 'caller_format', 'description' => 'Caller-owned schema', 'strict' => false, 'schema' => $this->schema() );
		$chat = $this->prepare( SuperdavAiTextGenerationModel::class, $envelope );
		$responses = $this->prepare( SuperdavAiResponsesToolSearchTextGenerationModel::class, $envelope );
		$this->assertSame( $envelope, $chat['response_format']['json_schema'] );
		$this->assertSame( array_merge( array( 'type' => 'json_schema' ), $envelope ), $responses['text']['format'] );
	}

	/** Schema-free JSON and ordinary text are not upgraded to strict schemas. */
	public function test_plain_json_and_text_requests_keep_their_modes(): void {
		$chat = $this->prepare( SuperdavAiTextGenerationModel::class, null );
		$responses = $this->prepare( SuperdavAiResponsesToolSearchTextGenerationModel::class, null );
		$this->assertSame( array( 'type' => 'json_object' ), $chat['response_format'] );
		$this->assertSame( array( 'type' => 'json_object' ), $responses['text']['format'] );
		$this->assertArrayNotHasKey( 'response_format', $this->prepare( SuperdavAiTextGenerationModel::class, null, null ) );
		$this->assertArrayNotHasKey( 'text', $this->prepare( SuperdavAiResponsesToolSearchTextGenerationModel::class, null, null ) );
	}
}
