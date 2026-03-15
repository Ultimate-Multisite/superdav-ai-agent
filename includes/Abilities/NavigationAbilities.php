<?php

declare(strict_types=1);
/**
 * Navigation abilities for the AI agent.
 *
 * Provides URL navigation and page HTML inspection.
 *
 * @package GratisAiAgent
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
	public static function handle_navigate( array $input ) {
		$ability = new NavigateAbility( 'gratis-ai-agent/navigate' );
		return $ability->execute( $input );
	}

	/**
	 * Get the HTML content of elements on the current page.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_get_page_html( array $input ) {
		$ability = new GetPageHtmlAbility( 'gratis-ai-agent/get-page-html' );
		return $ability->execute( $input );
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

/**
 * Navigate ability.
 *
 * @since 1.0.0
 */
class NavigateAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'url' => [
					'type'        => 'string',
					'description' => 'The URL to navigate to. Can be a full URL (must start with the site URL) or a relative path (e.g., "/wp-admin/edit.php").',
				],
			],
			'required'   => [ 'url' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'url'     => [ 'type' => 'string' ],
				'action'  => [ 'type' => 'string' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$url      = $input['url'] ?? '';
		$home_url = home_url();

		if ( empty( $url ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_url', __( 'URL is required.', 'gratis-ai-agent' ) );
		}

		$validated_url = null;

		// Handle relative URLs.
		if ( strpos( $url, '/' ) === 0 ) {
			$validated_url = home_url( $url );
		} elseif ( strpos( $url, $home_url ) === 0 ) {
			$validated_url = $url;
		} else {
			return new WP_Error(
				'gratis_ai_agent_invalid_url',
				sprintf(
					/* translators: %s: home URL */
					__( 'Invalid URL: must be within the WordPress site (start with "%s" or be a relative path).', 'gratis-ai-agent' ),
					$home_url
				)
			);
		}

		// Block ThickBox/iframe URLs.
		if ( strpos( $validated_url, 'TB_iframe=true' ) !== false ) {
			return new WP_Error(
				'gratis_ai_agent_iframe_url',
				__( 'Cannot navigate to modal/iframe URLs. Navigate to the main page instead.', 'gratis-ai-agent' )
			);
		}

		return [
			'url'     => $validated_url,
			'action'  => 'navigate',
			'message' => sprintf( 'Ready to navigate to: %s', $validated_url ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'read' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}

/**
 * Get Page HTML ability.
 *
 * @since 1.0.0
 */
class GetPageHtmlAbility extends AbstractAbility {

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'selector'   => [
					'type'        => 'string',
					'description' => 'CSS selector to query (e.g., "#main-content", ".entry-title", "article", "body")',
				],
				'max_length' => [
					'type'        => 'number',
					'description' => 'Maximum characters to return per element (default: 5000)',
				],
			],
			'required'   => [ 'selector' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'selector'   => [ 'type' => 'string' ],
				'max_length' => [ 'type' => 'integer' ],
				'action'     => [ 'type' => 'string' ],
				'message'    => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$selector   = $input['selector'] ?? '';
		$max_length = (int) ( $input['max_length'] ?? 5000 );

		if ( empty( $selector ) ) {
			return new WP_Error( 'gratis_ai_agent_empty_selector', __( 'CSS selector is required.', 'gratis-ai-agent' ) );
		}

		// This ability returns a client-side action instruction.
		// The React frontend intercepts this and executes the DOM query.
		return [
			'selector'   => $selector,
			'max_length' => $max_length,
			'action'     => 'get_page_html',
			'message'    => sprintf( 'Querying page HTML with selector: %s', $selector ),
		];
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'read' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
