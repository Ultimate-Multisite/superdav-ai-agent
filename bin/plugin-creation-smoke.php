<?php
/**
 * WordPress-side operations for the opt-in packaged-plugin smoke runner.
 *
 * Evaluated by scripts/plugin-creation-smoke.js through the existing WP-CLI.
 * Never load this helper in the released plugin or on a production site.
 *
 * @package SdAiAgent
 */

declare(strict_types=1);

return static function ( array $options ): array {
	$phase  = (string) ( $options['phase'] ?? '' );
	$slug   = (string) ( $options['slug'] ?? '' );
	$mode   = (string) ( $options['scenario'] ?? '' );
	$checks = [];
	$details = [];
	$requests = 0;
	$assert = static function ( bool $condition, string $code ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $code );
		}
	};
	$unwrap = static function ( mixed $value ) use ( $assert ): mixed {
		$assert( ! is_wp_error( $value ), is_wp_error( $value ) ? 'ability_' . $value->get_error_code() : 'ability_failed' );
		return $value;
	};
	$call = static function ( string $name, array $input ) use ( $assert, $unwrap ): mixed {
		$ability = wp_get_ability( 'sd-ai-agent/' . $name );
		$assert( null !== $ability, 'ability_missing' );
		return $unwrap( $ability->execute( $input ) );
	};

	try {
		$assert( defined( 'WP_CLI' ) && WP_CLI, 'cli_required' );
		$assert( ! empty( $options['live'] ) && ! empty( $options['disposableSite'] ), 'explicit_opt_in_required' );
		$assert( in_array( wp_get_environment_type(), [ 'local', 'development' ], true ), 'disposable_development_site_required' );
		$assert( ! is_multisite(), 'single_site_required' );
		$assert( current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' ), 'administrator_required' );
		$assert( untrailingslashit( home_url() ) === untrailingslashit( (string) $options['siteUrl'] ), 'site_url_mismatch' );
		$assert( (bool) preg_match( '/^sd-release-smoke-[a-f0-9]{12}-(single|multi|settings|rest|agent)$/D', $slug ), 'invalid_smoke_slug' );
		$assert( in_array( $mode, [ 'single', 'multi', 'settings', 'rest', 'agent' ], true ), 'invalid_scenario' );
		$assert( defined( 'SD_AI_AGENT_DIR' ) && defined( 'SD_AI_AGENT_ADVANCED_DIR' ), 'both_packages_required' );
		$assert( SD_AI_AGENT_VERSION === SD_AI_AGENT_ADVANCED_VERSION, 'package_version_mismatch' );
		$assert( ! is_link( WP_PLUGIN_DIR . '/superdav-ai-agent' ) && ! is_link( WP_PLUGIN_DIR . '/superdav-ai-agent-advanced' ), 'installed_packages_required' );
		$assert( realpath( SD_AI_AGENT_DIR ) === realpath( WP_PLUGIN_DIR . '/superdav-ai-agent' ), 'canonical_core_package_required' );
		$assert( realpath( SD_AI_AGENT_ADVANCED_DIR ) === realpath( WP_PLUGIN_DIR . '/superdav-ai-agent-advanced' ), 'canonical_advanced_package_required' );
		$assert( ! is_dir( SD_AI_AGENT_DIR . '/advanced-plugin' ), 'source_checkout_is_not_a_release_package' );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$dir       = WP_PLUGIN_DIR . '/' . $slug;
		$tag       = str_replace( '-', '_', $slug );
		$option    = $tag . '_value';
		$sentinel  = 'SD_SMOKE_' . strtoupper( substr( $slug, 17, 12 ) );
		$expected  = $sentinel . ( 'verify-updated' === $phase ? '_V2' : '_V1' );
		$main      = (string) ( $options['main'] ?? $slug . '/' . $slug . '.php' );
		$main_safe = str_starts_with( $main, $slug . '/' ) && ! str_contains( $main, '..' ) && str_ends_with( $main, '.php' );
		$assert( $main_safe, 'invalid_generated_main_path' );

		if ( 'preflight' === $phase ) {
			$assert( ! file_exists( $dir ) && ! is_link( $dir ), 'smoke_slug_already_exists' );
			return [ 'passed' => true, 'version' => SD_AI_AGENT_VERSION, 'phase' => $phase ];
		}

		if ( 'cleanup' === $phase ) {
			// Skip loading this QA plugin in the fresh cleanup process, even after a fatal.
			$assert( ! is_link( $dir ), 'cleanup_refuses_symlink' );
			deactivate_plugins( $main, true );
			\SdAiAgent\PluginBuilder\PluginInstaller::delete_generated_plugin( $slug );
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			$fs = new WP_Filesystem_Direct( [] );
			if ( is_dir( $dir ) ) {
				$assert( $fs->rmdir( $dir, true ), 'partial_install_cleanup_failed' );
			}
			$uploads = wp_upload_dir( null, false );
			foreach ( [ 'sd-ai-backups', 'sd-ai-staging' ] as $root ) {
				$paths = array_merge( [ $uploads['basedir'] . '/' . $root . '/' . $slug ], glob( $uploads['basedir'] . '/' . $root . '/' . $slug . '-*', GLOB_ONLYDIR ) ?: [] );
				foreach ( $paths as $backup ) {
					if ( ! file_exists( $backup ) && ! is_link( $backup ) ) {
						continue;
					}
					$assert( ! is_link( $backup ) && $fs->rmdir( $backup, true ), 'backup_cleanup_failed' );
				}
			}
			delete_option( $option );
			$assert( ! file_exists( $dir ) && ! is_plugin_active( $main ), 'cleanup_incomplete' );
			$assert( null === \SdAiAgent\Repositories\GeneratedPluginsRepository::get( $slug ), 'record_cleanup_unverified' );
			return [ 'passed' => true, 'phase' => $phase ];
		}

		// Bound outgoing POSTs and HTTP timeouts in every generation process.
		$deadline = microtime( true ) + (int) $options['phaseSeconds'];
		add_filter(
			'pre_http_request',
			static function ( mixed $pre, array $args, string $url ) use ( &$requests, $options, $deadline ): mixed {
				if ( 'POST' === strtoupper( $args['method'] ?? 'GET' ) && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
					++$requests;
					if ( $requests > (int) $options['maxRequests'] || microtime( true ) >= $deadline ) {
						return new WP_Error( 'sd_smoke_request_budget_exceeded', 'Live smoke request budget exhausted.' );
					}
				}
				return $pre;
			},
			PHP_INT_MAX,
			3
		);
		add_filter(
			'http_request_args',
			static function ( array $args ) use ( $deadline ): array {
				$args['timeout'] = min( (float) ( $args['timeout'] ?? 30 ), max( 1, $deadline - microtime( true ) ) );
				return $args;
			}
		);

		if ( 'generate' === $phase ) {
			$assert( ! file_exists( $dir ), 'smoke_slug_already_exists' );
			$ability = wp_get_ability( 'sd-ai-agent/generate-plugin' );
			$assert( null !== $ability, 'generation_ability_missing' );
			$user_id = get_current_user_id();
			try {
				wp_set_current_user( 0 );
				$denied = $ability->execute( [ 'description' => 'Unauthorized smoke request', 'install' => false ] );
				$assert( is_wp_error( $denied ) && 'ability_invalid_permissions' === $denied->get_error_code(), 'generation_permission_denial_failed' );
			} finally {
				wp_set_current_user( $user_id );
			}
			$checks[]    = 'unauthenticated_generation_denied';
			$description = "Create a minimal WordPress plugin with exact slug {$slug} and main file {$slug}/{$slug}.php. Register shortcode [{$tag}] returning the exact literal string {$sentinel}_V1. Include normal plugin headers and an ABSPATH guard. No external requests, dependencies, JavaScript, or CSS. ";
			$description .= 'multi' === $mode ? 'The complete file list must contain exactly two files total: the main PHP file and includes/shortcode.php. The main file must require_once the helper. Put shortcode registration in that helper.' : 'The complete file list must contain exactly one file total: the main PHP file.';
			$description .= ' Do not add README files, license files, metadata files, assets, or any other files.';
			if ( 'settings' === $mode ) {
				$description .= " Also register a string setting named {$option} in group {$tag}, on admin_init, with sanitize_text_field as its sanitize_callback. No settings page is needed.";
			}
			if ( 'rest' === $mode ) {
				$description .= " Also register GET /{$slug}/v1/probe on rest_api_init returning an object with value equal to the same exact literal {$sentinel}_V1. Its permission_callback must require current_user_can('manage_options').";
			}
			if ( 'agent' === $mode ) {
				( new \SdAiAgent\CLI\CliCommand() )(
					[ $description . ' Use generate-plugin, sandbox-test-plugin, and sandbox-activate-plugin to generate, test and activate only this QA plugin. Do not use browser tools or change other plugins/settings. Stop after activation; the runner will verify behavior and clean up.' ],
					[ 'provider' => $options['provider'], 'model' => $options['model'], 'max-iterations' => 8 ]
				);
			} else {
				$ability->set_provider_model_context( $options['provider'], $options['model'] );
				try {
					$result = $unwrap( $ability->execute( [ 'description' => $description, 'slug' => $slug, 'install' => true ] ) );
				} finally {
					$ability->clear_provider_model_context();
				}
				$assert( ! empty( $result['installed'] ) && $slug === $result['slug'], 'generation_install_failed' );
				$details = [ 'expectedFiles' => 'multi' === $mode ? 2 : 1, 'actualFiles' => count( $result['files'] ) ];
				$assert( count( $result['files'] ) === ( 'multi' === $mode ? 2 : 1 ), 'generated_file_count_mismatch' );
				$assert( $main === $result['plugin_file'], 'generated_main_file_mismatch' );
			}
			$assert( is_file( WP_PLUGIN_DIR . '/' . $main ), 'generated_main_missing' );
			$checks[] = 'real_generation_and_installation';
		} elseif ( 'sandbox' === $phase ) {
			$result = $call( 'sandbox-test-plugin', [ 'slug' => $slug, 'plugin_file' => substr( $main, strlen( $slug ) + 1 ) ] );
			$assert( ! empty( $result['layer1_passed'] ) && ! empty( $result['layer2_passed'] ) && ! empty( $result['passed'] ), 'sandbox_failed' );
			$checks[] = 'syntax_and_isolated_include';
		} elseif ( 'activate' === $phase ) {
			$result = $call( 'sandbox-activate-plugin', [ 'plugin_file' => $main ] );
			$assert( ! empty( $result['activated'] ), 'activation_failed' );
			$checks[] = 'sandboxed_activation';
		} elseif ( in_array( $phase, [ 'verify', 'verify-updated' ], true ) ) {
			$assert( is_plugin_active( $main ) && do_shortcode( '[' . $tag . ']' ) === $expected, 'fresh_request_shortcode_failed' );
			$checks[] = 'fresh_request_shortcode';
			if ( 'settings' === $mode ) {
				do_action( 'admin_init' );
				$settings = get_registered_settings();
				$assert( isset( $settings[ $option ]['sanitize_callback'] ) && is_callable( $settings[ $option ]['sanitize_callback'] ), 'setting_sanitizer_missing' );
				if ( 'verify-updated' === $phase ) {
					$assert( 'SMOKE_SETTING' === get_option( $option ), 'setting_persistence_failed' );
				}
				update_option( $option, ' <b>SMOKE_SETTING</b> ' );
				$assert( 'SMOKE_SETTING' === get_option( $option ), 'setting_sanitization_failed' );
				$checks[] = 'settings_api_sanitization';
			}
			if ( 'rest' === $mode ) {
				$request = new WP_REST_Request( 'GET', '/' . $slug . '/v1/probe' );
				$response = rest_do_request( $request );
				$assert( 200 === $response->get_status() && $expected === ( $response->get_data()['value'] ?? null ), 'rest_admin_behavior_failed' );
				$user_id = get_current_user_id();
				try {
					wp_set_current_user( 0 );
					$assert( in_array( rest_do_request( $request )->get_status(), [ 401, 403 ], true ), 'rest_anonymous_access_not_denied' );
				} finally {
					wp_set_current_user( $user_id );
				}
				$checks[] = 'rest_authorization_and_behavior';
			}
		} elseif ( in_array( $phase, [ 'update', 'reject-invalid' ], true ) ) {
			$files  = [];
			$hashes = [];
			$hits   = 0;
			$items  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $items as $item ) {
				$assert( ! $item->isLink(), 'generated_symlink_not_allowed' );
				if ( $item->isFile() ) {
					$relative            = substr( $item->getPathname(), strlen( $dir ) + 1 );
					$hashes[ $relative ] = hash_file( 'sha256', $item->getPathname() );
					$count               = 0;
					$files[ $relative ]  = str_replace( $sentinel . '_V1', $sentinel . '_V2', file_get_contents( $item->getPathname() ), $count );
					$hits               += $count;
				}
			}
			if ( 'update' === $phase ) {
				$assert( $hits > 0, 'update_literal_marker_missing' );
				$result = $call( 'update-plugin-sandboxed', [ 'slug' => $slug, 'files' => $files ] );
				$assert( ! empty( $result['swapped'] ), 'valid_update_failed' );
				$checks[] = 'sandboxed_update_and_health_check';
			} else {
				$ability = wp_get_ability( 'sd-ai-agent/update-plugin-sandboxed' );
				$result  = $ability->execute( [ 'slug' => $slug, 'files' => [ substr( $main, strlen( $slug ) + 1 ) => '<?php $broken = ;' ] ] );
				$assert( is_wp_error( $result ), 'invalid_update_not_rejected' );
				foreach ( $hashes as $relative => $hash ) {
					$assert( is_file( $dir . '/' . $relative ) && $hash === hash_file( 'sha256', $dir . '/' . $relative ), 'rollback_file_hash_changed' );
				}
				$checks[] = 'invalid_update_rejected_all_original_hashes_preserved';
			}
		} else {
			$assert( false, 'unknown_smoke_phase' );
		}
		return [ 'passed' => true, 'phase' => $phase, 'checks' => $checks, 'requests' => $requests ];
	} catch ( Throwable $error ) {
		// Never include provider messages, generated source, credentials, or stack traces.
		$code = $error instanceof RuntimeException && preg_match( '/^[a-z0-9_]+$/D', $error->getMessage() ) ? $error->getMessage() : 'smoke_operation_exception';
		return [ 'passed' => false, 'phase' => $phase, 'code' => $code, 'checks' => $checks, 'requests' => $requests, 'details' => $details ];
	}
};
