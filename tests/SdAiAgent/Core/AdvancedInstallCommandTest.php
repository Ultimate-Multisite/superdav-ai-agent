<?php
/**
 * Tests for verification of service-pushed Advanced install commands.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\AdvancedInstallCommand;
use WP_Error;
use WP_UnitTestCase;

final class AdvancedInstallCommandTest extends WP_UnitTestCase {

	private const INSTALLATION_ID = 'a3f1c2d4-1111-4222-8333-444455556666';
	private const SITE_URL        = 'https://Example.test/';
	private const NOW             = 1800000000;

	private string $secret_key;
	private string $public_key_hex;

	public function set_up(): void {
		parent::set_up();

		$keypair              = sodium_crypto_sign_keypair();
		$this->secret_key     = sodium_crypto_sign_secretkey( $keypair );
		$this->public_key_hex = bin2hex( sodium_crypto_sign_publickey( $keypair ) );
	}

	public function test_a_valid_signed_command_is_accepted(): void {
		$result = $this->verify( $this->body() );

		$this->assertIsArray( $result );
		$this->assertSame( '1.25.0', $result['version'] );
		$this->assertSame( str_repeat( 'a', 64 ), $result['package_sha256'] );
	}

	public function test_site_url_comparison_ignores_case_and_trailing_slash(): void {
		$body = $this->body( array( 'site_url' => 'https://example.test' ) );

		$this->assertIsArray( $this->verify( $body ) );
	}

	public function test_a_tampered_body_is_rejected(): void {
		$body      = $this->body();
		$signature = $this->sign( $body );
		$tampered  = str_replace( str_repeat( 'a', 64 ), str_repeat( 'b', 64 ), $body );

		$this->assertSame(
			'sd_ai_agent_install_signature_invalid',
			$this->error_code( $this->verify( $tampered, $signature ) )
		);
	}

	public function test_a_signature_from_an_untrusted_key_is_rejected(): void {
		$other     = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );
		$body      = $this->body();
		$signature = bin2hex( sodium_crypto_sign_detached( AdvancedInstallCommand::SIGNING_PREFIX . $body, $other ) );

		$this->assertSame(
			'sd_ai_agent_install_signature_invalid',
			$this->error_code( $this->verify( $body, $signature ) )
		);
	}

	public function test_malformed_signatures_are_rejected(): void {
		$body = $this->body();

		foreach ( array( '', 'not-hex', str_repeat( 'z', 128 ), bin2hex( 'short' ) ) as $signature ) {
			$this->assertSame(
				'sd_ai_agent_install_signature_invalid',
				$this->error_code( $this->verify( $body, $signature ) )
			);
		}
	}

	public function test_a_command_signed_without_the_domain_prefix_is_rejected(): void {
		$body      = $this->body();
		$signature = bin2hex( sodium_crypto_sign_detached( $body, $this->secret_key ) );

		$this->assertSame(
			'sd_ai_agent_install_signature_invalid',
			$this->error_code( $this->verify( $body, $signature ) )
		);
	}

	public function test_a_command_for_another_installation_is_rejected(): void {
		$body = $this->body( array( 'installation_id' => 'other-installation-id' ) );

		$this->assertSame( 'sd_ai_agent_install_target_mismatch', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_a_command_for_another_site_url_is_rejected(): void {
		$body = $this->body( array( 'site_url' => 'https://attacker.test/' ) );

		$this->assertSame( 'sd_ai_agent_install_target_mismatch', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_an_expired_command_is_rejected(): void {
		$body = $this->body(
			array(
				'issued_at'  => self::NOW - 400,
				'expires_at' => self::NOW - 100,
			)
		);

		$this->assertSame( 'sd_ai_agent_install_command_expired', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_an_overlong_validity_window_is_rejected(): void {
		$body = $this->body( array( 'expires_at' => self::NOW + AdvancedInstallCommand::MAX_LIFETIME + 100 ) );

		$this->assertSame( 'sd_ai_agent_install_command_invalid', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_a_command_issued_in_the_future_is_rejected(): void {
		$body = $this->body(
			array(
				'issued_at'  => self::NOW + 3600,
				'expires_at' => self::NOW + 3900,
			)
		);

		$this->assertSame( 'sd_ai_agent_install_command_invalid', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_an_unexpected_package_url_is_rejected(): void {
		$body = $this->body( array( 'download_url' => 'https://attacker.test/evil.zip' ) );

		$this->assertSame( 'sd_ai_agent_install_package_unexpected', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_invalid_package_metadata_is_rejected(): void {
		foreach ( array(
			array( 'slug' => 'another-plugin' ),
			array( 'version' => 'latest' ),
			array( 'package_sha256' => 'not-a-sha' ),
			array( 'type' => 'delete_everything' ),
			array( 'nonce' => 'x' ),
		) as $override ) {
			$this->assertSame(
				'sd_ai_agent_install_command_invalid',
				$this->error_code( $this->verify( $this->body( $override ) ) ),
				'Override ' . wp_json_encode( $override ) . ' must be rejected.'
			);
		}
	}

	public function test_an_oversized_body_is_rejected(): void {
		$body = $this->body( array( 'padding' => str_repeat( 'x', AdvancedInstallCommand::MAX_BODY_BYTES ) ) );

		$this->assertSame( 'sd_ai_agent_install_command_invalid', $this->error_code( $this->verify( $body ) ) );
	}

	public function test_the_shipped_trusted_key_is_a_valid_ed25519_public_key(): void {
		$keys = AdvancedInstallCommand::trusted_public_keys();

		$this->assertNotEmpty( $keys );
		foreach ( $keys as $key ) {
			$this->assertSame( SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES * 2, strlen( $key ) );
			$this->assertTrue( ctype_xdigit( $key ) );
		}
	}

	/**
	 * Build a signed-command body.
	 *
	 * @param array<string, mixed> $overrides Payload overrides.
	 */
	private function body( array $overrides = array() ): string {
		return (string) wp_json_encode(
			array_merge(
				array(
					'v'               => 1,
					'type'            => AdvancedInstallCommand::COMMAND_TYPE,
					'installation_id' => self::INSTALLATION_ID,
					'site_url'        => self::SITE_URL,
					'slug'            => AdvancedInstallCommand::SLUG,
					'version'         => '1.25.0',
					'download_url'    => AdvancedInstallCommand::DOWNLOAD_URL,
					'package_sha256'  => str_repeat( 'a', 64 ),
					'nonce'           => 'b2c4d6f8-0000-4111-8222-333344445555',
					'issued_at'       => self::NOW - 10,
					'expires_at'      => self::NOW + 290,
				),
				$overrides
			)
		);
	}

	private function sign( string $body ): string {
		return bin2hex( sodium_crypto_sign_detached( AdvancedInstallCommand::SIGNING_PREFIX . $body, $this->secret_key ) );
	}

	/** @return array<string, mixed>|WP_Error */
	private function verify( string $body, ?string $signature = null ): array|WP_Error {
		return AdvancedInstallCommand::verify(
			$body,
			$signature ?? $this->sign( $body ),
			array( $this->public_key_hex ),
			self::INSTALLATION_ID,
			'https://example.test/',
			self::NOW,
			AdvancedInstallCommand::DOWNLOAD_URL
		);
	}

	/** @param array<string, mixed>|WP_Error $result Verification result. */
	private function error_code( array|WP_Error $result ): string {
		$this->assertInstanceOf( WP_Error::class, $result );

		return (string) $result->get_error_code();
	}
}
