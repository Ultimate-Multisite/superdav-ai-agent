<?php

declare(strict_types=1);
/**
 * Navigate ability.
 *
 * Validates and returns a navigate action for a URL within the WordPress site.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Navigate ability.
 *
 * @since 1.0.0
 */
class NavigateAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Navigate', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Navigate within the current site. Use a known blog ID and an admin-relative path for target-site admin links. Other blogs return a link for the user to open in a new tab, preserving the current chat.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'url'     => [
					'type'        => 'string',
					'description' => 'The URL to navigate to. Can be a full URL (must start with the site URL) or a relative path (e.g., "/wp-admin/edit.php").',
				],
				'path'    => [
					'type'        => 'string',
					'description' => 'Admin-relative filename, e.g. plugins.php. Never include a site path or wp-admin prefix.',
				],
				'blog_id' => [
					'type'        => 'integer',
					'description' => 'Known target blog ID from the site-create response. Other blogs return a user-operated link, not automatic navigation.',
				],
			],
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
		/** @var array<string, mixed> $input */
		$url      = (string) ( $input['url'] ?? '' );
		$home_url = home_url();
		$blog_id  = (int) ( $input['blog_id'] ?? get_current_blog_id() );
		$path     = (string) ( $input['path'] ?? '' );

		if ( '' !== $path ) {
			if ( ( is_multisite() ? ! get_site( $blog_id ) : $blog_id !== get_current_blog_id() ) || preg_match( '~(?:^/|wp-admin|[\\\\:]|(?:^|/)\.\.(?:/|$))~i', $path ) ) {
				return new WP_Error( 'sd_ai_agent_invalid_url', __( 'Use a known blog ID and an admin-relative filename.', 'superdav-ai-agent' ) );
			}
			$url = get_admin_url( $blog_id, $path );
		}

		if ( empty( $url ) ) {
			return new WP_Error( 'sd_ai_agent_empty_url', __( 'URL is required.', 'superdav-ai-agent' ) );
		}

		$validated_url = null;

		// Handle relative URLs.
		// @phpstan-ignore-next-line
		if ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
			// @phpstan-ignore-next-line
			$validated_url = home_url( $url );
			$url           = $validated_url;
		}
		// Compare complete origin components, not host substrings.
		$target_parts  = wp_parse_url( $url );
		$current_parts = wp_parse_url( $home_url );
		$current_path  = trailingslashit( $current_parts['path'] ?? '/' );
		$target_path   = trailingslashit( $target_parts['path'] ?? '/' );
		$target_site   = is_multisite() && is_array( $target_parts )
			? $this->find_target_site( $target_parts, $target_path )
			: null;
		if ( $target_site && (int) $target_site->site_id !== get_current_network_id() ) {
			return new WP_Error( 'sd_ai_agent_invalid_url', __( 'Target site must be in the current network.', 'superdav-ai-agent' ) );
		}
		if ( $target_site && (int) $target_site->blog_id !== get_current_blog_id() ) {
			$current_parts = wp_parse_url( get_home_url( (int) $target_site->blog_id ) );
			$current_path  = trailingslashit( $current_parts['path'] ?? '/' );
		}
		if (
				is_array( $target_parts )
				&& is_array( $current_parts )
				&& ( $target_parts['scheme'] ?? '' ) === ( $current_parts['scheme'] ?? '' )
				&& strtolower( (string) ( $target_parts['host'] ?? '' ) ) === strtolower( (string) ( $current_parts['host'] ?? '' ) )
				&& (int) ( $target_parts['port'] ?? 0 ) === (int) ( $current_parts['port'] ?? 0 )
				&& 0 === strpos( $target_path, $current_path )
			) {
			$validated_url = $url;
		} else {
			return new WP_Error(
				'sd_ai_agent_invalid_url',
				sprintf(
					/* translators: %s: home URL */
					__( 'Invalid URL: must be within the WordPress site (start with "%s" or be a relative path).', 'superdav-ai-agent' ),
					$home_url
				)
			);
		}
		$parts        = wp_parse_url( $validated_url );
		$decoded_path = rawurldecode( (string) ( $parts['path'] ?? '/' ) );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || preg_match( '/[\x00-\x20\x7f]/', $url )
			|| preg_match( '/%(?:2f|5c|25|0[0-9a-f]|1[0-9a-f]|7f)/i', (string) ( $parts['path'] ?? '' ) )
			|| preg_match( '~(?:[\\\\]|(?:^|/)\.{1,2}(?:/|$)|/wp-admin/.*?/wp-admin/)~i', $decoded_path ) ) {
			return new WP_Error( 'sd_ai_agent_invalid_url', __( 'Invalid navigation URL. Use the target site\'s authoritative admin URL.', 'superdav-ai-agent' ) );
		}
		$target_site    = is_multisite() ? $this->find_target_site( $parts, $decoded_path ) : null;
		$target_blog_id = $target_site ? (int) $target_site->blog_id : get_current_blog_id();
		$admin_path     = (string) wp_parse_url( get_admin_url( $target_blog_id ), PHP_URL_PATH );
		if ( preg_match( '~/wp-admin(?:/|$)~i', $decoded_path ) && 0 !== strpos( trailingslashit( $decoded_path ), trailingslashit( $admin_path ) ) ) {
			return new WP_Error( 'sd_ai_agent_invalid_url', __( 'The target blog could not be identified. Use its blog ID and an admin-relative filename.', 'superdav-ai-agent' ) );
		}
		// Block iframe links before returning either navigation or a cross-blog link.
		if ( strpos( $validated_url, 'TB_iframe=true' ) !== false ) {
			return new WP_Error( 'sd_ai_agent_iframe_url', __( 'Cannot navigate to modal/iframe URLs. Navigate to the main page instead.', 'superdav-ai-agent' ) );
		}
		if ( $target_blog_id !== get_current_blog_id() ) {
			if ( ! current_user_can_for_site( $target_blog_id, 'read' ) ) {
				return new WP_Error( 'sd_ai_agent_invalid_url', __( 'You do not have access to the target site.', 'superdav-ai-agent' ) );
			}
			return [
				'url'     => $validated_url,
				'action'  => 'link',
				'message' => 'Offer this validated link for the user to open in a new tab. Keep the current chat on this site: ' . $validated_url,
			];
		}

		return [
			'url'     => $validated_url,
			'action'  => 'navigate',
			// @phpstan-ignore-next-line
			'message' => sprintf( 'Ready to navigate to: %s', $validated_url ),
		];
	}

	/**
	 * Match WordPress domains, including local multisite domains with a port.
	 *
	 * @param array<string, mixed> $parts Parsed target URL.
	 * @param string               $path Target path.
	 * @return \WP_Site|false
	 */
	private function find_target_site( array $parts, string $path ): \WP_Site|false {
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$domain = $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
		return get_site_by_path( $domain, $path ) ?: get_site_by_path( $host, $path );
	}

	protected function permission_callback( $input ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
		];
	}
}
