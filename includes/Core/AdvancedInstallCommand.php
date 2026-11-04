<?php

declare(strict_types=1);
/**
 * Verifies signed Advanced install commands pushed by the SD AI service.
 *
 * WordPress.org guideline 8 forbids a plugin from installing code from a
 * non-WordPress.org server on its own initiative. A management service that
 * pushes software to a site, with the interaction handled on the service's
 * own domain, is permitted. This class is the site-side gate for that push:
 * nothing is installed unless the command was signed by the service's private
 * key, targets this exact installation and site URL, is unexpired, and pins
 * the SHA-256 of the one package that may be installed.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signed-command verification (no side effects).
 */
final class AdvancedInstallCommand {

	public const SLUG           = 'superdav-ai-agent-advanced';
	public const COMMAND_TYPE   = 'install_advanced';
	public const SIGNING_PREFIX = "sdai-advanced-install-v1\n";
	public const DOWNLOAD_URL   = 'https://sdaiagent.com/?sdai_update_action=download&sdai_update_slug=superdav-ai-agent-advanced';

	/** Maximum accepted request body, in bytes. */
	public const MAX_BODY_BYTES = 4096;

	/** Maximum validity window a command may declare, in seconds. */
	public const MAX_LIFETIME = 600;

	/** Tolerated clock skew for `issued_at`, in seconds. */
	public const CLOCK_SKEW = 60;

	/**
	 * Hex-encoded Ed25519 public keys whose signatures are trusted.
	 *
	 * Rotation: add the new key here in a release, then retire the old signing
	 * key on the service once sites have updated.
	 */
	private const TRUSTED_PUBLIC_KEYS = array(
		'd0c3741f4c690bfeea1dffbc55e3069482e76b3b238daabea0fe927bcf284972',
	);

	/**
	 * Return the trusted public keys, including an optional wp-config override.
	 *
	 * @return array<int, string> Hex public keys.
	 */
	public static function trusted_public_keys(): array {
		$keys = self::TRUSTED_PUBLIC_KEYS;

		if ( defined( 'SD_AI_AGENT_ADVANCED_INSTALL_PUBLIC_KEY' ) && is_string( SD_AI_AGENT_ADVANCED_INSTALL_PUBLIC_KEY ) && '' !== SD_AI_AGENT_ADVANCED_INSTALL_PUBLIC_KEY ) {
			$keys[] = SD_AI_AGENT_ADVANCED_INSTALL_PUBLIC_KEY;
		}

		return $keys;
	}

	/**
	 * Return the only package URL a command may reference.
	 */
	public static function expected_download_url(): string {
		if ( defined( 'SD_AI_AGENT_ADVANCED_INSTALL_DOWNLOAD_URL' ) && is_string( SD_AI_AGENT_ADVANCED_INSTALL_DOWNLOAD_URL ) && '' !== SD_AI_AGENT_ADVANCED_INSTALL_DOWNLOAD_URL ) {
			return SD_AI_AGENT_ADVANCED_INSTALL_DOWNLOAD_URL;
		}

		return self::DOWNLOAD_URL;
	}

	/**
	 * Verify a signed command and return its validated payload.
	 *
	 * @param string             $body            Raw request body exactly as signed.
	 * @param string             $signature       Hex detached Ed25519 signature.
	 * @param array<int, string> $public_keys     Hex trusted public keys.
	 * @param string             $installation_id This site's installation ID.
	 * @param string             $site_url        This site's home URL.
	 * @param int                $now             Current Unix time.
	 * @param string             $download_url    The single permitted package URL.
	 * @return array{version:string,download_url:string,package_sha256:string,nonce:string,expires_at:int}|WP_Error
	 */
	public static function verify( string $body, string $signature, array $public_keys, string $installation_id, string $site_url, int $now, string $download_url ): array|WP_Error {
		if ( '' === $body || strlen( $body ) > self::MAX_BODY_BYTES ) {
			return self::error( 'sd_ai_agent_install_command_invalid', 400 );
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return self::error( 'sd_ai_agent_install_sodium_unavailable', 501 );
		}

		$signature_bytes = self::from_hex( $signature, SODIUM_CRYPTO_SIGN_BYTES );
		if ( null === $signature_bytes ) {
			return self::error( 'sd_ai_agent_install_signature_invalid', 401 );
		}

		$message = self::SIGNING_PREFIX . $body;
		$trusted = false;
		foreach ( $public_keys as $public_key ) {
			$key_bytes = self::from_hex( (string) $public_key, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES );
			if ( null === $key_bytes ) {
				continue;
			}
			if ( sodium_crypto_sign_verify_detached( $signature_bytes, $message, $key_bytes ) ) {
				$trusted = true;
				break;
			}
		}

		if ( ! $trusted ) {
			return self::error( 'sd_ai_agent_install_signature_invalid', 401 );
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) ) {
			return self::error( 'sd_ai_agent_install_command_invalid', 400 );
		}

		$version        = isset( $payload['version'] ) && is_string( $payload['version'] ) ? $payload['version'] : '';
		$package_sha256 = isset( $payload['package_sha256'] ) && is_string( $payload['package_sha256'] ) ? $payload['package_sha256'] : '';
		$nonce          = isset( $payload['nonce'] ) && is_string( $payload['nonce'] ) ? $payload['nonce'] : '';
		$issued_at      = isset( $payload['issued_at'] ) && is_int( $payload['issued_at'] ) ? $payload['issued_at'] : 0;
		$expires_at     = isset( $payload['expires_at'] ) && is_int( $payload['expires_at'] ) ? $payload['expires_at'] : 0;

		if ( 1 !== ( $payload['v'] ?? null )
			|| self::COMMAND_TYPE !== ( $payload['type'] ?? null )
			|| self::SLUG !== ( $payload['slug'] ?? null )
			|| 1 !== preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $package_sha256 )
			|| 1 !== preg_match( '/^[A-Za-z0-9-]{16,64}$/', $nonce )
			|| $issued_at <= 0
			|| $expires_at <= $issued_at
		) {
			return self::error( 'sd_ai_agent_install_command_invalid', 400 );
		}

		if ( $expires_at - $issued_at > self::MAX_LIFETIME || $issued_at > $now + self::CLOCK_SKEW ) {
			return self::error( 'sd_ai_agent_install_command_invalid', 400 );
		}

		if ( $expires_at < $now ) {
			return self::error( 'sd_ai_agent_install_command_expired', 410 );
		}

		$command_installation = isset( $payload['installation_id'] ) && is_string( $payload['installation_id'] ) ? $payload['installation_id'] : '';
		$command_site         = isset( $payload['site_url'] ) && is_string( $payload['site_url'] ) ? $payload['site_url'] : '';
		if ( '' === $installation_id
			|| ! hash_equals( $installation_id, $command_installation )
			|| '' === self::normalize_site_url( $site_url )
			|| ! hash_equals( self::normalize_site_url( $site_url ), self::normalize_site_url( $command_site ) )
		) {
			return self::error( 'sd_ai_agent_install_target_mismatch', 403 );
		}

		$command_download = isset( $payload['download_url'] ) && is_string( $payload['download_url'] ) ? $payload['download_url'] : '';
		if ( '' === $download_url || ! hash_equals( $download_url, $command_download ) ) {
			return self::error( 'sd_ai_agent_install_package_unexpected', 403 );
		}

		return array(
			'version'        => $version,
			'download_url'   => $command_download,
			'package_sha256' => $package_sha256,
			'nonce'          => $nonce,
			'expires_at'     => $expires_at,
		);
	}

	/**
	 * Decode a hex string to exactly $length bytes.
	 *
	 * @param string $hex    Hex string.
	 * @param int    $length Required decoded length in bytes.
	 * @return non-empty-string|null Raw bytes, or null when malformed.
	 */
	private static function from_hex( string $hex, int $length ): ?string {
		if ( $length < 1 || strlen( $hex ) !== $length * 2 || ! ctype_xdigit( $hex ) ) {
			return null;
		}

		$bytes = hex2bin( $hex );

		return is_string( $bytes ) && '' !== $bytes ? $bytes : null;
	}

	/**
	 * Normalize a site URL the way the service canonicalizes it.
	 *
	 * Lower-case host, no credentials, query, or fragment, and no trailing
	 * slash on the path, so `https://Example.com` and `https://example.com/`
	 * compare equal.
	 *
	 * @param string $url Site URL.
	 * @return string Normalized `scheme://host[:port]/path`, or '' if invalid.
	 */
	public static function normalize_site_url( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';

		return $scheme . '://' . strtolower( (string) $parts['host'] ) . $port . $path;
	}

	/**
	 * Build a stable, non-leaking error.
	 *
	 * @param string $code   Error code.
	 * @param int    $status HTTP status.
	 */
	private static function error( string $code, int $status ): WP_Error {
		return new WP_Error(
			$code,
			__( 'The SD AI Agent Advanced install command could not be verified.', 'superdav-ai-agent' ),
			array( 'status' => $status )
		);
	}
}
