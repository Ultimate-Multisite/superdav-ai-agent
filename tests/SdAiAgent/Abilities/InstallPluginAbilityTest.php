<?php

declare(strict_types=1);
/**
 * Tests for verified plugin installation results.
 *
 * @package SdAiAgent
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\InstallPluginAbility;
use WP_Error;
use WP_UnitTestCase;

class InstallPluginAbilityTest extends WP_UnitTestCase {

	public function test_null_install_result_is_reported_as_aborted(): void {
		$ability = $this->ability( null );

		$result = $ability->run( [ 'slug' => 'contact-form-7' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_install_aborted', $result->get_error_code() );
	}

	public function test_skin_error_is_preserved_when_installation_fails(): void {
		$download_error = new WP_Error( 'download_failed', 'Package download failed.' );
		$ability        = $this->ability( false, $download_error );

		$result = $ability->run( [ 'slug' => 'contact-form-7' ] );

		$this->assertSame( $download_error, $result );
	}

	public function test_success_requires_a_matching_plugin_file_on_disk(): void {
		$ability = $this->ability( true, new WP_Error(), 'contact-form-7/wp-contact-form-7.php' );

		$result = $ability->run( [ 'slug' => 'contact-form-7' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'sd_ai_agent_plugin_file_not_resolved', $result->get_error_code() );
	}

	public function test_success_reports_verified_matching_plugin_file(): void {
		$plugin_file = 'contact-form-7/wp-contact-form-7.php';
		$ability     = $this->ability( true, new WP_Error(), $plugin_file, [ $plugin_file => [] ], [ $plugin_file ] );

		$result = $ability->run( [ 'slug' => 'contact-form-7' ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'installed', $result['status'] );
		$this->assertSame( $plugin_file, $result['plugin_file'] );
		$this->assertFalse( $result['active'] );
	}

	public function test_activation_is_not_claimed_without_active_state(): void {
		$plugin_file = 'contact-form-7/wp-contact-form-7.php';
		$ability     = $this->ability( true, new WP_Error(), $plugin_file, [ $plugin_file => [] ], [ $plugin_file ] );

		$result = $ability->run(
			[
				'slug'     => 'contact-form-7',
				'activate' => true,
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'installed', $result['status'] );
		$this->assertFalse( $result['active'] );
	}

	/**
	 * @param mixed                           $install_result Upgrader result.
	 * @param array<string,array<string,mixed>> $plugins       Enumerated plugins.
	 * @param string[]                        $existing_files Existing main files.
	 */
	private function ability( $install_result, ?WP_Error $errors = null, string $plugin_file = '', array $plugins = [], array $existing_files = [] ): InstallPluginAbility {
		return new class( $install_result, $errors ?? new WP_Error(), $plugin_file, $plugins, $existing_files ) extends InstallPluginAbility {
			/** @var mixed */
			private $install_result;

			private WP_Error $errors;

			private string $reported_plugin_file;

			/** @var array<string,array<string,mixed>> */
			private array $plugins;

			/** @var string[] */
			private array $existing_files;

			private int $plugin_inventory_calls = 0;

			/**
			 * @param mixed                            $install_result Upgrader result.
			 * @param array<string,array<string,mixed>> $plugins       Enumerated plugins.
			 * @param string[]                         $existing_files Existing main files.
			 */
			public function __construct( $install_result, WP_Error $errors, string $plugin_file, array $plugins, array $existing_files ) {
				parent::__construct( 'sd-ai-agent/install-plugin' );
				$this->install_result      = $install_result;
				$this->errors             = $errors;
				$this->reported_plugin_file = $plugin_file;
				$this->plugins             = $plugins;
				$this->existing_files      = $existing_files;
			}

			protected function get_plugin_information( string $slug ) {
				return (object) [ 'download_link' => 'https://example.test/plugin.zip' ];
			}

			protected function install_package( string $download_link ): array {
				return [
					'result'      => $this->install_result,
					'errors'      => $this->errors,
					'plugin_file' => $this->reported_plugin_file,
				];
			}

			protected function get_installed_plugins(): array {
				++$this->plugin_inventory_calls;
				if ( 1 === $this->plugin_inventory_calls ) {
					return [];
				}

				return $this->plugins;
			}

			protected function plugin_file_exists( string $plugin_file ): bool {
				return in_array( $plugin_file, $this->existing_files, true );
			}

			protected function activate_plugin( string $plugin_file ) {
				return null;
			}

			protected function is_plugin_active( string $plugin_file ): bool {
				return false;
			}
		};
	}
}
