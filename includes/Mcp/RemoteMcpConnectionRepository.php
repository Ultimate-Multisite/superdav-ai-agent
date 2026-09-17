<?php

declare(strict_types=1);
/**
 * Stores outbound MCP connection metadata and bounded discovery snapshots.
 *
 * Secrets deliberately live in a separate option and are never returned by
 * the public connection methods used by REST responses and ability discovery.
 *
 * @package SdAiAgent\Mcp
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use SdAiAgent\Core\Net\SsrfGuard;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpConnectionRepository {

	public const CONNECTIONS_OPTION = 'sd_ai_agent_remote_mcp_connections';
	public const SECRETS_OPTION     = 'sd_ai_agent_remote_mcp_secrets';

	/** @var list<string> */
	private const AUTH_TYPES = array( 'none', 'bearer', 'api_key', 'custom_header' );

	/**
	 * Return all connection metadata without credential values.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list(): array {
		return array_values( array_map( array( $this, 'public_connection' ), $this->connections() ) );
	}

	/**
	 * Return enabled persisted snapshots without performing network I/O.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list_enabled(): array {
		return array_values(
			array_filter(
				$this->list(),
				static fn( array $connection ): bool => ! empty( $connection['enabled'] ) && ! empty( $connection['tools'] )
			)
		);
	}

	/**
	 * Return one connection without secrets.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		$connection = $this->connections()[ $id ] ?? null;
		return is_array( $connection ) ? $this->public_connection( $connection ) : null;
	}

	/**
	 * Return internal metadata for an execution path. Credentials remain separate.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_for_execution( string $id ): ?array {
		$connection = $this->connections()[ $id ] ?? null;
		return is_array( $connection ) ? $connection : null;
	}

	/**
	 * Create or update a connection and separately persist supplied credentials.
	 *
	 * @param array<string, mixed> $input Connection metadata from an admin-only route.
	 * @param array<string, mixed> $secret Credential values that must never enter metadata.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save( array $input, array $secret = array() ): array|WP_Error {
		$id       = isset( $input['id'] ) ? sanitize_key( (string) $input['id'] ) : '';
		$id       = '' !== $id ? $id : str_replace( '-', '', wp_generate_uuid4() );
		$name     = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$endpoint = esc_url_raw( (string) ( $input['endpoint'] ?? '' ) );
		$auth     = sanitize_key( (string) ( $input['auth_type'] ?? 'none' ) );

		if ( '' === $name || '' === $endpoint || ! in_array( $auth, self::AUTH_TYPES, true ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_connection', __( 'A name, safe endpoint, and supported authentication type are required.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}

		$safe = ( new SsrfGuard() )->assert_safe_url( $endpoint );
		if ( is_wp_error( $safe ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_unsafe_endpoint', __( 'The MCP endpoint is not a permitted public HTTP endpoint.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}

		if ( $this->is_self_endpoint( $endpoint ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_recursive_endpoint', __( 'This site’s private MCP endpoint cannot be configured as an outbound server.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}

		$connections        = $this->connections();
		$existing           = isset( $connections[ $id ] ) && is_array( $connections[ $id ] ) ? $connections[ $id ] : array();
		$has_secret         = array_key_exists( $id, $this->secrets() );
		$has_new_secret     = isset( $secret['value'] ) && '' !== (string) $secret['value'];
		$changes_credential = ! empty( $existing ) && ( $endpoint !== (string) ( $existing['endpoint'] ?? '' ) || $auth !== (string) ( $existing['auth_type'] ?? 'none' ) );
		if ( 'none' !== $auth && $has_secret && $changes_credential && ! $has_new_secret && empty( $input['reuse_secret'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_credential_confirmation_required', __( 'Changing the endpoint or authentication type requires a new credential or confirmation to reuse the existing credential.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}
		$connections[ $id ] = array(
			'id'               => $id,
			'name'             => $name,
			'slug'             => sanitize_title( $name ),
			'endpoint'         => $endpoint,
			'transport'        => 'streamable-http',
			'auth_type'        => $auth,
			'enabled'          => array_key_exists( 'enabled', $input ) ? (bool) $input['enabled'] : (bool) ( $existing['enabled'] ?? false ),
			'tools'            => is_array( $existing['tools'] ?? null ) ? $existing['tools'] : array(),
			'protocol_version' => (string) ( $existing['protocol_version'] ?? '' ),
			'capabilities'     => is_array( $existing['capabilities'] ?? null ) ? $existing['capabilities'] : array(),
			'status'           => (string) ( $existing['status'] ?? 'new' ),
			'last_error_code'  => (string) ( $existing['last_error_code'] ?? '' ),
			'last_discovered'  => (string) ( $existing['last_discovered'] ?? '' ),
			'updated_at'       => gmdate( 'c' ),
			'created_at'       => (string) ( $existing['created_at'] ?? gmdate( 'c' ) ),
		);
		update_option( self::CONNECTIONS_OPTION, $connections, false );

		if ( 'none' === $auth ) {
			$this->delete_secret( $id );
		} elseif ( $has_new_secret ) {
			$this->save_secret( $id, $auth, $secret );
		}

		return $this->public_connection( $connections[ $id ] );
	}

	/**
	 * Atomically replace the last-known-good discovered tool snapshot.
	 *
	 * @param string                     $id Connection ID.
	 * @param list<array<string, mixed>> $tools Validated remote tool definitions.
	 * @param string                     $protocol_version Negotiated protocol version.
	 * @param array<string, mixed>       $capabilities Remote server capabilities.
	 */
	public function replace_snapshot( string $id, array $tools, string $protocol_version, array $capabilities = array() ): bool {
		$connections = $this->connections();
		if ( ! isset( $connections[ $id ] ) || ! is_array( $connections[ $id ] ) ) {
			return false;
		}

		$connections[ $id ]['tools']            = $tools;
		$connections[ $id ]['protocol_version'] = sanitize_text_field( $protocol_version );
		$connections[ $id ]['capabilities']     = $capabilities;
		$connections[ $id ]['status']           = 'ready';
		$connections[ $id ]['last_error_code']  = '';
		$connections[ $id ]['last_discovered']  = gmdate( 'c' );
		$connections[ $id ]['updated_at']       = gmdate( 'c' );
		if ( update_option( self::CONNECTIONS_OPTION, $connections, false ) ) {
			return true;
		}
		$stored = $this->connections()[ $id ] ?? null;
		return is_array( $stored )
			&& $tools === $stored['tools']
			&& sanitize_text_field( $protocol_version ) === $stored['protocol_version']
			&& $capabilities === $stored['capabilities']
			&& 'ready' === $stored['status'];
	}

	public function mark_failed( string $id, string $error_code ): void {
		$connections = $this->connections();
		if ( ! isset( $connections[ $id ] ) || ! is_array( $connections[ $id ] ) ) {
			return;
		}
		$connections[ $id ]['status']          = 'stale';
		$connections[ $id ]['last_error_code'] = sanitize_key( $error_code );
		$connections[ $id ]['updated_at']      = gmdate( 'c' );
		update_option( self::CONNECTIONS_OPTION, $connections, false );
	}

	public function set_enabled( string $id, bool $enabled ): bool {
		$connections = $this->connections();
		if ( ! isset( $connections[ $id ] ) || ! is_array( $connections[ $id ] ) ) {
			return false;
		}
		$connections[ $id ]['enabled']    = $enabled;
		$connections[ $id ]['updated_at'] = gmdate( 'c' );
		return update_option( self::CONNECTIONS_OPTION, $connections, false );
	}

	public function delete( string $id ): bool {
		$connections = $this->connections();
		if ( ! array_key_exists( $id, $connections ) ) {
			return false;
		}
		unset( $connections[ $id ] );
		$secrets = $this->secrets();
		unset( $secrets[ $id ] );
		update_option( self::SECRETS_OPTION, $secrets, false );
		return update_option( self::CONNECTIONS_OPTION, $connections, false );
	}

	/** @return array<string, string> */
	public function authorization_headers( string $id ): array {
		$connection = $this->get_for_execution( $id );
		$secret     = $this->secrets()[ $id ] ?? array();
		if ( ! is_array( $connection ) || ! is_array( $secret ) ) {
			return array();
		}
		$value = isset( $secret['value'] ) ? (string) $secret['value'] : '';
		if ( '' === $value ) {
			return array();
		}
		if ( 'custom_header' === $connection['auth_type'] ) {
			$name = isset( $secret['header_name'] ) ? (string) $secret['header_name'] : '';
			return preg_match( '/^[A-Za-z0-9-]{1,64}$/', $name ) ? array( $name => $value ) : array();
		}
		if ( ! in_array( $connection['auth_type'], array( 'bearer', 'api_key' ), true ) ) {
			return array();
		}
		return array( 'Authorization' => ( 'bearer' === $connection['auth_type'] ? 'Bearer ' : '' ) . $value );
	}

	/** @return array<string, array<string, mixed>> */
	private function connections(): array {
		$value = get_option( self::CONNECTIONS_OPTION, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}
		$connections = array();
		foreach ( $value as $id => $connection ) {
			if ( is_string( $id ) && is_array( $connection ) ) {
				$connections[ $id ] = $this->normalise_record( $connection );
			}
		}
		return $connections;
	}

	/** @return array<string, array<string, mixed>> */
	private function secrets(): array {
		$value = get_option( self::SECRETS_OPTION, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}
		$secrets = array();
		foreach ( $value as $id => $secret ) {
			if ( is_string( $id ) && is_array( $secret ) ) {
				$secrets[ $id ] = $this->normalise_record( $secret );
			}
		}
		return $secrets;
	}

	/**
	 * Remove malformed numeric keys from a persisted option record.
	 *
	 * @param array<mixed> $record Stored option value.
	 * @return array<string, mixed> String-keyed record.
	 */
	private function normalise_record( array $record ): array {
		$normalised = array();
		foreach ( $record as $key => $value ) {
			if ( is_string( $key ) ) {
				$normalised[ $key ] = $value;
			}
		}
		return $normalised;
	}

	/**
	 * Persist one credential value outside connection metadata.
	 *
	 * @param string               $id Connection ID.
	 * @param string               $auth Authentication type.
	 * @param array<string, mixed> $secret Credential values.
	 */
	private function save_secret( string $id, string $auth, array $secret ): void {
		$value = isset( $secret['value'] ) ? (string) $secret['value'] : '';
		if ( '' === $value ) {
			return;
		}
		$secrets        = $this->secrets();
		$secrets[ $id ] = array( 'value' => $value );
		if ( 'custom_header' === $auth && isset( $secret['header_name'] ) && preg_match( '/^[A-Za-z0-9-]{1,64}$/', (string) $secret['header_name'] ) ) {
			$secrets[ $id ]['header_name'] = (string) $secret['header_name'];
		}
		update_option( self::SECRETS_OPTION, $secrets, false );
	}

	private function delete_secret( string $id ): void {
		$secrets = $this->secrets();
		unset( $secrets[ $id ] );
		update_option( self::SECRETS_OPTION, $secrets, false );
	}

	/**
	 * Build the externally safe view of a connection.
	 *
	 * @param array<string, mixed> $connection Stored connection metadata.
	 * @return array<string, mixed> Safe connection metadata.
	 */
	private function public_connection( array $connection ): array {
		unset( $connection['secret'], $connection['token'], $connection['authorization'] );
		$connection['configured'] = ! empty( $this->secrets()[ (string) ( $connection['id'] ?? '' ) ] );
		return $connection;
	}

	private function is_self_endpoint( string $endpoint ): bool {
		$target = wp_parse_url( $endpoint );
		$self   = wp_parse_url( rest_url( 'sd-ai-agent/v1/mcp' ) );
		return is_array( $target ) && is_array( $self )
			&& isset( $target['host'], $self['host'], $target['path'], $self['path'] )
			&& 0 === strcasecmp( (string) $target['host'], (string) $self['host'] )
			&& rtrim( (string) $target['path'], '/' ) === rtrim( (string) $self['path'], '/' );
	}
}
