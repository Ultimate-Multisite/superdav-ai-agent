<?php

declare(strict_types=1);
/**
 * Auto-inject relevant skill content into the system prompt based on
 * the user's message. Mirrors the knowledge-base RAG injection pattern
 * but uses keyword matching instead of vector search.
 *
 * @package GratisAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Models\Skill;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SkillAutoInjector {

	/**
	 * Maximum number of skills to inject per prompt to limit token usage.
	 *
	 * Capped at 1: weak models can only effectively follow one skill guide
	 * at a time. Two guides were found to cause instruction conflicts and
	 * confused execution.
	 */
	private const MAX_INJECTED_SKILLS = 1;

	/**
	 * Keyword-to-skill trigger map.
	 *
	 * Keys are regex patterns matched against the user message (case-insensitive).
	 * Values are skill slugs from the skills table.
	 *
	 * @var array<string, string>
	 */
	private const TRIGGER_MAP = [
		'/\b(?:create|build|make|write|generate|add)\b.*\b(?:page|pages|post|posts|blog|article|content|landing|homepage|layout)\b/i' => 'gutenberg-blocks',
		'/\b(?:page|pages|landing|homepage|layout|column|columns|hero|section|block|blocks|gutenberg)\b/i'                            => 'gutenberg-blocks',
		'/\b(?:woocommerce|product|products|store|shop|order|orders|cart|checkout|coupon)\b/i'                                         => 'woocommerce',
		'/\b(?:seo|ranking|rankings|meta\s*tags?|meta\s*description|sitemap|search\s*engine|keyword|keywords)\b/i'                     => 'seo-optimization',
		'/\b(?:full\s*site\s*edit|fse|block\s*theme|template\s*part|site\s*editor|theme\.json)\b/i'                                    => 'full-site-editing',
		'/\b(?:multisite|network|subsite|subsites|sub-site)\b/i'                                                                       => 'multisite-management',
		'/\b(?:content\s*market|editorial|content\s*strateg|publish\s*schedule|content\s*audit)\b/i'                                    => 'content-marketing',
		'/\b(?:analytic|report|metric|dashboard|performance\s*report|growth)\b/i'                                                      => 'analytics-reporting',
		'/\b(?:debug|error|broken|fix|troubleshoot|white\s*screen|500|fatal|crash|slow)\b/i'                                           => 'site-troubleshooting',
	];

	/**
	 * Analyze the user message and return matching skill content to inject.
	 *
	 * @param string $user_message The user's chat message.
	 * @return string Formatted skill content for system prompt injection, or empty string.
	 */
	public static function inject_for_message( string $user_message ): string {
		if ( '' === trim( $user_message ) ) {
			return '';
		}

		$matched_slugs = self::match_skills( $user_message );

		if ( empty( $matched_slugs ) ) {
			return '';
		}

		$sections = [];

		foreach ( $matched_slugs as $slug ) {
			$content = Skill::get_content_by_slug( $slug );

			if ( null === $content || '' === $content ) {
				continue;
			}

			$sections[] = $content;
		}

		if ( empty( $sections ) ) {
			return '';
		}

		return "## Active Skill Guide\n"
			. 'The following skill guide has been auto-loaded based on your request. '
			. "Follow these instructions for the best results.\n\n"
			. implode( "\n\n---\n\n", $sections );
	}

	/**
	 * Get a context-aware skill hint for strong models.
	 *
	 * Strong models receive the lean skill index (~15 tok/skill) and are
	 * expected to call `ai-agent/skill-load` on their own when needed.
	 * This method supplements the index with a targeted, one-line hint
	 * pointing at which skill(s) are particularly relevant to the current
	 * request — helping the model decide whether to load before proceeding,
	 * without injecting the full 1 500-3 000 token guide.
	 *
	 * Returns an empty string when no trigger pattern matches the message.
	 *
	 * @param string $user_message The user's chat message.
	 * @return string Inline hint text to append after the skill index, or empty string.
	 */
	public static function get_index_description( string $user_message ): string {
		if ( '' === trim( $user_message ) ) {
			return '';
		}

		$matched_slugs = self::match_skills( $user_message );

		if ( empty( $matched_slugs ) ) {
			return '';
		}

		return '> **Skill hint:** the following skill guide(s) are likely relevant to this request — '
			. 'call `ai-agent/skill-load` before proceeding: `'
			. implode( '`, `', $matched_slugs )
			. '`';
	}

	/**
	 * Match user message against the trigger map and return unique skill slugs.
	 *
	 * Uses a key-indexed set for deduplication so PHPStan can track the element
	 * type correctly across loop iterations without false "always false" warnings.
	 *
	 * @param string $user_message The user's chat message.
	 * @return list<string> Matched skill slugs (max MAX_INJECTED_SKILLS).
	 */
	private static function match_skills( string $user_message ): array {
		/** @var array<string, true> $seen */
		$seen    = [];
		$matched = [];

		foreach ( self::TRIGGER_MAP as $pattern => $slug ) {
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}

			if ( preg_match( $pattern, $user_message ) ) {
				$seen[ $slug ] = true;
				$matched[]     = $slug;

				if ( count( $matched ) >= self::MAX_INJECTED_SKILLS ) {
					break;
				}
			}
		}

		return $matched;
	}
}
