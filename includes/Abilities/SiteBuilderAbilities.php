<?php

declare(strict_types=1);
/**
 * Site builder abilities for the AI agent.
 *
 * Provides tools for detecting fresh WordPress installs and managing
 * the site builder conversation mode. The site builder interviews the
 * user about their business and generates a complete site (pages, nav,
 * title, tagline, SEO) in a single guided conversation.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\Core\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteBuilderAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Detect whether this is a fresh WordPress install.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_detect_fresh_install( array $input = [] ) {
		$ability = new DetectFreshInstallAbility(
			'gratis-ai-agent/detect-fresh-install',
			[
				'label'       => __( 'Detect Fresh Install', 'gratis-ai-agent' ),
				'description' => __( 'Check whether this WordPress site is a fresh install with no real content. Returns a boolean and a summary of site state.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Enable or disable site builder mode.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_set_site_builder_mode( array $input = [] ) {
		$ability = new SetSiteBuilderModeAbility(
			'gratis-ai-agent/set-site-builder-mode',
			[
				'label'       => __( 'Set Site Builder Mode', 'gratis-ai-agent' ),
				'description' => __( 'Enable or disable site builder mode. When enabled, the floating widget opens automatically and the agent uses the site builder interview system prompt.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Get the current site builder status.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_site_builder_status( array $input = [] ) {
		$ability = new GetSiteBuilderStatusAbility(
			'gratis-ai-agent/get-site-builder-status',
			[
				'label'       => __( 'Get Site Builder Status', 'gratis-ai-agent' ),
				'description' => __( 'Get the current site builder mode status and any collected site information.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Mark site builder as complete.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_complete_site_builder( array $input = [] ) {
		$ability = new CompleteSiteBuilderAbility(
			'gratis-ai-agent/complete-site-builder',
			[
				'label'       => __( 'Complete Site Builder', 'gratis-ai-agent' ),
				'description' => __( 'Mark the site builder conversation as complete. Disables site builder mode and marks onboarding as done.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register site builder abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all site builder abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/detect-fresh-install',
			[
				'label'         => __( 'Detect Fresh Install', 'gratis-ai-agent' ),
				'description'   => __( 'Check whether this WordPress site is a fresh install with no real content. Returns a boolean and a summary of site state.', 'gratis-ai-agent' ),
				'ability_class' => DetectFreshInstallAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/set-site-builder-mode',
			[
				'label'         => __( 'Set Site Builder Mode', 'gratis-ai-agent' ),
				'description'   => __( 'Enable or disable site builder mode. When enabled, the floating widget opens automatically and the agent uses the site builder interview system prompt.', 'gratis-ai-agent' ),
				'ability_class' => SetSiteBuilderModeAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/get-site-builder-status',
			[
				'label'         => __( 'Get Site Builder Status', 'gratis-ai-agent' ),
				'description'   => __( 'Get the current site builder mode status and any collected site information.', 'gratis-ai-agent' ),
				'ability_class' => GetSiteBuilderStatusAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/complete-site-builder',
			[
				'label'         => __( 'Complete Site Builder', 'gratis-ai-agent' ),
				'description'   => __( 'Mark the site builder conversation as complete. Disables site builder mode and marks onboarding as done.', 'gratis-ai-agent' ),
				'ability_class' => CompleteSiteBuilderAbility::class,
			]
		);
	}

	/**
	 * Check whether this is a fresh WordPress install.
	 *
	 * A site is considered "fresh" when:
	 *   - It has 0 or 1 published posts (the default "Hello World" post)
	 *   - It has 0 or 1 published pages (the default "Sample Page")
	 *   - The site title is still the default ("My WordPress Website" or similar)
	 *   - No custom menus have been created
	 *
	 * @return array{is_fresh: bool, post_count: int, page_count: int, has_custom_menu: bool, site_title: string}
	 */
	public static function check_fresh_install(): array {
		$post_count = (int) wp_count_posts( 'post' )->publish;
		$page_count = (int) wp_count_posts( 'page' )->publish;

		// Check for custom nav menus (excluding auto-created ones).
		$menus           = wp_get_nav_menus();
		$has_custom_menu = ! empty( $menus );

		$site_title = get_bloginfo( 'name' );

		// Default WordPress titles that indicate a fresh install.
		$default_titles = [
			'My WordPress Website',
			'My WordPress Blog',
			'My Blog',
			'My Site',
			'WordPress',
			'Just another WordPress site',
		];

		$has_default_title = in_array( $site_title, $default_titles, true )
			|| '' === trim( $site_title );

		// A site is fresh if it has at most 1 post and 1 page (the defaults).
		$is_fresh = $post_count <= 1
			&& $page_count <= 1
			&& ! $has_custom_menu;

		return [
			'is_fresh'          => $is_fresh,
			'post_count'        => $post_count,
			'page_count'        => $page_count,
			'has_custom_menu'   => $has_custom_menu,
			'site_title'        => $site_title,
			'has_default_title' => $has_default_title,
		];
	}
}

/**
 * Detect Fresh Install ability.
 *
 * @since 1.1.0
 */
class DetectFreshInstallAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Detect Fresh Install', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Check whether this WordPress site is a fresh install with no real content. Returns a boolean and a summary of site state.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'is_fresh'          => [ 'type' => 'boolean' ],
				'post_count'        => [ 'type' => 'integer' ],
				'page_count'        => [ 'type' => 'integer' ],
				'has_custom_menu'   => [ 'type' => 'boolean' ],
				'site_title'        => [ 'type' => 'string' ],
				'has_default_title' => [ 'type' => 'boolean' ],
				'message'           => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
		$result  = SiteBuilderAbilities::check_fresh_install();
		$message = $result['is_fresh']
			? __( 'This appears to be a fresh WordPress install with no real content.', 'gratis-ai-agent' )
			: sprintf(
				/* translators: 1: post count, 2: page count */
				__( 'This site has existing content: %1$d posts and %2$d pages.', 'gratis-ai-agent' ),
				$result['post_count'],
				$result['page_count']
			);

		return array_merge( $result, [ 'message' => $message ] );
	}

	protected function permission_callback( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Set Site Builder Mode ability.
 *
 * @since 1.1.0
 */
class SetSiteBuilderModeAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Set Site Builder Mode', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Enable or disable site builder mode. When enabled, the floating widget opens automatically and the agent uses the site builder interview system prompt.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'enabled' => [
					'type'        => 'boolean',
					'description' => 'Whether to enable (true) or disable (false) site builder mode',
				],
			],
			'required'   => [ 'enabled' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success'           => [ 'type' => 'boolean' ],
				'site_builder_mode' => [ 'type' => 'boolean' ],
				'message'           => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$enabled = (bool) ( $input['enabled'] ?? false );

		Settings::update( [ 'site_builder_mode' => $enabled ] );

		return [
			'success'           => true,
			'site_builder_mode' => $enabled,
			'message'           => $enabled
				? __( 'Site builder mode enabled. The widget will open automatically on the next page load.', 'gratis-ai-agent' )
				: __( 'Site builder mode disabled.', 'gratis-ai-agent' ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Get Site Builder Status ability.
 *
 * @since 1.1.0
 */
class GetSiteBuilderStatusAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get Site Builder Status', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Get the current site builder mode status and any collected site information.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'site_builder_mode'   => [ 'type' => 'boolean' ],
				'onboarding_complete' => [ 'type' => 'boolean' ],
				'site_title'          => [ 'type' => 'string' ],
				'site_url'            => [ 'type' => 'string' ],
				'message'             => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
		$settings = Settings::get();

		return [
			// @phpstan-ignore-next-line
			'site_builder_mode'   => (bool) ( $settings['site_builder_mode'] ?? false ),
			// @phpstan-ignore-next-line
			'onboarding_complete' => (bool) ( $settings['onboarding_complete'] ?? false ),
			'site_title'          => get_bloginfo( 'name' ),
			'site_url'            => get_site_url(),
			'message'             => __( 'Site builder status retrieved.', 'gratis-ai-agent' ),
		];
	}

	protected function permission_callback( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}

/**
 * Complete Site Builder ability.
 *
 * @since 1.1.0
 */
class CompleteSiteBuilderAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Complete Site Builder', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Mark the site builder conversation as complete. Disables site builder mode and marks onboarding as done.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input = null ) {
		/** @var array<string, mixed> $input */
		Settings::update(
			[
				'site_builder_mode'   => false,
				'onboarding_complete' => true,
			]
		);

		return [
			'success' => true,
			'message' => __( 'Site builder complete. Onboarding marked as done.', 'gratis-ai-agent' ),
		];
	}

	protected function permission_callback( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}
}
