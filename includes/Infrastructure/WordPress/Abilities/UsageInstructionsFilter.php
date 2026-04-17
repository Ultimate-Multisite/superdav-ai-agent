<?php
/**
 * Handler: seed default ability usage instructions for auto-discovery.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Infrastructure\WordPress\Abilities;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects baseline per-category usage instructions into the auto-discovery
 * manifest served to the agent. Other plugins can override or extend these
 * by hooking into the same filter at a later priority — we only fill in
 * categories the consumer hasn't already populated.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class UsageInstructionsFilter {

	/**
	 * Default usage guidance per ability category.
	 *
	 * Kept as a class constant (rather than built inside the filter body) so
	 * companion tests can assert defaults without invoking the filter chain.
	 *
	 * @var array<string,string>
	 */
	private const DEFAULTS = array(
		'gratis-ai-agent'    => 'Built-in agent abilities — memory, knowledge, file ops, image/SEO/analytics helpers, WP/site management, and the discovery meta-tools (`ability-search`, `ability-call`).',
		'multisite-ultimate' => 'CRUD for the Multisite Ultimate WaaS platform: subsites, customers, memberships, products, payments, domains, broadcasts, and webhooks. **Prefer these abilities over `db-query`/`run-php` when creating or managing subsites and related entities.**',
		'site'               => 'Built-in WordPress core abilities for posts, pages, media, options, taxonomies, and site information.',
		'user'               => 'Built-in WordPress core abilities for user lookup and management.',
		'ai-experiments'     => 'WordPress core AI experiments — prompt helpers, image analysis, etc.',
		'mcp-adapter'        => 'MCP-adapter introspection abilities for browsing other registered abilities.',
		'wpcli'              => 'WP-CLI bridge abilities — every WP-CLI command exposed as an ability. Use these for site/post/option/theme/plugin operations when no more specific ability exists.',
	);

	/**
	 * Merge the default instructions into the filtered `$blocks` map.
	 *
	 * Values already present in `$blocks` win — this filter is additive only.
	 * Non-string values inherited from upstream filters are coerced to string
	 * so the contract published by this filter (map of string to string) holds
	 * for downstream consumers, regardless of what earlier hooks injected.
	 *
	 * @param mixed $blocks Existing category => instruction blocks (may not be array).
	 * @return array<string,string> Blocks with defaults backfilled.
	 */
	#[Filter( tag: 'gratis_ai_agent_ability_usage_instructions', priority: 10 )]
	public function provide_defaults( mixed $blocks ): array {
		$merged = array();

		if ( is_array( $blocks ) ) {
			foreach ( $blocks as $cat => $text ) {
				if ( is_string( $cat ) ) {
					$merged[ $cat ] = is_string( $text ) ? $text : (string) $text;
				}
			}
		}

		foreach ( self::DEFAULTS as $cat => $text ) {
			if ( ! isset( $merged[ $cat ] ) ) {
				$merged[ $cat ] = $text;
			}
		}

		return $merged;
	}
}
