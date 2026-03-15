<?php

declare(strict_types=1);
/**
 * REST API controller for the AI Agent.
 *
 * Uses an async job + polling pattern so that long-running LLM inference
 * does not block the browser->nginx connection.
 *
 * @package AiAgent
 */

namespace AiAgent\REST;

use AiAgent\Automations\AutomationLogs;
use AiAgent\Automations\AutomationRunner;
use AiAgent\Automations\Automations;
use AiAgent\Automations\EventAutomations;
use AiAgent\Automations\EventTriggerRegistry;
use AiAgent\Core\AgentLoop;
use AiAgent\Core\CostCalculator;
use AiAgent\Core\Database;
use AiAgent\Core\Export;
use AiAgent\Core\Settings;
use AiAgent\Knowledge\Knowledge;
use AiAgent\Knowledge\KnowledgeDatabase;
use AiAgent\Models\Memory;
use AiAgent\Models\Skill;
use AiAgent\Tools\CustomToolExecutor;
use AiAgent\Tools\CustomTools;
use AiAgent\Tools\ToolProfiles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestController {

	const NAMESPACE = 'ai-agent/v1';

	/**
	 * Transient prefix for job data.
	 */
	const JOB_PREFIX = 'ai_agent_job_';

	/**
	 * How long job data persists (seconds).
	 */
	const JOB_TTL = 600;

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/run',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_run' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'message'            => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'history'            => [
						'required' => false,
						'type'     => 'array',
						'default'  => [],
					],
					'abilities'          => [
						'required' => false,
						'type'     => 'array',
						'default'  => [],
					],
					'system_instruction' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'max_iterations'     => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					],
					'session_id'         => [
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'provider_id'        => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'model_id'           => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'page_context'       => [
						'required' => false,
						'type'     => 'object',
						'default'  => [],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_job_status' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/process',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_process' ],
				'permission_callback' => [ __CLASS__, 'check_process_permission' ],
				'args'                => [
					'job_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'token'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/abilities',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_abilities' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// Providers endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/providers',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_providers' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// Settings endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_get_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_update_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
			]
		);

		// Claude Max token endpoint (credential — stored separately, never returned in GET /settings).
		register_rest_route(
			self::NAMESPACE,
			'/settings/claude-max-token',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_set_claude_max_token' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'token' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Direct provider API key endpoint (credential — never returned in GET /settings).
		register_rest_route(
			self::NAMESPACE,
			'/settings/provider-key',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_set_provider_key' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'provider' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'api_key'  => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Test a direct provider API key.
		register_rest_route(
			self::NAMESPACE,
			'/settings/provider-key/test',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_test_provider_key' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'provider' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'api_key'  => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Memory endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/memory',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'category' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'content'  => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/memory/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id'       => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'category' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'content'  => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Skills endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/skills',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_skills' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'slug'        => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						],
						'name'        => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'content'     => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id'          => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'name'        => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'content'     => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
						'enabled'     => [
							'required' => false,
							'type'     => 'boolean',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)/reset',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_reset_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Sessions endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/sessions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_sessions' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'status' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => 'active',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'folder' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'search' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'pinned' => [
							'required' => false,
							'type'     => 'boolean',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_session' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'title'       => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'provider_id' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'model_id'    => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/folders',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_list_folders' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/bulk',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_bulk_sessions' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'ids'    => [
						'required' => true,
						'type'     => 'array',
					],
					'action' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'folder' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/trash',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'handle_empty_trash' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_get_session' ],
					'permission_callback' => [ __CLASS__, 'check_session_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_session' ],
					'permission_callback' => [ __CLASS__, 'check_session_permission' ],
					'args'                => [
						'id'     => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'title'  => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'status' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'pinned' => [
							'required' => false,
							'type'     => 'boolean',
						],
						'folder' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_session' ],
					'permission_callback' => [ __CLASS__, 'check_session_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Usage endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/usage',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_get_usage' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'period'     => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'start_date' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date'   => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Export endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)/export',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_export_session' ],
				'permission_callback' => [ __CLASS__, 'check_session_permission' ],
				'args'                => [
					'id'     => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'format' => [
						'required'          => false,
						'type'              => 'string',
						'default'           => 'json',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Import endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/sessions/import',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_import_session' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// Memory forget endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/memory/forget',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_forget_memory' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'topic' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Knowledge endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_collections' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_collection' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'name'          => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'slug'          => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						],
						'description'   => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'auto_index'    => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						],
						'source_config' => [
							'required' => false,
							'type'     => 'object',
							'default'  => [],
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_collection' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id'            => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'name'          => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description'   => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'auto_index'    => [
							'required' => false,
							'type'     => 'boolean',
						],
						'source_config' => [
							'required' => false,
							'type'     => 'object',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_collection' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)/sources',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_list_sources' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)/index',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_index_collection' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/upload',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_knowledge_upload' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/sources/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'handle_delete_source' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_knowledge_search' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'q'          => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'collection' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/knowledge/stats',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_knowledge_stats' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// Tool confirmation endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)/confirm',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_confirm_tool' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id'           => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'always_allow' => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)/reject',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_reject_tool' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// ─── Custom Tools endpoints ─────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/custom-tools',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_custom_tools' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_custom_tool' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'name'         => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'type'         => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'slug'         => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						],
						'description'  => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'config'       => [
							'required' => false,
							'type'     => 'object',
							'default'  => [],
						],
						'input_schema' => [
							'required' => false,
							'type'     => 'object',
							'default'  => [],
						],
						'enabled'      => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => true,
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/custom-tools/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_custom_tool' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_custom_tool' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/custom-tools/(?P<id>\d+)/test',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_test_custom_tool' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id'    => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'input' => [
						'required' => false,
						'type'     => 'object',
						'default'  => [],
					],
				],
			]
		);

		// ─── Tool Profiles endpoints ────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/tool-profiles',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_tool_profiles' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_save_tool_profile' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'slug'        => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						],
						'name'        => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'tool_names'  => [
							'required' => false,
							'type'     => 'array',
							'default'  => [],
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/tool-profiles/(?P<slug>[a-z0-9-]+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'handle_delete_tool_profile' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					],
				],
			]
		);

		// ─── Automations endpoints ──────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/automations',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_automations' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_automation' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'name'     => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'prompt'   => [
							'required' => true,
							'type'     => 'string',
						],
						'schedule' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => 'daily',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_automation' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_automation' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)/run',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_run_automation' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)/logs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_automation_logs' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/automation-templates',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_automation_templates' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// ─── Event Automations endpoints ────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/event-automations',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_event_automations' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_event_automation' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'name'            => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'hook_name'       => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'prompt_template' => [
							'required' => true,
							'type'     => 'string',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/event-automations/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_event_automation' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_event_automation' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/event-triggers',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_list_event_triggers' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/automation-logs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_list_all_logs' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);
	}

	/**
	 * Permission check — admin only.
	 */
	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for session-specific endpoints.
	 *
	 * Verifies manage_options + session ownership.
	 */
	public static function check_session_permission( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$session_id = absint( $request->get_param( 'id' ) );
		$session    = Database::get_session( $session_id );

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
	public static function check_process_permission( WP_REST_Request $request ): bool {
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
	public static function handle_run( WP_REST_Request $request ) {
		$job_id = wp_generate_uuid4();
		$token  = wp_generate_password( 40, false );

		$job = [
			'status'  => 'processing',
			'token'   => $token,
			'user_id' => get_current_user_id(),
			'params'  => [
				'message'            => $request->get_param( 'message' ),
				'history'            => $request->get_param( 'history' ),
				'abilities'          => $request->get_param( 'abilities' ),
				'system_instruction' => $request->get_param( 'system_instruction' ),
				'max_iterations'     => $request->get_param( 'max_iterations' ),
				'session_id'         => $request->get_param( 'session_id' ),
				'provider_id'        => $request->get_param( 'provider_id' ),
				'model_id'           => $request->get_param( 'model_id' ),
				'page_context'       => $request->get_param( 'page_context' ),
			],
		];

		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		// Spawn background worker via non-blocking loopback.
		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'   => [
					'Content-Type' => 'application/json',
				],
			]
		);

		return new WP_REST_Response(
			[
				'job_id' => $job_id,
				'status' => 'processing',
			],
			202
		);
	}

	/**
	 * Handle the /job/{id} polling endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_job_status( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( false === $job || ! is_array( $job ) ) {
			return new WP_Error(
				'ai_agent_job_not_found',
				__( 'Job not found or expired.', 'ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$response = [ 'status' => $job['status'] ];

		if ( 'awaiting_confirmation' === $job['status'] && isset( $job['pending_tools'] ) ) {
			$response['pending_tools'] = $job['pending_tools'];
			return new WP_REST_Response( $response, 200 );
		}

		if ( 'complete' === $job['status'] && isset( $job['result'] ) ) {
			$response['reply']           = $job['result']['reply'] ?? '';
			$response['history']         = $job['result']['history'] ?? [];
			$response['tool_calls']      = $job['result']['tool_calls'] ?? [];
			$response['session_id']      = $job['result']['session_id'] ?? null;
			$response['token_usage']     = $job['result']['token_usage'] ?? [
				'prompt'     => 0,
				'completion' => 0,
			];
			$response['model_id']        = $job['result']['model_id'] ?? ( $job['params']['model_id'] ?? '' );
			$response['iterations_used'] = $job['result']['iterations_used'] ?? 0;

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
	public static function handle_confirm_tool( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || 'awaiting_confirmation' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error(
				'ai_agent_invalid_job',
				__( 'Job not found or not awaiting confirmation.', 'ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		if ( ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'ai_agent_forbidden', __( 'Not authorized.', 'ai-agent' ), [ 'status' => 403 ] );
		}

		// "Always allow" — update tool_permissions to auto.
		if ( $request->get_param( 'always_allow' ) && ! empty( $job['pending_tools'] ) ) {
			$settings = Settings::get();
			$perms    = $settings['tool_permissions'] ?? [];
			foreach ( $job['pending_tools'] as $tool ) {
				$perms[ $tool['name'] ] = 'auto';
			}
			Settings::update( [ 'tool_permissions' => $perms ] );
		}

		return self::resume_job( $job_id, $job, 'confirm' );
	}

	/**
	 * Handle POST /job/{id}/reject — user denies a pending tool call.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_reject_tool( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || 'awaiting_confirmation' !== ( $job['status'] ?? '' ) ) {
			return new WP_Error(
				'ai_agent_invalid_job',
				__( 'Job not found or not awaiting confirmation.', 'ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		if ( ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new WP_Error( 'ai_agent_forbidden', __( 'Not authorized.', 'ai-agent' ), [ 'status' => 403 ] );
		}

		return self::resume_job( $job_id, $job, 'reject' );
	}

	/**
	 * Resume a paused job after confirmation or rejection.
	 *
	 * @param string $job_id Job identifier.
	 * @param array  $job    Job transient data.
	 * @param string $action 'confirm' or 'reject'.
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
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => wp_json_encode(
					[
						'job_id' => $job_id,
						'token'  => $token,
					]
				),
				'headers'   => [
					'Content-Type' => 'application/json',
				],
			]
		);

		return new WP_REST_Response(
			[
				'status' => 'processing',
				'job_id' => $job_id,
			],
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
	public static function handle_process( WP_REST_Request $request ): WP_REST_Response {
		ignore_user_abort( true );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Agent loops need extended execution time.
		set_time_limit( 600 );

		$job_id = $request->get_param( 'job_id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || empty( $job['params'] ) ) {
			return new WP_REST_Response( [ 'ok' => false ], 200 );
		}

		// Restore the user context — the loopback request has no cookies,
		// but the AI Client needs a user for provider auth binding.
		if ( ! empty( $job['user_id'] ) ) {
			wp_set_current_user( $job['user_id'] );
		}

		$params     = $job['params'];
		$session_id = ! empty( $params['session_id'] ) ? (int) $params['session_id'] : 0;

		// Load history from session if session_id is provided.
		$history = [];
		if ( $session_id ) {
			$session = Database::get_session( $session_id );
			if ( $session ) {
				$session_messages = json_decode( $session->messages, true ) ?: [];
				if ( ! empty( $session_messages ) ) {
					try {
						$history = AgentLoop::deserialize_history( $session_messages );
					} catch ( \Exception $e ) {
						$history = [];
					}
				}
			}
		} elseif ( ! empty( $params['history'] ) && is_array( $params['history'] ) ) {
			try {
				$history = AgentLoop::deserialize_history( $params['history'] );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Invalid conversation history format.', 'ai-agent' );
				unset( $job['token'] );
				set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
				return new WP_REST_Response( [ 'ok' => false ], 200 );
			}
		}

		$options = [
			'max_iterations' => $params['max_iterations'] ?? 10,
		];

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

		// Check if this is a resume from a tool confirmation/rejection.
		$is_resume = ! empty( $job['resume'] );

		if ( $is_resume ) {
			$confirmed = 'confirm' === $job['resume'];
			$state     = $job['confirmation_state'] ?? [];

			try {
				$resume_history = AgentLoop::deserialize_history( $state['history'] ?? [] );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Failed to resume conversation.', 'ai-agent' );
				unset( $job['token'] );
				set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
				return new WP_REST_Response( [ 'ok' => false ], 200 );
			}

			$resume_options                  = $options;
			$resume_options['tool_call_log'] = $state['tool_call_log'] ?? [];
			$resume_options['token_usage']   = $state['token_usage'] ?? [
				'prompt'     => 0,
				'completion' => 0,
			];

			$loop   = new AgentLoop( '', [], $resume_history, $resume_options );
			$result = $loop->resume_after_confirmation( $confirmed, $state['iterations_remaining'] ?? 5 );
		} else {
			$loop   = new AgentLoop( $params['message'], $params['abilities'] ?? [], $history, $options );
			$result = $loop->run();
		}

		if ( is_wp_error( $result ) ) {
			$job['status'] = 'error';
			$job['error']  = $result->get_error_message();
		} elseif ( ! empty( $result['awaiting_confirmation'] ) ) {
			$job['status']             = 'awaiting_confirmation';
			$job['pending_tools']      = $result['pending_tools'] ?? [];
			$job['confirmation_state'] = [
				'history'              => $result['history'] ?? [],
				'tool_call_log'        => $result['tool_call_log'] ?? [],
				'token_usage'          => $result['token_usage'] ?? [
					'prompt'     => 0,
					'completion' => 0,
				],
				'iterations_remaining' => $result['iterations_remaining'] ?? 5,
			];
			// Keep token and params for the resume flow.
			unset( $job['token'] );
			set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
			return new WP_REST_Response( [ 'ok' => true ], 200 );
		} else {
			$job['status'] = 'complete';
			$job['result'] = $result;

			// Persist to session if session_id is provided.
			if ( $session_id ) {
				$job['result']['session_id'] = $session_id;

				// The full history from the loop includes existing + new messages.
				// Slice off only the new ones to append.
				$session        = Database::get_session( $session_id );
				$existing_count = 0;
				if ( $session ) {
					$existing_messages = json_decode( $session->messages, true ) ?: [];
					$existing_count    = count( $existing_messages );
				}

				$full_history = $result['history'] ?? [];
				$appended     = array_slice( $full_history, $existing_count );

				Database::append_to_session( $session_id, $appended, $result['tool_calls'] ?? [] );

				// Persist token usage.
				$token_usage = $result['token_usage'] ?? [];
				if ( ! empty( $token_usage ) ) {
					Database::update_session_tokens(
						$session_id,
						$token_usage['prompt'] ?? 0,
						$token_usage['completion'] ?? 0
					);
				}

				// Log to usage tracking table.
				$provider_id  = $params['provider_id'] ?? '';
				$model_id     = $params['model_id'] ?? '';
				$prompt_t     = $token_usage['prompt'] ?? 0;
				$completion_t = $token_usage['completion'] ?? 0;

				if ( $prompt_t > 0 || $completion_t > 0 ) {
					$cost = CostCalculator::calculate_cost( $model_id, $prompt_t, $completion_t );
					Database::log_usage(
						[
							'user_id'           => $job['user_id'] ?? 0,
							'session_id'        => $session_id,
							'provider_id'       => $provider_id,
							'model_id'          => $model_id,
							'prompt_tokens'     => $prompt_t,
							'completion_tokens' => $completion_t,
							'cost_usd'          => $cost,
						]
					);
				}

				// Auto-generate title from first user message if empty.
				if ( $session && empty( $session->title ) ) {
					$title = mb_substr( $params['message'], 0, 60 );
					if ( mb_strlen( $params['message'] ) > 60 ) {
						$title .= '...';
					}
					Database::update_session( $session_id, [ 'title' => $title ] );
				}
			}
		}

		// Clear the token — no longer needed.
		unset( $job['token'] );
		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/**
	 * Handle the /abilities endpoint — list available abilities.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_abilities(): WP_REST_Response {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$abilities = wp_get_abilities();
		$list      = [];

		foreach ( $abilities as $ability ) {
			$description = $ability->get_description();

			// Truncate long descriptions for the settings UI.
			if ( strlen( $description ) > 200 ) {
				$description = substr( $description, 0, 197 ) . '...';
			}

			$list[] = [
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $description,
				'category'    => $ability->get_category(),
			];
		}

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle the /providers endpoint — list registered AI providers and models.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_providers(): WP_REST_Response {
		$providers = [];

		// ── 1. Directly-configured providers (OpenAI, Anthropic, Google) ──
		// These are available regardless of whether the WordPress AI SDK is installed.
		foreach ( Settings::get_configured_direct_providers() as $direct ) {
			if ( $direct['has_key'] ) {
				$providers[] = [
					'id'         => $direct['id'],
					'name'       => $direct['name'],
					'type'       => 'direct',
					'configured' => true,
					'models'     => $direct['models'],
				];
			}
		}

		// ── 2. WordPress AI SDK providers (requires WP 6.9+ AI Experiments) ──
		if ( class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			try {
				$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
				$provider_ids = $registry->getRegisteredProviderIds();

				// Ensure credentials are loaded (same logic the agent loop uses).
				AgentLoop::ensure_provider_credentials_static();

				// Track IDs already added via direct config to avoid duplicates.
				$direct_ids = array_column( $providers, 'id' );

				foreach ( $provider_ids as $provider_id ) {
					// Skip if already listed as a direct provider.
					if ( in_array( $provider_id, $direct_ids, true ) ) {
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
						$models   = [];

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
									$models[] = [
										'id'   => $model_meta->getId(),
										'name' => $model_meta->getName(),
									];
								}
							} catch ( \Throwable $e ) {
								// Model listing failed — still include the provider.
							}
						}

						$providers[] = [
							'id'         => $provider_id,
							'name'       => $metadata->getName(),
							'type'       => (string) $metadata->getType(),
							'configured' => true,
							'models'     => $models,
						];
					} catch ( \Throwable $e ) {
						continue;
					}
				}
			} catch ( \Throwable $e ) {
				// SDK registry unavailable — direct providers already added above.
			}
		}

		return new WP_REST_Response( $providers, 200 );
	}

	/**
	 * Handle GET /sessions — list sessions for current user.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_list_sessions( WP_REST_Request $request ): WP_REST_Response {
		$filters = [];

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

		$sessions = Database::list_sessions( get_current_user_id(), $filters );

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Handle GET /sessions/folders — list folders for current user.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_list_folders(): WP_REST_Response {
		$folders = Database::list_folders( get_current_user_id() );

		return new WP_REST_Response( $folders, 200 );
	}

	/**
	 * Handle POST /sessions/bulk — bulk update sessions.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_bulk_sessions( WP_REST_Request $request ) {
		$ids    = array_map( 'absint', $request->get_param( 'ids' ) );
		$action = $request->get_param( 'action' );

		$data = [];
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
				return new WP_Error( 'ai_agent_invalid_action', __( 'Invalid bulk action.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		$count = Database::bulk_update_sessions( $ids, get_current_user_id(), $data );

		return new WP_REST_Response( [ 'updated' => $count ], 200 );
	}

	/**
	 * Handle DELETE /sessions/trash — empty trash for current user.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_empty_trash(): WP_REST_Response {
		$count = Database::empty_trash( get_current_user_id() );

		return new WP_REST_Response( [ 'deleted' => $count ], 200 );
	}

	/**
	 * Handle GET /sessions/{id} — get full session with messages.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$session    = Database::get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error(
				'ai_agent_session_not_found',
				__( 'Session not found.', 'ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => json_decode( $session->messages, true ) ?: [],
				'tool_calls'  => json_decode( $session->tool_calls, true ) ?: [],
				'token_usage' => [
					'prompt'     => (int) ( $session->prompt_tokens ?? 0 ),
					'completion' => (int) ( $session->completion_tokens ?? 0 ),
				],
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			200
		);
	}

	/**
	 * Handle POST /sessions — create a new session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_session( WP_REST_Request $request ) {
		$session_id = Database::create_session(
			[
				'user_id'     => get_current_user_id(),
				'title'       => $request->get_param( 'title' ),
				'provider_id' => $request->get_param( 'provider_id' ),
				'model_id'    => $request->get_param( 'model_id' ),
			]
		);

		if ( ! $session_id ) {
			return new WP_Error(
				'ai_agent_session_create_failed',
				__( 'Failed to create session.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$session = Database::get_session( $session_id );

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => [],
				'tool_calls'  => [],
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			201
		);
	}

	/**
	 * Handle PATCH /sessions/{id} — update session fields.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );

		$data = [];
		if ( $request->has_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}
		if ( $request->has_param( 'status' ) ) {
			$status = $request->get_param( 'status' );
			if ( in_array( $status, [ 'active', 'archived', 'trash' ], true ) ) {
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
			return new WP_Error( 'ai_agent_no_data', __( 'No fields to update.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		$updated = Database::update_session( $session_id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'ai_agent_session_update_failed',
				__( 'Failed to update session.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$session = Database::get_session( $session_id );

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'status'      => $session->status,
				'pinned'      => (bool) (int) $session->pinned,
				'folder'      => $session->folder,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			200
		);
	}

	/**
	 * Handle DELETE /sessions/{id} — delete a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );

		$deleted = Database::delete_session( $session_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'ai_agent_session_delete_failed',
				__( 'Failed to delete session.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	// ─── Skills ─────────────────────────────────────────────────────

	/**
	 * Handle GET /skills — list all skills.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_list_skills(): WP_REST_Response {
		$skills = Skill::get_all();

		$list = array_map(
			function ( $s ) {
				return [
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
				];
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
	public static function handle_create_skill( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		$existing = Skill::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'ai_agent_skill_slug_exists',
				__( 'A skill with this slug already exists.', 'ai-agent' ),
				[ 'status' => 409 ]
			);
		}

		$id = Skill::create(
			[
				'slug'        => $slug,
				'name'        => $request->get_param( 'name' ),
				'description' => $request->get_param( 'description' ),
				'content'     => $request->get_param( 'content' ),
				'is_builtin'  => false,
				'enabled'     => true,
			]
		);

		if ( false === $id ) {
			return new WP_Error(
				'ai_agent_skill_create_failed',
				__( 'Failed to create skill.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response(
			[
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
			],
			201
		);
	}

	/**
	 * Handle PATCH /skills/{id} — update a skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_skill( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = [];

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
				'ai_agent_skill_update_failed',
				__( 'Failed to update skill.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response(
			[
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
			],
			200
		);
	}

	/**
	 * Handle DELETE /skills/{id} — delete a custom skill (refuses built-in).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_skill( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = Skill::delete( $id );

		if ( $result === 'builtin' ) {
			return new WP_Error(
				'ai_agent_skill_builtin_delete',
				__( 'Built-in skills cannot be deleted. You can disable them instead.', 'ai-agent' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! $result ) {
			return new WP_Error(
				'ai_agent_skill_delete_failed',
				__( 'Failed to delete skill or skill not found.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Handle POST /skills/{id}/reset — reset a built-in skill to defaults.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_reset_skill( WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$reset = Skill::reset_builtin( $id );

		if ( ! $reset ) {
			return new WP_Error(
				'ai_agent_skill_reset_failed',
				__( 'Failed to reset skill. Only built-in skills can be reset.', 'ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response(
			[
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
			],
			200
		);
	}

	// ─── Settings ────────────────────────────────────────────────────

	/**
	 * Handle GET /settings.
	 */
	public static function handle_get_settings(): WP_REST_Response {
		$settings = Settings::get();

		// Include built-in defaults so the UI can show them as placeholders.
		$settings['_defaults'] = [
			'system_prompt'    => AgentLoop::get_default_system_prompt(),
			'greeting_message' => __( 'Send a message to start a conversation.', 'ai-agent' ),
		];

		// Indicate whether a Claude Max token is stored without exposing the token itself.
		$settings['_has_claude_max_token'] = '' !== Settings::get_claude_max_token();

		// Indicate which direct providers have API keys configured (without exposing the keys).
		$settings['_provider_keys'] = [];
		foreach ( array_keys( Settings::DIRECT_PROVIDERS ) as $provider_id ) {
			$settings['_provider_keys'][ $provider_id ] = '' !== Settings::get_provider_key( $provider_id );
		}

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Handle POST /settings — partial update.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_REST_Response( [ 'error' => 'No data provided.' ], 400 );
		}

		Settings::update( $data );

		return new WP_REST_Response( Settings::get(), 200 );
	}

	/**
	 * Handle POST /settings/claude-max-token — store the Claude Max OAuth token.
	 *
	 * The token is stored in a dedicated WordPress option and never returned
	 * in GET /settings to avoid leaking it through the general settings endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_set_claude_max_token( WP_REST_Request $request ): WP_REST_Response {
		$token = $request->get_param( 'token' );

		// Allow clearing the token by passing an empty string.
		$token = is_string( $token ) ? trim( $token ) : '';

		$success = Settings::set_claude_max_token( $token );

		if ( ! $success && ! empty( $token ) ) {
			return new WP_REST_Response( [ 'error' => 'Failed to save token.' ], 500 );
		}

		return new WP_REST_Response(
			[
				'saved'        => true,
				'has_token'    => ! empty( $token ),
				'token_prefix' => ! empty( $token ) ? substr( $token, 0, 20 ) . '…' : '',
			],
			200
		);
	}

	/**
	 * Handle POST /settings/provider-key — store a direct provider API key.
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

		$valid_providers = array_keys( Settings::DIRECT_PROVIDERS );
		if ( ! in_array( $provider, $valid_providers, true ) ) {
			return new WP_REST_Response(
				[ 'error' => sprintf( 'Unknown provider "%s". Valid: %s', $provider, implode( ', ', $valid_providers ) ) ],
				400
			);
		}

		$success = Settings::set_provider_key( $provider, $api_key );

		if ( ! $success && ! empty( $api_key ) ) {
			return new WP_REST_Response( [ 'error' => 'Failed to save API key.' ], 500 );
		}

		return new WP_REST_Response(
			[
				'saved'      => true,
				'provider'   => $provider,
				'has_key'    => ! empty( $api_key ),
				'key_prefix' => ! empty( $api_key ) ? substr( $api_key, 0, 8 ) . '…' : '',
			],
			200
		);
	}

	/**
	 * Handle POST /settings/provider-key/test — test a direct provider API key.
	 *
	 * Sends a minimal request to the provider's API to verify the key works.
	 * Uses the stored key if api_key param is omitted.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_test_provider_key( WP_REST_Request $request ): WP_REST_Response {
		$provider = (string) $request->get_param( 'provider' );
		$api_key  = trim( (string) ( $request->get_param( 'api_key' ) ?: '' ) );

		// Fall back to stored key if none provided.
		if ( empty( $api_key ) ) {
			$api_key = Settings::get_provider_key( $provider );
		}

		if ( empty( $api_key ) ) {
			return new WP_REST_Response(
				[ 'success' => false, 'error' => __( 'No API key provided or stored.', 'ai-agent' ) ],
				400
			);
		}

		$result = self::test_provider_api_key( $provider, $api_key );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				[ 'success' => false, 'error' => $result->get_error_message() ],
				200
			);
		}

		return new WP_REST_Response( [ 'success' => true, 'model' => $result ], 200 );
	}

	/**
	 * Test a provider API key by sending a minimal prompt.
	 *
	 * @param string $provider One of 'openai', 'anthropic', 'google'.
	 * @param string $api_key  The API key to test.
	 * @return string|WP_Error Model name on success, WP_Error on failure.
	 */
	private static function test_provider_api_key( string $provider, string $api_key ) {
		switch ( $provider ) {
			case 'openai':
				$response = wp_remote_post(
					'https://api.openai.com/v1/chat/completions',
					[
						'timeout' => 30,
						'headers' => [
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $api_key,
						],
						'body'    => wp_json_encode( [
							'model'      => 'gpt-4o-mini',
							'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
							'max_tokens' => 5,
						] ),
					]
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$code = wp_remote_retrieve_response_code( $response );
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( 200 !== $code ) {
					$msg = $data['error']['message'] ?? "HTTP $code";
					return new WP_Error( 'ai_agent_test_failed', $msg );
				}
				return $data['model'] ?? 'gpt-4o-mini';

			case 'anthropic':
				$response = wp_remote_post(
					'https://api.anthropic.com/v1/messages',
					[
						'timeout' => 30,
						'headers' => [
							'Content-Type'      => 'application/json',
							'x-api-key'         => $api_key,
							'anthropic-version' => '2023-06-01',
						],
						'body'    => wp_json_encode( [
							'model'      => 'claude-haiku-3-20241022',
							'max_tokens' => 5,
							'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
						] ),
					]
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$code = wp_remote_retrieve_response_code( $response );
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( 200 !== $code ) {
					$msg = $data['error']['message'] ?? "HTTP $code";
					return new WP_Error( 'ai_agent_test_failed', $msg );
				}
				return $data['model'] ?? 'claude-haiku-3-20241022';

			case 'google':
				$response = wp_remote_post(
					'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
					[
						'timeout' => 30,
						'headers' => [
							'Content-Type'  => 'application/json',
							'Authorization' => 'Bearer ' . $api_key,
						],
						'body'    => wp_json_encode( [
							'model'      => 'gemini-2.0-flash-lite',
							'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
							'max_tokens' => 5,
						] ),
					]
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$code = wp_remote_retrieve_response_code( $response );
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( 200 !== $code ) {
					$msg = $data['error']['message'] ?? "HTTP $code";
					return new WP_Error( 'ai_agent_test_failed', $msg );
				}
				return $data['model'] ?? 'gemini-2.0-flash-lite';

			default:
				return new WP_Error( 'ai_agent_unknown_provider', "Unknown provider: $provider" );
		}
	}

	// ─── Memory ──────────────────────────────────────────────────────

	/**
	 * Handle GET /memory — list memories.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_list_memory( WP_REST_Request $request ): WP_REST_Response {
		$category = $request->get_param( 'category' );
		$memories = Memory::get_all( $category ?: null );

		$list = array_map(
			function ( $m ) {
				return [
					'id'         => (int) $m->id,
					'category'   => $m->category,
					'content'    => $m->content,
					'created_at' => $m->created_at,
					'updated_at' => $m->updated_at,
				];
			},
			$memories
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /memory — create a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_create_memory( WP_REST_Request $request ) {
		$category = $request->get_param( 'category' );
		$content  = $request->get_param( 'content' );

		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return new WP_Error( 'ai_agent_memory_create_failed', __( 'Failed to create memory.', 'ai-agent' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'id'       => $id,
				'category' => $category,
				'content'  => $content,
			],
			201
		);
	}

	/**
	 * Handle PATCH /memory/{id} — update a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_update_memory( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = [];

		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}

		$updated = Memory::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'ai_agent_memory_update_failed', __( 'Failed to update memory.', 'ai-agent' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'updated' => true,
				'id'      => $id,
			],
			200
		);
	}

	/**
	 * Handle DELETE /memory/{id} — delete a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_delete_memory( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = Memory::delete( $id );

		if ( ! $deleted ) {
			return new WP_Error( 'ai_agent_memory_delete_failed', __( 'Failed to delete memory.', 'ai-agent' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	// ─── Usage ──────────────────────────────────────────────────────

	/**
	 * Handle GET /usage — get usage summary.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_get_usage( WP_REST_Request $request ): WP_REST_Response {
		$filters = [
			'user_id' => get_current_user_id(),
		];

		if ( $request->has_param( 'period' ) ) {
			$filters['period'] = $request->get_param( 'period' );
		}
		if ( $request->has_param( 'start_date' ) ) {
			$filters['start_date'] = $request->get_param( 'start_date' );
		}
		if ( $request->has_param( 'end_date' ) ) {
			$filters['end_date'] = $request->get_param( 'end_date' );
		}

		$summary = Database::get_usage_summary( $filters );

		return new WP_REST_Response( $summary, 200 );
	}

	// ─── Knowledge ──────────────────────────────────────────────────

	/**
	 * Handle GET /knowledge/collections — list all collections.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_list_collections(): WP_REST_Response {
		$collections = KnowledgeDatabase::list_collections();

		$list = array_map(
			function ( $c ) {
				return [
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
				];
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
	public static function handle_create_collection( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		$existing = KnowledgeDatabase::get_collection_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'ai_agent_collection_exists',
				__( 'A collection with this slug already exists.', 'ai-agent' ),
				[ 'status' => 409 ]
			);
		}

		$id = KnowledgeDatabase::create_collection(
			[
				'name'          => $request->get_param( 'name' ),
				'slug'          => $slug,
				'description'   => $request->get_param( 'description' ),
				'auto_index'    => $request->get_param( 'auto_index' ),
				'source_config' => $request->get_param( 'source_config' ),
			]
		);

		if ( ! $id ) {
			return new WP_Error(
				'ai_agent_collection_create_failed',
				__( 'Failed to create collection.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$collection = KnowledgeDatabase::get_collection( $id );

		return new WP_REST_Response(
			[
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
			],
			201
		);
	}

	/**
	 * Handle PATCH /knowledge/collections/{id} — update a collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_collection( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = [];

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
				'ai_agent_collection_update_failed',
				__( 'Failed to update collection.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$collection = KnowledgeDatabase::get_collection( $id );

		return new WP_REST_Response(
			[
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
			],
			200
		);
	}

	/**
	 * Handle DELETE /knowledge/collections/{id} — delete collection + all data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_collection( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = KnowledgeDatabase::delete_collection( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'ai_agent_collection_delete_failed',
				__( 'Failed to delete collection.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Handle GET /knowledge/collections/{id}/sources — list sources.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_list_sources( WP_REST_Request $request ): WP_REST_Response {
		$id      = absint( $request->get_param( 'id' ) );
		$sources = KnowledgeDatabase::get_sources_for_collection( $id );

		$list = array_map(
			function ( $s ) {
				return [
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
				];
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
	public static function handle_index_collection( WP_REST_Request $request ) {
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
	public static function handle_knowledge_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'ai_agent_no_file', __( 'No file uploaded.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		$collection_id = absint( $request->get_param( 'collection_id' ) );

		if ( ! $collection_id ) {
			return new WP_Error( 'ai_agent_no_collection', __( 'Collection ID is required.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		$collection = KnowledgeDatabase::get_collection( $collection_id );
		if ( ! $collection ) {
			return new WP_Error( 'ai_agent_collection_not_found', __( 'Collection not found.', 'ai-agent' ), [ 'status' => 404 ] );
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
				[
					'attachment_id' => $attachment_id,
					'status'        => 'error',
					'error'         => $result->get_error_message(),
				],
				200
			);
		}

		return new WP_REST_Response(
			[
				'attachment_id' => $attachment_id,
				'status'        => 'indexed',
			],
			201
		);
	}

	/**
	 * Handle DELETE /knowledge/sources/{id} — delete a source.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_source( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = Knowledge::delete_source( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'ai_agent_source_delete_failed',
				__( 'Failed to delete source.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Handle GET /knowledge/search — search chunks.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_knowledge_search( WP_REST_Request $request ): WP_REST_Response {
		$query      = $request->get_param( 'q' );
		$collection = $request->get_param( 'collection' );

		$options = [ 'limit' => 10 ];
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
	public static function handle_knowledge_stats(): WP_REST_Response {
		$collections  = KnowledgeDatabase::list_collections();
		$total_chunks = KnowledgeDatabase::get_total_chunk_count();

		$per_collection = [];
		foreach ( $collections as $c ) {
			$per_collection[] = [
				'id'              => (int) $c->id,
				'name'            => $c->name,
				'slug'            => $c->slug,
				'chunk_count'     => (int) $c->chunk_count,
				'last_indexed_at' => $c->last_indexed_at,
			];
		}

		return new WP_REST_Response(
			[
				'total_collections' => count( $collections ),
				'total_chunks'      => $total_chunks,
				'collections'       => $per_collection,
			],
			200
		);
	}

	/**
	 * Handle POST /memory/forget — delete memories matching a topic.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_forget_memory( WP_REST_Request $request ): WP_REST_Response {
		$topic   = $request->get_param( 'topic' );
		$deleted = Memory::forget_by_topic( $topic );

		return new WP_REST_Response(
			[
				'deleted' => $deleted,
				'topic'   => $topic,
			],
			200
		);
	}

	// ─── Export / Import ─────────────────────────────────────────────

	/**
	 * Handle GET /sessions/{id}/export — export a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_export_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$format     = $request->get_param( 'format' ) ?: 'json';
		$session    = Database::get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error( 'ai_agent_session_not_found', __( 'Session not found.', 'ai-agent' ), [ 'status' => 404 ] );
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
	public static function handle_import_session( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			return new WP_Error( 'ai_agent_import_empty', __( 'No import data provided.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		$session_id = Export::import_json( $data, get_current_user_id() );

		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}

		$session = Database::get_session( $session_id );

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			201
		);
	}

	// ─── Custom Tools handlers ──────────────────────────────────

	/**
	 * List custom tools.
	 */
	public static function handle_list_custom_tools(): WP_REST_Response {
		return new WP_REST_Response( CustomTools::list(), 200 );
	}

	/**
	 * Create a custom tool.
	 */
	public static function handle_create_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = CustomTools::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create custom tool.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 201 );
	}

	/**
	 * Update a custom tool.
	 */
	public static function handle_update_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! CustomTools::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update custom tool.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( CustomTools::get( $id ), 200 );
	}

	/**
	 * Delete a custom tool.
	 */
	public static function handle_delete_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! CustomTools::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete custom tool.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Test-execute a custom tool with provided input.
	 */
	public static function handle_test_custom_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id    = absint( $request->get_param( 'id' ) );
		$input = $request->get_param( 'input' ) ?: [];
		$tool  = CustomTools::get( $id );

		if ( ! $tool ) {
			return new WP_Error( 'not_found', __( 'Tool not found.', 'ai-agent' ), [ 'status' => 404 ] );
		}

		$result = CustomToolExecutor::execute( $tool, $input );

		return new WP_REST_Response( $result, 200 );
	}

	// ─── Tool Profiles handlers ─────────────────────────────────

	/**
	 * List tool profiles.
	 */
	public static function handle_list_tool_profiles(): WP_REST_Response {
		return new WP_REST_Response( ToolProfiles::list(), 200 );
	}

	/**
	 * Save (create or update) a tool profile.
	 */
	public static function handle_save_tool_profile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();

		if ( ! ToolProfiles::save( $data ) ) {
			return new WP_Error( 'save_failed', __( 'Failed to save tool profile.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( ToolProfiles::get( $data['slug'] ), 200 );
	}

	/**
	 * Delete a tool profile.
	 */
	public static function handle_delete_tool_profile( WP_REST_Request $request ): WP_REST_Response {
		$slug = $request->get_param( 'slug' );
		ToolProfiles::delete( $slug );

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	// ─── Automations handlers ───────────────────────────────────

	/**
	 * List scheduled automations.
	 */
	public static function handle_list_automations(): WP_REST_Response {
		return new WP_REST_Response( Automations::list(), 200 );
	}

	/**
	 * Create a scheduled automation.
	 */
	public static function handle_create_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = Automations::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create automation.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( Automations::get( $id ), 201 );
	}

	/**
	 * Update a scheduled automation.
	 */
	public static function handle_update_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! Automations::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update automation.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( Automations::get( $id ), 200 );
	}

	/**
	 * Delete a scheduled automation.
	 */
	public static function handle_delete_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! Automations::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete automation.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Manually run a scheduled automation.
	 */
	public static function handle_run_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = absint( $request->get_param( 'id' ) );
		$result = AutomationRunner::run( $id );

		if ( null === $result ) {
			return new WP_Error( 'not_found', __( 'Automation not found.', 'ai-agent' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get logs for a specific automation.
	 */
	public static function handle_automation_logs( WP_REST_Request $request ): WP_REST_Response {
		$id   = absint( $request->get_param( 'id' ) );
		$logs = AutomationLogs::list_for_automation( $id );

		return new WP_REST_Response( $logs, 200 );
	}

	/**
	 * Get automation templates.
	 */
	public static function handle_automation_templates(): WP_REST_Response {
		return new WP_REST_Response( Automations::get_templates(), 200 );
	}

	// ─── Event Automations handlers ─────────────────────────────

	/**
	 * List event automations.
	 */
	public static function handle_list_event_automations(): WP_REST_Response {
		return new WP_REST_Response( EventAutomations::list(), 200 );
	}

	/**
	 * Create an event automation.
	 */
	public static function handle_create_event_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();
		$id   = EventAutomations::create( $data );

		if ( false === $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create event automation.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( EventAutomations::get( $id ), 201 );
	}

	/**
	 * Update an event automation.
	 */
	public static function handle_update_event_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		if ( ! EventAutomations::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update event automation.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( EventAutomations::get( $id ), 200 );
	}

	/**
	 * Delete an event automation.
	 */
	public static function handle_delete_event_automation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = absint( $request->get_param( 'id' ) );

		if ( ! EventAutomations::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete event automation.', 'ai-agent' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * List available event triggers from the registry.
	 */
	public static function handle_list_event_triggers(): WP_REST_Response {
		return new WP_REST_Response( EventTriggerRegistry::get_all(), 200 );
	}

	/**
	 * List recent automation logs across all automations.
	 */
	public static function handle_list_all_logs(): WP_REST_Response {
		return new WP_REST_Response( AutomationLogs::list_recent(), 200 );
	}
}
