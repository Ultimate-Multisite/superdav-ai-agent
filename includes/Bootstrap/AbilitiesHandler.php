<?php
/**
 * DI handler that registers all WordPress Abilities.
 *
 * Replaces the 35 inline `XxxAbilities::register()` calls in
 * `gratis-ai-agent.php` with a single DI-managed handler. Each ability
 * class's `register_abilities()` method is called on the
 * `wp_abilities_api_init` hook — bypassing the now-removed `register()`
 * stub layer since the DI system handles hook attachment directly.
 *
 * Also owns the `init`-time hooks previously embedded in the three ability
 * classes that needed extra wiring beyond abilities registration:
 *  - CustomPostTypeAbilities::restore_persisted_post_types() at priority 5
 *  - CustomTaxonomyAbilities::restore_persisted_taxonomies() at priority 5
 *  - PluginSandbox::auto_deactivate_fatal_plugins() at priority 10
 *
 * @package GratisAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\Abilities\AiImageAbilities;
use GratisAiAgent\Abilities\BlockAbilities;
use GratisAiAgent\Abilities\ContentAbilities;
use GratisAiAgent\Abilities\CustomPostTypeAbilities;
use GratisAiAgent\Abilities\CustomTaxonomyAbilities;
use GratisAiAgent\Abilities\DatabaseAbilities;
use GratisAiAgent\Abilities\DesignSystemAbilities;
use GratisAiAgent\Abilities\EditorialAbilities;
use GratisAiAgent\Abilities\FeedbackAbilities;
use GratisAiAgent\Abilities\FileAbilities;
use GratisAiAgent\Abilities\GitAbilities;
use GratisAiAgent\Abilities\GlobalStylesAbilities;
use GratisAiAgent\Abilities\GoogleAnalyticsAbilities;
use GratisAiAgent\Abilities\GscAbilities;
use GratisAiAgent\Abilities\ImageAbilities;
use GratisAiAgent\Abilities\InternetSearchAbilities;
use GratisAiAgent\Abilities\KnowledgeAbilities;
use GratisAiAgent\Abilities\MarketingAbilities;
use GratisAiAgent\Abilities\MediaAbilities;
use GratisAiAgent\Abilities\MemoryAbilities;
use GratisAiAgent\Abilities\MenuAbilities;
use GratisAiAgent\Abilities\NavigationAbilities;
use GratisAiAgent\Abilities\OptionsAbilities;
use GratisAiAgent\Abilities\PluginBuilderAbilities;
use GratisAiAgent\Abilities\PluginDownloadAbilities;
use GratisAiAgent\PluginBuilder\PluginSandbox;
use GratisAiAgent\Abilities\PostAbilities;
use GratisAiAgent\Abilities\SeoAbilities;
use GratisAiAgent\Abilities\SiteBuilderAbilities;
use GratisAiAgent\Abilities\SiteHealthAbilities;
use GratisAiAgent\Abilities\SkillAbilities;
use GratisAiAgent\Abilities\StockImageAbilities;
use GratisAiAgent\Abilities\UserAbilities;
use GratisAiAgent\Abilities\WooCommerceAbilities;
use GratisAiAgent\Abilities\WordPressAbilities;
use GratisAiAgent\Abilities\WpCliAbilities;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all ability groups on `wp_abilities_api_init` and wires
 * the `init`-time hooks that were previously inside the per-class
 * `register()` stubs.
 *
 * Uses `INIT_IMMEDIATELY` so all callbacks are queued during
 * `plugins_loaded` — well before `init` or `wp_abilities_api_init` fires.
 */
#[Handler(
	container: 'gratis-ai-agent',
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class AbilitiesHandler {

	/**
	 * Register all ability groups.
	 *
	 * Called on `wp_abilities_api_init` which fires during `init`.
	 * Each class's `register_abilities()` method calls
	 * `wp_register_ability()` for its individual abilities.
	 */
	#[Action( tag: 'wp_abilities_api_init', priority: 10 )]
	public function register_all_abilities(): void {
		MemoryAbilities::register_abilities();
		FeedbackAbilities::register_abilities();
		SkillAbilities::register_abilities();
		KnowledgeAbilities::register_abilities();
		ImageAbilities\UnifiedImageAbility::register();
		StockImageAbilities::register_abilities();
		AiImageAbilities::register_abilities();
		InternetSearchAbilities::register_abilities();
		SeoAbilities::register_abilities();
		GscAbilities::register_abilities();
		ContentAbilities::register_abilities();
		MarketingAbilities::register_abilities();
		GoogleAnalyticsAbilities::register_abilities();
		BlockAbilities::register_abilities();
		GlobalStylesAbilities::register_abilities();
		FileAbilities::register_abilities();
		GitAbilities::register_abilities();
		PluginDownloadAbilities::register_abilities();
		PluginBuilderAbilities::register_abilities();
		DatabaseAbilities::register_abilities();
		WordPressAbilities::register_abilities();
		WpCliAbilities::register_ability();
		OptionsAbilities::register_abilities();
		WooCommerceAbilities::register_abilities();
		SiteHealthAbilities::register_abilities();
		NavigationAbilities::register_abilities();
		MenuAbilities::register_abilities();
		PostAbilities::register_abilities();
		CustomPostTypeAbilities::register_abilities();
		CustomTaxonomyAbilities::register_abilities();
		UserAbilities::register_abilities();
		MediaAbilities::register_abilities();
		EditorialAbilities::register_abilities();
		ImageAbilities::register_abilities();
		SiteBuilderAbilities::register_abilities();
		DesignSystemAbilities::register_abilities();
	}

	/**
	 * Register the WP-CLI ability category.
	 *
	 * WpCliAbilities uses a separate hook (`wp_abilities_api_categories_init`)
	 * for its category registration, unlike the other ability classes.
	 */
	#[Action( tag: 'wp_abilities_api_categories_init', priority: 10 )]
	public function register_wpcli_category(): void {
		WpCliAbilities::register_category();
	}

	/**
	 * Re-register persisted custom post types and taxonomies on `init`.
	 *
	 * Runs at priority 5 (before most plugins) so AI-created CPTs and
	 * taxonomies are available to the rest of WordPress on every request.
	 * Previously wired by CustomPostTypeAbilities::register() and
	 * CustomTaxonomyAbilities::register() via add_action() — those
	 * register() stubs have been removed.
	 */
	#[Action( tag: 'init', priority: 5 )]
	public function restore_persisted_types(): void {
		CustomPostTypeAbilities::restore_persisted_post_types();
		CustomTaxonomyAbilities::restore_persisted_taxonomies();
	}

	/**
	 * Auto-deactivate plugins that triggered a fatal error on a previous activation.
	 *
	 * Previously wired by PluginBuilderAbilities::register() via add_action() —
	 * that register() stub has been removed.
	 */
	#[Action( tag: 'init', priority: 10 )]
	public function auto_deactivate_fatal_plugins(): void {
		PluginSandbox::auto_deactivate_fatal_plugins();
	}
}
