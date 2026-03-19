<?php

declare(strict_types=1);
/**
 * REST API controller for the AI Agent.
 *
 * Uses an async job + polling pattern so that long-running LLM inference
 * does not block the browser->nginx connection.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Abilities\GoogleAnalyticsAbilities;
use GratisAiAgent\Automations\AutomationLogs;
use GratisAiAgent\Automations\AutomationRunner;
use GratisAiAgent\Automations\Automations;
use GratisAiAgent\Automations\EventAutomations;
use GratisAiAgent\Automations\EventTriggerRegistry;
use GratisAiAgent\Automations\NotificationDispatcher;
use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\BudgetManager;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\Export;
use GratisAiAgent\Core\RolePermissions;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Knowledge\Knowledge;
use GratisAiAgent\Knowledge\KnowledgeDatabase;
use GratisAiAgent\Models\ChangesLog;
use GratisAiAgent\Models\ConversationTemplate;
use GratisAiAgent\Models\Memory;
use GratisAiAgent\Models\Agent;
use GratisAiAgent\Models\Skill;
use GratisAiAgent\Core\FreshInstallDetector;
use GratisAiAgent\Tools\CustomToolExecutor;
use GratisAiAgent\REST\SseStreamer;
use GratisAiAgent\REST\WebhookDatabase;
use GratisAiAgent\Tools\CustomTools;
use GratisAiAgent\Tools\ToolProfiles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestController {

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Transient prefix for job data.
	 */
	const JOB_PREFIX = 'gratis_ai_agent_job_';

	/**
	 * How long job data persists (seconds).
	 */
	const JOB_TTL = 600;

	/** @var Settings Injected settings dependency. */
	private Settings $settings;

	/** @var Database Injected database dependency. */
	private Database $database;

	/**
	 * Constructor — accepts injected dependencies for testability.
	 *
	 * @param Settings|null $settings  Settings service (defaults to new Settings()).
	 * @param Database|null $database  Database service (defaults to new Database()).
	 */
	public function __construct( ?Settings $settings = null, ?Database $database = null ) {
		$this->settings = $settings ?? new Settings();
		$this->database = $database ?? new Database();
	}

	/**
	 * Register REST routes.
	 *
	 * Creates a controller instance and registers instance methods as callbacks,
	 * enabling constructor injection of dependencies.
	 */
	public static function register_routes(): void {
		// MCP (Model Context Protocol) endpoint.
		McpController::register_routes();

		// Webhook API endpoints.
		WebhookController::register_routes();

		// Resale API endpoints.
		ResaleApiController::register_routes();

		$instance = new self();
		register_rest_route(
			self::NAMESPACE,
			'/stream',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_stream' ),
				'permission_callback' => array( $instance, 'check_chat_permission' ),
				'args'                => array(
					'message'            => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'abilities'          => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'system_instruction' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'max_iterations'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'provider_id'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'model_id'           => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_context'       => array(
						'required'          => false,
						'type'              => array( 'object', 'string' ),
						'default'           => array(),
						'sanitize_callback' => array( __CLASS__, 'sanitize_page_context' ),
					),
					'agent_id'           => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'attachments'        => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'name'     => array( 'type' => 'string' ),
								'type'     => array( 'type' => 'string' ),
								'data_url' => array( 'type' => 'string' ),
								'is_image' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_run' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'message'            => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'history'            => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'abilities'          => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'system_instruction' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'max_iterations'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'session_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'provider_id'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'model_id'           => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_context'       => array(
						'required'          => false,
						'type'              => array( 'object', 'string' ),
						'default'           => array(),
						'sanitize_callback' => array( __CLASS__, 'sanitize_page_context' ),
					),
					'agent_id'           => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_job_status' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/process',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_process' ),
				'permission_callback' => array( $instance, 'check_process_permission' ),
				'args'                => array(
					'job_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/abilities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_abilities' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Abilities Explorer endpoint — richer data for the admin explorer page.
		register_rest_route(
			self::NAMESPACE,
			'/abilities/explorer',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_abilities_explorer' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Providers endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/providers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_providers' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Alerts endpoint — proactive issues surfaced as a badge count on the FAB.
		register_rest_route(
			self::NAMESPACE,
			'/alerts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_alerts' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Site builder endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/site-builder/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_site_builder_start' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/site-builder/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_site_builder_status' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// WooCommerce store status endpoint — returns detection result and basic stats.
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_woocommerce_status' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Settings endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_settings' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_update_settings' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
			)
		);

		// Claude Max token endpoint (credential — stored separately, never returned in GET /settings).
		register_rest_route(
			self::NAMESPACE,
			'/settings/claude-max-token',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_set_claude_max_token' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Direct provider API key endpoint (credential — never returned in GET /settings).
		register_rest_route(
			self::NAMESPACE,
			'/settings/provider-key',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_set_provider_key' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'api_key'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Direct provider API key test endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/provider-key/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_test_provider_key' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'api_key'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Role permissions endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/role-permissions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_role_permissions' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_update_role_permissions' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'permissions' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		// Role permissions — available roles list.
		register_rest_route(
			self::NAMESPACE,
			'/role-permissions/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_roles' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
			)
		);

		// Fresh install detection endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/fresh-install',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_fresh_install_status' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);

		// Google Analytics credentials endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/settings/google-analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_get_ga_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_set_ga_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'property_id'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'service_account_json' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'handle_clear_ga_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);

		// Google Search Console credentials endpoint (credential — never returned in GET /settings).
		register_rest_route(
			self::NAMESPACE,
			'/settings/gsc-credentials',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_set_gsc_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'handle_delete_gsc_credentials' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);

		// Memory endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/memory',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/memory/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id'       => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'category' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Skills endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_skills' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_skill' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'slug'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'name'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'content'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_skill' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id'          => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'name'        => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'content'     => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
						'enabled'     => array(
							'required' => false,
							'type'     => 'boolean',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_skill' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_reset_skill' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Agents endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/agents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_agents' ),
					'permission_callback' => array( $instance, 'check_chat_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_agent' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'slug'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'name'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'system_prompt'  => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'provider_id'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_id'       => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tool_profile'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'temperature'    => array(
							'required' => false,
							'type'     => 'number',
						),
						'max_iterations' => array(
							'required' => false,
							'type'     => 'integer',
						),
						'greeting'       => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'avatar_icon'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/agents/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_agent' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_agent' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id'             => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'name'           => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'system_prompt'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'provider_id'    => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_id'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'tool_profile'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'temperature'    => array(
							'required' => false,
							'type'     => 'number',
						),
						'max_iterations' => array(
							'required' => false,
							'type'     => 'integer',
						),
						'greeting'       => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'avatar_icon'    => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'enabled'        => array(
							'required' => false,
							'type'     => 'boolean',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_agent' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Sessions endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_sessions' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'status' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => 'active',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'folder' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'search' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'pinned' => array(
							'required' => false,
							'type'     => 'boolean',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_session' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'title'       => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'provider_id' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model_id'    => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'agent_id'    => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/folders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_folders' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_bulk_sessions' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'ids'    => array(
						'required' => true,
						'type'     => 'array',
					),
					'action' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'folder' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/trash',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $instance, 'handle_empty_trash' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_session' ),
					'permission_callback' => array( $instance, 'check_session_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_session' ),
					'permission_callback' => array( $instance, 'check_session_permission' ),
					'args'                => array(
						'id'     => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'title'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'pinned' => array(
							'required' => false,
							'type'     => 'boolean',
						),
						'folder' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_session' ),
					'permission_callback' => array( $instance, 'check_session_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Usage endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/usage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_get_usage' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'period'     => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'start_date' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Budget status endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/budget',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $instance, 'handle_get_budget' ],
				'permission_callback' => [ $instance, 'check_permission' ],
			]
		);

		// Export endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_export_session' ),
				'permission_callback' => array( $instance, 'check_session_permission' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'format' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'json',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Import endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_import_session' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Shared sessions list endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/shared',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_shared_sessions' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Share / unshare a session.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/share',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_share_session' ),
					'permission_callback' => array( $instance, 'check_session_owner_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_unshare_session' ),
					'permission_callback' => array( $instance, 'check_session_owner_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Memory forget endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/memory/forget',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_forget_memory' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'topic' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Knowledge endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_collections' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_collection' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'name'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'slug'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'description'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'auto_index'    => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						),
						'source_config' => array(
							'required' => false,
							'type'     => 'object',
							'default'  => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_collection' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id'            => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'name'          => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'auto_index'    => array(
							'required' => false,
							'type'     => 'boolean',
						),
						'source_config' => array(
							'required' => false,
							'type'     => 'object',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_collection' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_sources' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)/index',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_index_collection' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_knowledge_upload' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/sources/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $instance, 'handle_delete_source' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_knowledge_search' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'q'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'collection' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_knowledge_stats' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// Tool confirmation endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_confirm_tool' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'always_allow' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_reject_tool' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// ─── Custom Tools endpoints ─────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/custom-tools',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_custom_tools' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_custom_tool' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'name'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'         => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'slug'         => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'description'  => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'config'       => array(
							'required' => false,
							'type'     => 'object',
							'default'  => array(),
						),
						'input_schema' => array(
							'required' => false,
							'type'     => 'object',
							'default'  => array(),
						),
						'enabled'      => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/custom-tools/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_custom_tool' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_custom_tool' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/custom-tools/(?P<id>\d+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_test_custom_tool' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'input' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
				),
			)
		);

		// ─── Tool Profiles endpoints ────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/tool-profiles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_tool_profiles' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_save_tool_profile' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'slug'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'name'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'tool_names'  => array(
							'required' => false,
							'type'     => 'array',
							'default'  => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tool-profiles/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $instance, 'handle_delete_tool_profile' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		// ─── Automations endpoints ──────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/automations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_automations' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_automation' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'name'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'prompt'   => array(
							'required' => true,
							'type'     => 'string',
						),
						'schedule' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => 'daily',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_automation' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_automation' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_run_automation' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_automation_logs' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automation-templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_automation_templates' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// ─── Event Automations endpoints ────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/event-automations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_event_automations' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_event_automation' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'name'            => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'hook_name'       => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'prompt_template' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/event-automations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_event_automation' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_event_automation' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/event-triggers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_event_triggers' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automation-logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_all_logs' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		// ─── Conversation Templates endpoints ────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/conversation-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_conversation_templates' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_conversation_template' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'name'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'prompt' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/conversation-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_conversation_template' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_conversation_template' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/test-notification',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_test_notification' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'type'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'webhook_url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// ─── Changes log endpoints ───────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/changes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_changes' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'session_id'  => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'object_type' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'reverted'    => array(
						'required' => false,
						'type'     => 'boolean',
					),
					'per_page'    => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 50,
					),
					'page'        => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 1,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_get_change' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_change' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/(?P<id>\d+)/diff',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_get_change_diff' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/(?P<id>\d+)/revert',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_revert_change' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_export_changes' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// Plugin download endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/modified-plugins',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_list_modified_plugins' ),
				'permission_callback' => array( $instance, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/download-plugin/(?P<slug>[a-z0-9\-_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $instance, 'handle_download_plugin' ),
				'permission_callback' => array( $instance, 'check_download_permission' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Permission check — admin only (for admin-only endpoints).
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sanitize the page_context parameter.
	 *
	 * Accepts either an object (associative array) or a string and always
	 * returns an associative array.  The JS store may send a string when the
	 * screen-meta entry point provides context as a pre-formatted string.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return array<string, mixed> Normalised page context.
	 */
	public static function sanitize_page_context( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && $value !== '' ) {
			return array( 'summary' => sanitize_textarea_field( $value ) );
		}

		return array();
	}

	/**
	 * Permission check for chat endpoints (stream, run, process).
	 *
	 * Allows access based on role-based permissions configuration.
	 * Administrators always have access.
	 *
	 * @return bool|WP_Error
	 */
	public function check_chat_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to use the AI chat.', 'gratis-ai-agent' ),
				array( 'status' => 401 )
			);
		}

		if ( ! RolePermissions::current_user_has_chat_access() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Your user role does not have permission to access the AI chat.', 'gratis-ai-agent' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check for session-specific endpoints.
	 *
	 * Verifies chat access + (session ownership OR session is shared with all admins).
	 * Destructive operations (delete, trash, archive) additionally require ownership.
	 */
	public function check_session_permission( WP_REST_Request $request ): bool {
		if ( ! RolePermissions::current_user_has_chat_access() ) {
			return false;
		}

		$session_id = absint( $request->get_param( 'id' ) );
		$session    = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return false;
		}

		$is_owner = (int) $session->user_id === get_current_user_id();

		if ( $is_owner ) {
			return true;
		}

		// Non-owners may access shared sessions (read + continue), but not delete/trash/archive.
		$method = $request->get_method();
		if ( 'DELETE' === $method ) {
			return false;
		}

		// For PATCH (update), only allow title/pinned/folder changes by non-owners on shared sessions.
		// Status changes (archive/trash) are owner-only.
		if ( 'PATCH' === $method ) {
			$status = $request->get_param( 'status' );
			if ( ! empty( $status ) ) {
				return false;
			}
		}

		// Allow if the session is shared.
		$shared = Database::get_shared_session( $session_id );
		return $shared !== null;
	}

	/**
	 * Permission check for share/unshare endpoints — owner only.
	 */
	public function check_session_owner_permission( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$session_id = absint( $request->get_param( 'id' ) );
		$session    = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return false;
		}

		return (int) $session->user_id === get_current_user_id();
	}

	/**
	 * Permission check for the internal /process endpoint.
	 *
	 * Validates a one-time token stored in the job transient instead of
	 * requiring cookie-based auth (the loopback request has no session).
	 */
	public function check_process_permission( WP_REST_Request $request ): bool {
		$job_id = $request->get_param( 'job_id' );
		$token  = $request->get_param( 'token' );

		if ( empty( $job_id ) || empty( $token ) ) {
			return false;
		}

		$job = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || empty( $job['token'] ) ) {
			return false;
		}

		return hash_equals( $job['token'], $token );
	}

	/**
	 * Handle the /run endpoint.
	 *
	 * Creates a job, spawns a background worker, and returns immediately.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_run( WP_REST_Request $request ) {
		$job_id = wp_generate_uuid4();
		$token  = wp_generate_password( 40, false );

		$job = array(
			'status'  => 'processing',
			'token'   => $token,
			'user_id' => get_current_user_id(),
			'params'  => array(
				'message'            => $request->get_param( 'message' ),
				'history'            => $request->get_param( 'history' ),
				'abilities'          => $request->get_param( 'abilities' ),
				'system_instruction' => $request->get_param( 'system_instruction' ),
				'max_iterations'     => $request->get_param( 'max_iterations' ),
				'session_id'         => $request->get_param( 'session_id' ),
				'provider_id'        => $request->get_param( 'provider_id' ),
				'model_id'           => $request->get_param( 'model_id' ),
				'page_context'       => $request->get_param( 'page_context' ),
				'agent_id'           => $request->get_param( 'agent_id' ),
			),
		);

		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		// Spawn background worker via non-blocking loopback.
		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => (string) wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'   => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return new WP_REST_Response(
			array(
				'job_id' => $job_id,
				'status' => 'processing',
			),
			202
		);
	}

	/**
	 * Handle the /job/{id} polling endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_job_status( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( false === $job || ! is_array( $job ) ) {
			return new WP_Error(
				'gratis_ai_agent_job_not_found',
				__( 'Job not found or expired.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		$response = array( 'status' => $job['status'] );

		if ( 'awaiting_confirmation' === $job['status'] && isset( $job['pending_tools'] ) ) {
			$response['pending_tools'] = $job['pending_tools'];
			return new WP_REST_Response( $response, 200 );
		}

		if ( 'complete' === $job['status'] && isset( $job['result'] ) ) {
			$response['reply']           = $job['result']['reply'] ?? '';
			$response['history']         = $job['result']['history'] ?? array();
			$response['tool_calls']      = $job['result']['tool_calls'] ?? array();
			$response['session_id']      = $job['result']['session_id'] ?? null;
			$response['token_usage']     = $job['result']['token_usage'] ?? array(
				'prompt'     => 0,
				'completion' => 0,
			);
			$response['model_id']        = $job['result']['model_id'] ?? ( $job['params']['model_id'] ?? '' );
			$response['iterations_used'] = $job['result']['iterations_used'] ?? 0;

			// Include generated title if one was produced.
			if ( isset( $job['result']['generated_title'] ) ) {
				$response['generated_title'] = $job['result']['generated_title'];
			}

			// Compute cost estimate from token usage and model.
			$model                     = $response['model_id'];
			$tokens                    = $response['token_usage'];
			$response['cost_estimate'] = CostCalculator::calculate_cost(
				$model,
				(int) ( $tokens['prompt'] ?? 0 ),
				(int) ( $tokens['completion'] ?? 0 )
			);

			// Clean up — result has been delivered.
			delete_transient( self::JOB_PREFIX . $job_id );
		}

		if ( 'error' === $job['status'] && isset( $job['error'] ) ) {
			$response['message'] = $job['error'];

			// Clean up.
			delete_transient( self::JOB_PREFIX . $job_id );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle POST /job/{id}/confirm — user approves a pending tool call.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_confirm_tool( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || 'awaiting_confirmation' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_job',
				__( 'Job not found or not awaiting confirmation.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		if ( ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'gratis_ai_agent_forbidden', __( 'Not authorized.', 'gratis-ai-agent' ), array( 'status' => 403 ) );
		}

		// "Always allow" — update tool_permissions to auto.
		if ( $request->get_param( 'always_allow' ) && ! empty( $job['pending_tools'] ) ) {
			$settings = $this->settings->get();
			$perms    = $settings['tool_permissions'] ?? array();
			foreach ( $job['pending_tools'] as $tool ) {
				$perms[ $tool['name'] ] = 'auto';
			}
			$this->settings->update( array( 'tool_permissions' => $perms ) );
		}

		return $this->resume_job( $job_id, $job, 'confirm' );
	}

	/**
	 * Handle POST /job/{id}/reject — user denies a pending tool call.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reject_tool( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || 'awaiting_confirmation' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_job',
				__( 'Job not found or not awaiting confirmation.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		if ( ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'gratis_ai_agent_forbidden', __( 'Not authorized.', 'gratis-ai-agent' ), array( 'status' => 403 ) );
		}

		return $this->resume_job( $job_id, $job, 'reject' );
	}

	/**
	 * Resume a paused job after confirmation or rejection.
	 *
	 * @param string               $job_id Job identifier.
	 * @param array<string, mixed> $job    Job transient data.
	 * @param string               $action 'confirm' or 'reject'.
	 * @return WP_REST_Response
	 */
	private static function resume_job( string $job_id, array $job, string $action ): WP_REST_Response {
		$token = wp_generate_password( 40, false );

		$job['status'] = 'processing';
		$job['token']  = $token;
		$job['resume'] = $action;

		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		// Spawn background worker.
		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => (string) wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'   => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return new WP_REST_Response(
			array(
				'status' => 'processing',
				'job_id' => $job_id,
			),
			200
		);
	}

	/**
	 * Handle the internal /process endpoint (background worker).
	 *
	 * Runs the Agent_Loop and stores the result in the job transient.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_process( WP_REST_Request $request ): WP_REST_Response {
		ignore_user_abort( true );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Agent loops need extended execution time.
		set_time_limit( 600 );

		$job_id = $request->get_param( 'job_id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || empty( $job['params'] ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		// Restore the user context — the loopback request has no cookies,
		// but the AI Client needs a user for provider auth binding.
		if ( ! empty( $job['user_id'] ) ) {
			wp_set_current_user( $job['user_id'] );
		}

		$params     = $job['params'];
		$session_id = ! empty( $params['session_id'] ) ? (int) $params['session_id'] : 0;

		// Load history from session if session_id is provided.
		$history = array();
		if ( $session_id ) {
			$session = $this->database->get_session( $session_id );
			if ( $session ) {
				$session_messages = json_decode( $session->messages, true ) ?: array();
				if ( ! empty( $session_messages ) ) {
					try {
						$history = AgentLoop::deserialize_history( $session_messages );
					} catch ( \Exception $e ) {
						$history = array();
					}
				}
			}
		} elseif ( ! empty( $params['history'] ) && is_array( $params['history'] ) ) {
			try {
				$history = AgentLoop::deserialize_history( $params['history'] );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Invalid conversation history format.', 'gratis-ai-agent' );
				unset( $job['token'] );
				set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
				return new WP_REST_Response( array( 'ok' => false ), 200 );
			}
		}

		$options = array(
			'max_iterations' => $params['max_iterations'] ?? 10,
		);

		if ( ! empty( $params['system_instruction'] ) ) {
			$options['system_instruction'] = $params['system_instruction'];
		}

		if ( ! empty( $params['provider_id'] ) ) {
			$options['provider_id'] = $params['provider_id'];
		}

		if ( ! empty( $params['model_id'] ) ) {
			$options['model_id'] = $params['model_id'];
		}

		if ( ! empty( $params['page_context'] ) ) {
			$options['page_context'] = $params['page_context'];
		}

		// Pass session_id to AgentLoop for change attribution.
		if ( ! empty( $params['session_id'] ) ) {
			$options['session_id'] = (int) $params['session_id'];
		}

		// Apply agent overrides (agent_id takes precedence over individual params).
		if ( ! empty( $params['agent_id'] ) ) {
			$agent_options = Agent::get_loop_options( (int) $params['agent_id'] );
			$options       = array_merge( $options, $agent_options );
		}

		// Record start time for webhook duration tracking.
		$start_ms = (int) round( microtime( true ) * 1000 );

		// Check if this is a resume from a tool confirmation/rejection.
		$is_resume = ! empty( $job['resume'] );

		if ( $is_resume ) {
			$confirmed = 'confirm' === $job['resume'];
			$state     = $job['confirmation_state'] ?? array();

			try {
				$resume_history = AgentLoop::deserialize_history( $state['history'] ?? array() );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Failed to resume conversation.', 'gratis-ai-agent' );
				unset( $job['token'] );
				set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
				return new WP_REST_Response( array( 'ok' => false ), 200 );
			}

			$resume_options                  = $options;
			$resume_options['tool_call_log'] = $state['tool_call_log'] ?? array();
			$resume_options['token_usage']   = $state['token_usage'] ?? array(
				'prompt'     => 0,
				'completion' => 0,
			);

			$loop   = new AgentLoop( '', array(), $resume_history, $resume_options );
			$result = $loop->resume_after_confirmation( $confirmed, $state['iterations_remaining'] ?? 5 );
		} else {
			$loop   = new AgentLoop( $params['message'], $params['abilities'] ?? array(), $history, $options );
			$result = $loop->run();
		}

		if ( is_wp_error( $result ) ) {
			$job['status'] = 'error';
			$job['error']  = $result->get_error_message();

			// Log webhook execution failure.
			if ( ! empty( $job['webhook_id'] ) ) {
				$duration_ms = $start_ms > 0 ? (int) round( microtime( true ) * 1000 ) - $start_ms : 0;
				WebhookDatabase::log_execution(
					(int) $job['webhook_id'],
					'error',
					'',
					array(),
					0,
					0,
					$duration_ms,
					$result->get_error_message()
				);
			}
		} elseif ( ! empty( $result['awaiting_confirmation'] ) ) {
			$job['status']             = 'awaiting_confirmation';
			$job['pending_tools']      = $result['pending_tools'] ?? array();
			$job['confirmation_state'] = array(
				'history'              => $result['history'] ?? array(),
				'tool_call_log'        => $result['tool_call_log'] ?? array(),
				'token_usage'          => $result['token_usage'] ?? array(
					'prompt'     => 0,
					'completion' => 0,
				),
				'iterations_remaining' => $result['iterations_remaining'] ?? 5,
			);
			// Keep token and params for the resume flow.
			unset( $job['token'] );
			set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		} else {
			$job['status'] = 'complete';
			$job['result'] = $result;

			// Persist to session if session_id is provided.
			if ( $session_id ) {
				$job['result']['session_id'] = $session_id;

				// The full history from the loop includes existing + new messages.
				// Slice off only the new ones to append.
				$session        = $this->database->get_session( $session_id );
				$existing_count = 0;
				if ( $session ) {
					$existing_messages = json_decode( $session->messages, true ) ?: array();
					$existing_count    = count( $existing_messages );
				}

				$full_history = $result['history'] ?? array();
				$appended     = array_slice( $full_history, $existing_count );

				$this->database->append_to_session( $session_id, array_values( $appended ), $result['tool_calls'] ?? [] );

				// Persist token usage.
				$token_usage = $result['token_usage'] ?? array();
				if ( ! empty( $token_usage ) ) {
					$this->database->update_session_tokens(
						$session_id,
						$token_usage['prompt'] ?? 0,
						$token_usage['completion'] ?? 0
					);
				}

				// Log to usage tracking table.
				// Use resolved options (which include agent overrides) rather than raw params.
				$provider_id  = $options['provider_id'] ?? $params['provider_id'] ?? '';
				$model_id     = $options['model_id'] ?? $params['model_id'] ?? '';
				$prompt_t     = $token_usage['prompt'] ?? 0;
				$completion_t = $token_usage['completion'] ?? 0;

				if ( $prompt_t > 0 || $completion_t > 0 ) {
					$cost = CostCalculator::calculate_cost( $model_id, $prompt_t, $completion_t );
					$this->database->log_usage(
						array(
							'user_id'           => $job['user_id'] ?? 0,
							'session_id'        => $session_id,
							'provider_id'       => $provider_id,
							'model_id'          => $model_id,
							'prompt_tokens'     => $prompt_t,
							'completion_tokens' => $completion_t,
							'cost_usd'          => $cost,
						)
					);
				}

				// Auto-generate title from first user message if empty.
				if ( $session && empty( $session->title ) ) {
					$reply = $result['reply'] ?? '';
					$title = self::generate_session_title(
						$params['message'],
						$reply,
						$options['provider_id'] ?? $params['provider_id'] ?? '',
						$options['model_id'] ?? $params['model_id'] ?? ''
					);
					$this->database->update_session( $session_id, array( 'title' => $title ) );
					$job['result']['generated_title'] = $title;
				}
			}

			// Log webhook execution success.
			if ( ! empty( $job['webhook_id'] ) ) {
				$token_usage = $result['token_usage'] ?? array(
					'prompt'     => 0,
					'completion' => 0,
				);
				$duration_ms = $start_ms > 0 ? (int) round( microtime( true ) * 1000 ) - $start_ms : 0;
				WebhookDatabase::log_execution(
					(int) $job['webhook_id'],
					'success',
					$result['reply'] ?? '',
					$result['tool_calls'] ?? array(),
					(int) ( $token_usage['prompt'] ?? 0 ),
					(int) ( $token_usage['completion'] ?? 0 ),
					$duration_ms,
					''
				);
			}
		}

		// Clear the token — no longer needed.
		unset( $job['token'] );
		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Upload base64-encoded image attachments to the WordPress media library.
	 *
	 * Decodes each data URL, writes a temporary file, and sideloads it via
	 * media_handle_sideload() so the file is stored in the uploads directory and
	 * registered as a media attachment owned by the current user.
	 *
	 * Only image attachments (is_image === true) are uploaded; non-image files
	 * (plain text, CSV, PDF) are passed through as raw data URLs so the AI can
	 * still read their content without storing them permanently.
	 *
	 * @param array<int, array{name: string, type: string, data_url: string, is_image: bool}> $attachments Raw attachment objects from the REST request.
	 * @return array<int, array{name: string, type: string, data_url: string, is_image: bool, attachment_id?: int, url?: string}> Enriched attachment objects.
	 */
	private static function upload_attachments_to_media_library( array $attachments ): array {
		if ( empty( $attachments ) ) {
			return array();
		}

		// Ensure media-handling functions are available outside the admin context.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$processed = array();

		foreach ( $attachments as $att ) {
			$name     = sanitize_file_name( $att['name'] ?? 'upload' );
			$type     = $att['type'] ?? '';
			$data_url = $att['data_url'] ?? '';
			$is_image = ! empty( $att['is_image'] );

			// Only upload images to the media library; pass other files through.
			if ( ! $is_image || empty( $data_url ) ) {
				$processed[] = $att;
				continue;
			}

			// Decode the base64 data URL.
			if ( ! preg_match( '/^data:([^;]+);base64,(.+)$/s', $data_url, $matches ) ) {
				$processed[] = $att;
				continue;
			}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding image data URLs from user uploads, not obfuscating code.
			$decoded = base64_decode( $matches[2], true );
			if ( false === $decoded ) {
				$processed[] = $att;
				continue;
			}

			// Write to a temporary file.
			$tmp_file = wp_tempnam( $name );
			if ( ! $tmp_file ) {
				$processed[] = $att;
				continue;
			}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $tmp_file, $decoded ) ) {
				wp_delete_file( $tmp_file );
				$processed[] = $att;
				continue;
			}

			$file_array = array(
				'name'     => $name,
				'type'     => $type,
				'tmp_name' => $tmp_file,
				'error'    => '0',
				'size'     => (string) strlen( $decoded ),
			);

			$attachment_id = media_handle_sideload( $file_array, 0, null );

			wp_delete_file( $tmp_file );

			if ( is_wp_error( $attachment_id ) ) {
				// Fall back to passing the raw data URL on upload failure.
				$processed[] = $att;
				continue;
			}

			$url = wp_get_attachment_url( $attachment_id );

			$processed[] = array_merge(
				$att,
				array(
					'attachment_id' => $attachment_id,
					'url'           => ( $url ? $url : $data_url ),
				)
			);
		}

		return $processed;
	}

	/**
	 * Handle POST /stream — run the agent loop and stream tokens via SSE.
	 *
	 * This endpoint bypasses the normal WP_REST_Response system and emits
	 * a raw text/event-stream response. The agent loop runs synchronously
	 * in the same request, streaming each text token as it is produced.
	 *
	 * SSE event types emitted:
	 *   - token              {"token": "..."}
	 *   - tool_call          {"name": "...", "args": {...}}
	 *   - tool_result        {"name": "...", "result": ...}
	 *   - confirmation_required {"job_id": "...", "pending_tools": [...]}
	 *   - done               {"session_id": N, "token_usage": {...}, ...}
	 *   - error              {"code": "...", "message": "..."}
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return void This method exits after streaming; it never returns a WP_REST_Response.
	 */
	public static function handle_stream( WP_REST_Request $request ): void {
		$streamer = new SseStreamer();
		$streamer->start();

		$session_id      = absint( $request->get_param( 'session_id' ) );
		$raw_attachments = $request->get_param( 'attachments' ) ?? array();
		$attachments     = self::upload_attachments_to_media_library( (array) $raw_attachments );

		$params = array(
			'message'            => $request->get_param( 'message' ),
			'abilities'          => $request->get_param( 'abilities' ) ?? array(),
			'system_instruction' => $request->get_param( 'system_instruction' ),
			'max_iterations'     => $request->get_param( 'max_iterations' ) ?? 10,
			'provider_id'        => $request->get_param( 'provider_id' ),
			'model_id'           => $request->get_param( 'model_id' ),
			'page_context'       => $request->get_param( 'page_context' ) ?? array(),
			'agent_id'           => $request->get_param( 'agent_id' ),
			'attachments'        => $attachments,
		);

		// Load conversation history from session.
		$history = array();
		if ( $session_id ) {
			$session = Database::get_session( $session_id );
			if ( $session ) {
				$session_messages = json_decode( $session->messages, true ) ?: array();
				if ( ! empty( $session_messages ) ) {
					try {
						$history = AgentLoop::deserialize_history( $session_messages );
					} catch ( \Exception $e ) {
						$history = array();
					}
				}
			}
		}

		$options = array(
			'max_iterations' => $params['max_iterations'],
		);

		if ( ! empty( $params['system_instruction'] ) ) {
			$options['system_instruction'] = $params['system_instruction'];
		}
		if ( ! empty( $params['provider_id'] ) ) {
			$options['provider_id'] = $params['provider_id'];
		}
		if ( ! empty( $params['model_id'] ) ) {
			$options['model_id'] = $params['model_id'];
		}
		if ( ! empty( $params['page_context'] ) ) {
			$options['page_context'] = $params['page_context'];
		}

		// Apply agent overrides (agent_id takes precedence over individual params).
		if ( ! empty( $params['agent_id'] ) ) {
			$agent_options = Agent::get_loop_options( (int) $params['agent_id'] );
			$options       = array_merge( $options, $agent_options );
		}

		// Pass image attachments to AgentLoop for vision model support.
		if ( ! empty( $params['attachments'] ) ) {
			$options['attachments'] = $params['attachments'];
		}

		// Attach the SSE streamer so AgentLoop can emit tokens as they arrive.
		$options['sse_streamer'] = $streamer;

		$loop   = new AgentLoop( $params['message'], $params['abilities'], $history, $options );
		$result = $loop->run();

		if ( is_wp_error( $result ) ) {
			$streamer->send_error( $result->get_error_message(), (string) $result->get_error_code() );
			exit;
		}

		// Handle tool confirmation pause — emit a confirmation_required event
		// so the frontend can show the confirmation dialog, then the user
		// confirms/rejects via the existing /job/{id}/confirm|reject endpoints.
		if ( ! empty( $result['awaiting_confirmation'] ) ) {
			// Persist the paused state as a job transient so the confirm/reject
			// endpoints can resume it.
			$job_id = wp_generate_uuid4();
			$token  = wp_generate_password( 40, false );

			$job = array(
				'status'             => 'awaiting_confirmation',
				'token'              => $token,
				'user_id'            => get_current_user_id(),
				'pending_tools'      => $result['pending_tools'] ?? array(),
				'confirmation_state' => array(
					'history'              => $result['history'] ?? array(),
					'tool_call_log'        => $result['tool_call_log'] ?? array(),
					'token_usage'          => $result['token_usage'] ?? array(
						'prompt'     => 0,
						'completion' => 0,
					),
					'iterations_remaining' => $result['iterations_remaining'] ?? 5,
				),
				'params'             => $params,
			);

			set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

			$streamer->send_confirmation_required( $job_id, $result['pending_tools'] ?? array() );
			exit;
		}

		// Persist to session.
		$generated_title = null;
		if ( $session_id && ! empty( $result ) ) {
			$session        = Database::get_session( $session_id );
			$existing_count = 0;
			if ( $session ) {
				$existing_messages = json_decode( $session->messages, true ) ?: array();
				$existing_count    = count( $existing_messages );
			}

			$full_history = $result['history'] ?? array();
			$appended     = array_slice( $full_history, $existing_count );

			Database::append_to_session( $session_id, array_values( $appended ), $result['tool_calls'] ?? [] );

			$token_usage = $result['token_usage'] ?? array();
			if ( ! empty( $token_usage ) ) {
				Database::update_session_tokens(
					$session_id,
					$token_usage['prompt'] ?? 0,
					$token_usage['completion'] ?? 0
				);
			}

			// Log usage.
			// Use resolved options (which include agent overrides) rather than raw params.
			$prompt_t     = $token_usage['prompt'] ?? 0;
			$completion_t = $token_usage['completion'] ?? 0;
			if ( $prompt_t > 0 || $completion_t > 0 ) {
				$model_id = $options['model_id'] ?? $params['model_id'] ?? '';
				$cost     = CostCalculator::calculate_cost( $model_id, $prompt_t, $completion_t );
				Database::log_usage(
					array(
						'user_id'           => get_current_user_id(),
						'session_id'        => $session_id,
						'provider_id'       => $options['provider_id'] ?? $params['provider_id'] ?? '',
						'model_id'          => $model_id,
						'prompt_tokens'     => $prompt_t,
						'completion_tokens' => $completion_t,
						'cost_usd'          => $cost,
					)
				);
			}

			// Auto-generate title.
			$generated_title = null;
			if ( $session && empty( $session->title ) ) {
				$generated_title = self::generate_session_title(
					$params['message'],
					$result['reply'] ?? '',
					$options['provider_id'] ?? $params['provider_id'] ?? '',
					$options['model_id'] ?? $params['model_id'] ?? ''
				);
				Database::update_session( $session_id, array( 'title' => $generated_title ) );
			}
		}

		$token_usage = $result['token_usage'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
		);
		$model_id    = $result['model_id'] ?? ( $params['model_id'] ?? '' );

		$done_payload = array(
			'session_id'      => $session_id ?: null,
			'token_usage'     => $token_usage,
			'model_id'        => $model_id,
			'iterations_used' => $result['iterations_used'] ?? 0,
			'cost_estimate'   => CostCalculator::calculate_cost(
				$model_id,
				(int) ( $token_usage['prompt'] ?? 0 ),
				(int) ( $token_usage['completion'] ?? 0 )
			),
			'tool_calls'      => $result['tool_calls'] ?? array(),
		);

		if ( null !== $generated_title ) {
			$done_payload['generated_title'] = $generated_title;
		}

		$streamer->send_done( $done_payload );

		exit;
	}

	/**
	 * Handle the /abilities endpoint — list available abilities.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_abilities(): WP_REST_Response {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$abilities = wp_get_abilities();
		$list      = array();

		foreach ( $abilities as $ability ) {
			$description = $ability->get_description();

			// Truncate long descriptions for the settings UI.
			if ( strlen( $description ) > 200 ) {
				$description = substr( $description, 0, 197 ) . '...';
			}

			$list[] = array(
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $description,
				'category'    => $ability->get_category(),
			);
		}

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle the /abilities/explorer endpoint — richer ability data for the Abilities Explorer admin page.
	 *
	 * Returns full descriptions, input parameter counts, annotations (readonly/destructive/idempotent),
	 * output_schema, show_in_rest, and configuration status (whether required API keys are present).
	 *
	 * @return WP_REST_Response
	 */
	public function handle_abilities_explorer(): WP_REST_Response {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$abilities = wp_get_abilities();
		$list      = array();

		// Build a map of configured provider IDs for configuration status checks.
		$configured_providers = array();
		foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $provider_meta ) {
			$key = Settings::get_provider_key( $provider_id );
			if ( '' !== $key ) {
				$configured_providers[] = $provider_id;
			}
		}

		foreach ( $abilities as $ability ) {
			$input_schema = $ability->get_input_schema();
			$meta         = $ability->get_meta();
			$annotations  = $meta['annotations'] ?? array();

			// Count required parameters from input schema.
			$required_params = array();
			if ( ! empty( $input_schema['required'] ) && is_array( $input_schema['required'] ) ) {
				$required_params = $input_schema['required'];
			}

			$param_count = 0;
			if ( ! empty( $input_schema['properties'] ) && is_array( $input_schema['properties'] ) ) {
				$param_count = count( $input_schema['properties'] );
			}

			// Derive configuration status from ability name/category.
			// Abilities in the 'gratis-ai-agent' category are always available (no external API needed).
			// Abilities that reference a specific provider in their name may need that provider configured.
			$ability_name      = $ability->get_name();
			$is_configured     = true;
			$required_api_keys = array();

			// Check for provider-specific abilities by name pattern.
			foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $provider_meta ) {
				if ( str_contains( $ability_name, $provider_id ) ) {
					$required_api_keys[] = $provider_meta['name'] . ' API Key';
					if ( ! in_array( $provider_id, $configured_providers, true ) ) {
						$is_configured = false;
					}
				}
			}

			$list[] = array(
				'name'              => $ability_name,
				'label'             => $ability->get_label(),
				'description'       => $ability->get_description(),
				'category'          => $ability->get_category(),
				'param_count'       => $param_count,
				'required_params'   => $required_params,
				'is_configured'     => $is_configured,
				'required_api_keys' => $required_api_keys,
				'annotations'       => array(
					'readonly'    => (bool) ( $annotations['readonly'] ?? false ),
					'destructive' => (bool) ( $annotations['destructive'] ?? false ),
					'idempotent'  => (bool) ( $annotations['idempotent'] ?? false ),
				),
				'output_schema'     => $ability->get_output_schema(),
				'show_in_rest'      => (bool) ( $meta['show_in_rest'] ?? false ),
			);
		}

		// Sort by category then label for consistent display.
		usort(
			$list,
			static function ( array $a, array $b ): int {
				$cat_cmp = strcmp( $a['category'], $b['category'] );
				if ( 0 !== $cat_cmp ) {
					return $cat_cmp;
				}
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle the /providers endpoint — list registered AI providers and models.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_providers(): WP_REST_Response {
		$providers = array();

		// Direct providers (OpenAI, Anthropic, Google) — listed first, no WP SDK required.
		foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $meta ) {
			$key = Settings::get_provider_key( $provider_id );
			if ( '' === $key ) {
				continue;
			}
			$providers[] = array(
				'id'         => $provider_id,
				'name'       => $meta['name'],
				'type'       => 'direct',
				'configured' => true,
				'models'     => $meta['models'],
			);
		}

		// Collect IDs already added to avoid duplicates from the WP SDK registry.
		$added_ids = array_column( $providers, 'id' );

		// WP SDK providers (AI Experiments plugin, OpenAI-compatible connector, etc.).
		if ( class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			$registry     = null;
			$provider_ids = array();
			try {
				$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
				$provider_ids = $registry->getRegisteredProviderIds();
			} catch ( \Throwable $e ) {
				$provider_ids = array();
			}

			// Ensure credentials are loaded (same logic the agent loop uses).
			AgentLoop::ensure_provider_credentials_static();

			foreach ( $provider_ids as $provider_id ) {
				// Skip if already added as a direct provider.
				if ( in_array( $provider_id, $added_ids, true ) ) {
					continue;
				}

				if ( null === $registry ) {
					continue;
				}

				try {
					$class = $registry->getProviderClassName( $provider_id );

					// Only include providers that have authentication set.
					$auth = $registry->getProviderRequestAuthentication( $provider_id );
					if ( null === $auth ) {
						continue;
					}

					$metadata = $class::metadata();
					$models   = array();

					// For the OpenAI-compatible connector, fetch models directly
					// from the endpoint rather than going through the SDK model
					// directory (which can fail due to SDK transporter issues).
					if ( 'ai-provider-for-any-openai-compatible' === $provider_id
						&& function_exists( 'OpenAiCompatibleConnector\\rest_list_models' )
					) {
						$fake_request = new WP_REST_Request( 'GET' );
						$result       = \OpenAiCompatibleConnector\rest_list_models( $fake_request );
						if ( ! is_wp_error( $result ) ) {
							$data = $result instanceof WP_REST_Response ? $result->get_data() : $result;
							if ( is_array( $data ) ) {
								$models = $data;
							}
						}
					} else {
						try {
							$directory      = $class::modelMetadataDirectory();
							$model_metadata = $directory->listModelMetadata();

							foreach ( $model_metadata as $model_meta ) {
								$models[] = array(
									'id'   => $model_meta->getId(),
									'name' => $model_meta->getName(),
								);
							}
						} catch ( \Throwable $e ) {
							// Model listing failed — still include the provider.
						}
					}

					$providers[] = array(
						'id'         => $provider_id,
						'name'       => $metadata->getName(),
						'type'       => (string) $metadata->getType(),
						'configured' => true,
						'models'     => $models,
					);
				} catch ( \Throwable $e ) {
					continue;
				}
			}
		}

		return new WP_REST_Response( $providers, 200 );
	}

	/**
	 * Handle GET /woocommerce/status — detect WooCommerce and return store info.
	 *
	 * Returns whether WooCommerce is active, the version, product/order counts,
	 * and currency. Used by the onboarding wizard to conditionally show the
	 * WooCommerce step and offer AI product creation.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_woocommerce_status(): WP_REST_Response {
		$active = class_exists( 'WooCommerce' );

		if ( ! $active ) {
			return new WP_REST_Response(
				array(
					'active'  => false,
					'version' => null,
				),
				200
			);
		}

		// Product counts.
		$product_counts     = wp_count_posts( 'product' );
		$published_products = $product_counts ? (int) ( $product_counts->publish ?? 0 ) : 0;
		$total_products     = 0;
		if ( $product_counts ) {
			foreach ( (array) $product_counts as $count ) {
				$total_products += (int) $count;
			}
		}

		// Order counts.
		$pending_orders    = 0;
		$processing_orders = 0;
		if ( function_exists( 'wc_orders_count' ) ) {
			$pending_orders    = (int) wc_orders_count( 'pending' );
			$processing_orders = (int) wc_orders_count( 'processing' );
		}

		return new WP_REST_Response(
			array(
				'active'             => true,
				'version'            => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
				'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
				'published_products' => $published_products,
				'total_products'     => $total_products,
				'pending_orders'     => $pending_orders,
				'processing_orders'  => $processing_orders,
				'shop_url'           => function_exists( 'wc_get_page_id' ) ? ( get_permalink( wc_get_page_id( 'shop' ) ) ?: '' ) : '',
			),
			200
		);
	}

	/**
	 * Handle GET /sessions — list sessions for current user.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_sessions( WP_REST_Request $request ): WP_REST_Response {
		$filters = array();

		if ( $request->has_param( 'status' ) ) {
			$filters['status'] = $request->get_param( 'status' );
		}
		if ( $request->has_param( 'folder' ) ) {
			$filters['folder'] = $request->get_param( 'folder' );
		}
		if ( $request->has_param( 'search' ) ) {
			$filters['search'] = $request->get_param( 'search' );
		}
		if ( $request->has_param( 'pinned' ) ) {
			$filters['pinned'] = $request->get_param( 'pinned' );
		}

		$sessions = $this->database->list_sessions( get_current_user_id(), $filters );

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Handle GET /sessions/folders — list folders for current user.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_folders(): WP_REST_Response {
		$folders = $this->database->list_folders( get_current_user_id() );

		return new WP_REST_Response( $folders, 200 );
	}

	/**
	 * Handle POST /sessions/bulk — bulk update sessions.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_bulk_sessions( WP_REST_Request $request ) {
		$ids    = array_map( 'absint', $request->get_param( 'ids' ) );
		$action = $request->get_param( 'action' );

		$data = array();
		switch ( $action ) {
			case 'archive':
				$data['status'] = 'archived';
				break;
			case 'restore':
				$data['status'] = 'active';
				break;
			case 'trash':
				$data['status'] = 'trash';
				break;
			case 'pin':
				$data['pinned'] = 1;
				break;
			case 'unpin':
				$data['pinned'] = 0;
				break;
			case 'move':
				$data['folder'] = sanitize_text_field( $request->get_param( 'folder' ) ?? '' );
				break;
			default:
				return new WP_Error( 'gratis_ai_agent_invalid_action', __( 'Invalid bulk action.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$count = $this->database->bulk_update_sessions( $ids, get_current_user_id(), $data );

		return new WP_REST_Response( array( 'updated' => $count ), 200 );
	}

	/**
	 * Handle DELETE /sessions/trash — empty trash for current user.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_empty_trash(): WP_REST_Response {
		$count = $this->database->empty_trash( get_current_user_id() );

		return new WP_REST_Response( array( 'deleted' => $count ), 200 );
	}

	/**
	 * Handle GET /sessions/shared — list all sessions shared with admins.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_shared_sessions(): WP_REST_Response {
		$sessions = Database::list_shared_sessions();

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Handle POST /sessions/{id}/share — share a session with all admins.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_share_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$success    = Database::share_session( $session_id, get_current_user_id() );

		if ( ! $success ) {
			return new WP_Error(
				'gratis_ai_agent_share_failed',
				__( 'Failed to share session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'shared' => true ), 200 );
	}

	/**
	 * Handle DELETE /sessions/{id}/share — unshare a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_unshare_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$success    = Database::unshare_session( $session_id );

		if ( ! $success ) {
			return new WP_Error(
				'gratis_ai_agent_unshare_failed',
				__( 'Failed to unshare session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'shared' => false ), 200 );
	}

	/**
	 * Handle GET /sessions/{id} — get full session with messages.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$session    = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error(
				'gratis_ai_agent_session_not_found',
				__( 'Session not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		$shared    = Database::get_shared_session( (int) $session->id );
		$is_shared = $shared !== null;

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => json_decode( $session->messages, true ) ?: array(),
				'tool_calls'  => json_decode( $session->tool_calls, true ) ?: array(),
				'token_usage' => array(
					'prompt'     => (int) ( $session->prompt_tokens ?? 0 ),
					'completion' => (int) ( $session->completion_tokens ?? 0 ),
				),
				'is_shared'   => $is_shared,
				'shared_by'   => $is_shared ? (int) $shared->shared_by : null,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			200
		);
	}

	/**
	 * Handle POST /sessions — create a new session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_session( WP_REST_Request $request ) {
		$provider_id = $request->get_param( 'provider_id' ) ?? '';
		$model_id    = $request->get_param( 'model_id' ) ?? '';

		// If an agent is selected, resolve its provider/model overrides so the
		// session is stored with the agent's effective provider/model rather than
		// the caller's pre-agent selection.
		$agent_id = (int) ( $request->get_param( 'agent_id' ) ?? 0 );
		if ( $agent_id > 0 ) {
			$agent_options = Agent::get_loop_options( $agent_id );
			if ( ! empty( $agent_options['provider_id'] ) ) {
				$provider_id = $agent_options['provider_id'];
			}
			if ( ! empty( $agent_options['model_id'] ) ) {
				$model_id = $agent_options['model_id'];
			}
		}

		$session_id = $this->database->create_session(
			array(
				'user_id'     => get_current_user_id(),
				'title'       => $request->get_param( 'title' ),
				'provider_id' => $provider_id,
				'model_id'    => $model_id,
			)
		);

		if ( ! $session_id ) {
			return new WP_Error(
				'gratis_ai_agent_session_create_failed',
				__( 'Failed to create session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$session = $this->database->get_session( $session_id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => array(),
				'tool_calls'  => array(),
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			201
		);
	}

	/**
	 * Handle PATCH /sessions/{id} — update session fields.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );

		$data = array();
		if ( $request->has_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}
		if ( $request->has_param( 'status' ) ) {
			$status = $request->get_param( 'status' );
			if ( in_array( $status, array( 'active', 'archived', 'trash' ), true ) ) {
				$data['status'] = $status;
			}
		}
		if ( $request->has_param( 'pinned' ) ) {
			$data['pinned'] = $request->get_param( 'pinned' ) ? 1 : 0;
		}
		if ( $request->has_param( 'folder' ) ) {
			$data['folder'] = $request->get_param( 'folder' );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'gratis_ai_agent_no_data', __( 'No fields to update.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$updated = $this->database->update_session( $session_id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_session_update_failed',
				__( 'Failed to update session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$session = $this->database->get_session( $session_id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'status'      => $session->status,
				'pinned'      => (bool) (int) $session->pinned,
				'folder'      => $session->folder,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			200
		);
	}

	/**
	 * Handle DELETE /sessions/{id} — delete a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );

		$deleted = $this->database->delete_session( $session_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'gratis_ai_agent_session_delete_failed',
				__( 'Failed to delete session.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// ─── Skills ─────────────────────────────────────────────────────

	/**
	 * Handle GET /skills — list all skills.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_skills(): WP_REST_Response {
		$skills = Skill::get_all();

		$list = array_map(
			function ( $s ) {
				return array(
					'id'          => (int) $s->id,
					'slug'        => $s->slug,
					'name'        => $s->name,
					'description' => $s->description,
					'content'     => $s->content,
					'is_builtin'  => (bool) (int) $s->is_builtin,
					'enabled'     => (bool) (int) $s->enabled,
					'word_count'  => str_word_count( $s->content ),
					'created_at'  => $s->created_at,
					'updated_at'  => $s->updated_at,
				);
			},
			$skills
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /skills — create a custom skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_skill( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		$existing = Skill::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'gratis_ai_agent_skill_slug_exists',
				__( 'A skill with this slug already exists.', 'gratis-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = Skill::create(
			array(
				'slug'        => $slug,
				'name'        => $request->get_param( 'name' ),
				'description' => $request->get_param( 'description' ),
				'content'     => $request->get_param( 'content' ),
				'is_builtin'  => false,
				'enabled'     => true,
			)
		);

		if ( false === $id ) {
			return new WP_Error(
				'gratis_ai_agent_skill_create_failed',
				__( 'Failed to create skill.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $skill->id,
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
				'content'     => $skill->content,
				'is_builtin'  => false,
				'enabled'     => true,
				'word_count'  => str_word_count( $skill->content ),
				'created_at'  => $skill->created_at,
				'updated_at'  => $skill->updated_at,
			),
			201
		);
	}

	/**
	 * Handle PATCH /skills/{id} — update a skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_skill( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}
		if ( $request->has_param( 'enabled' ) ) {
			$data['enabled'] = $request->get_param( 'enabled' );
		}

		$updated = Skill::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_skill_update_failed',
				__( 'Failed to update skill.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $skill->id,
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
				'content'     => $skill->content,
				'is_builtin'  => (bool) (int) $skill->is_builtin,
				'enabled'     => (bool) (int) $skill->enabled,
				'word_count'  => str_word_count( $skill->content ),
				'created_at'  => $skill->created_at,
				'updated_at'  => $skill->updated_at,
			),
			200
		);
	}

	/**
	 * Handle DELETE /skills/{id} — delete a custom skill (refuses built-in).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_skill( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = Skill::delete( $id );

		if ( $result === 'builtin' ) {
			return new WP_Error(
				'gratis_ai_agent_skill_builtin_delete',
				__( 'Built-in skills cannot be deleted. You can disable them instead.', 'gratis-ai-agent' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $result ) {
			return new WP_Error(
				'gratis_ai_agent_skill_delete_failed',
				__( 'Failed to delete skill or skill not found.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle POST /skills/{id}/reset — reset a built-in skill to defaults.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reset_skill( WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$reset = Skill::reset_builtin( $id );

		if ( ! $reset ) {
			return new WP_Error(
				'gratis_ai_agent_skill_reset_failed',
				__( 'Failed to reset skill. Only built-in skills can be reset.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $skill->id,
				'slug'        => $skill->slug,
				'name'        => $skill->name,
				'description' => $skill->description,
				'content'     => $skill->content,
				'is_builtin'  => (bool) (int) $skill->is_builtin,
				'enabled'     => (bool) (int) $skill->enabled,
				'word_count'  => str_word_count( $skill->content ),
				'created_at'  => $skill->created_at,
				'updated_at'  => $skill->updated_at,
			),
			200
		);
	}

	// ─── Agents ──────────────────────────────────────────────────────

	/**
	 * Handle GET /agents — list all agents.
	 *
	 * Returns only public-safe fields (id, slug, name, description, avatar_icon,
	 * enabled, greeting) so that chat users cannot read system_prompt or
	 * provider/model configuration.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_agents(): WP_REST_Response {
		$agents = Agent::get_all();
		$list   = array_map(
			static function ( object $agent ): array {
				return array(
					'id'          => (int) $agent->id,
					'slug'        => $agent->slug,
					'name'        => $agent->name,
					'description' => $agent->description,
					'avatar_icon' => $agent->avatar_icon,
					'greeting'    => $agent->greeting,
					'enabled'     => (bool) $agent->enabled,
				);
			},
			$agents
		);
		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle GET /agents/{id} — get a single agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_agent( WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$agent = Agent::get( $id );

		if ( ! $agent ) {
			return new WP_Error(
				'gratis_ai_agent_agent_not_found',
				__( 'Agent not found.', 'gratis-ai-agent' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( Agent::to_array( $agent ), 200 );
	}

	/**
	 * Handle POST /agents — create a new agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_agent( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		$existing = Agent::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'gratis_ai_agent_agent_slug_exists',
				__( 'An agent with this slug already exists.', 'gratis-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = Agent::create(
			array(
				'slug'           => $slug,
				'name'           => $request->get_param( 'name' ),
				'description'    => $request->get_param( 'description' ) ?? '',
				'system_prompt'  => $request->get_param( 'system_prompt' ) ?? '',
				'provider_id'    => $request->get_param( 'provider_id' ) ?? '',
				'model_id'       => $request->get_param( 'model_id' ) ?? '',
				'tool_profile'   => $request->get_param( 'tool_profile' ) ?? '',
				'temperature'    => $request->get_param( 'temperature' ),
				'max_iterations' => $request->get_param( 'max_iterations' ),
				'greeting'       => $request->get_param( 'greeting' ) ?? '',
				'avatar_icon'    => $request->get_param( 'avatar_icon' ) ?? '',
				'enabled'        => true,
			)
		);

		if ( false === $id ) {
			return new WP_Error(
				'gratis_ai_agent_agent_create_failed',
				__( 'Failed to create agent.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$agent = Agent::get( $id );
		return new WP_REST_Response( Agent::to_array( $agent ), 201 );
	}

	/**
	 * Handle PATCH /agents/{id} — update an agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_agent( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = array();

		$fields = array(
			'name',
			'description',
			'system_prompt',
			'provider_id',
			'model_id',
			'tool_profile',
			'temperature',
			'max_iterations',
			'greeting',
			'avatar_icon',
			'enabled',
		);

		foreach ( $fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		$updated = Agent::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_agent_update_failed',
				__( 'Failed to update agent.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$agent = Agent::get( $id );
		return new WP_REST_Response( Agent::to_array( $agent ), 200 );
	}

	/**
	 * Handle DELETE /agents/{id} — delete an agent.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_agent( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = Agent::delete( $id );

		if ( ! $result ) {
			return new WP_Error(
				'gratis_ai_agent_agent_delete_failed',
				__( 'Failed to delete agent or agent not found.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// ─── Settings ────────────────────────────────────────────────────

	/**
	 * Handle GET /fresh-install — return fresh-install detection status.
	 *
	 * Returns a JSON object with:
	 *   - is_fresh_install (bool)  — true when the site qualifies as a fresh install
	 *   - has_real_posts   (bool)  — true when published posts beyond defaults exist
	 *   - has_real_pages   (bool)  — true when published pages beyond defaults exist
	 *   - is_default_theme (bool)  — true when the active theme is a WordPress default
	 *   - active_theme     (string)— stylesheet slug of the active theme
	 *   - site_builder_mode(bool)  — current value of the site_builder_mode setting
	 */
	public static function handle_fresh_install_status(): WP_REST_Response {
		$status                      = FreshInstallDetector::getStatus();
		$status['site_builder_mode'] = (bool) Settings::get( 'site_builder_mode' );

		// Auto-enable site_builder_mode when a fresh install is detected and
		// the flag has not been explicitly set by the user yet.
		if ( $status['is_fresh_install'] && ! $status['site_builder_mode'] ) {
			Settings::update( array( 'site_builder_mode' => true ) );
			$status['site_builder_mode'] = true;
		}

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Handle GET /settings.
	 */
	public function handle_get_settings(): WP_REST_Response {
		$settings = $this->settings->get();

		// Include built-in defaults so the UI can show them as placeholders.
		$settings['_defaults'] = array(
			'system_prompt'    => AgentLoop::get_default_system_prompt(),
			'greeting_message' => __( 'Send a message to start a conversation.', 'gratis-ai-agent' ),
		);

		// Indicate whether a Claude Max token is stored without exposing the token itself.
		$settings['_has_claude_max_token'] = '' !== Settings::get_claude_max_token();

		// Indicate which direct provider keys are configured (boolean per provider, no values).
		$provider_keys = array();
		foreach ( array_keys( Settings::DIRECT_PROVIDERS ) as $provider_id ) {
			$provider_keys[ $provider_id ] = '' !== Settings::get_provider_key( $provider_id );
		}
		$settings['_provider_keys'] = $provider_keys;

		// Indicate whether GSC credentials are configured (boolean + type only, no credential values).
		$gsc_creds                    = Settings::get_gsc_credentials();
		$settings['_gsc_credentials'] = array(
			'configured'       => Settings::has_gsc_credentials(),
			'type'             => $gsc_creds['type'] ?? null,
			'default_site_url' => $gsc_creds['default_site_url'] ?? null,
		);

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Handle POST /settings — partial update.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function handle_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'error' => 'No data provided.' ), 400 );
		}

		$this->settings->update( $data );

		return new WP_REST_Response( $this->settings->get(), 200 );
	}


	/**
	 * Handle GET /role-permissions — return current role permissions config.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_role_permissions(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'permissions'    => RolePermissions::get(),
				'always_allowed' => RolePermissions::ALWAYS_ALLOWED_ROLES,
			),
			200
		);
	}

	/**
	 * Handle POST /role-permissions — update role permissions config.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_role_permissions( WP_REST_Request $request ) {
		$permissions = $request->get_param( 'permissions' );

		if ( ! is_array( $permissions ) ) {
			return new WP_Error(
				'invalid_permissions',
				__( 'Invalid permissions data.', 'gratis-ai-agent' ),
				array( 'status' => 400 )
			);
		}

		$success = RolePermissions::update( $permissions );

		if ( ! $success ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to save role permissions.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'permissions'    => RolePermissions::get(),
				'always_allowed' => RolePermissions::ALWAYS_ALLOWED_ROLES,
			),
			200
		);
	}

	/**
	 * Handle GET /role-permissions/roles — return all registered WordPress roles.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_roles(): WP_REST_Response {
		return new WP_REST_Response( RolePermissions::get_all_roles(), 200 );
	}

	/**
	 * Handle POST /settings/claude-max-token — store the Claude Max OAuth token.
	 *
	 * The token is stored in a dedicated WordPress option and never returned
	 * in GET /settings to avoid leaking it through the general settings endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function handle_set_claude_max_token( WP_REST_Request $request ): WP_REST_Response {
		$token = $request->get_param( 'token' );

		// Allow clearing the token by passing an empty string.
		$token = is_string( $token ) ? trim( $token ) : '';

		$success = Settings::set_claude_max_token( $token );

		if ( ! $success && ! empty( $token ) ) {
			return new WP_REST_Response( array( 'error' => 'Failed to save token.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'        => true,
				'has_token'    => ! empty( $token ),
				'token_prefix' => ! empty( $token ) ? substr( $token, 0, 20 ) . '…' : '',
			),
			200
		);
	}

	/**
	 * Handle POST /settings/provider-key — save or clear a direct provider API key.
	 *
	 * The key is stored in a dedicated WordPress option and never returned
	 * in GET /settings to avoid leaking credentials through the general endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_provider_key( WP_REST_Request $request ): WP_REST_Response {
		$provider = (string) $request->get_param( 'provider' );
		$api_key  = (string) $request->get_param( 'api_key' );
		$api_key  = trim( $api_key );

		if ( ! array_key_exists( $provider, Settings::DIRECT_PROVIDERS ) ) {
			return new WP_REST_Response( array( 'error' => 'Unknown provider.' ), 400 );
		}

		$success = Settings::set_provider_key( $provider, $api_key );

		if ( ! $success && ! empty( $api_key ) ) {
			return new WP_REST_Response( array( 'error' => 'Failed to save API key.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'   => true,
				'has_key' => ! empty( $api_key ),
			),
			200
		);
	}

	/**
	 * Handle POST /settings/gsc-credentials — save Google Search Console credentials.
	 *
	 * Accepts either a service-account JSON key (as a JSON string or object) or
	 * a plain OAuth2 access token. Credentials are stored in a dedicated WordPress
	 * option and never returned through GET /settings.
	 *
	 * Request body (JSON):
	 *   { "type": "service_account", "credentials_json": "<JSON string>", "default_site_url": "https://..." }
	 *   { "type": "access_token", "access_token": "<token>", "default_site_url": "https://..." }
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_gsc_credentials( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		if ( empty( $params ) || ! is_array( $params ) ) {
			return new WP_REST_Response( array( 'error' => 'No data provided.' ), 400 );
		}

		$type         = sanitize_text_field( $params['type'] ?? '' );
		$default_site = esc_url_raw( $params['default_site_url'] ?? '' );

		if ( 'service_account' === $type ) {
			$credentials_json = $params['credentials_json'] ?? '';

			// Accept either a JSON string or a pre-decoded object/array.
			if ( is_string( $credentials_json ) ) {
				$decoded = json_decode( $credentials_json, true );
			} else {
				$decoded = $credentials_json;
			}

			if ( ! is_array( $decoded ) ) {
				return new WP_REST_Response( array( 'error' => 'Invalid service account JSON.' ), 400 );
			}

			$required = array( 'client_email', 'private_key' );
			foreach ( $required as $field ) {
				if ( empty( $decoded[ $field ] ) ) {
					return new WP_REST_Response(
						/* translators: %s: field name */
						array( 'error' => sprintf( 'Missing required field: %s', $field ) ),
						400
					);
				}
			}

			$creds = array(
				'type'             => 'service_account',
				'client_email'     => sanitize_email( $decoded['client_email'] ),
				'private_key'      => $decoded['private_key'],
				'default_site_url' => $default_site,
			);

		} elseif ( 'access_token' === $type ) {
			$access_token = sanitize_text_field( $params['access_token'] ?? '' );

			if ( empty( $access_token ) ) {
				return new WP_REST_Response( array( 'error' => 'access_token is required.' ), 400 );
			}

			$creds = array(
				'type'             => 'access_token',
				'access_token'     => $access_token,
				'default_site_url' => $default_site,
			);

		} else {
			return new WP_REST_Response(
				array( 'error' => 'type must be "service_account" or "access_token".' ),
				400
			);
		}

		$success = Settings::set_gsc_credentials( $creds );

		if ( ! $success ) {
			return new WP_REST_Response( array( 'error' => 'Failed to save GSC credentials.' ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'            => true,
				'type'             => $creds['type'],
				'has_credentials'  => true,
				'default_site_url' => $creds['default_site_url'],
			),
			200
		);
	}

	/**
	 * Handle DELETE /settings/gsc-credentials — remove Google Search Console credentials.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_delete_gsc_credentials( WP_REST_Request $request ): WP_REST_Response {
		Settings::set_gsc_credentials( array() );

		return new WP_REST_Response(
			array(
				'deleted'         => true,
				'has_credentials' => false,
			),
			200
		);
	}

	/**
	 * Handle POST /settings/provider-key/test — test a direct provider API key.
	 *
	 * Sends a minimal prompt to verify the key works. Uses the stored key if
	 * no api_key param is provided.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_test_provider_key( WP_REST_Request $request ): WP_REST_Response {
		$provider = (string) $request->get_param( 'provider' );
		$api_key  = (string) $request->get_param( 'api_key' );
		$api_key  = trim( $api_key );

		if ( ! array_key_exists( $provider, Settings::DIRECT_PROVIDERS ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Unknown provider.',
				),
				400
			);
		}

		// Use the provided key or fall back to the stored key.
		$key_to_test = '' !== $api_key ? $api_key : Settings::get_provider_key( $provider );

		if ( '' === $key_to_test ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'No API key configured.',
				),
				400
			);
		}

		$meta          = Settings::DIRECT_PROVIDERS[ $provider ];
		$default_model = $meta['default_model'];

		// Send a minimal test prompt.
		$test_body = array(
			'model'      => $default_model,
			'max_tokens' => 16,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Say "ok".',
				),
			),
		);

		if ( 'anthropic' === $provider ) {
			$response = wp_remote_post(
				'https://api.anthropic.com/v1/messages',
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type'      => 'application/json',
						'x-api-key'         => $key_to_test,
						'anthropic-version' => '2023-06-01',
					],
					'body'    => (string) wp_json_encode( $test_body ),
				]
			);
		} elseif ( 'google' === $provider ) {
			$openai_body = [
				'model'      => $default_model,
				'max_tokens' => 16,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => 'Say "ok".',
					],
				],
			];
			$response    = wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key_to_test,
					],
					'body'    => (string) wp_json_encode( $openai_body ),
				]
			);
		} else {
			// OpenAI.
			$openai_body = [
				'model'      => $default_model,
				'max_tokens' => 16,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => 'Say "ok".',
					],
				],
			];
			$response    = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				[
					'timeout' => 30,
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key_to_test,
					],
					'body'    => (string) wp_json_encode( $openai_body ),
				]
			);
		}

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'error'   => $response->get_error_message(),
				],
				200
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP $code";
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $error_msg,
				),
				200
			);
		}

		// Extract model name from response.
		$model_name = $data['model'] ?? $default_model;

		return new WP_REST_Response(
			array(
				'success' => true,
				'model'   => $model_name,
			),
			200
		);
	}

	// ─── Memory ──────────────────────────────────────────────────────

	/**
	 * Handle GET /memory — list memories.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function handle_list_memory( WP_REST_Request $request ): WP_REST_Response {
		$category = $request->get_param( 'category' );
		$memories = Memory::get_all( $category ?: null );

		$list = array_map(
			function ( $m ) {
				return array(
					'id'         => (int) $m->id,
					'category'   => $m->category,
					'content'    => $m->content,
					'created_at' => $m->created_at,
					'updated_at' => $m->updated_at,
				);
			},
			$memories
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /memory — create a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_memory( WP_REST_Request $request ) {
		$category = $request->get_param( 'category' );
		$content  = $request->get_param( 'content' );

		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return new WP_Error( 'gratis_ai_agent_memory_create_failed', __( 'Failed to create memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'       => $id,
				'category' => $category,
				'content'  => $content,
			),
			201
		);
	}

	/**
	 * Handle PATCH /memory/{id} — update a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_memory( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = array();

		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}

		$updated = Memory::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'gratis_ai_agent_memory_update_failed', __( 'Failed to update memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'updated' => true,
				'id'      => $id,
			),
			200
		);
	}

	/**
	 * Handle DELETE /memory/{id} — delete a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_memory( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = Memory::delete( $id );

		if ( ! $deleted ) {
			return new WP_Error( 'gratis_ai_agent_memory_delete_failed', __( 'Failed to delete memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// ─── Usage ──────────────────────────────────────────────────────

	/**
	 * Handle GET /usage — get usage summary.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_get_usage( WP_REST_Request $request ): WP_REST_Response {
		$filters = array(
			'user_id' => get_current_user_id(),
		);

		if ( $request->has_param( 'period' ) ) {
			$filters['period'] = $request->get_param( 'period' );
		}
		if ( $request->has_param( 'start_date' ) ) {
			$filters['start_date'] = $request->get_param( 'start_date' );
		}
		if ( $request->has_param( 'end_date' ) ) {
			$filters['end_date'] = $request->get_param( 'end_date' );
		}

		$summary = $this->database->get_usage_summary( $filters );

		return new WP_REST_Response( $summary, 200 );
	}

	// ─── Knowledge ──────────────────────────────────────────────────

	/**
	 * Handle GET /knowledge/collections — list all collections.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_collections(): WP_REST_Response {
		$collections = KnowledgeDatabase::list_collections();

		$list = array_map(
			function ( $c ) {
				return array(
					'id'              => (int) $c->id,
					'name'            => $c->name,
					'slug'            => $c->slug,
					'description'     => $c->description,
					'auto_index'      => (bool) (int) $c->auto_index,
					'source_config'   => $c->source_config,
					'status'          => $c->status,
					'chunk_count'     => (int) $c->chunk_count,
					'last_indexed_at' => $c->last_indexed_at,
					'created_at'      => $c->created_at,
					'updated_at'      => $c->updated_at,
				);
			},
			$collections
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /knowledge/collections — create a collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_collection( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		$existing = KnowledgeDatabase::get_collection_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'gratis_ai_agent_collection_exists',
				__( 'A collection with this slug already exists.', 'gratis-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = KnowledgeDatabase::create_collection(
			array(
				'name'          => $request->get_param( 'name' ),
				'slug'          => $slug,
				'description'   => $request->get_param( 'description' ),
				'auto_index'    => $request->get_param( 'auto_index' ),
				'source_config' => $request->get_param( 'source_config' ),
			)
		);

		if ( ! $id ) {
			return new WP_Error(
				'gratis_ai_agent_collection_create_failed',
				__( 'Failed to create collection.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$collection = KnowledgeDatabase::get_collection( $id );

		return new WP_REST_Response(
			array(
				'id'              => (int) $collection->id,
				'name'            => $collection->name,
				'slug'            => $collection->slug,
				'description'     => $collection->description,
				'auto_index'      => (bool) (int) $collection->auto_index,
				'source_config'   => $collection->source_config,
				'status'          => $collection->status,
				'chunk_count'     => 0,
				'last_indexed_at' => null,
				'created_at'      => $collection->created_at,
				'updated_at'      => $collection->updated_at,
			),
			201
		);
	}

	/**
	 * Handle PATCH /knowledge/collections/{id} — update a collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_collection( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}
		if ( $request->has_param( 'auto_index' ) ) {
			$data['auto_index'] = $request->get_param( 'auto_index' );
		}
		if ( $request->has_param( 'source_config' ) ) {
			$data['source_config'] = $request->get_param( 'source_config' );
		}

		$updated = KnowledgeDatabase::update_collection( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'gratis_ai_agent_collection_update_failed',
				__( 'Failed to update collection.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$collection = KnowledgeDatabase::get_collection( $id );

		return new WP_REST_Response(
			array(
				'id'              => (int) $collection->id,
				'name'            => $collection->name,
				'slug'            => $collection->slug,
				'description'     => $collection->description,
				'auto_index'      => (bool) (int) $collection->auto_index,
				'source_config'   => $collection->source_config,
				'status'          => $collection->status,
				'chunk_count'     => (int) $collection->chunk_count,
				'last_indexed_at' => $collection->last_indexed_at,
				'created_at'      => $collection->created_at,
				'updated_at'      => $collection->updated_at,
			),
			200
		);
	}

	/**
	 * Handle DELETE /knowledge/collections/{id} — delete collection + all data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_collection( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = KnowledgeDatabase::delete_collection( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'gratis_ai_agent_collection_delete_failed',
				__( 'Failed to delete collection.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle GET /knowledge/collections/{id}/sources — list sources.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_sources( WP_REST_Request $request ): WP_REST_Response {
		$id      = absint( $request->get_param( 'id' ) );
		$sources = KnowledgeDatabase::get_sources_for_collection( $id );

		$list = array_map(
			function ( $s ) {
				return array(
					'id'            => (int) $s->id,
					'collection_id' => (int) $s->collection_id,
					'source_type'   => $s->source_type,
					'source_id'     => $s->source_id ? (int) $s->source_id : null,
					'source_url'    => $s->source_url,
					'title'         => $s->title,
					'status'        => $s->status,
					'chunk_count'   => (int) $s->chunk_count,
					'error_message' => $s->error_message,
					'created_at'    => $s->created_at,
					'updated_at'    => $s->updated_at,
				);
			},
			$sources
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /knowledge/collections/{id}/index — trigger indexing.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_index_collection( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = Knowledge::reindex_collection( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle POST /knowledge/upload — upload and index a document.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_knowledge_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'gratis_ai_agent_no_file', __( 'No file uploaded.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$collection_id = absint( $request->get_param( 'collection_id' ) );

		if ( ! $collection_id ) {
			return new WP_Error( 'gratis_ai_agent_no_collection', __( 'Collection ID is required.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$collection = KnowledgeDatabase::get_collection( $collection_id );
		if ( ! $collection ) {
			return new WP_Error( 'gratis_ai_agent_collection_not_found', __( 'Collection not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		// Use WordPress media handling to create an attachment.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Index the attachment.
		$result = Knowledge::index_attachment( $attachment_id, $collection_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'attachment_id' => $attachment_id,
					'status'        => 'error',
					'error'         => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'attachment_id' => $attachment_id,
				'status'        => 'indexed',
			),
			201
		);
	}

	/**
	 * Handle DELETE /knowledge/sources/{id} — delete a source.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_source( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = Knowledge::delete_source( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'gratis_ai_agent_source_delete_failed',
				__( 'Failed to delete source.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle GET /knowledge/search — search chunks.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_knowledge_search( WP_REST_Request $request ): WP_REST_Response {
		$query      = $request->get_param( 'q' );
		$collection = $request->get_param( 'collection' );

		$options = array( 'limit' => 10 );
		if ( $collection ) {
			$options['collection'] = $collection;
		}

		$results = Knowledge::search( $query, $options );

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Handle GET /knowledge/stats — get knowledge base statistics.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_knowledge_stats(): WP_REST_Response {
		$collections  = KnowledgeDatabase::list_collections();
		$total_chunks = KnowledgeDatabase::get_total_chunk_count();

		$per_collection = array();
		foreach ( $collections as $c ) {
			$per_collection[] = array(
				'id'              => (int) $c->id,
				'name'            => $c->name,
				'slug'            => $c->slug,
				'chunk_count'     => (int) $c->chunk_count,
				'last_indexed_at' => $c->last_indexed_at,
			);
		}

		return new WP_REST_Response(
			array(
				'total_collections' => count( $collections ),
				'total_chunks'      => $total_chunks,
				'collections'       => $per_collection,
			),
			200
		);
	}

	/**
	 * Handle POST /memory/forget — delete memories matching a topic.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_forget_memory( WP_REST_Request $request ): WP_REST_Response {
		$topic   = $request->get_param( 'topic' );
		$deleted = Memory::forget_by_topic( $topic );

		return new WP_REST_Response(
			array(
				'deleted' => $deleted,
				'topic'   => $topic,
			),
			200
		);
	}

	// ─── Budget ──────────────────────────────────────────────────────

	/**
	 * Handle GET /budget — return current budget status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_get_budget( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( BudgetManager::get_status() );
	}

	// ─── Export / Import ─────────────────────────────────────────────

	/**
	 * Handle GET /sessions/{id}/export — export a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_export_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$format     = $request->get_param( 'format' ) ?: 'json';
		$session    = $this->database->get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'gratis_ai_agent_session_not_found', __( 'Session not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		$result = Export::export( $session, $format );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle POST /sessions/import — import a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_import_session( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			return new WP_Error( 'gratis_ai_agent_import_empty', __( 'No import data provided.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$session_id = Export::import_json( $data, get_current_user_id() );

		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}

		$session = $this->database->get_session( $session_id );

		return new WP_REST_Response(
			array(
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			),
			201
		);
	}

	// ─── Custom Tools handlers ──────────────────────────────────

	/**
	 * List custom tools.
	 */
	public function handle_list_custom_tools(): WP_REST_Response {
		return new WP_REST_Response( CustomTools::list(), 200 );
	}

	/**
	 * Create a custom tool.
	 */
	public function handle_create_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = CustomTools::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create custom tool.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 201 );
	}

	/**
	 * Update a custom tool.
	 */
	public function handle_update_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! CustomTools::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update custom tool.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 200 );
	}

	/**
	 * Delete a custom tool.
	 */
	public function handle_delete_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! CustomTools::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete custom tool.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Test-execute a custom tool with provided input.
	 */
	public function handle_test_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id    = absint( $request->get_param( 'id' ) );
		$input = $request->get_param( 'input' ) ?: array();
		$tool  = CustomTools::get( $id );

		if ( ! $tool ) {
			return new WP_Error( 'not_found', __( 'Tool not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		$result = CustomToolExecutor::execute( $tool, $input );

		return new WP_REST_Response( $result, 200 );
	}

	// ─── Tool Profiles handlers ─────────────────────────────────

	/**
	 * List tool profiles.
	 */
	public function handle_list_tool_profiles(): WP_REST_Response {
		return new WP_REST_Response( ToolProfiles::list(), 200 );
	}

	/**
	 * Save (create or update) a tool profile.
	 */
	public function handle_save_tool_profile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();

		if ( ! ToolProfiles::save( $data ) ) {
			return new WP_Error( 'save_failed', __( 'Failed to save tool profile.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( ToolProfiles::get( $data['slug'] ), 200 );
	}

	/**
	 * Delete a tool profile.
	 */
	public function handle_delete_tool_profile( WP_REST_Request $request ): WP_REST_Response {
		$slug = $request->get_param( 'slug' );
		ToolProfiles::delete( $slug );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// ─── Automations handlers ───────────────────────────────────

	/**
	 * List scheduled automations.
	 */
	public function handle_list_automations(): WP_REST_Response {
		return new WP_REST_Response( Automations::list(), 200 );
	}

	/**
	 * Create a scheduled automation.
	 */
	public function handle_create_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = Automations::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create automation.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( Automations::get( $id ), 201 );
	}

	/**
	 * Update a scheduled automation.
	 */
	public function handle_update_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! Automations::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update automation.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( Automations::get( $id ), 200 );
	}

	/**
	 * Delete a scheduled automation.
	 */
	public function handle_delete_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! Automations::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete automation.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Manually run a scheduled automation.
	 */
	public function handle_run_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = absint( $request->get_param( 'id' ) );
		$result = AutomationRunner::run( $id );

		if ( null === $result ) {
			return new WP_Error( 'not_found', __( 'Automation not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get logs for a specific automation.
	 */
	public function handle_automation_logs( WP_REST_Request $request ): WP_REST_Response {
		$id   = absint( $request->get_param( 'id' ) );
		$logs = AutomationLogs::list_for_automation( $id );

		return new WP_REST_Response( $logs, 200 );
	}

	/**
	 * Get automation templates.
	 */
	public function handle_automation_templates(): WP_REST_Response {
		return new WP_REST_Response( Automations::get_templates(), 200 );
	}

	// ─── Event Automations handlers ─────────────────────────────

	/**
	 * List event automations.
	 */
	public function handle_list_event_automations(): WP_REST_Response {
		return new WP_REST_Response( EventAutomations::list(), 200 );
	}

	/**
	 * Create an event automation.
	 */
	public function handle_create_event_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = EventAutomations::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create event automation.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( EventAutomations::get( $id ), 201 );
	}

	/**
	 * Update an event automation.
	 */
	public function handle_update_event_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! EventAutomations::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update event automation.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( EventAutomations::get( $id ), 200 );
	}

	/**
	 * Delete an event automation.
	 */
	public function handle_delete_event_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! EventAutomations::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete event automation.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * List available event triggers from the registry.
	 */
	public function handle_list_event_triggers(): WP_REST_Response {
		return new WP_REST_Response( EventTriggerRegistry::get_all(), 200 );
	}

	/**
	 * List recent automation logs across all automations.
	 */
	public function handle_list_all_logs(): WP_REST_Response {
		return new WP_REST_Response( AutomationLogs::list_recent(), 200 );
	}

	/**
	 * Handle GET /alerts — return proactive issues that should surface as a
	 * notification badge on the floating action button.
	 *
	 * Each alert has:
	 *   - type    (string) machine-readable key, e.g. 'no_provider'
	 *   - message (string) human-readable description
	 *
	 * @return WP_REST_Response { count: int, alerts: array<array{type: string, message: string}> }
	 */
	public function handle_alerts(): WP_REST_Response {
		$alerts = array();

		// Check whether at least one AI provider is configured.
		$has_provider = false;

		// Direct providers (API key stored in plugin options).
		foreach ( Settings::DIRECT_PROVIDERS as $provider_id => $meta ) {
			if ( '' !== Settings::get_provider_key( $provider_id ) ) {
				$has_provider = true;
				break;
			}
		}

		// WP SDK providers (AI Experiments plugin, OpenAI-compatible connector, etc.).
		if ( ! $has_provider && class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			try {
				$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
				$provider_ids = $registry->getRegisteredProviderIds();
				AgentLoop::ensure_provider_credentials_static();
				foreach ( $provider_ids as $provider_id ) {
					$auth = $registry->getProviderRequestAuthentication( $provider_id );
					if ( null !== $auth ) {
						$has_provider = true;
						break;
					}
				}
			} catch ( \Throwable $e ) {
				// Registry unavailable — treat as no provider.
			}
		}

		if ( ! $has_provider ) {
			$alerts[] = array(
				'type'    => 'no_provider',
				'message' => __( 'No AI provider configured. Add an API key in Settings.', 'gratis-ai-agent' ),
			);
		}

		// Check whether site builder mode is active.
		$settings = $this->settings->get();
		if ( ! empty( $settings['site_builder_mode'] ) ) {
			$alerts[] = array(
				'type'    => 'site_builder_mode',
				'message' => __( 'Site builder mode is active. Open the chat to build your site.', 'gratis-ai-agent' ),
			);
		}

		return new WP_REST_Response(
			array(
				'count'               => count( $alerts ),
				'alerts'              => $alerts,
				'site_builder_mode'   => ! empty( $settings['site_builder_mode'] ),
				'onboarding_complete' => ! empty( $settings['onboarding_complete'] ),
			),
			200
		);
	}

	// ─── Conversation Templates handlers ────────────────────────

	/**
	 * List conversation templates, optionally filtered by category.
	 */
	public function handle_list_conversation_templates( WP_REST_Request $request ): WP_REST_Response {
		$category  = $request->get_param( 'category' );
		$templates = ConversationTemplate::get_all( $category ?: null );

		return new WP_REST_Response( $templates, 200 );
	}

	/**
	 * Create a conversation template.
	 */
	public function handle_create_conversation_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = ConversationTemplate::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create conversation template.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( ConversationTemplate::get( $id ), 201 );
	}

	/**
	 * Update a conversation template.
	 */
	public function handle_update_conversation_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! ConversationTemplate::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update conversation template.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( ConversationTemplate::get( $id ), 200 );
	}

	/**
	 * Delete a conversation template. Built-in templates cannot be deleted.
	 */
	public function handle_delete_conversation_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! ConversationTemplate::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete conversation template. Built-in templates cannot be deleted.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// ─── Site Builder handlers ───────────────────────────────────

	/**
	 * Handle POST /site-builder/start.
	 *
	 * Creates a new session with the site builder system prompt and enables
	 * site builder mode. The floating widget will auto-open on the next page
	 * load and inject the opening interview message.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_site_builder_start(): WP_REST_Response {
		// Enable site builder mode in settings.
		$this->settings->update( array( 'site_builder_mode' => true ) );

		// Create a dedicated session for the site builder conversation.
		$session_id = Database::create_session(
			array(
				'user_id'     => get_current_user_id(),
				'title'       => __( 'Site Builder', 'gratis-ai-agent' ),
				'provider_id' => $this->settings->get( 'default_provider' ) ?: '',
				'model_id'    => $this->settings->get( 'default_model' ) ?: '',
			)
		);

		return new WP_REST_Response(
			array(
				'started'           => true,
				'site_builder_mode' => true,
				'session_id'        => $session_id,
				'system_prompt'     => AgentLoop::get_site_builder_system_prompt(),
				'message'           => __( 'Site builder mode enabled. The widget will open automatically.', 'gratis-ai-agent' ),
			),
			200
		);
	}

	/**
	 * Handle GET /site-builder/status.
	 *
	 * Returns the current site builder mode status and fresh-install detection.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_site_builder_status(): WP_REST_Response {
		$settings = $this->settings->get();

		// Run fresh install detection.
		$fresh_install = \GratisAiAgent\Abilities\SiteBuilderAbilities::check_fresh_install();

		return new WP_REST_Response(
			array(
				'site_builder_mode'   => (bool) ( $settings['site_builder_mode'] ?? false ),
				'onboarding_complete' => (bool) ( $settings['onboarding_complete'] ?? false ),
				'is_fresh_install'    => $fresh_install['is_fresh'],
				'post_count'          => $fresh_install['post_count'],
				'page_count'          => $fresh_install['page_count'],
				'site_title'          => get_bloginfo( 'name' ),
			),
			200
		);
	}

	/**
	 * Handle POST /automations/test-notification.
	 *
	 * Sends a test message to a Slack or Discord webhook and returns the result.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_test_notification( WP_REST_Request $request ): WP_REST_Response {
		$type        = $request->get_param( 'type' );
		$webhook_url = $request->get_param( 'webhook_url' );

		$result = NotificationDispatcher::test( $type, $webhook_url );

		return new WP_REST_Response( $result, $result['success'] ? 200 : 422 );
	}

	// ─── Changes log handlers ────────────────────────────────────────────────

	/**
	 * List change records with optional filters.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_list_changes( WP_REST_Request $request ): WP_REST_Response {
		$filters = array(
			'per_page' => (int) $request->get_param( 'per_page' ),
			'page'     => (int) $request->get_param( 'page' ),
		);

		$session_id = $request->get_param( 'session_id' );
		if ( $session_id ) {
			$filters['session_id'] = (int) $session_id;
		}

		$object_type = $request->get_param( 'object_type' );
		if ( $object_type ) {
			$filters['object_type'] = sanitize_key( $object_type );
		}

		$reverted = $request->get_param( 'reverted' );
		if ( null !== $reverted ) {
			$filters['reverted'] = (bool) $reverted;
		}

		$result = ChangesLog::list( $filters );

		return new WP_REST_Response(
			array(
				'items'    => $result['items'],
				'total'    => $result['total'],
				'per_page' => $filters['per_page'],
				'page'     => $filters['page'],
			),
			200
		);
	}

	/**
	 * Get a single change record.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_change( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$change = ChangesLog::get( $id );

		if ( ! $change ) {
			return new WP_Error( 'not_found', __( 'Change record not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $change, 200 );
	}

	/**
	 * Get the diff for a single change record.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_change_diff( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$change = ChangesLog::get( $id );

		if ( ! $change ) {
			return new WP_Error( 'not_found', __( 'Change record not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		$diff = ChangesLog::generate_diff( $change->before_value, $change->after_value );

		return new WP_REST_Response(
			array(
				'id'           => $change->id,
				'before_value' => $change->before_value,
				'after_value'  => $change->after_value,
				'diff'         => $diff,
			),
			200
		);
	}

	/**
	 * Revert a single change — restores the before_value to the object.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_revert_change( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$change = ChangesLog::get( $id );

		if ( ! $change ) {
			return new WP_Error( 'not_found', __( 'Change record not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		if ( $change->reverted ) {
			return new WP_Error( 'already_reverted', __( 'This change has already been reverted.', 'gratis-ai-agent' ), array( 'status' => 409 ) );
		}

		$result = $this->apply_revert( $change );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		ChangesLog::mark_reverted( $id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Change reverted successfully.', 'gratis-ai-agent' ),
				'id'      => $id,
			),
			200
		);
	}

	/**
	 * Apply the revert operation for a change record.
	 *
	 * Dispatches to the appropriate WordPress API based on object_type.
	 *
	 * @param object $change Change record row.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function apply_revert( object $change ) {
		switch ( $change->object_type ) {
			case 'post':
				$result = wp_update_post(
					array(
						'ID'                => (int) $change->object_id,
						$change->field_name => $change->before_value,
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return true;

			case 'option':
				update_option( $change->field_name, $change->before_value );
				return true;

			case 'term':
				$result = wp_update_term(
					(int) $change->object_id,
					$change->field_name,
					array( 'name' => $change->before_value )
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return true;

			case 'user':
				$result = wp_update_user(
					array(
						'ID'                => (int) $change->object_id,
						$change->field_name => $change->before_value,
					)
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return true;

			default:
				/**
				 * Allow third-party code to handle revert for custom object types.
				 *
				 * @param true|WP_Error $result  Default WP_Error (unhandled).
				 * @param object        $change  Change record row.
				 */
				$result = apply_filters(
					'gratis_ai_agent_revert_change',
					new WP_Error(
						'unsupported_object_type',
						sprintf(
							/* translators: %s: object type slug */
							__( 'Revert is not supported for object type "%s".', 'gratis-ai-agent' ),
							$change->object_type
						),
						array( 'status' => 422 )
					),
					$change
				);
				return $result;
		}
	}

	/**
	 * Export selected changes as a patch file.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_export_changes( WP_REST_Request $request ) {
		$ids = array_map( 'absint', (array) $request->get_param( 'ids' ) );

		if ( empty( $ids ) ) {
			return new WP_Error( 'no_ids', __( 'No change IDs provided.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		$patch = ChangesLog::generate_patch( $ids );

		return new WP_REST_Response(
			array(
				'patch'    => $patch,
				'filename' => 'ai-changes-' . gmdate( 'Y-m-d-His' ) . '.patch',
			),
			200
		);
	}

	/**
	 * Delete a change record.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_change( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$change = ChangesLog::get( $id );

		if ( ! $change ) {
			return new WP_Error( 'not_found', __( 'Change record not found.', 'gratis-ai-agent' ), array( 'status' => 404 ) );
		}

		$deleted = ChangesLog::delete( $id );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete change record.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			),
			200
		);
	}

	// ─── Plugin download endpoints ────────────────────────────────────────────

	/**
	 * Permission check for the plugin download endpoint.
	 *
	 * Requires manage_options capability and a valid nonce for the specific
	 * plugin slug. The nonce is generated by the ability and passed as
	 * `_wpnonce` in the query string.
	 */
	public function check_download_permission( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$slug  = sanitize_key( $request->get_param( 'slug' ) );
		$nonce = $request->get_param( '_wpnonce' );

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gratis_ai_agent_download_plugin_' . $slug ) ) {
			return false;
		}

		return true;
	}

	/**
	 * List all plugins that have been modified by the AI agent.
	 *
	 * Returns plugin slugs with modification counts, last-modified timestamps,
	 * and pre-signed download URLs.
	 */
	public function handle_list_modified_plugins(): WP_REST_Response {
		$rows    = Database::get_modified_plugins();
		$plugins = array();

		foreach ( $rows as $row ) {
			$slug         = $row->plugin_slug ?? '';
			$nonce        = wp_create_nonce( 'gratis_ai_agent_download_plugin_' . $slug );
			$rest_url     = rest_url( self::NAMESPACE . '/download-plugin/' . rawurlencode( $slug ) );
			$download_url = add_query_arg( '_wpnonce', $nonce, $rest_url );

			$plugins[] = array(
				'plugin_slug'        => $slug,
				'modification_count' => (int) ( $row->modification_count ?? 0 ),
				'last_modified'      => $row->last_modified ?? '',
				'download_url'       => $download_url,
			);
		}

		return new WP_REST_Response(
			array(
				'plugins' => $plugins,
				'count'   => count( $plugins ),
			),
			200
		);
	}

	/**
	 * Stream a zip archive of an AI-modified plugin directory.
	 *
	 * The plugin directory is zipped on-the-fly and streamed as a download.
	 * Requires manage_options + valid nonce (checked in check_download_permission).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_download_plugin( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug = sanitize_key( $request->get_param( 'slug' ) );

		if ( empty( $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Plugin slug is required.', 'gratis-ai-agent' ), array( 'status' => 400 ) );
		}

		// Verify the plugin has been AI-modified.
		$modified_files = Database::get_modified_files_for_plugin( $slug );
		if ( empty( $modified_files ) ) {
			return new WP_Error(
				'plugin_not_modified',
				sprintf(
					/* translators: %s: plugin slug */
					__( 'No AI modifications recorded for plugin: %s', 'gratis-ai-agent' ),
					$slug
				),
				array( 'status' => 404 )
			);
		}

		// Verify the plugin directory exists.
		$plugin_dir = WP_CONTENT_DIR . '/plugins/' . $slug;
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'plugin_not_found',
				sprintf(
					/* translators: %s: plugin slug */
					__( 'Plugin directory not found: %s', 'gratis-ai-agent' ),
					$slug
				),
				array( 'status' => 404 )
			);
		}

		// Check ZipArchive is available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'zip_unavailable',
				__( 'ZipArchive PHP extension is not available on this server.', 'gratis-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		// Create a temporary zip file.
		$tmp_file = wp_tempnam( $slug . '.zip' );
		if ( ! $tmp_file ) {
			return new WP_Error( 'tmp_failed', __( 'Failed to create temporary file.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp_file, \ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $tmp_file );
			return new WP_Error( 'zip_open_failed', __( 'Failed to open zip archive for writing.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		$this->add_directory_to_zip( $zip, $plugin_dir, $slug );
		$zip->close();

		// Stream the zip file to the browser.
		$filename = $slug . '-ai-modified.zip';
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp_file ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming local temp file; WP_Filesystem does not support streaming output.
		readfile( $tmp_file );
		wp_delete_file( $tmp_file );
		exit;
	}

	/**
	 * Recursively add a directory to a ZipArchive.
	 *
	 * @param \ZipArchive $zip        The zip archive instance.
	 * @param string      $dir        Absolute path to the directory to add.
	 * @param string      $zip_prefix Prefix for entries inside the zip (the plugin slug).
	 */
	private function add_directory_to_zip( \ZipArchive $zip, string $dir, string $zip_prefix ): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$file_path = $file->getRealPath();
			$relative  = $zip_prefix . '/' . substr( $file_path, strlen( $dir ) + 1 );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative );
			} else {
				$zip->addFile( $file_path, $relative );
			}
		}
	}

	// ─── Google Analytics credential endpoints ────────────────────────────────

	/**
	 * Handle GET /settings/google-analytics — return whether credentials are configured.
	 *
	 * Never returns the actual credentials to avoid leaking the service account key.
	 */
	public static function handle_get_ga_credentials(): WP_REST_Response {
		$creds = GoogleAnalyticsAbilities::get_credentials();
		return new WP_REST_Response(
			array(
				'has_credentials' => '' !== $creds['property_id'] && '' !== $creds['service_account_json'],
				'has_property_id' => '' !== $creds['property_id'],
				'property_id'     => $creds['property_id'],
				'has_service_key' => '' !== $creds['service_account_json'],
			),
			200
		);
	}

	/**
	 * Handle POST /settings/google-analytics — save GA4 credentials.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_ga_credentials( WP_REST_Request $request ): WP_REST_Response {
		$property_id          = (string) $request->get_param( 'property_id' );
		$service_account_json = (string) $request->get_param( 'service_account_json' );

		// Validate property ID format (numeric string).
		$property_id = preg_replace( '/[^0-9]/', '', $property_id );
		if ( empty( $property_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'property_id must be a numeric GA4 property ID.', 'gratis-ai-agent' ) ), 400 );
		}

		// Validate service account JSON structure.
		$sa = json_decode( $service_account_json, true );
		if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'service_account_json must be a valid Google service account JSON key containing client_email and private_key.', 'gratis-ai-agent' ) ),
				400
			);
		}

		$success = GoogleAnalyticsAbilities::set_credentials( $property_id, $service_account_json );
		if ( ! $success ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to save Google Analytics credentials.', 'gratis-ai-agent' ) ), 500 );
		}

		return new WP_REST_Response(
			array(
				'saved'           => true,
				'property_id'     => $property_id,
				'has_service_key' => true,
			),
			200
		);
	}

	/**
	 * Handle DELETE /settings/google-analytics — clear GA4 credentials.
	 */
	public static function handle_clear_ga_credentials(): WP_REST_Response {
		GoogleAnalyticsAbilities::clear_credentials();
		return new WP_REST_Response( array( 'cleared' => true ), 200 );
	}

	// ─── Session Title Generation ─────────────────────────────────────────────

	/**
	 * Generate a short 3-5 word session title from the first user message and AI reply.
	 *
	 * Makes a lightweight AI call using the same provider/model as the conversation.
	 * Falls back to a truncated version of the user message if the AI call fails.
	 *
	 * @param string $user_message The first user message.
	 * @param string $ai_reply     The first AI reply.
	 * @param string $provider_id  Provider identifier (e.g. 'openai', 'anthropic').
	 * @param string $model_id     Model identifier.
	 * @return string A short title (3-5 words, no quotes, no punctuation at end).
	 */
	public static function generate_session_title( string $user_message, string $ai_reply, string $provider_id, string $model_id ): string {
		$fallback = self::title_fallback( $user_message );

		if ( empty( $user_message ) ) {
			return $fallback;
		}

		// Build a minimal prompt asking for a 3-5 word title.
		$prompt_text = sprintf(
			'Generate a short 3-5 word title for this conversation. Reply with ONLY the title — no quotes, no punctuation at the end, no explanation.

User: %s
Assistant: %s',
			mb_substr( $user_message, 0, 500 ),
			mb_substr( $ai_reply, 0, 500 )
		);

		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt_text,
			),
		);

		$request_body = array(
			'messages'   => $messages,
			'max_tokens' => 20,
			'stream'     => false,
		);

		$raw_title = self::call_provider_for_title( $provider_id, $model_id, $request_body );

		if ( null === $raw_title ) {
			return $fallback;
		}

		// Sanitize: strip surrounding quotes, trim whitespace, limit length.
		$title = trim( $raw_title, " \t\n\r\0\x0B\"'" );
		$title = wp_strip_all_tags( $title );
		$title = mb_substr( $title, 0, 100 );

		return '' !== $title ? $title : $fallback;
	}

	/**
	 * Make a minimal API call to the configured provider to generate a title.
	 *
	 * Returns the raw text response, or null on failure.
	 *
	 * @param string               $provider_id  Provider identifier.
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body (messages, max_tokens, stream).
	 * @return string|null Raw response text, or null on failure.
	 */
	private static function call_provider_for_title( string $provider_id, string $model_id, array $request_body ): ?string {
		// Determine which provider to use.
		$effective_provider = $provider_id;
		if ( empty( $effective_provider ) ) {
			$settings           = Settings::get();
			$effective_provider = $settings['default_provider'] ?? '';
		}

		switch ( $effective_provider ) {
			case 'openai':
				return self::call_openai_for_title( $model_id, $request_body );

			case 'anthropic':
				return self::call_anthropic_for_title( $model_id, $request_body );

			case 'google':
				return self::call_google_for_title( $model_id, $request_body );

			default:
				// Try OpenAI-compatible proxy.
				return self::call_openai_compat_for_title( $model_id, $request_body );
		}
	}

	/**
	 * Call OpenAI to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_openai_for_title( string $model_id, array $request_body ): ?string {
		$api_key = Settings::get_provider_key( 'openai' );
		if ( '' === $api_key ) {
			return null;
		}

		$request_body['model'] = $model_id ?: Settings::DIRECT_PROVIDERS['openai']['default_model'];

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => (string) wp_json_encode( $request_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['choices'][0]['message']['content'] ?? null;
	}

	/**
	 * Call Anthropic to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_anthropic_for_title( string $model_id, array $request_body ): ?string {
		$api_key = Settings::get_provider_key( 'anthropic' );
		if ( '' === $api_key ) {
			return null;
		}

		// Anthropic uses a different format.
		$anthropic_body = [
			'model'      => $model_id ?: Settings::DIRECT_PROVIDERS['anthropic']['default_model'],
			'max_tokens' => 20,
			'messages'   => $request_body['messages'],
		];

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				],
				'body'    => (string) wp_json_encode( $anthropic_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['content'][0]['text'] ?? null;
	}

	/**
	 * Call Google Gemini to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_google_for_title( string $model_id, array $request_body ): ?string {
		$api_key = Settings::get_provider_key( 'google' );
		if ( '' === $api_key ) {
			return null;
		}

		$effective_model = $model_id ?: Settings::DIRECT_PROVIDERS['google']['default_model'];

		// Google uses OpenAI-compatible endpoint via their REST API.
		$google_body = [
			'model'      => $effective_model,
			'max_tokens' => 20,
			'messages'   => $request_body['messages'],
			'stream'     => false,
		];

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => (string) wp_json_encode( $google_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['choices'][0]['message']['content'] ?? null;
	}

	/**
	 * Call an OpenAI-compatible proxy to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_openai_compat_for_title( string $model_id, array $request_body ): ?string {
		$endpoint_url = \GratisAiAgent\Core\CredentialResolver::getOpenAiCompatEndpointUrl();
		if ( '' === $endpoint_url ) {
			return null;
		}

		$api_key = \GratisAiAgent\Core\CredentialResolver::getOpenAiCompatApiKey();

		$effective_model = $model_id;
		if ( empty( $effective_model ) ) {
			if ( function_exists( 'OpenAiCompatibleConnector\\get_default_model' ) ) {
				$effective_model = \OpenAiCompatibleConnector\get_default_model();
			}
			if ( empty( $effective_model ) ) {
				$effective_model = Settings::get_default_model();
			}
		}

		$request_body['model'] = $effective_model;

		$response = wp_remote_post(
			rtrim( $endpoint_url, '/' ) . '/chat/completions',
			[
				'timeout'   => 15,
				'headers'   => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . ( $api_key ?: 'no-key' ),
				],
				'body'      => (string) wp_json_encode( $request_body ),
				'sslverify' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['choices'][0]['message']['content'] ?? null;
	}

	/**
	 * Fallback title: truncate the user message to 60 characters.
	 *
	 * @param string $user_message The user message.
	 * @return string Truncated title.
	 */
	private static function title_fallback( string $user_message ): string {
		$title = mb_substr( $user_message, 0, 60 );
		if ( mb_strlen( $user_message ) > 60 ) {
			$title .= '...';
		}
		return $title;
	}
}
