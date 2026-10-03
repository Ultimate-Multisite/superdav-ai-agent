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
	private const AUTH_TYPES = array( 'none', 'bearer', 'api_key', 'custom_header', 'oauth' );

	public const FRESHNESS_SECONDS = 900;

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
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			return $this->save_unlocked( $input, $secret, $lock );
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
	}

	/**
	 * @param array<string,mixed> $input Safe connection fields.
	 * @param array<string,mixed> $secret Credentials, separate from metadata.
	 * @param string              $owner Registry lease.
	 * @return array<string,mixed>|WP_Error
	 */
	private function save_unlocked( array $input, array $secret, string $owner ): array|WP_Error {
		$id       = isset( $input['id'] ) ? sanitize_key( (string) $input['id'] ) : '';
		$id       = '' !== $id ? $id : str_replace( '-', '', wp_generate_uuid4() );
		$name     = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$endpoint = esc_url_raw( (string) ( $input['endpoint'] ?? '' ) );
		$auth     = sanitize_key( (string) ( $input['auth_type'] ?? 'none' ) );
		if ( '' === $name ) {
			$name = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
		}
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || ( 'none' !== $auth && 'https' !== ( $parts['scheme'] ?? '' ) ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_connection', __( 'Use a valid HTTPS server URL without embedded credentials or a fragment.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}

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
		if ( $has_new_secret && ( strlen( (string) $secret['value'] ) > 8192 || preg_match( '/[\r\n\x00]/', (string) $secret['value'] ) ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_invalid_credential', __( 'Enter a valid credential without line breaks.', 'superdav-ai-agent' ) );
		}
		if ( 'oauth' !== $auth && 'none' !== $auth && $has_secret && $changes_credential && ! $has_new_secret && empty( $input['reuse_secret'] ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_credential_confirmation_required', __( 'Changing the endpoint or authentication type requires a new credential or confirmation to reuse the existing credential.', 'superdav-ai-agent' ), array( 'status' => 400 ) );
		}
		$connections[ $id ] = array(
			'id'               => $id,
			'name'             => $name,
			'slug'             => sanitize_title( $name ),
			'endpoint'         => $endpoint,
			'transport'        => 'streamable-http',
			'auth_type'        => $auth,
			'enabled'          => $changes_credential ? false : ( array_key_exists( 'enabled', $input ) ? (bool) $input['enabled'] : (bool) ( $existing['enabled'] ?? false ) ),
			'tools'            => $changes_credential ? array() : ( is_array( $existing['tools'] ?? null ) ? $existing['tools'] : array() ),
			'protocol_version' => (string) ( $existing['protocol_version'] ?? '' ),
			'capabilities'     => is_array( $existing['capabilities'] ?? null ) ? $existing['capabilities'] : array(),
			'status'           => $changes_credential ? 'new' : (string) ( $existing['status'] ?? 'new' ),
			'last_error_code'  => (string) ( $existing['last_error_code'] ?? '' ),
			'last_discovered'  => (string) ( $existing['last_discovered'] ?? '' ),
			'updated_at'       => gmdate( 'c' ),
			'created_at'       => (string) ( $existing['created_at'] ?? gmdate( 'c' ) ),
			'revision'         => (int) ( $existing['revision'] ?? 0 ) + 1,
			'generation'       => $existing['generation'] ?? wp_generate_uuid4(),
		);
		// Remove old credentials first unless reuse was explicitly authorized.
		if ( $changes_credential && empty( $input['reuse_secret'] ) && ! $this->delete_secret( $id, $owner ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_busy', __( 'The connection changed before it could be saved. Try again.', 'superdav-ai-agent' ) );
		}
		if ( ! RemoteMcpLock::commit( 'connection-store', $owner, self::CONNECTIONS_OPTION, $connections ) ) {
			return new WP_Error( 'sd_ai_agent_remote_mcp_busy', __( 'The connection changed before it could be saved. Try again.', 'superdav-ai-agent' ) );
		}

		if ( 'none' === $auth || ( $changes_credential && 'oauth' === $auth ) ) {
			if ( ! $this->delete_secret( $id, $owner ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_credentials_failed', __( 'Credential cleanup could not be completed. Try again.', 'superdav-ai-agent' ) );
			}
			( new RemoteMcpOAuthStateRepository() )->delete_connection( $id );
		} elseif ( $has_new_secret ) {
			if ( ! $this->save_secret( $id, $auth, $secret, $owner ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_credentials_failed', __( 'Credentials could not be saved. Enter them again and retry.', 'superdav-ai-agent' ) );
			}
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
	 * @param int|null                   $revision Expected revision.
	 * @param array<string,string>       $fence Discovery lease.
	 */
	public function replace_snapshot( string $id, array $tools, string $protocol_version, array $capabilities = array(), ?int $revision = null, array $fence = array() ): bool {
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return false;
		}
		try {
			return $this->replace_snapshot_unlocked( $id, $tools, $protocol_version, $capabilities, $revision, $lock, $fence );
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
	}

	/**
	 * @param string                    $id Connection ID.
	 * @param list<array<string,mixed>> $tools Validated tools.
	 * @param string                    $protocol_version Negotiated version.
	 * @param array<string,mixed>       $capabilities Server capabilities.
	 * @param int|null                  $revision Snapshot's original revision.
	 * @param string                    $owner Registry lease.
	 * @param array<string,string>      $fence Refresh lease.
	 */
	private function replace_snapshot_unlocked( string $id, array $tools, string $protocol_version, array $capabilities, ?int $revision, string $owner, array $fence ): bool {
		$connections = $this->connections();
		if ( ! isset( $connections[ $id ] ) || ! is_array( $connections[ $id ] ) ) {
			return false;
		}
		if ( null !== $revision && $revision !== (int) ( $connections[ $id ]['revision'] ?? 0 ) ) {
			return false;
		}

		$connections[ $id ]['tools']            = $tools;
		$connections[ $id ]['protocol_version'] = sanitize_text_field( $protocol_version );
		$connections[ $id ]['capabilities']     = $capabilities;
		$connections[ $id ]['status']           = 'ready';
		$connections[ $id ]['last_error_code']  = '';
		$connections[ $id ]['last_discovered']  = gmdate( 'c' );
		$connections[ $id ]['updated_at']       = gmdate( 'c' );
		return RemoteMcpLock::commit( 'connection-store', $owner, self::CONNECTIONS_OPTION, $connections, $fence );
	}

	public function mark_failed( string $id, string $error_code ): void {
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return;
		}
		try {
			$this->mark_failed_unlocked( $id, $error_code, $lock );
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
	}

	private function mark_failed_unlocked( string $id, string $error_code, string $owner ): void {
		$connections = $this->connections();
		if ( ! isset( $connections[ $id ] ) || ! is_array( $connections[ $id ] ) ) {
			return;
		}
		$connections[ $id ]['status']          = 'stale';
		$connections[ $id ]['last_error_code'] = sanitize_key( $error_code );
		$connections[ $id ]['updated_at']      = gmdate( 'c' );
		RemoteMcpLock::commit( 'connection-store', $owner, self::CONNECTIONS_OPTION, $connections );
	}

	/**
	 * @param string               $id Connection ID.
	 * @param bool                 $enabled Desired state.
	 * @param int|null             $revision Expected revision.
	 * @param string|null          $generation Expected generation.
	 * @param array<string,string> $fence Network lease.
	 */
	public function set_enabled( string $id, bool $enabled, ?int $revision = null, ?string $generation = null, array $fence = array() ): bool {
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return false;
		}
		try {
			return $this->set_enabled_unlocked( $id, $enabled, $revision, $generation, $lock, $fence );
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
	}

	/**
	 * @param string               $id Connection ID.
	 * @param bool                 $enabled Desired state.
	 * @param int|null             $revision Expected revision.
	 * @param string|null          $generation Expected generation.
	 * @param string               $owner Registry lease.
	 * @param array<string,string> $fence Network operation lease.
	 */
	private function set_enabled_unlocked( string $id, bool $enabled, ?int $revision, ?string $generation, string $owner, array $fence ): bool {
		$connections = $this->connections();
		if ( ! isset( $connections[ $id ] ) || ! is_array( $connections[ $id ] ) ) {
			return false;
		}
		if ( ( null !== $revision && $revision !== (int) ( $connections[ $id ]['revision'] ?? 0 ) ) || ( null !== $generation && $generation !== ( $connections[ $id ]['generation'] ?? '' ) ) || ( $enabled && ! $this->is_fresh( $connections[ $id ] ) ) ) {
			return false;
		}
		$connections[ $id ]['enabled']    = $enabled;
		$connections[ $id ]['revision']   = (int) ( $connections[ $id ]['revision'] ?? 0 ) + 1;
		$connections[ $id ]['updated_at'] = gmdate( 'c' );
		return RemoteMcpLock::commit( 'connection-store', $owner, self::CONNECTIONS_OPTION, $connections, $fence );
	}

	public function delete( string $id ): bool {
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return false;
		}
		try {
			$deleted = $this->delete_unlocked( $id, $lock );
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
		if ( $deleted ) {
			( new RemoteMcpOAuthStateRepository() )->delete_connection( $id );
		}
		return $deleted;
	}

	private function delete_unlocked( string $id, string $owner ): bool {
		$connections = $this->connections();
		if ( ! array_key_exists( $id, $connections ) ) {
			return false;
		}
		unset( $connections[ $id ] );
		$secrets = $this->secrets();
		unset( $secrets[ $id ] );
		return RemoteMcpLock::commit( 'connection-store', $owner, self::SECRETS_OPTION, $secrets ) && RemoteMcpLock::commit( 'connection-store', $owner, self::CONNECTIONS_OPTION, $connections );
	}

	/** @return array<string, string> */
	public function authorization_headers( string $id ): array {
		$connection = $this->get_for_execution( $id );
		$secret     = $this->get_secret( $id );
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
		if ( ! in_array( $connection['auth_type'], array( 'bearer', 'api_key', 'oauth' ), true ) ) {
			return array();
		}
		return array( 'Authorization' => ( 'api_key' === $connection['auth_type'] ? '' : 'Bearer ' ) . $value );
	}

	/**
	 * @param array<string,mixed> $connection Snapshot to assess.
	 */
	public function is_fresh( array $connection ): bool {
		$discovered = strtotime( (string) ( $connection['last_discovered'] ?? '' ) );
		return 'ready' === ( $connection['status'] ?? '' ) && false !== $discovered && time() - $discovered < self::FRESHNESS_SECONDS;
	}

	/** @return array<string,mixed> Internal credentials; never expose through REST. */
	public function get_secret( string $id ): array {
		$record = $this->secrets()[ $id ] ?? array();
		if ( ! isset( $record['sealed'] ) ) {
			return $record; // Existing static credentials remain usable.
		}
		if ( ! is_string( $record['sealed'] ) || ! function_exists( 'openssl_decrypt' ) ) {
			return array();
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Encrypted credential envelope, never executable code.
		$raw = base64_decode( $record['sealed'], true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return array();
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $this->secret_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), $id );
		$data  = is_string( $plain ) ? json_decode( $plain, true ) : null;
		return is_array( $data ) ? $this->normalise_record( $data ) : array();
	}

	/**
	 * @param string               $id Connection ID.
	 * @param array<string,mixed>  $record Internal credentials.
	 * @param int|null             $revision Reject a changed connection after an HTTP exchange.
	 * @param string|null          $generation Reject a recreated connection.
	 * @param array<string,string> $fence Token operation lease.
	 */
	public function store_secret( string $id, array $record, ?int $revision = null, ?string $generation = null, array $fence = array() ): true|WP_Error {
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$connection = $this->get_for_execution( $id );
			if ( null === $connection || ( null !== $revision && $revision !== (int) ( $connection['revision'] ?? 0 ) ) || ( null !== $generation && $generation !== ( $connection['generation'] ?? '' ) ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_not_found', __( 'This MCP connection was removed.', 'superdav-ai-agent' ) );
			}
			$json = wp_json_encode( $record );
			if ( ! is_string( $json ) || strlen( $json ) > 65536 || ! function_exists( 'openssl_encrypt' ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_credentials_failed', __( 'Secure credential storage is unavailable.', 'superdav-ai-agent' ) );
			}
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $json, 'aes-256-gcm', $this->secret_key(), OPENSSL_RAW_DATA, $iv, $tag, $id );
			if ( false === $cipher ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_credentials_failed', __( 'Secure credential storage is unavailable.', 'superdav-ai-agent' ) );
			}
			$secrets = $this->secrets();
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary AEAD envelope for database storage.
			$secrets[ $id ] = array( 'sealed' => base64_encode( $iv . $tag . $cipher ) );
			return RemoteMcpLock::commit( 'connection-store', $lock, self::SECRETS_OPTION, $secrets, $fence ) ? true : new WP_Error( 'sd_ai_agent_remote_mcp_credentials_failed', __( 'Credentials could not be saved.', 'superdav-ai-agent' ) );
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
	}

	/**
	 * @param string               $id Connection ID.
	 * @param int|null             $revision Expected revision.
	 * @param string|null          $generation Expected generation.
	 * @param array<string,string> $fence Optional network lease.
	 */
	public function clear_secret( string $id, ?int $revision = null, ?string $generation = null, array $fence = array() ): true|WP_Error {
		$lock = RemoteMcpLock::acquire( 'connection-store' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$connection = $this->get_for_execution( $id );
			if ( ! is_array( $connection ) || ( null !== $revision && $revision !== (int) ( $connection['revision'] ?? 0 ) ) || ( null !== $generation && $generation !== ( $connection['generation'] ?? '' ) ) || ! $this->delete_secret( $id, $lock, $fence ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_credentials_failed', __( 'Credential cleanup could not be completed. Try again.', 'superdav-ai-agent' ) );
			}
		} finally {
			RemoteMcpLock::release( 'connection-store', $lock );
		}
		( new RemoteMcpOAuthStateRepository() )->delete_connection( $id );
		return true;
	}

	private function secret_key(): string {
		return hash( 'sha256', 'sd-ai-agent-mcp:' . get_current_blog_id() . ':' . wp_salt( 'auth' ), true );
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
	 * @param string               $owner Registry lease.
	 */
	private function save_secret( string $id, string $auth, array $secret, string $owner ): bool {
		$value = isset( $secret['value'] ) ? (string) $secret['value'] : '';
		if ( '' === $value ) {
			return true;
		}
		$secrets        = $this->secrets();
		$secrets[ $id ] = array( 'value' => $value );
		if ( 'custom_header' === $auth && isset( $secret['header_name'] ) && preg_match( '/^[A-Za-z0-9-]{1,64}$/', (string) $secret['header_name'] ) ) {
			$secrets[ $id ]['header_name'] = (string) $secret['header_name'];
		}
		return RemoteMcpLock::commit( 'connection-store', $owner, self::SECRETS_OPTION, $secrets );
	}

	/**
	 * @param string               $id Connection ID.
	 * @param string               $owner Registry lease.
	 * @param array<string,string> $fence Optional network lease.
	 */
	private function delete_secret( string $id, string $owner, array $fence = array() ): bool {
		$secrets = $this->secrets();
		unset( $secrets[ $id ] );
		return RemoteMcpLock::commit( 'connection-store', $owner, self::SECRETS_OPTION, $secrets, $fence );
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
		$secret                   = $this->get_secret( (string) ( $connection['id'] ?? '' ) );
		$connection['configured'] = ! empty( $secret['value'] );
		if ( 'oauth' === ( $connection['auth_type'] ?? '' ) ) {
			$connection['oauth_configured'] = ! empty( $secret['oauth'] );
			$connection['expires_at']       = (int) ( $secret['expires_at'] ?? 0 );
		}
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
