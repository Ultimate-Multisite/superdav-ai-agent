<?php

declare(strict_types=1);
/**
 * Auto-discovery layer for the AI agent's tool catalog.
 *
 * Two-tier design:
 *
 *   • Tier 1 (always loaded with full schemas) — a small curated cold-start
 *     list unioned with the most-frequently-used abilities (top-N from
 *     {@see AbilityUsageTracker}). The two meta-tools below are also always
 *     part of Tier 1.
 *
 *   • Tier 2 (name + one-line description in the system prompt) — every
 *     other registered ability, regardless of which plugin registered it.
 *     The model fetches the full schema for any of them on demand via the
 *     {@see ability-search} meta-tool.
 *
 * Two meta-tools:
 *
 *   • gratis-ai-agent/ability-search — keyword / select: / +substr search
 *     across the registered ability catalog. Returns full input/output
 *     schemas inline so the agent gets everything it needs in one call.
 *
 *   • gratis-ai-agent/ability-call — execute any ability by id with an
 *     `arguments` object. (The bridge for Tier 2 abilities the model can't
 *     call directly because their FunctionDeclaration wasn't sent in the
 *     current turn.)
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tools;

use GratisAiAgent\Core\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolDiscovery {

	/**
	 * Curated cold-start Tier 1 list. These are the abilities the agent
	 * needs on its very first turn before any usage history exists. The
	 * usage tracker can grow this list, but these names are always present.
	 *
	 * Keep this list short — every entry burns prompt tokens on every turn.
	 *
	 * @var string[]
	 */
	public const DEFAULT_TIER_1 = array(
		'gratis-ai-agent/ability-search',
		'gratis-ai-agent/ability-call',
		// Memory + skill + knowledge are registered under the `ai-agent/`
		// prefix by their feature classes, not under `gratis-ai-agent/`.
		'ai-agent/memory-save',
		'ai-agent/memory-list',
		'ai-agent/skill-load',
		'ai-agent/knowledge-search',
		'gratis-ai-agent/get-plugins',
		'gratis-ai-agent/get-themes',
		'gratis-ai-agent/file-read',
		'gratis-ai-agent/file-list',
		'gratis-ai-agent/db-query',
		// WP-CLI is the proper tool for admin commands like `wp site list`,
		// `wp plugin list`, etc. Registered by the cli-abilities-bridge plugin.
		'wp-cli/execute',
		// `create-post` is the single most common WordPress operation the
		// agent is ever asked for. Keeping it in cold-start so smaller
		// local models don't fall back to `run-php` + positional-arg
		// guesswork on `wp_insert_post`. See issue #831.
		'ai-agent/create-post',
	);

	/**
	 * Hard cap on Tier 1 size (curated + tracked) excluding the two
	 * meta-tools, which are always added on top.
	 */
	public const MAX_TIER_1 = 15;

	/**
	 * The two meta-tools — always present in Tier 1.
	 *
	 * @var string[]
	 */
	private const META_TOOLS = array(
		'gratis-ai-agent/ability-search',
		'gratis-ai-agent/ability-call',
	);

	/**
	 * Register the meta-tool abilities.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Register the ability-search and ability-call meta-tools.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/ability-search',
			array(
				'label'               => __( 'Search Abilities', 'gratis-ai-agent' ),
				'description'         => __( 'Search the full catalog of registered WordPress abilities and return matching ids together with their full input/output schemas. Use this whenever you need an ability that is not already loaded in your tool list. Query forms: bare keywords for ranked search ("create site"), `select:foo,bar` to fetch specific abilities by id, or `+substr keyword` to require a substring before ranking.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'       => array(
							'type'        => 'string',
							'description' => 'Keywords, "select:id1,id2", or "+substr keyword". Required.',
						),
						'max_results' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of abilities to return (default 10, hard max 25).',
							'default'     => 10,
						),
					),
					'required'   => array( 'query' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'idempotent'  => true,
						'destructive' => false,
					),
				),
				'execute_callback'    => array( __CLASS__, 'handle_ability_search' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		wp_register_ability(
			'gratis-ai-agent/ability-call',
			array(
				'label'               => __( 'Call Ability', 'gratis-ai-agent' ),
				'description'         => __( 'Execute any registered ability by its id, passing the matching arguments object. Use ability-search first if you do not already know the input schema.', 'gratis-ai-agent' ),
				'category'            => 'gratis-ai-agent',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'ability'   => array(
							'type'        => 'string',
							'description' => 'The ability id to invoke (e.g. "multisite-ultimate/site-create-item").',
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => 'Arguments object that matches the ability\'s input schema.',
						),
					),
					'required'   => array( 'ability' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'idempotent'  => false,
						'destructive' => false,
					),
				),
				'execute_callback'    => array( __CLASS__, 'handle_ability_call' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	// ─── Tier-1 selection ────────────────────────────────────────────────

	/**
	 * Return the list of ability names that should be loaded as Tier 1 for
	 * this run. This is the curated cold-start list unioned with the
	 * top-N most-frequently used abilities, capped at MAX_TIER_1, plus the
	 * two meta-tools.
	 *
	 * Disabled or non-existent abilities are filtered out.
	 *
	 * @return string[]
	 */
	public static function tier_1_for_run(): array {
		$tracked = AbilityUsageTracker::top( self::MAX_TIER_1 );
		$curated = self::DEFAULT_TIER_1;

		// Tracked first (so the most-used floats to the top of the list);
		// curated entries fill remaining slots up to the cap.
		$names = array_values( array_unique( array_merge( $tracked, $curated ) ) );

		// Hard cap, then re-add meta-tools so they survive truncation.
		if ( count( $names ) > self::MAX_TIER_1 ) {
			$names = array_slice( $names, 0, self::MAX_TIER_1 );
		}
		foreach ( self::META_TOOLS as $meta ) {
			if ( ! in_array( $meta, $names, true ) ) {
				$names[] = $meta;
			}
		}

		// Filter against actually-registered, non-disabled abilities so
		// callers don't have to recheck.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return array();
		}
		$perms  = self::tool_permissions();
		$result = array();
		foreach ( $names as $name ) {
			if ( 'disabled' === ( $perms[ $name ] ?? 'auto' ) ) {
				continue;
			}
			// @phpstan-ignore-next-line
			$ability = wp_get_ability( $name );
			if ( $ability instanceof \WP_Ability ) {
				$result[] = $name;
			}
		}

		return $result;
	}

	// ─── Tier-2 manifest ─────────────────────────────────────────────────

	/**
	 * Build the Tier-2 manifest section that is injected into the system
	 * prompt every turn. Lists every visible ability that is NOT in Tier 1
	 * by id + one-line description, grouped by category.
	 *
	 * @return string An empty string when there are no Tier-2 abilities.
	 */
	public static function build_manifest_section(): string {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return '';
		}

		$tier_1_set = array_flip( self::tier_1_for_run() );
		$perms      = self::tool_permissions();
		$by_cat     = array();

		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();

			if ( isset( $tier_1_set[ $name ] ) ) {
				continue;
			}

			$meta = $ability->get_meta();
			if ( ! empty( $meta['ai_hidden'] ) ) {
				continue;
			}

			if ( 'disabled' === ( $perms[ $name ] ?? 'auto' ) ) {
				continue;
			}

			$cat = $ability->get_category();
			if ( '' === $cat ) {
				$cat = 'uncategorized';
			}

			$desc = (string) $ability->get_description();
			if ( strlen( $desc ) > 140 ) {
				$desc = substr( $desc, 0, 137 ) . '...';
			}

			// Pull out the `required` field from the input schema so the
			// model sees the minimum arg shape inline and stops guessing
			// empty arguments. Cheap (~5 tokens per ability with required
			// fields) but a major win for weaker models.
			// @phpstan-ignore-next-line — get_input_schema() exists at runtime in WP 7.0.
			$schema_required = array();
			$schema          = $ability->get_input_schema();
			if ( is_array( $schema ) && isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
				$schema_required = array_values(
					array_filter(
						$schema['required'],
						static function ( $r ) {
							return is_string( $r ) && '' !== $r;
						}
					)
				);
			}

			$by_cat[ $cat ][] = array(
				'name'     => $name,
				'desc'     => $desc,
				'required' => $schema_required,
			);
		}

		if ( empty( $by_cat ) ) {
			return '';
		}

		ksort( $by_cat );

		$instructions  = self::usage_instructions();
		$category_meta = self::category_metadata();

		$lines   = array();
		$lines[] = '## Available Abilities';
		$lines[] = 'The abilities listed below are NOT loaded as direct tools — call `gratis-ai-agent/ability-search` with a keyword query (or `select:id1,id2`) to retrieve their full schemas, then call `gratis-ai-agent/ability-call` to invoke them.';
		$lines[] = '';

		foreach ( $by_cat as $cat => $entries ) {
			$heading = isset( $category_meta[ $cat ]['label'] )
				? $category_meta[ $cat ]['label'] . " (`{$cat}`)"
				: "`{$cat}`";
			$lines[] = "### {$heading}";

			if ( ! empty( $instructions[ $cat ] ) ) {
				$lines[] = $instructions[ $cat ];
			} elseif ( ! empty( $category_meta[ $cat ]['description'] ) ) {
				$lines[] = $category_meta[ $cat ]['description'];
			}

			usort(
				$entries,
				static function ( $a, $b ) {
					return strcmp( $a['name'], $b['name'] );
				}
			);

			foreach ( $entries as $e ) {
				$line = "- `{$e['name']}` — {$e['desc']}";
				if ( ! empty( $e['required'] ) ) {
					$line .= ' Required: ' . implode( ', ', $e['required'] );
				}
				$lines[] = $line;
			}
			$lines[] = '';
		}

		// Append any cached schemas the agent fetched on a previous turn so
		// it can re-use them without spending another search call.
		$cache_section = self::recently_fetched_section();
		if ( '' !== $cache_section ) {
			$lines[] = $cache_section;
		}

		return rtrim( implode( "\n", $lines ) );
	}

	/**
	 * Per-category usage instructions, supplied by plugin authors via the
	 * `gratis_ai_agent_ability_usage_instructions` filter. Maps category
	 * slug => prose blurb the model sees in the manifest.
	 *
	 * @return array<string, string>
	 */
	public static function usage_instructions(): array {
		/**
		 * Filter the prose usage instructions injected into the system
		 * prompt under each ability category. Maps category slug => string.
		 *
		 * @param array<string, string> $blocks
		 */
		$blocks = (array) apply_filters( 'gratis_ai_agent_ability_usage_instructions', array() );
		$out    = array();
		foreach ( $blocks as $cat => $text ) {
			if ( is_string( $cat ) && '' !== $cat && is_string( $text ) && '' !== $text ) {
				$out[ $cat ] = $text;
			}
		}
		return $out;
	}

	// ─── ability-search handler ──────────────────────────────────────────

	/**
	 * Handle a call to gratis-ai-agent/ability-search.
	 *
	 * @param array<string, mixed> $input The input arguments from the model.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function handle_ability_search( array $input ) {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_Error( 'api_unavailable', __( 'Abilities API not available.', 'gratis-ai-agent' ) );
		}

		$query_raw = isset( $input['query'] ) ? trim( (string) $input['query'] ) : '';
		// @phpstan-ignore-next-line
		$max_results = isset( $input['max_results'] ) ? (int) $input['max_results'] : 10;
		$max_results = max( 1, min( 25, $max_results ) );

		$candidates = self::visible_abilities();

		// `select:foo,bar` exact-id form.
		if ( str_starts_with( $query_raw, 'select:' ) ) {
			$ids   = array_filter( array_map( 'trim', explode( ',', substr( $query_raw, 7 ) ) ) );
			$found = array();
			foreach ( $ids as $id ) {
				foreach ( $candidates as $a ) {
					if ( $a->get_name() === $id ) {
						$found[] = $a;
						break;
					}
				}
			}
			return self::format_search_response( $query_raw, $found, count( $found ) );
		}

		// `+substr keyword` required-substring form.
		$require = '';
		$query   = $query_raw;
		if ( str_starts_with( $query, '+' ) ) {
			$parts   = preg_split( '/\s+/', $query, 2 );
			$parts   = is_array( $parts ) ? $parts : array( $query );
			$require = strtolower( substr( (string) $parts[0], 1 ) );
			$query   = isset( $parts[1] ) ? (string) $parts[1] : '';
		}

		if ( '' !== $require ) {
			$candidates = array_values(
				array_filter(
					$candidates,
					static function ( $a ) use ( $require ) {
						return str_contains( strtolower( $a->get_name() . ' ' . $a->get_label() . ' ' . $a->get_description() ), $require );
					}
				)
			);
		}

		// If the query is empty after handling +require, just return the
		// filtered list (no scoring).
		if ( '' === trim( $query ) ) {
			$slice = array_slice( $candidates, 0, $max_results );
			return self::format_search_response( $query_raw, $slice, count( $candidates ) );
		}

		$ranked = self::rank( $candidates, $query );
		$slice  = array_slice( $ranked, 0, $max_results );

		return self::format_search_response( $query_raw, $slice, count( $ranked ) );
	}

	/**
	 * Score and sort abilities by how well they match a free-text query.
	 * Same scoring rules as the legacy list-tools fuzzy search.
	 *
	 * @param \WP_Ability[] $abilities Candidate abilities to score.
	 * @param string        $query     The free-text query to score against.
	 * @return \WP_Ability[]
	 */
	private static function rank( array $abilities, string $query ): array {
		$q = strtolower( $query );

		$split = preg_split( '/[\s\-_\/]+/', $q );
		$words = array_values(
			array_filter(
				is_array( $split ) ? $split : array(),
				static function ( $w ) {
					return '' !== $w;
				}
			)
		);

		$scored = array();
		foreach ( $abilities as $ability ) {
			$name  = strtolower( $ability->get_name() );
			$label = strtolower( $ability->get_label() );
			$desc  = strtolower( $ability->get_description() );

			$score = 0;
			if ( $name === $q ) {
				$score += 100;
			} elseif ( str_contains( $name, $q ) ) {
				$score += 50;
			}
			if ( str_contains( $label, $q ) ) {
				$score += 30;
			}
			if ( str_contains( $desc, $q ) ) {
				$score += 10;
			}

			if ( count( $words ) > 1 ) {
				$haystack = $name . ' ' . $label . ' ' . $desc;
				foreach ( $words as $w ) {
					if ( str_contains( $haystack, $w ) ) {
						$score += 5;
					}
				}
			}

			if ( $score > 0 ) {
				$scored[] = array(
					'ability' => $ability,
					'score'   => $score,
				);
			}
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		return array_map(
			static function ( $row ) {
				return $row['ability'];
			},
			$scored
		);
	}

	/**
	 * Build the response payload for ability-search, caching schemas as we
	 * format them so subsequent turns can re-inject them via
	 * recently_fetched_section().
	 *
	 * @param string        $query     The original query string for echo.
	 * @param \WP_Ability[] $abilities The page of results.
	 * @param int           $total     Total matches before slicing.
	 * @return array<string, mixed>
	 */
	private static function format_search_response( string $query, array $abilities, int $total ): array {
		$results = array();
		foreach ( $abilities as $ability ) {
			$name   = $ability->get_name();
			$schema = self::serialise_schema( $ability->get_input_schema() );
			// @phpstan-ignore-next-line — get_output_schema() exists at runtime in WP 7.0.
			$out = self::serialise_schema( $ability->get_output_schema() );

			self::cache_schema( $name );

			$results[] = array(
				'id'            => $name,
				'label'         => $ability->get_label(),
				'description'   => $ability->get_description(),
				'category'      => $ability->get_category(),
				'input_schema'  => $schema,
				'output_schema' => $out,
			);
		}

		return array(
			'query'   => $query,
			'total'   => $total,
			'count'   => count( $results ),
			'results' => $results,
			'hint'    => 'Use gratis-ai-agent/ability-call with the chosen `id` and an `arguments` object that matches `input_schema`.',
		);
	}

	// ─── ability-call handler ────────────────────────────────────────────

	/**
	 * Handle a call to gratis-ai-agent/ability-call.
	 *
	 * @param array<string, mixed> $input The input arguments from the model.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function handle_ability_call( array $input ) {
		$ability_id = isset( $input['ability'] ) ? (string) $input['ability'] : '';
		$args       = $input['arguments'] ?? array();

		if ( '' === $ability_id ) {
			return new WP_Error( 'invalid_argument', __( 'ability is required.', 'gratis-ai-agent' ) );
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'api_unavailable', __( 'Abilities API not available.', 'gratis-ai-agent' ) );
		}

		// @phpstan-ignore-next-line
		$ability = wp_get_ability( $ability_id );
		if ( ! $ability instanceof \WP_Ability ) {
			return new WP_Error(
				'ability_not_found',
				sprintf(
					/* translators: %s: ability id */
					__( 'Ability "%s" not found.', 'gratis-ai-agent' ),
					$ability_id
				)
			);
		}

		$perms = self::tool_permissions();
		if ( 'disabled' === ( $perms[ $ability_id ] ?? 'auto' ) ) {
			return new WP_Error(
				'ability_disabled',
				sprintf(
					/* translators: %s: ability id */
					__( 'Ability "%s" is disabled.', 'gratis-ai-agent' ),
					$ability_id
				)
			);
		}

		// Normalize the arguments to a plain PHP associative array.
		//
		// Three cases handled:
		// 1. JSON string — some AI providers / SDK versions return nested
		// tool-call arguments as a raw JSON string rather than a parsed
		// object (e.g. when the outer layer is parsed but the inner
		// `arguments` value is left as a string). Decode it explicitly
		// so the args are never silently dropped.
		// 2. stdClass / array — recursively convert stdClass objects to
		// arrays via a json round-trip.  Guard against wp_json_encode()
		// failure (e.g. invalid UTF-8 in content) by falling back to the
		// original array rather than silently losing all arguments.
		// 3. Anything else (null, int, …) — treat as no arguments.
		if ( is_string( $args ) && '' !== $args ) {
			$decoded = json_decode( $args, true );
			$args    = is_array( $decoded ) ? $decoded : array();
		} elseif ( $args instanceof \stdClass || is_array( $args ) ) {
			$encoded = wp_json_encode( $args );
			if ( false !== $encoded ) {
				$decoded = json_decode( $encoded, true );
				$args    = is_array( $decoded ) ? $decoded : ( is_array( $args ) ? $args : array() );
			} elseif ( ! is_array( $args ) ) {
				// wp_json_encode failed (e.g. invalid UTF-8); args was stdClass.
				// Shallow-cast to array as last resort — better than losing everything.
				$args = (array) $args;
			}
		} else {
			$args = array();
		}

		// Pass an empty assoc array (not null) so parameterless abilities
		// with `type: object` schemas pass input validation.
		$input_data = $args;

		// Diagnostic: log large payloads and empty argument objects to help
		// diagnose GH#1113 (arguments silently dropped for large content).
		// Logs are written only when WP_DEBUG_LOG is enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$raw_args    = $input['arguments'] ?? null;
			$raw_type    = gettype( $raw_args );
			$raw_size    = is_string( $raw_args )
				? strlen( $raw_args )
				: strlen( (string) wp_json_encode( $raw_args ) );
			$result_keys = array_keys( $input_data );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[Gratis AI Agent] ability-call: ability=%s raw_type=%s raw_size=%d normalized_keys=[%s]',
					$ability_id,
					$raw_type,
					$raw_size,
					implode( ',', $result_keys )
				)
			);
		}

		// @phpstan-ignore-next-line — execute() exists at runtime in WP 7.0.
		$result = $ability->execute( $input_data );

		if ( is_wp_error( $result ) ) {
			$error_code = (string) $result->get_error_code();

			$payload = array(
				'success' => false,
				'ability' => $ability_id,
				'error'   => $result->get_error_message(),
				'code'    => $error_code,
			);

			// Inline the input_schema on validation errors so the model
			// can self-correct without making another search call. Also
			// synthesise an example_arguments stub and pull the specific
			// missing field name(s) out of the error message — gives the
			// model a copy-paste path to a valid call.
			if ( 'ability_invalid_input' === $error_code ) {
				// @phpstan-ignore-next-line — get_input_schema() exists at runtime in WP 7.0.
				$schema                             = $ability->get_input_schema();
				$payload['input_schema']            = $schema;
				$payload['missing_required_fields'] = SchemaExampleBuilder::extract_missing_required( (string) $result->get_error_message() );
				$payload['example_arguments']       = SchemaExampleBuilder::build_example( $schema );
				$payload['hint']                    = 'Copy `example_arguments`, replace each `<placeholder>` with a real value, then call ability-call again. Do not retry with empty arguments.';
				ModelHealthTracker::record_validation_error();
			}

			// Per-call spin detection: after the second identical failure,
			// inject a hard stop-and-rethink nudge.
			$count = \GratisAiAgent\Core\IdenticalFailureTracker::record( $ability_id, $input_data, $error_code );
			if ( \GratisAiAgent\Core\IdenticalFailureTracker::should_nudge( $count ) ) {
				$schema_for_nudge = $payload['input_schema'] ?? $ability->get_input_schema();
				$payload['nudge'] = \GratisAiAgent\Core\IdenticalFailureTracker::nudge_message( $ability_id, $schema_for_nudge );
				ModelHealthTracker::record_nudge();
			}

			return $payload;
		}

		AbilityUsageTracker::record( $ability_id );
		ModelHealthTracker::record_success();

		return array(
			'ability' => $ability_id,
			'success' => true,
			'result'  => $result,
		);
	}

	// ─── Schema cache ────────────────────────────────────────────────────

	/**
	 * Per-request schema cache. Populated as ability-search returns
	 * schemas; consumed by recently_fetched_section() when building the
	 * next system prompt.
	 *
	 * @var array<string, true>
	 */
	private static array $schema_cache = array();

	private static function cache_schema( string $ability_id ): void {
		self::$schema_cache[ $ability_id ] = true;
	}

	/**
	 * Build a "Recently fetched ability schemas" block for re-injection.
	 * Empty when no schemas have been fetched yet this request.
	 *
	 * @return string
	 */
	public static function recently_fetched_section(): string {
		if ( empty( self::$schema_cache ) ) {
			return '';
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return '';
		}

		$lines = array( '## Recently fetched ability schemas', 'These schemas have already been retrieved this session — call them via `ability-call` directly without searching again.', '' );
		foreach ( array_keys( self::$schema_cache ) as $name ) {
			// @phpstan-ignore-next-line
			$ability = wp_get_ability( $name );
			if ( ! $ability instanceof \WP_Ability ) {
				continue;
			}
			$schema  = self::serialise_schema( $ability->get_input_schema() );
			$json    = (string) wp_json_encode( $schema );
			$lines[] = "- `{$name}` input: `{$json}`";
		}

		return rtrim( implode( "\n", $lines ) );
	}

	/**
	 * Reset the schema cache. Tests + AgentLoop use this between requests.
	 *
	 * @return void
	 */
	public static function reset_schema_cache(): void {
		self::$schema_cache = array();
	}

	// ─── Helpers ─────────────────────────────────────────────────────────

	/**
	 * Tool permission map from settings (legacy `disabled_abilities` is no
	 * longer consulted; only `tool_permissions`).
	 *
	 * @return array<string, string>
	 */
	private static function tool_permissions(): array {
		$perms = Settings::instance()->get( 'tool_permissions' );
		if ( ! is_array( $perms ) ) {
			return array();
		}
		$out = array();
		foreach ( $perms as $name => $level ) {
			if ( is_string( $name ) && is_string( $level ) ) {
				$out[ $name ] = $level;
			}
		}
		return $out;
	}

	/**
	 * All registered abilities the current user can see — `ai_hidden` and
	 * `disabled` entries removed.
	 *
	 * @return \WP_Ability[]
	 */
	private static function visible_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$perms = self::tool_permissions();
		$out   = array();
		foreach ( wp_get_abilities() as $ability ) {
			$meta = $ability->get_meta();
			if ( ! empty( $meta['ai_hidden'] ) ) {
				continue;
			}
			if ( 'disabled' === ( $perms[ $ability->get_name() ] ?? 'auto' ) ) {
				continue;
			}
			$out[] = $ability;
		}
		return $out;
	}

	/**
	 * Recursively coerce stdClass nodes to assoc arrays so JSON encoding
	 * always emits a clean object structure.
	 *
	 * @param mixed $schema The schema node to walk.
	 * @return mixed
	 */
	private static function serialise_schema( $schema ) {
		if ( $schema instanceof \stdClass ) {
			$schema = (array) $schema;
		}
		if ( is_array( $schema ) ) {
			foreach ( $schema as $k => $v ) {
				$schema[ $k ] = self::serialise_schema( $v );
			}
		}
		return $schema;
	}

	/**
	 * Look up category metadata (label + description) from the abilities
	 * registry. Falls back to an empty array when the registry is missing.
	 *
	 * @return array<string, array{label?:string,description?:string}>
	 */
	private static function category_metadata(): array {
		if ( ! function_exists( 'wp_get_ability_categories' ) ) {
			return array();
		}
		$out = array();
		// @phpstan-ignore-next-line — wp_get_ability_categories() is WP 7.0.
		foreach ( wp_get_ability_categories() as $cat ) {
			$slug = method_exists( $cat, 'get_slug' ) ? (string) $cat->get_slug() : ( method_exists( $cat, 'get_name' ) ? (string) $cat->get_name() : '' );
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = array(
				'label'       => method_exists( $cat, 'get_label' ) ? (string) $cat->get_label() : $slug,
				'description' => method_exists( $cat, 'get_description' ) ? (string) $cat->get_description() : '',
			);
		}
		return $out;
	}
}
