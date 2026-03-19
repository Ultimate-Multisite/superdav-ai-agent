<?php

declare(strict_types=1);
/**
 * Navigation abilities for the AI agent.
 *
 * Provides URL navigation and page HTML inspection.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NavigationAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Navigate to a URL within the WordPress site.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_navigate( array $input = [] ) {
		$ability = new NavigateAbility(
			'gratis-ai-agent/navigate',
			[
				'label'       => __( 'Navigate', 'gratis-ai-agent' ),
				'description' => __( 'Navigate the user to a URL within the WordPress site. The URL must be within the current site. This will reload the page.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Get the HTML content of elements on the current page.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_page_html( array $input = [] ) {
		$ability = new GetPageHtmlAbility(
			'gratis-ai-agent/get-page-html',
			[
				'label'       => __( 'Get Page HTML', 'gratis-ai-agent' ),
				'description' => __( 'Get the HTML content of elements on the current page the user is viewing. Use CSS selectors to query specific elements. Returns the outer HTML of matched elements.', 'gratis-ai-agent' ),
			]
		);
		// @phpstan-ignore-next-line
		return $ability->run( $input );
	}

	/**
	 * Register navigation abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register navigation abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/navigate',
			[
				'label'         => __( 'Navigate', 'gratis-ai-agent' ),
				'description'   => __( 'Navigate the user to a URL within the WordPress site. The URL must be within the current site. This will reload the page.', 'gratis-ai-agent' ),
				'ability_class' => NavigateAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/get-page-html',
			[
				'label'         => __( 'Get Page HTML', 'gratis-ai-agent' ),
				'description'   => __( 'Get the HTML content of elements on the current page the user is viewing. Use CSS selectors to query specific elements. Returns the outer HTML of matched elements.', 'gratis-ai-agent' ),
				'ability_class' => GetPageHtmlAbility::class,
			]
		);
	}
}
