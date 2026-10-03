<?php

declare(strict_types=1);
/**
 * Fixture-based MCP OAuth tests: no external servers or real credentials.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Mcp;

use SdAiAgent\Mcp\RemoteMcpConnectionRepository;
use SdAiAgent\Mcp\RemoteMcpOAuthClient;
use SdAiAgent\Mcp\RemoteMcpOAuthStateRepository;
use WP_UnitTestCase;

class RemoteMcpOAuthClientTest extends WP_UnitTestCase {

	private RemoteMcpConnectionRepository $repository;
	private RemoteMcpOAuthClient $client;
	/** @var array<string,mixed> */
	private array $connection;
	/** @var array<int,array<string,mixed>> */
	private array $token_requests = array();
	private bool $wrong_issuer = false;
	private bool $invalid_grant = false;
	private bool $previous_ssl_admin = false;

	public function set_up(): void {
		parent::set_up();
		$this->previous_ssl_admin = force_ssl_admin();
		force_ssl_admin( true );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'siteurl', 'https://fixture.wp.test' );
		update_option( 'home', 'https://fixture.wp.test' );
		delete_option( RemoteMcpConnectionRepository::CONNECTIONS_OPTION );
		delete_option( RemoteMcpConnectionRepository::SECRETS_OPTION );
		delete_option( RemoteMcpOAuthStateRepository::OPTION );
		add_filter( 'sd_ai_agent_ssrf_allow_hosts', static fn( array $hosts ): array => array_merge( $hosts, array( 'fixture.mcp.test' ) ) );
		add_filter( 'pre_http_request', array( $this, 'http' ), 10, 3 );
		$this->repository = new RemoteMcpConnectionRepository();
		$this->client = new RemoteMcpOAuthClient( $this->repository );
		$connection = $this->repository->save( array( 'name' => 'OAuth fixture', 'endpoint' => 'https://fixture.mcp.test/mcp', 'auth_type' => 'oauth' ) );
		$this->assertIsArray( $connection );
		$this->connection = $connection;
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'sd_ai_agent_ssrf_allow_hosts' );
		delete_option( RemoteMcpConnectionRepository::CONNECTIONS_OPTION );
		delete_option( RemoteMcpConnectionRepository::SECRETS_OPTION );
		delete_option( RemoteMcpOAuthStateRepository::OPTION );
		wp_set_current_user( 0 );
		force_ssl_admin( $this->previous_ssl_admin );
		parent::tear_down();
	}

	public function test_sign_in_has_pkce_and_resource_and_cannot_be_replayed(): void {
		$query = $this->begin();
		$this->assertSame( 'S256', $query['code_challenge_method'] );
		$this->assertSame( 'https://fixture.mcp.test/mcp', $query['resource'] );
		$this->assertSame( RemoteMcpOAuthClient::client_metadata_url(), $query['client_id'] );
		$result = $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code', 'iss' => 'https://fixture.mcp.test/auth' ) );
		$this->assertSame( $this->connection['id'], $result );
		$this->assertTrue( $this->repository->get( $result )['enabled'] );
		$this->assertSame( 'https://fixture.mcp.test/mcp', $this->token_requests[0]['resource'] );
		$this->assertNotEmpty( $this->token_requests[0]['code_verifier'] );
		$this->assertWPError( $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code' ) ) );
		$this->assertCount( 1, $this->token_requests );
		$this->assertStringNotContainsString( 'fixture-access', wp_json_encode( $this->repository->list() ) );
	}

	public function test_issuer_mismatch_and_cross_user_state_are_rejected_before_exchange(): void {
		$query = $this->begin();
		$this->assertWPError( $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code', 'iss' => 'https://unexpected.example' ) ) );
		$query = $this->begin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertWPError( $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code', 'iss' => 'https://fixture.mcp.test/auth' ) ) );
		$this->assertCount( 0, $this->token_requests );
	}

	public function test_metadata_issuer_is_validated_and_consent_denial_is_safe(): void {
		$this->wrong_issuer = true;
		$this->assertWPError( $this->client->begin( $this->connection['id'] ) );
		$this->wrong_issuer = false;
		$query = $this->begin();
		$this->assertWPError( $this->client->complete( array( 'state' => $query['state'], 'error' => 'access_denied', 'iss' => 'https://fixture.mcp.test/auth' ) ) );
		$this->assertCount( 0, $this->token_requests );
		$this->assertFalse( $this->repository->get( $this->connection['id'] )['enabled'] );
	}

	public function test_refresh_rotates_and_invalid_grant_requires_sign_in(): void {
		$query = $this->begin();
		$this->assertSame( $this->connection['id'], $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code', 'iss' => 'https://fixture.mcp.test/auth' ) ) );
		$this->assertSame( 'fixture-access', $this->client->access_token( $this->connection['id'], true ) );
		$this->assertSame( 'refresh_token', $this->token_requests[1]['grant_type'] );
		$this->invalid_grant = true;
		$this->assertWPError( $this->client->access_token( $this->connection['id'], true ) );
		$this->assertFalse( $this->repository->get( $this->connection['id'] )['configured'] );
	}

	public function test_changed_connection_and_deleted_pending_state_cannot_complete(): void {
		$query = $this->begin();
		$this->repository->set_enabled( $this->connection['id'], false );
		$this->assertWPError( $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code', 'iss' => 'https://fixture.mcp.test/auth' ) ) );
		$query = $this->begin();
		$this->assertTrue( $this->repository->delete( $this->connection['id'] ) );
		$this->assertWPError( $this->client->complete( array( 'state' => $query['state'], 'code' => 'fixture-code', 'iss' => 'https://fixture.mcp.test/auth' ) ) );
		$this->assertCount( 0, $this->token_requests );
	}

	/** @return array<string,string> */
	private function begin(): array {
		$result = $this->client->begin( $this->connection['id'] );
		$this->assertIsArray( $result );
		parse_str( wp_parse_url( $result['authorization_url'], PHP_URL_QUERY ), $query );
		return $query;
	}

	/**
	 * @param mixed               $preempt Previous response.
	 * @param array<string,mixed> $args HTTP arguments.
	 * @param string              $url Request URL.
	 * @return mixed
	 */
	public function http( $preempt, array $args, string $url ) {
		if ( ! str_starts_with( $url, 'https://fixture.mcp.test/' ) ) {
			return $preempt;
		}
		$headers = array( 'content-type' => 'application/json' );
		$status = 200;
		if ( str_contains( $url, 'oauth-protected-resource' ) ) {
			$data = array( 'resource' => 'https://fixture.mcp.test/mcp', 'authorization_servers' => array( 'https://fixture.mcp.test/auth' ), 'scopes_supported' => array( 'tools' ) );
		} elseif ( str_contains( $url, 'oauth-authorization-server' ) ) {
			$data = array( 'issuer' => $this->wrong_issuer ? 'https://wrong.example' : 'https://fixture.mcp.test/auth', 'authorization_endpoint' => 'https://fixture.mcp.test/authorize', 'token_endpoint' => 'https://fixture.mcp.test/token', 'code_challenge_methods_supported' => array( 'S256' ), 'token_endpoint_auth_methods_supported' => array( 'none' ), 'client_id_metadata_document_supported' => true, 'authorization_response_iss_parameter_supported' => true );
		} elseif ( str_ends_with( $url, '/token' ) ) {
			parse_str( $args['body'], $params );
			$this->token_requests[] = $params;
			$data = $this->invalid_grant ? array( 'error' => 'invalid_grant', 'error_description' => 'private server detail' ) : array( 'access_token' => 'fixture-access', 'refresh_token' => 'fixture-refresh', 'expires_in' => 3600, 'token_type' => 'Bearer' );
			$status = $this->invalid_grant ? 400 : 200;
		} else {
			$message = json_decode( $args['body'], true );
			if ( empty( $args['headers']['Authorization'] ) ) {
				$status = 401;
				$headers['www-authenticate'] = 'Bearer resource_metadata="https://fixture.mcp.test/.well-known/oauth-protected-resource/mcp", scope="tools"';
				$data = array();
			} else {
				$result = 'tools/list' === $message['method'] ? array( 'tools' => array() ) : array( 'protocolVersion' => '2026-07-28', 'capabilities' => array() );
				$data = array( 'jsonrpc' => '2.0', 'id' => $message['id'] ?? 0, 'result' => $result );
			}
		}
		return array( 'headers' => $headers, 'body' => wp_json_encode( $data ), 'response' => array( 'code' => $status ), 'cookies' => array(), 'http_response' => null );
	}
}
