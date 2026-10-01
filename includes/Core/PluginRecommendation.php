<?php

declare(strict_types=1);

namespace SdAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable value object describing a plugin recommendation.
 *
 * @since 1.11.0
 */
final class PluginRecommendation {

	/**
	 * @since 1.11.0
	 *
	 * @param string   $name                Human-readable plugin name.
	 * @param string   $plugin_slug         WordPress.org plugin slug.
	 * @param string[] $blocks              Block names this plugin registers.
	 * @param string   $guidance            System-prompt guidance.
	 * @param string[] $html_patterns       Regex patterns matched against core/html innerHTML.
	 * @param string   $html_policy_message Message returned when an html_pattern matches.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $plugin_slug,
		public readonly array $blocks = [],
		public readonly string $guidance = '',
		public readonly array $html_patterns = [],
		public readonly string $html_policy_message = '',
	) {}
}
