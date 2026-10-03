<?php

declare(strict_types=1);
/**
 * Cookie-authenticated OAuth callback and public, nonsecret client metadata.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Bootstrap;

use SdAiAgent\Mcp\RemoteMcpConnectionRepository;
use SdAiAgent\Mcp\RemoteMcpOAuthClient;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[Handler( container: 'sd-ai-agent', strategy: Handler::INIT_IMMEDIATELY )]
final class RemoteMcpOAuthHandler {

	#[Action( tag: 'admin_post_sd_ai_agent_mcp_client_metadata' )]
	#[Action( tag: 'admin_post_nopriv_sd_ai_agent_mcp_client_metadata' )]
	public function metadata(): void {
		// This standards identity document contains only fixed public client URLs.
		// It deliberately does not expose the private REST namespace or connections.
		wp_send_json( RemoteMcpOAuthClient::client_metadata() );
	}

	#[Action( tag: 'admin_post_sd_ai_agent_mcp_oauth_callback' )]
	public function callback(): void {
		nocache_headers();
		$query = array();
		foreach ( array( 'state', 'code', 'iss', 'error' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- One-time state validates context; RFC 9207 forbids issuer normalization and opaque codes must retain exact bytes.
			$value         = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
			$query[ $key ] = substr( $value, 0, 8192 );
		}
		$result = ( new RemoteMcpOAuthClient( new RemoteMcpConnectionRepository() ) )->complete( $query );
		$status = is_wp_error( $result ) ? 'failed' : 'connected';
		if ( is_wp_error( $result ) && 'sd_ai_agent_remote_mcp_oauth_cancelled' === $result->get_error_code() ) {
			$status = 'cancelled';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=sd-ai-agent&mcp_oauth=' . $status . '#settings' ) );
		exit;
	}

	#[Action( tag: 'admin_post_nopriv_sd_ai_agent_mcp_oauth_callback' )]
	public function unauthenticated_callback(): void {
		// Do not consume state as an anonymous REST caller or forward a code to login.
		wp_safe_redirect( admin_url( 'admin.php?page=sd-ai-agent&mcp_oauth=failed#settings' ) );
		exit;
	}
}
