<?php

declare(strict_types=1);

namespace SdAiAgent\Core;

use WP_Error;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports browser-safe state for the separately distributed Advanced plugin and
 * installs it when the SD AI service pushes a verified, signed command.
 *
 * The plugin never fetches Advanced on its own initiative. Installation only
 * runs from {@see AdvancedPluginManager::install_from_command()}, whose caller
 * must already have verified the command with {@see AdvancedInstallCommand}.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class AdvancedPluginManager {

	public const PLUGIN_BASENAME   = 'superdav-ai-agent-advanced/superdav-ai-agent-advanced.php';
	public const DIAGNOSTIC_OPTION = 'sd_ai_agent_advanced_update_diagnostic';

	private const NONCE_TRANSIENT_PREFIX = 'sd_ai_agent_adv_nonce_';
	private const INSTALL_LOCK_TRANSIENT = 'sd_ai_agent_advanced_install_lock';
	private const INSTALL_LOCK_TTL       = 300;

	private const STATUS_CURRENT              = 'current';
	private const STATUS_UPDATE_AVAILABLE     = 'update_available';
	private const STATUS_INCOMPATIBLE         = 'incompatible';
	private const STATUS_METADATA_UNAVAILABLE = 'metadata_unavailable';
	private const STATUS_UPDATE_FAILED        = 'update_failed';

	/**
	 * Persist the safe outcome reported by WordPress' automatic plugin updater.
	 *
	 * @param mixed $send        Existing debug-email decision.
	 * @param mixed $type        Automatic-update type.
	 * @param mixed $update_item Plugin update item.
	 * @param mixed $result      Update result.
	 * @return mixed Existing debug-email decision.
	 */
	#[Filter( tag: 'automatic_updates_send_debug_email', priority: 10, args: 4 )]
	public function record_automatic_update_result( mixed $send, mixed $type, mixed $update_item, mixed $result ): mixed {
		$plugin = is_object( $update_item ) && isset( $update_item->plugin ) && is_string( $update_item->plugin ) ? $update_item->plugin : '';
		if ( 'plugin' !== $type || self::PLUGIN_BASENAME !== $plugin ) {
			return $send;
		}

		if ( $result instanceof \WP_Error ) {
			$this->record_diagnostic( (string) $result->get_error_code() );
		} elseif ( true === $result ) {
			delete_option( self::DIAGNOSTIC_OPTION );
		} elseif ( false === $result ) {
			$this->record_diagnostic( 'sd_ai_agent_advanced_update_failed' );
		}

		return $send;
	}

	/**
	 * Return browser-safe local installation state.
	 *
	 * @return array<string, bool|string|null>
	 */
	public function get_status(): array {
		$installed   = file_exists( $this->plugin_file() );
		$active      = $installed && $this->is_active();
		$bundled     = $this->is_bundled_copy();
		$plugin_data = $installed ? $this->plugin_data() : array();
		$version     = isset( $plugin_data['Version'] ) && is_string( $plugin_data['Version'] ) ? $plugin_data['Version'] : null;
		$update      = $this->update_information( $version );
		$compatible  = is_string( $version ) ? $this->is_compatible_version( $version ) : null;
		$diagnostic  = $this->diagnostic_code();
		$status      = $this->status_state( $installed, $bundled, $compatible, $update['available'], $update['metadata_available'], $diagnostic );

		return array(
			'installed'        => $installed,
			'active'           => $active,
			'bundled'          => $bundled,
			'version'          => $version,
			'latest_version'   => $update['version'],
			'compatible'       => $compatible,
			'update_available' => $update['available'],
			'status'           => $status,
			'last_error_code'  => $diagnostic,
			'can_install'      => ! $bundled && self::current_user_can_manage() && $this->file_mods_allowed(),
		);
	}

	/** Whether the current administrator may install and activate plugins. */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );
	}

	/**
	 * Install and activate Advanced from a command the SD AI service pushed.
	 *
	 * The caller MUST have verified the command's signature, target, and expiry
	 * with {@see AdvancedInstallCommand::verify()}. This method additionally
	 * enforces single use, serialises concurrent installs, verifies the package
	 * against the SHA-256 the service signed, and is idempotent when Advanced is
	 * already installed (it then only activates).
	 *
	 * @param array{version:string,download_url:string,package_sha256:string,nonce:string,expires_at:int} $command Verified command payload.
	 * @return array<string, bool|string|null>|WP_Error Browser-safe status or error.
	 */
	public function install_from_command( array $command ): array|WP_Error {
		if ( $this->is_bundled_copy() ) {
			return new WP_Error( 'sd_ai_agent_advanced_bundled', __( 'The repository-bundled Advanced plugin is already loaded.', 'superdav-ai-agent' ), array( 'status' => 409 ) );
		}

		if ( ! $this->file_mods_allowed() ) {
			return new WP_Error( 'sd_ai_agent_file_mods_disabled', __( 'Plugin installation is disabled by this site configuration.', 'superdav-ai-agent' ), array( 'status' => 409 ) );
		}

		$nonce_key = self::NONCE_TRANSIENT_PREFIX . substr( hash( 'sha256', $command['nonce'] ), 0, 40 );
		if ( false !== get_transient( $nonce_key ) ) {
			return new WP_Error( 'sd_ai_agent_install_command_replayed', __( 'This install command was already used.', 'superdav-ai-agent' ), array( 'status' => 409 ) );
		}
		set_transient( $nonce_key, 1, max( 60, $command['expires_at'] - time() + 60 ) );

		if ( false !== get_transient( self::INSTALL_LOCK_TRANSIENT ) ) {
			return new WP_Error( 'sd_ai_agent_install_in_progress', __( 'An Advanced installation is already in progress.', 'superdav-ai-agent' ), array( 'status' => 409 ) );
		}
		set_transient( self::INSTALL_LOCK_TRANSIENT, 1, self::INSTALL_LOCK_TTL );

		try {
			if ( ! file_exists( $this->plugin_file() ) ) {
				$installed = $this->download_and_install( $command );
				if ( $installed instanceof WP_Error ) {
					return $installed;
				}
			}

			$activated = $this->activate();
			if ( $activated instanceof WP_Error ) {
				return $activated;
			}
		} finally {
			delete_transient( self::INSTALL_LOCK_TRANSIENT );
		}

		return $this->get_status();
	}

	/**
	 * Download the pinned package, verify its checksum, and install it.
	 *
	 * @param array{version:string,download_url:string,package_sha256:string,nonce:string,expires_at:int} $command Verified command payload.
	 */
	private function download_and_install( array $command ): true|WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$temporary_file = wp_tempnam( self::PLUGIN_BASENAME );
		if ( ! is_string( $temporary_file ) || '' === $temporary_file ) {
			return new WP_Error( 'sd_ai_agent_advanced_download_failed', __( 'WordPress could not create a temporary file for Advanced.', 'superdav-ai-agent' ), array( 'status' => 500 ) );
		}

		$response = wp_remote_get(
			$command['download_url'],
			array(
				'timeout'     => 300.0,
				'redirection' => 0,
				'stream'      => true,
				'filename'    => $temporary_file,
				'headers'     => array( 'Accept' => 'application/zip, application/octet-stream' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			wp_delete_file( $temporary_file );
			return new WP_Error( 'sd_ai_agent_advanced_download_failed', __( 'SD AI Agent Advanced could not be downloaded.', 'superdav-ai-agent' ), array( 'status' => 502 ) );
		}

		$checksum = hash_file( 'sha256', $temporary_file );
		if ( ! is_string( $checksum ) || ! hash_equals( $command['package_sha256'], $checksum ) ) {
			wp_delete_file( $temporary_file );
			return new WP_Error( 'sd_ai_agent_advanced_checksum_mismatch', __( 'SD AI Agent Advanced failed integrity verification.', 'superdav-ai-agent' ), array( 'status' => 422 ) );
		}

		// WP_Upgrader treats an existing local path as an already-downloaded package.
		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $temporary_file );
		wp_delete_file( $temporary_file );

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		if ( true !== $result || ! file_exists( $this->plugin_file() ) ) {
			return new WP_Error( 'sd_ai_agent_advanced_install_failed', __( 'SD AI Agent Advanced could not be installed.', 'superdav-ai-agent' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/** Activate Advanced, network-wide when the core plugin is network-active. */
	private function activate(): true|WP_Error {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( $this->is_active() ) {
			return true;
		}

		$core_basename = defined( 'SD_AI_AGENT_DIR' ) ? plugin_basename( SD_AI_AGENT_DIR . '/superdav-ai-agent.php' ) : '';
		$network_wide  = is_multisite() && '' !== $core_basename && is_plugin_active_for_network( $core_basename );
		$activation    = activate_plugin( self::PLUGIN_BASENAME, '', $network_wide, true );

		return $activation instanceof WP_Error ? $activation : true;
	}

	private function file_mods_allowed(): bool {
		return wp_is_file_mod_allowed( 'sd_ai_agent_advanced' );
	}

	/**
	 * @param string|null $current Installed Advanced version.
	 * @return array{available:bool,metadata_available:bool,version:string|null}
	 */
	private function update_information( ?string $current ): array {
		$updates            = get_site_transient( 'update_plugins' );
		$metadata_available = is_object( $updates ) && isset( $updates->last_checked );
		$response           = $metadata_available && isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : array();
		$item               = $response[ self::PLUGIN_BASENAME ] ?? null;
		$version            = is_object( $item ) && isset( $item->new_version ) && is_string( $item->new_version ) ? $item->new_version : null;

		return array(
			'available'          => is_string( $current ) && is_string( $version ) && version_compare( $version, $current, '>' ),
			'metadata_available' => $metadata_available,
			'version'            => $version,
		);
	}

	private function is_compatible_version( string $version ): bool {
		$core_version = defined( 'SD_AI_AGENT_VERSION' ) ? (string) SD_AI_AGENT_VERSION : '';

		return '' !== $core_version && 0 === version_compare( $version, $core_version );
	}

	private function diagnostic_code(): ?string {
		$diagnostic = get_option( self::DIAGNOSTIC_OPTION, '' );

		return is_string( $diagnostic ) && '' !== $diagnostic ? sanitize_key( $diagnostic ) : null;
	}

	private function record_diagnostic( string $code ): void {
		update_option( self::DIAGNOSTIC_OPTION, sanitize_key( $code ), false );
	}

	private function status_state( bool $installed, bool $bundled, ?bool $compatible, bool $update_available, bool $metadata_available, ?string $diagnostic ): string {
		if ( ! $installed || $bundled ) {
			return self::STATUS_CURRENT;
		}
		if ( null !== $diagnostic ) {
			return self::STATUS_UPDATE_FAILED;
		}
		if ( false === $compatible ) {
			return self::STATUS_INCOMPATIBLE;
		}
		if ( ! $metadata_available ) {
			return self::STATUS_METADATA_UNAVAILABLE;
		}
		if ( $update_available ) {
			return self::STATUS_UPDATE_AVAILABLE;
		}

		return self::STATUS_CURRENT;
	}

	private function plugin_file(): string {
		return WP_PLUGIN_DIR . '/' . self::PLUGIN_BASENAME;
	}

	private function is_bundled_copy(): bool {
		$bundled = defined( 'SD_AI_AGENT_DIR' ) && file_exists( SD_AI_AGENT_DIR . '/advanced-plugin/superdav-ai-agent-advanced.php' );

		return (bool) apply_filters( 'sd_ai_agent_advanced_plugin_bundled_copy', $bundled );
	}

	private function is_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::PLUGIN_BASENAME ) || ( is_multisite() && is_plugin_active_for_network( self::PLUGIN_BASENAME ) );
	}

	/** @return array<string, mixed> */
	private function plugin_data(): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugin_data( $this->plugin_file(), false, false );
	}
}
