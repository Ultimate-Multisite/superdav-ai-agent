<?php

declare(strict_types=1);
/**
 * Tests for outbound remote MCP connection persistence and discovery.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Mcp;

use SdAiAgent\Mcp\RemoteMcpAbilityRegistrar;
use SdAiAgent\Mcp\RemoteMcpClient;
use SdAiAgent\Mcp\RemoteMcpConnectionRepository;
use SdAiAgent\Mcp\RemoteMcpHttpTransport;
use WP_UnitTestCase;

class RemoteMcpClientTest extends WP_UnitTestCase {

	private RemoteMcpConnectionRepository $connections;

	public function set_up(): void {
		parent::set_up();
		delete_option( RemoteMcpConnectionRepository::CONNECTIONS_OPTION );
		delete_option( RemoteMcpConnectionRepository::SECRETS_OPTION );
		$this->connections = new RemoteMcpConnectionRepository();
		add_filter(
			'sd_ai_agent_ssrf_allow_hosts',
			static function ( array $hosts ): array {
				$hosts[] = 'fixture.mcp.test';
				return $hosts;
			}
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'sd_ai_agent_ssrf_allow_hosts' );
		delete_option( RemoteMcpConnectionRepository::CONNECTIONS_OPTION );
		delete_option( RemoteMcpConnectionRepository::SECRETS_OPTION );
		parent::tear_down();
	}

	public function test_connection_metadata_never_includes_secret(): void {
		$connection = $this->connections->save(
			array(
				'name'      => 'Fixture',
				'endpoint'  => 'https://fixture.mcp.test/mcp',
				'auth_type' => 'bearer',
			),
			array( 'value' => 'test-secret-value' )
		);

		$this->assertIsArray( $connection );
		$this->assertTrue( $connection['configured'] );
		$this->assertStringNotContainsString( 'test-secret-value', wp_json_encode( $connection ) );
		$this->assertSame( array( 'Authorization' => 'Bearer test-secret-value' ), $this->connections->authorization_headers( $connection['id'] ) );
	}

	public function test_discovery_persists_tool_snapshot_after_initialize(): void {
		$connection = $this->connections->save(
			array(
				'name'      => 'Fixture',
				'endpoint'  => 'https://fixture.mcp.test/mcp',
				'auth_type' => 'none',
				'enabled'   => true,
			)
		);
		$this->assertIsArray( $connection );

		add_filter( 'pre_http_request', array( $this, 'respond_to_mcp_request' ), 10, 3 );
		$client = new RemoteMcpClient( $this->connections, new RemoteMcpHttpTransport( $this->connections ) );
		$result = $client->discover( $connection['id'] );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result['tools'] );
		$stored = $this->connections->get( $connection['id'] );
		$this->assertSame( 'ready', $stored['status'] );
		$this->assertCount( 2, $stored['tools'] );
	}

	public function test_proxy_ability_names_are_isolated_per_connection(): void {
		$first  = RemoteMcpAbilityRegistrar::ability_name( 'server-one', 'get_availability' );
		$second = RemoteMcpAbilityRegistrar::ability_name( 'server-two', 'get_availability' );

		$this->assertStringStartsWith( 'sd-ai-agent/', $first );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * Return JSON-RPC fixture responses without network I/O.
	 *
	 * @param mixed                $preempt Previous preempted response.
	 * @param array<string, mixed> $args Request arguments.
	 * @param string               $url Request URL.
	 * @return mixed
	 */
	public function respond_to_mcp_request( $preempt, array $args, string $url ) {
		if ( 'https://fixture.mcp.test/mcp' !== $url ) {
			return $preempt;
		}
		$request = json_decode( (string) $args['body'], true );
		$method  = is_array( $request ) ? (string) ( $request['method'] ?? '' ) : '';
		$id      = is_array( $request ) && isset( $request['id'] ) ? $request['id'] : null;
		if ( 'notifications/initialized' === $method ) {
			return $this->response( '', 202 );
		}
		if ( 'initialize' === $method ) {
			return $this->response(
				wp_json_encode(
					array(
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => array( 'protocolVersion' => '2025-06-18', 'capabilities' => array( 'tools' => array( 'listChanged' => true ) ) ),
					)
				),
				200,
				array( 'mcp-session-id' => 'fixture-session' )
			);
		}
		return $this->response(
			wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => array(
						'tools' => array(
							array( 'name' => 'get_availability', 'description' => 'Availability.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
							array( 'name' => 'get_balance', 'description' => 'Balance.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
						),
					),
				)
			)
		);
	}

	/**
	 * @param string                $body Response body.
	 * @param int                   $code HTTP status code.
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, mixed>
	 */
	private function response( string $body, int $code = 200, array $headers = array() ): array {
		$headers['content-type'] = 'application/json';
		return array(
			'headers'       => $headers,
			'body'          => $body,
			'response'      => array( 'code' => $code ),
			'cookies'       => array(),
			'http_response' => null,
		);
	}
}
