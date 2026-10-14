<?php

declare(strict_types=1);
/**
 * One-time, bounded OAuth state stored outside general settings and agent tools.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpOAuthStateRepository {

	public const OPTION = 'sd_ai_agent_remote_mcp_oauth_states';

	/**
	 * @param array<string,mixed> $record Authorization context, never returned by REST.
	 */
	public function create( array $record ): string|WP_Error {
		$lock = RemoteMcpLock::acquire( 'oauth-states' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$states = $this->load();
			foreach ( $states as $key => $state ) {
				if ( (int) ( $state['expires'] ?? 0 ) < time() || ( $state['connection_id'] ?? '' ) === ( $record['connection_id'] ?? '' ) ) {
					unset( $states[ $key ] );
				}
			}
			if ( count( $states ) >= 20 ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_oauth_busy', __( 'Too many sign-in attempts are pending. Try again later.', 'superdav-ai-agent' ) );
			}
			$token                              = bin2hex( random_bytes( 32 ) );
			$states[ hash( 'sha256', $token ) ] = array_merge( $record, array( 'expires' => time() + 600 ) );
			if ( ! RemoteMcpLock::commit( 'oauth-states', $lock, self::OPTION, $states ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_oauth_state_failed', __( 'The sign-in request could not be saved.', 'superdav-ai-agent' ) );
			}
			return $token;
		} finally {
			RemoteMcpLock::release( 'oauth-states', $lock );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	public function consume( string $token ): array|WP_Error {
		$lock = RemoteMcpLock::acquire( 'oauth-states' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$key    = hash( 'sha256', $token );
			$states = $this->load();
			$record = $states[ $key ] ?? null;
			unset( $states[ $key ] );
			if ( ! RemoteMcpLock::commit( 'oauth-states', $lock, self::OPTION, $states ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_oauth_state_failed', __( 'The sign-in request could not be consumed safely. Sign in again.', 'superdav-ai-agent' ) );
			}
			if ( ! is_array( $record ) || (int) ( $record['expires'] ?? 0 ) < time() || (int) ( $record['user_id'] ?? 0 ) !== get_current_user_id() || (int) ( $record['blog_id'] ?? 0 ) !== get_current_blog_id() || ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'sd_ai_agent_remote_mcp_oauth_state_invalid', __( 'This sign-in request expired or belongs to another administrator. Sign in again.', 'superdav-ai-agent' ) );
			}
			return $record;
		} finally {
			RemoteMcpLock::release( 'oauth-states', $lock );
		}
	}

	public function delete_connection( string $id ): void {
		$lock = RemoteMcpLock::acquire( 'oauth-states' );
		if ( is_wp_error( $lock ) ) {
			return; // The callback also rechecks connection identity and existence.
		}
		try {
			$states = array_filter( $this->load(), static fn( array $state ): bool => ( $state['connection_id'] ?? '' ) !== $id );
			RemoteMcpLock::commit( 'oauth-states', $lock, self::OPTION, $states );
		} finally {
			RemoteMcpLock::release( 'oauth-states', $lock );
		}
	}

	/** @return array<string,array<string,mixed>> */
	private function load(): array {
		$states = get_option( self::OPTION, array() );
		$valid  = array();
		if ( is_array( $states ) ) {
			foreach ( $states as $key => $record ) {
				if ( is_string( $key ) && is_array( $record ) ) {
					$valid[ $key ] = RemoteMcpPolicy::record( $record );
				}
			}
		}
		return $valid;
	}
}
