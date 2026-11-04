<?php

declare(strict_types=1);
/**
 * Receiver for the SD AI service's signed "install Advanced" command.
 *
 * POST /sd-ai-agent/v1/service/install-advanced
 *
 * This is the only route in the private namespace that is not authorised by a
 * WordPress capability or cookie: the caller is the SD AI service, not a
 * browser. Authorisation is an Ed25519 signature over the raw body that is
 * checked in the permission callback, before any handler code runs. The
 * administrator's intent is established on the service's own domain (the
 * dashboard only opens a one-time account-action link), which is the
 * management-service pattern WordPress.org guideline 8 permits.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\REST;

use SdAiAgent\Core\AdvancedInstallCommand;
use SdAiAgent\Core\AdvancedPluginManager;
use SdAiAgent\Core\SuperdavSiteConnectionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs and activates Advanced when the service pushes a verified command.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class AdvancedInstallController {

	public const SIGNATURE_HEADER = 'x-sdai-signature';

	/** Register the signed-command route. */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/service/install-advanced',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_install' ),
				'permission_callback' => array( $this, 'check_signed_command' ),
			)
		);
	}

	/**
	 * Authorise the request by verifying the service's signature.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error True when the signed command is valid.
	 */
	public function check_signed_command( WP_REST_Request $request ): true|WP_Error {
		$verified = $this->verify( $request );

		return $verified instanceof WP_Error ? $verified : true;
	}

	/**
	 * Install and activate Advanced from the verified command.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error Browser-safe Advanced status or error.
	 */
	public function handle_install( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$command = $this->verify( $request );
		if ( $command instanceof WP_Error ) {
			return $command;
		}

		$status = ( new AdvancedPluginManager() )->install_from_command( $command );
		if ( $status instanceof WP_Error ) {
			return $status;
		}

		return new WP_REST_Response(
			array(
				'installed' => (bool) ( $status['installed'] ?? false ),
				'active'    => (bool) ( $status['active'] ?? false ),
				'version'   => $status['version'] ?? null,
			),
			200
		);
	}

	/**
	 * Verify the raw body against the signature header and this installation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array{version:string,download_url:string,package_sha256:string,nonce:string,expires_at:int}|WP_Error
	 */
	private function verify( WP_REST_Request $request ): array|WP_Error {
		$signature = $request->get_header( self::SIGNATURE_HEADER );

		return AdvancedInstallCommand::verify(
			$request->get_body(),
			is_string( $signature ) ? trim( $signature ) : '',
			AdvancedInstallCommand::trusted_public_keys(),
			( new SuperdavSiteConnectionService() )->get_installation_id(),
			(string) home_url( '/' ),
			time(),
			AdvancedInstallCommand::expected_download_url()
		);
	}
}
