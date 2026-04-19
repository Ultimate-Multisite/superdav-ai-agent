<?php

declare(strict_types=1);
/**
 * Bootstrap Prompt — generates the onboarding system prompt for the auto-discovery session.
 *
 * This prompt is prepended to the regular system instruction for the first session
 * only. It instructs the AI to explore the site autonomously using available abilities,
 * infer the site's purpose/audience/style, store memories, queue RAG indexing, and
 * present findings with tailored starter prompts to the site owner.
 *
 * @package GratisAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BootstrapPrompt {

	/**
	 * Generate the onboarding bootstrap system prompt.
	 *
	 * Returns a prompt string that instructs the AI to:
	 * 1. Explore the site with available abilities (posts, pages, plugins, theme, WooCommerce).
	 * 2. Infer the site's purpose, target audience, and content style/tone.
	 * 3. Queue RAG indexing if available.
	 * 4. Store key memories for future sessions.
	 * 5. Present a concise summary and tailored starter prompts.
	 *
	 * @return string The bootstrap system prompt.
	 */
	public static function generate(): string {
		$site_url = get_site_url();
		$wp_path  = ABSPATH;

		return "## Onboarding Discovery Session\n\n"
			. 'This is a one-time auto-discovery run for a new WordPress site. Your job is to explore the site '
			. "autonomously, store what you learn in memory, and greet the site owner with a helpful summary.\n\n"
			. "**Site:** {$site_url}\n"
			. "**WordPress path:** {$wp_path}\n\n"
			. "## Discovery Steps (execute in order, without waiting for user input)\n\n"
			. "### Step 1 — Read recent content\n"
			. 'Use `ai-agent/get-posts` (or equivalent) to fetch recent posts and pages. '
			. "Note topics covered, writing style, tone (formal/casual/technical), and content categories.\n\n"
			. "### Step 2 — Check active plugins\n"
			. 'Use `gratis-ai-agent/get-plugins` to list installed and active plugins. '
			. 'Identify key capabilities: WooCommerce (e-commerce), Yoast/Rank Math (SEO), '
			. "contact form plugins, page builders, membership systems, LMS, booking systems, etc.\n\n"
			. "### Step 3 — Check active theme\n"
			. 'Use `gratis-ai-agent/run-php` to call `wp_get_theme()` and get the theme name, '
			. "description, and parent theme. Note the visual style implied by the theme name.\n\n"
			. "### Step 4 — Check WooCommerce products (if WooCommerce is active)\n"
			. 'If WooCommerce is detected in Step 2, use `gratis-ai-agent/get-posts` with '
			. "`post_type: product` to fetch a sample of products. Note product types and price ranges.\n\n"
			. "### Step 5 — Infer site purpose, audience, and style\n"
			. "Based on steps 1–4, infer:\n"
			. "- **Site purpose**: What this site is for (blog, e-commerce, portfolio, business, etc.)\n"
			. "- **Target audience**: Who visits this site\n"
			. "- **Content style**: The tone and style used (professional, casual, technical, creative)\n"
			. "- **Key capabilities**: What the site can do based on active plugins\n\n"
			. "### Step 6 — Store memories\n"
			. "Use `gratis-ai-agent/memory-save` to save the following (separate calls):\n"
			. "- Site purpose and type\n"
			. "- Target audience description\n"
			. "- Content style and tone\n"
			. "- List of key active plugins and their purpose\n"
			. "- Theme name\n\n"
			. "### Step 7 — Queue knowledge indexing (if available)\n"
			. 'Search for a RAG/knowledge indexing ability using `gratis-ai-agent/ability-search` '
			. "with query \"index knowledge\" or \"knowledge collection\". If found, trigger indexing.\n\n"
			. "### Step 8 — Present findings and starter prompts\n"
			. "Write a warm welcome message with your findings. Format it as:\n\n"
			. "```\n"
			. "Welcome! I've explored your site and here's what I found:\n\n"
			. "**About your site:** [2-3 sentence summary of purpose, audience, style]\n"
			. "**Key capabilities:** [active plugins relevant to the site]\n\n"
			. "Here are some things I can help you with right now:\n"
			. "[suggestion] [tailored to the site type and plugins]\n"
			. "[suggestion] [tailored to the site type and plugins]\n"
			. "[suggestion] [tailored to the site type and plugins]\n"
			. "[suggestion] [tailored to the site type and plugins]\n"
			. "```\n\n"
			. "## Discovery Rules\n"
			. "- Execute all steps WITHOUT waiting for user input — this is an automated discovery run.\n"
			. "- If a tool call fails, skip that step and continue.\n"
			. "- Steps 1–7 are preparation; Step 8 is your ONLY visible output to the user.\n"
			. "- Make starter suggestions SPECIFIC to what you discovered (not generic).\n"
			. "- If WooCommerce is active, include at least one product/sales suggestion.\n"
			. "- Keep the welcome message concise — 10 lines maximum for the summary section.\n"
			. '- Do NOT include tool call logs or exploration notes in the visible output.';
	}
}
