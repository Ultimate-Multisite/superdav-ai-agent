<?php
/**
 * Reset runtime state while retaining template-owned agent definitions.
 *
 * @package SdAiAgent\Core
 */

declare(strict_types=1);

namespace SdAiAgent\Core;

use SdAiAgent\Models\Agent;

defined( 'ABSPATH' ) || exit;

/** SD AI Agent owns its clone lifecycle, independent of template domain names. */
final class CloneInitialization {

	/**
	 * Declare cloneable configuration without requiring the plugin's runtime container.
	 *
	 * @param int $site_id Source site ID.
	 * @return list<string> Source definition table names.
	 */
	public static function definition_tables( int $site_id ): array {
		switch_to_blog( $site_id );
		try {
			return array(
				Database::agents_table_name(),
				Database::conversation_templates_table_name(),
				Database::custom_tools_table_name(),
				Database::skills_table_name(),
			);
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Initialize a freshly copied site in the destination blog context.
	 *
	 * @param array<string, mixed> $payload Source and destination blog IDs.
	 * @return true|\WP_Error Initialization result.
	 */
	public static function initialize( array $payload ): bool|\WP_Error {
		$site_id = (int) ( $payload['site_id'] ?? 0 );
		if ( $site_id <= 1 || $site_id !== get_current_blog_id() || $site_id === (int) ( $payload['from_site_id'] ?? 0 ) ) {
			return new \WP_Error( 'sd_ai_agent_clone_context', 'Clone initialization requires the destination site context.' );
		}

		$settings        = get_option( Settings::OPTION_NAME, null );
		$has_definitions = true;
		if ( ! is_array( $settings ) ) {
			global $wpdb;
			$suppressed = $wpdb->suppress_errors();
			$has_agents = $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', Database::agents_table_name() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->suppress_errors( $suppressed );
			$has_definitions = (bool) $has_agents;
			$settings        = ( new Settings() )->get_defaults();
		}

		global $wpdb;
		$prefix = $wpdb->prefix . 'sd_ai_agent_';
		// Definitions are configuration; every other plugin table is tenant runtime state.
		$retained = array( 'agents', 'conversation_templates', 'custom_tools', 'skills' );
		$tables   = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! is_array( $tables ) || self::has_database_error() ) {
			return new \WP_Error( 'sd_ai_agent_clone_tables', 'Could not inspect copied SD AI runtime tables.' );
		}
		// Registry-backed names also cover temporary tables used by WordPress multisite tests.
		foreach ( array( Database::class, \SdAiAgent\Knowledge\KnowledgeDatabase::class, \SdAiAgent\REST\WebhookDatabase::class ) as $registry ) {
			foreach ( get_class_methods( $registry ) as $method ) {
				if ( ! str_ends_with( $method, 'table_name' ) ) {
					continue;
				}
				$table      = $registry::$method();
				$suppressed = $wpdb->suppress_errors();
				$exists     = $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->suppress_errors( $suppressed );
				if ( $exists ) {
					$tables[] = $table;
				}
			}
		}
		$seen_tables = array();
		foreach ( $tables as $table ) {
			if ( ! is_string( $table ) ) {
				return new \WP_Error( 'sd_ai_agent_clone_tables', 'An SD AI table registry entry is invalid.' );
			}
			if ( isset( $seen_tables[ $table ] ) || ! str_starts_with( $table, $prefix ) || in_array( substr( $table, strlen( $prefix ) ), $retained, true ) ) {
				continue;
			}
			$seen_tables[ $table ] = true;
			if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return new \WP_Error( 'sd_ai_agent_clone_cleanup', 'Could not clear copied SD AI runtime data.' );
			}
		}

		$connector_options = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'connectors_ai_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! is_array( $connector_options ) || self::has_database_error() ) {
			return new \WP_Error( 'sd_ai_agent_clone_connectors', 'Could not inspect copied AI connector credentials.' );
		}
		foreach ( array_merge( self::runtime_options(), $connector_options ) as $name ) {
			delete_option( $name );
			if ( false !== get_option( $name, false ) ) {
				return new \WP_Error( 'sd_ai_agent_clone_credentials', 'Could not remove copied SD AI identity or runtime state.' );
			}
		}

		if ( ! $has_definitions ) {
			$plugins = get_option( 'active_plugins', array() );
			if ( is_array( $plugins ) && in_array( 'superdav-ai-agent/superdav-ai-agent.php', $plugins, true ) ) {
				return new \WP_Error( 'sd_ai_agent_clone_agent', 'The template AI plugin definitions were not copied.' );
			}
			return true; // Sanitation is still required; unrelated templates need no SD AI setup.
		}

		$settings['onboarding_complete'] = false;
		update_option( Settings::OPTION_NAME, $settings, false );
		if ( $settings !== get_option( Settings::OPTION_NAME ) ) {
			return new \WP_Error( 'sd_ai_agent_clone_settings', 'Could not reset SD AI onboarding state.' );
		}

		// Runtime tables may have been excluded by the cloner. A copied version is not proof of schema.
		$onboarding_agent = Agent::get_by_slug( Agent::ONBOARDING_AGENT_SLUG );
		if ( ! $onboarding_agent ) {
			return new \WP_Error( 'sd_ai_agent_clone_agent', 'The template Setup Assistant was not copied. Refusing to replace it with a generic agent.' );
		}
		delete_option( Database::DB_VERSION_OPTION );
		Database::install();
		if ( Database::DB_VERSION !== get_option( Database::DB_VERSION_OPTION ) ) {
			return new \WP_Error( 'sd_ai_agent_clone_schema', 'Could not initialize the SD AI schema.' );
		}
		$configuration = get_option( 'sd_ai_agent_clone_configuration', array() );
		$configuration = is_array( $configuration ) ? $configuration : array();
		if ( ! empty( $configuration['starter_home'] ) ) {
			self::mark_starter_home();
		}
		$managed_source     = 'sd-ai-agent-cloud' === ( $settings['default_provider'] ?? '' )
			|| 'sd-ai-agent-cloud' === $onboarding_agent->provider_id;
		$managed_connection = array_key_exists( 'managed_connection', $configuration )
			? (bool) $configuration['managed_connection'] : $managed_source;
		if ( $managed_connection ) {
			$result = ( new SuperdavSiteConnectionService() )->ensure_site_token();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Read mutable query diagnostics rather than a previous query's state.
	 *
	 * @phpstan-impure
	 */
	private static function has_database_error(): bool {
		global $wpdb;
		return '' !== $wpdb->last_error;
	}

	/**
	 * Identity, credentials and onboarding progress must never cross a clone boundary.
	 *
	 * @return list<string> Destination options to remove.
	 */
	private static function runtime_options(): array {
		return array(
			'connectors_ai_sd_ai_agent_cloud_api_key',
			'sd_ai_agent_ability_usage',
			'sd_ai_agent_bootstrap_session_id',
			'sd_ai_agent_cloud_connection_metadata',
			'sd_ai_agent_ga_credentials',
			'sd_ai_agent_gsc_credentials',
			'sd_ai_agent_google_calendar_credentials',
			'sd_ai_agent_invalid_default_model_notice',
			'sd_ai_agent_model_health',
			'sd_ai_agent_onboarding_complete',
			'sd_ai_agent_onboarding_context',
			'sd_ai_agent_onboarding_scan',
			'sd_ai_agent_onboarding_triggered',
			'sd_ai_agent_site_installation_id',
			'sd_ai_agent_sms_provider',
			'sd_ai_agent_telegram_provider',
			'sd_ai_agent_theme_builder_session_id',
			'sd_ai_agent_theme_builder_started',
			'sd_ai_agent_whatsapp_provider',
			'ultimate_ai_connector_api_key',
			'ultimate_ai_connector_connected',
			'ultimate_ai_connector_endpoint_url',
		);
	}

	/** Mark only the configured static front page as unchanged starter content. */
	private static function mark_starter_home(): void {
		$post = get_post( (int) get_option( 'page_on_front' ) );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
			return;
		}
		$payload = wp_json_encode(
			array(
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
			)
			);
		if ( is_string( $payload ) ) {
			update_post_meta( $post->ID, '_sd_ai_agent_cloned_starter_fingerprint', hash( 'sha256', $payload ) );
		}
	}
}
