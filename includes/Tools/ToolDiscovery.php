<?php

declare(strict_types=1);
/**
 * Tool Discovery Mode for AI Agent.
 *
 * Provides meta-tools (list-tools, execute-tool) that let the AI agent
 * discover and run any registered ability on demand, reducing the number
 * of tools loaded per request from ~64 to ~11 priority tools.
 *
 * @package AiAgent
 */

namespace AiAgent\Tools;

use AiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolDiscovery {

	/**
	 * Default priority categories — always loaded as direct tools.
	 *
	 * @var string[]
	 */
	private const DEFAULT_PRIORITY_CATEGORIES = [ 'ai-agent', 'site', 'user' ];

	/**
	 * Default priority tool names — loaded directly even if their category
	 * isn't in the priority categories list. These are the most commonly
	 * needed WP-CLI tools for content creation workflows.
	 *
	 * @var string[]
	 */
	private const DEFAULT_PRIORITY_TOOLS = [
		'wpcli/site/create',
		'wpcli/site/list',
		'wpcli/post/create',
		'wpcli/post/update',
		'wpcli/post/list',
		'wpcli/post/get',
		'wpcli/media/import',
		'wpcli/option/update',
		'wpcli/option/get',
		'wpcli/theme/list',
		'wpcli/theme/activate',
		'wpcli/plugin/list',
	];

	/**
	 * Register the meta-tool abilities.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the list-tools and execute-tool abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'ai-agent/list-tools',
			[
				'label'               => __( 'List Tools', 'ai-agent' ),
				'description'         => __( 'Search and browse all available tools by name, description, or category. Call with no arguments to get a category overview with counts. Use query for fuzzy search, category to filter.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'query'    => [
							'type'        => 'string',
							'description' => 'Search query to fuzzy-match against tool names and descriptions.',
						],
						'category' => [
							'type'        => 'string',
							'description' => 'Filter tools by category slug.',
						],
						'page'     => [
							'type'        => 'integer',
							'description' => 'Page number for pagination (default: 1).',
							'default'     => 1,
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => 'Tools per page (default: 20, max: 50).',
							'default'     => 20,
						],
					],
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_list_tools' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'ai-agent/execute-tool',
			[
				'label'               => __( 'Execute Tool', 'ai-agent' ),
				'description'         => __( 'Execute any registered tool by name. Pass the tool_name and its parameters. For tools requiring confirmation, set confirmed: true after user approval.', 'ai-agent' ),
				'category'            => 'ai-agent',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'tool_name'  => [
							'type'        => 'string',
							'description' => 'The ability name to execute (e.g. "wpcli/wp-cli").',
						],
						'parameters' => [
							'type'        => 'object',
							'description' => 'Parameters to pass to the tool.',
						],
						'confirmed'  => [
							'type'        => 'boolean',
							'description' => 'Set to true to confirm execution of tools that require confirmation.',
							'default'     => false,
						],
					],
					'required'   => [ 'tool_name' ],
				],
				'meta'                => [
					'show_in_rest' => true,
				],
				'execute_callback'    => [ __CLASS__, 'handle_execute_tool' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Handle the list-tools ability call.
	 *
	 * @param array $input The input parameters.
	 * @return array The result.
	 */
	public static function handle_list_tools( array $input ): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [ 'error' => 'Abilities API not available.' ];
		}

		$query    = $input['query'] ?? '';
		$category = $input['category'] ?? '';
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );

		$all        = wp_get_abilities();
		$perms      = Settings::get( 'tool_permissions' ) ?: [];
		$priorities = self::get_priority_categories();

		// Filter out disabled tools and priority tools (already loaded directly).
		$priority_tools = self::get_priority_tools();
		$tools          = [];
		foreach ( $all as $ability ) {
			$name = $ability->get_name();
			$perm = $perms[ $name ] ?? 'auto';

			if ( 'disabled' === $perm ) {
				continue;
			}

			if ( in_array( $ability->get_category(), $priorities, true ) ) {
				continue;
			}

			if ( self::is_priority_tool( $ability, $priority_tools ) ) {
				continue;
			}

			$tools[] = $ability;
		}

		// If no query and no category filter, return category overview.
		if ( '' === $query && '' === $category ) {
			$categories = self::count_categories( $tools );

			return [
				'mode'       => 'category_overview',
				'categories' => $categories,
				'total'      => count( $tools ),
				'hint'       => 'Use category or query parameter to browse specific tools.',
			];
		}

		// Filter by category.
		if ( '' !== $category ) {
			$tools = array_filter(
				$tools,
				function ( $ability ) use ( $category ) {
					return $ability->get_category() === $category;
				}
			);
			$tools = array_values( $tools );
		}

		// Fuzzy search by query.
		if ( '' !== $query ) {
			$query_lower = strtolower( $query );
			$scored      = [];

			foreach ( $tools as $ability ) {
				$name_lower  = strtolower( $ability->get_name() );
				$label_lower = strtolower( $ability->get_label() );
				$desc_lower  = strtolower( $ability->get_description() );

				$score = 0;

				// Exact name match.
				if ( $name_lower === $query_lower ) {
					$score += 100;
				} elseif ( str_contains( $name_lower, $query_lower ) ) {
					$score += 50;
				}

				// Label match.
				if ( str_contains( $label_lower, $query_lower ) ) {
					$score += 30;
				}

				// Description match.
				if ( str_contains( $desc_lower, $query_lower ) ) {
					$score += 10;
				}

				// Word-level matching for multi-word queries.
				$words = preg_split( '/[\s\-_\/]+/', $query_lower );
				if ( count( $words ) > 1 ) {
					$haystack = $name_lower . ' ' . $label_lower . ' ' . $desc_lower;
					foreach ( $words as $word ) {
						if ( '' !== $word && str_contains( $haystack, $word ) ) {
							$score += 5;
						}
					}
				}

				if ( $score > 0 ) {
					$scored[] = [
						'ability' => $ability,
						'score'   => $score,
					];
				}
			}

			usort(
				$scored,
				function ( $a, $b ) {
					return $b['score'] - $a['score'];
				}
			);

			$tools = array_map(
				function ( $item ) {
					return $item['ability'];
				},
				$scored
			);
		}

		$total  = count( $tools );
		$offset = ( $page - 1 ) * $per_page;
		$paged  = array_slice( $tools, $offset, $per_page );

		$results = [];
		foreach ( $paged as $ability ) {
			$results[] = self::format_tool_summary( $ability );
		}

		// Collect categories from the full (pre-paged) set.
		$categories = self::count_categories( $tools );

		return [
			'tools'      => $results,
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'categories' => $categories,
		];
	}

	/**
	 * Handle the execute-tool ability call.
	 *
	 * @param array $input The input parameters.
	 * @return array The result.
	 */
	public static function handle_execute_tool( array $input ): array {
		$tool_name  = $input['tool_name'] ?? '';
		$parameters = $input['parameters'] ?? null;
		$confirmed  = $input['confirmed'] ?? false;

		if ( '' === $tool_name ) {
			return [ 'error' => 'tool_name is required.' ];
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return [ 'error' => 'Abilities API not available.' ];
		}

		$ability = wp_get_ability( $tool_name );

		if ( ! $ability instanceof \WP_Ability ) {
			return [ 'error' => sprintf( 'Tool "%s" not found.', $tool_name ) ];
		}

		// Check tool permissions.
		$perms      = Settings::get( 'tool_permissions' ) ?: [];
		$permission = $perms[ $tool_name ] ?? 'auto';

		if ( 'disabled' === $permission ) {
			return [ 'error' => sprintf( 'Tool "%s" is disabled.', $tool_name ) ];
		}

		if ( 'confirm' === $permission && ! $confirmed ) {
			return [
				'needs_confirmation' => true,
				'tool_name'          => $tool_name,
				'message'            => sprintf(
					'The tool "%s" requires user confirmation before execution. Ask the user for permission, then call execute-tool again with confirmed: true.',
					$tool_name
				),
			];
		}

		// Execute the ability.
		$result = $ability->execute( $parameters );

		if ( is_wp_error( $result ) ) {
			return [
				'error' => $result->get_error_message(),
				'code'  => $result->get_error_code(),
			];
		}

		return [
			'success' => true,
			'tool'    => $tool_name,
			'result'  => $result,
		];
	}

	/**
	 * Check whether discovery mode should be active.
	 *
	 * @return bool
	 */
	public static function should_use_discovery_mode(): bool {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return false;
		}

		$mode = Settings::get( 'tool_discovery_mode' ) ?: 'auto';

		if ( 'never' === $mode ) {
			return false;
		}

		if ( 'always' === $mode ) {
			return true;
		}

		// Auto mode: activate when total tool count exceeds threshold.
		$threshold = (int) ( Settings::get( 'tool_discovery_threshold' ) ?: 20 );
		$all       = wp_get_abilities();

		return count( $all ) > $threshold;
	}

	/**
	 * Get the list of priority categories.
	 *
	 * @return string[]
	 */
	public static function get_priority_categories(): array {
		/**
		 * Filter the priority categories that are always loaded as direct tools.
		 *
		 * @param string[] $categories Default priority category slugs.
		 */
		return apply_filters( 'ai_agent_priority_categories', self::DEFAULT_PRIORITY_CATEGORIES );
	}

	/**
	 * Get the list of priority tool names (loaded directly regardless of category).
	 *
	 * @return string[]
	 */
	public static function get_priority_tools(): array {
		/**
		 * Filter the priority tool names that are always loaded as direct tools.
		 *
		 * @param string[] $tools Default priority tool names.
		 */
		return apply_filters( 'ai_agent_priority_tools', self::DEFAULT_PRIORITY_TOOLS );
	}

	/**
	 * Check if an ability matches a priority tool name.
	 *
	 * Matches both exact names (e.g. "wpcli/post/create") and suffix patterns
	 * so that registered names like "wpcli/wp-cli/post/create" also match
	 * a priority entry of "wpcli/post/create".
	 *
	 * @param \WP_Ability $ability        The ability to check.
	 * @param string[]    $priority_tools The priority tool names.
	 * @return bool
	 */
	private static function is_priority_tool( \WP_Ability $ability, array $priority_tools ): bool {
		$name = $ability->get_name();

		foreach ( $priority_tools as $tool ) {
			if ( $name === $tool ) {
				return true;
			}

			// Also match if the ability name ends with the priority tool's
			// path segments (e.g. "wpcli/wp-cli/post/create" ends with "post/create").
			$suffix = substr( $tool, strpos( $tool, '/' ) + 1 );
			if ( str_ends_with( $name, '/' . $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the system prompt section describing discovery mode.
	 *
	 * @return string
	 */
	public static function get_system_prompt_section(): string {
		$categories = self::get_discoverable_category_counts();

		if ( empty( $categories ) ) {
			return '';
		}

		$total = array_sum( $categories );

		$lines   = [];
		$lines[] = '## Tool Discovery';
		$lines[] = 'Your most-used tools (site management, content creation, media) are loaded directly — use them without discovery.';
		$lines[] = sprintf( '%d additional tools are available via `ai-agent/list-tools` (search by name or category) and `ai-agent/execute-tool`.', $total );
		$lines[] = 'Only use discovery if you need a tool not already in your loaded set.';

		return implode( "\n", $lines );
	}

	/**
	 * Get category counts for discoverable (non-priority) tools.
	 *
	 * @return array<string, int>
	 */
	public static function get_discoverable_category_counts(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		$all            = wp_get_abilities();
		$perms          = Settings::get( 'tool_permissions' ) ?: [];
		$priorities     = self::get_priority_categories();
		$priority_tools = self::get_priority_tools();

		$tools = [];
		foreach ( $all as $ability ) {
			$name = $ability->get_name();
			$perm = $perms[ $name ] ?? 'auto';

			if ( 'disabled' === $perm ) {
				continue;
			}

			if ( in_array( $ability->get_category(), $priorities, true ) ) {
				continue;
			}

			if ( self::is_priority_tool( $ability, $priority_tools ) ) {
				continue;
			}

			$tools[] = $ability;
		}

		return self::count_categories( $tools );
	}

	/**
	 * Format a single ability as a compact summary for the list-tools response.
	 *
	 * @param \WP_Ability $ability The ability to format.
	 * @return array
	 */
	private static function format_tool_summary( \WP_Ability $ability ): array {
		$schema = $ability->get_input_schema();
		$hint   = self::build_parameters_hint( $schema );

		$description = $ability->get_description();
		if ( strlen( $description ) > 150 ) {
			$description = substr( $description, 0, 147 ) . '...';
		}

		return [
			'name'            => $ability->get_name(),
			'label'           => $ability->get_label(),
			'description'     => $description,
			'category'        => $ability->get_category(),
			'parameters_hint' => $hint,
		];
	}

	/**
	 * Build a compact parameters hint from an input schema.
	 *
	 * @param array $schema The input schema.
	 * @return string A compact string like "command(string, required), working_dir(string)".
	 */
	private static function build_parameters_hint( array $schema ): string {
		if ( empty( $schema ) || empty( $schema['properties'] ) ) {
			return '(no parameters)';
		}

		$properties = $schema['properties'];

		if ( $properties instanceof \stdClass ) {
			return '(no parameters)';
		}

		$required = $schema['required'] ?? [];
		$parts    = [];

		foreach ( $properties as $name => $prop ) {
			$type = $prop['type'] ?? 'any';
			if ( is_array( $type ) ) {
				$type = implode( '|', $type );
			}
			$req     = in_array( $name, $required, true ) ? ', required' : '';
			$parts[] = sprintf( '%s(%s%s)', $name, $type, $req );
		}

		return implode( ', ', $parts );
	}

	/**
	 * Count abilities per category.
	 *
	 * @param \WP_Ability[] $tools The abilities to count.
	 * @return array<string, int>
	 */
	private static function count_categories( array $tools ): array {
		$counts = [];
		foreach ( $tools as $ability ) {
			$cat = $ability->get_category();
			if ( '' === $cat ) {
				$cat = 'uncategorized';
			}
			$counts[ $cat ] = ( $counts[ $cat ] ?? 0 ) + 1;
		}

		arsort( $counts );

		return $counts;
	}
}
