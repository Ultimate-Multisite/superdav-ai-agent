<?php

declare(strict_types=1);
/**
 * Curated registry of plugins known to register WordPress Abilities.
 *
 * Each entry describes a plugin that exposes abilities via the WordPress
 * Abilities API (wp_register_ability). The registry is used by the
 * recommend-plugin ability to suggest plugins based on a need category.
 *
 * Preference order when ranking: has_abilities > has_blocks > active_installs.
 *
 * @package GratisAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curated registry of plugins that register WordPress Abilities.
 *
 * @since 1.2.0
 */
class AbilityPluginRegistry {

	/**
	 * Registry of plugins known to register abilities.
	 *
	 * Each entry contains:
	 *   - slug           (string)   WordPress.org plugin slug.
	 *   - name           (string)   Human-readable plugin name.
	 *   - ability_count  (int)      Approximate number of abilities registered.
	 *   - has_abilities  (bool)     Whether the plugin registers WP Abilities.
	 *   - has_blocks     (bool)     Whether the plugin registers Gutenberg blocks.
	 *   - categories     (string[]) Need categories this plugin addresses.
	 *   - active_installs (int)     Approximate active installs (from wp.org).
	 *   - description    (string)   Short description of what the plugin does.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const REGISTRY = [
		// ── E-commerce ────────────────────────────────────────────────────────
		[
			'slug'            => 'woocommerce',
			'name'            => 'WooCommerce',
			'ability_count'   => 12,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'ecommerce', 'shop', 'store', 'products', 'payments', 'orders' ],
			'active_installs' => 9000000,
			'description'     => 'Full-featured e-commerce platform: products, cart, checkout, orders, and payments.',
		],
		[
			'slug'            => 'easy-digital-downloads',
			'name'            => 'Easy Digital Downloads',
			'ability_count'   => 6,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'ecommerce', 'digital', 'downloads', 'payments' ],
			'active_installs' => 500000,
			'description'     => 'Sell digital products and downloads with a lightweight e-commerce solution.',
		],

		// ── Forms ─────────────────────────────────────────────────────────────
		[
			'slug'            => 'contact-form-7',
			'name'            => 'Contact Form 7',
			'ability_count'   => 4,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'forms', 'contact', 'email', 'lead-capture' ],
			'active_installs' => 10000000,
			'description'     => 'Create and manage contact forms with flexible field types and email routing.',
		],
		[
			'slug'            => 'wpforms-lite',
			'name'            => 'WPForms Lite',
			'ability_count'   => 5,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'forms', 'contact', 'surveys', 'lead-capture' ],
			'active_installs' => 6000000,
			'description'     => 'Drag-and-drop form builder with templates for contact, survey, and payment forms.',
		],
		[
			'slug'            => 'gravityforms',
			'name'            => 'Gravity Forms',
			'ability_count'   => 8,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'forms', 'contact', 'surveys', 'payments', 'lead-capture' ],
			'active_installs' => 1000000,
			'description'     => 'Advanced form builder with conditional logic, multi-page forms, and payment integrations.',
		],

		// ── SEO ───────────────────────────────────────────────────────────────
		[
			'slug'            => 'wordpress-seo',
			'name'            => 'Yoast SEO',
			'ability_count'   => 6,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'seo', 'meta', 'sitemap', 'schema', 'readability' ],
			'active_installs' => 10000000,
			'description'     => 'Comprehensive SEO: meta tags, XML sitemaps, schema markup, and readability analysis.',
		],
		[
			'slug'            => 'all-in-one-seo-pack',
			'name'            => 'All in One SEO',
			'ability_count'   => 5,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'seo', 'meta', 'sitemap', 'schema' ],
			'active_installs' => 3000000,
			'description'     => 'SEO plugin with smart schema markup, XML sitemaps, and social media integration.',
		],
		[
			'slug'            => 'rank-math',
			'name'            => 'Rank Math SEO',
			'ability_count'   => 7,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'seo', 'meta', 'sitemap', 'schema', 'analytics' ],
			'active_installs' => 2000000,
			'description'     => 'Feature-rich SEO with built-in schema, keyword tracking, and Google Search Console integration.',
		],

		// ── Security ──────────────────────────────────────────────────────────
		[
			'slug'            => 'wordfence',
			'name'            => 'Wordfence Security',
			'ability_count'   => 4,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'security', 'firewall', 'malware', 'login-protection' ],
			'active_installs' => 5000000,
			'description'     => 'Web application firewall, malware scanner, and login security for WordPress.',
		],
		[
			'slug'            => 'really-simple-ssl',
			'name'            => 'Really Simple SSL',
			'ability_count'   => 2,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'security', 'ssl', 'https' ],
			'active_installs' => 5000000,
			'description'     => 'Automatically configure SSL/HTTPS and fix mixed content issues.',
		],

		// ── Performance / Caching ─────────────────────────────────────────────
		[
			'slug'            => 'w3-total-cache',
			'name'            => 'W3 Total Cache',
			'ability_count'   => 3,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'performance', 'caching', 'cdn', 'speed' ],
			'active_installs' => 1000000,
			'description'     => 'Page, object, and database caching with CDN integration for faster load times.',
		],
		[
			'slug'            => 'wp-super-cache',
			'name'            => 'WP Super Cache',
			'ability_count'   => 2,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'performance', 'caching', 'speed' ],
			'active_installs' => 2000000,
			'description'     => 'Static HTML file caching to serve pages faster and reduce server load.',
		],

		// ── Backup ────────────────────────────────────────────────────────────
		[
			'slug'            => 'updraftplus',
			'name'            => 'UpdraftPlus',
			'ability_count'   => 4,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'backup', 'restore', 'migration' ],
			'active_installs' => 3000000,
			'description'     => 'Scheduled backups to cloud storage (Dropbox, Google Drive, S3) with one-click restore.',
		],

		// ── Media / Images ────────────────────────────────────────────────────
		[
			'slug'            => 'smush',
			'name'            => 'Smush Image Compression',
			'ability_count'   => 3,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'media', 'images', 'performance', 'optimization' ],
			'active_installs' => 1000000,
			'description'     => 'Compress, resize, and lazy-load images to improve page speed.',
		],

		// ── Page Builders ─────────────────────────────────────────────────────
		[
			'slug'            => 'elementor',
			'name'            => 'Elementor',
			'ability_count'   => 8,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'page-builder', 'design', 'layout', 'templates' ],
			'active_installs' => 10000000,
			'description'     => 'Visual drag-and-drop page builder with 100+ widgets and theme builder.',
		],

		// ── Membership / Users ────────────────────────────────────────────────
		[
			'slug'            => 'memberpress',
			'name'            => 'MemberPress',
			'ability_count'   => 6,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'membership', 'subscriptions', 'access-control', 'payments' ],
			'active_installs' => 100000,
			'description'     => 'Membership site management with content restriction, subscriptions, and payment gateways.',
		],
		[
			'slug'            => 'buddypress',
			'name'            => 'BuddyPress',
			'ability_count'   => 5,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'community', 'social', 'users', 'profiles', 'groups' ],
			'active_installs' => 200000,
			'description'     => 'Social networking features: user profiles, activity streams, groups, and messaging.',
		],

		// ── Analytics ─────────────────────────────────────────────────────────
		[
			'slug'            => 'google-analytics-for-wordpress',
			'name'            => 'MonsterInsights',
			'ability_count'   => 4,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'analytics', 'tracking', 'reporting', 'google-analytics' ],
			'active_installs' => 3000000,
			'description'     => 'Google Analytics integration with dashboard reports and e-commerce tracking.',
		],

		// ── Email Marketing ───────────────────────────────────────────────────
		[
			'slug'            => 'mailchimp-for-wp',
			'name'            => 'Mailchimp for WordPress',
			'ability_count'   => 3,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'email', 'newsletter', 'marketing', 'lead-capture' ],
			'active_installs' => 2000000,
			'description'     => 'Connect WordPress forms to Mailchimp lists for email marketing and newsletters.',
		],

		// ── Social / Sharing ──────────────────────────────────────────────────
		[
			'slug'            => 'social-warfare',
			'name'            => 'Social Warfare',
			'ability_count'   => 2,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'social', 'sharing', 'social-media' ],
			'active_installs' => 100000,
			'description'     => 'Social sharing buttons with share counts and Pinterest-specific image support.',
		],

		// ── Multilingual ──────────────────────────────────────────────────────
		[
			'slug'            => 'polylang',
			'name'            => 'Polylang',
			'ability_count'   => 4,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'multilingual', 'translation', 'languages', 'i18n' ],
			'active_installs' => 700000,
			'description'     => 'Create multilingual WordPress sites with per-language content and URLs.',
		],

		// ── Events ────────────────────────────────────────────────────────────
		[
			'slug'            => 'the-events-calendar',
			'name'            => 'The Events Calendar',
			'ability_count'   => 5,
			'has_abilities'   => true,
			'has_blocks'      => true,
			'categories'      => [ 'events', 'calendar', 'booking', 'scheduling' ],
			'active_installs' => 800000,
			'description'     => 'Event management with calendar views, RSVP, and ticketing integrations.',
		],

		// ── Booking / Appointments ────────────────────────────────────────────
		[
			'slug'            => 'bookly-responsive-appointment-booking-tool',
			'name'            => 'Bookly',
			'ability_count'   => 5,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'booking', 'appointments', 'scheduling', 'services' ],
			'active_installs' => 60000,
			'description'     => 'Online appointment booking system with staff management and payment integration.',
		],

		// ── Reviews / Testimonials ────────────────────────────────────────────
		[
			'slug'            => 'wp-customer-reviews',
			'name'            => 'WP Customer Reviews',
			'ability_count'   => 2,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'reviews', 'testimonials', 'ratings', 'social-proof' ],
			'active_installs' => 60000,
			'description'     => 'Collect and display customer reviews and testimonials with schema markup.',
		],

		// ── Sliders / Galleries ───────────────────────────────────────────────
		[
			'slug'            => 'revslider',
			'name'            => 'Slider Revolution',
			'ability_count'   => 3,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'slider', 'gallery', 'media', 'design' ],
			'active_installs' => 9000000,
			'description'     => 'Responsive slider and carousel builder with animation effects and templates.',
		],

		// ── Popups / Lead Generation ──────────────────────────────────────────
		[
			'slug'            => 'optinmonster',
			'name'            => 'OptinMonster',
			'ability_count'   => 3,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'popups', 'lead-capture', 'marketing', 'email', 'conversion' ],
			'active_installs' => 1000000,
			'description'     => 'Popup and opt-in form builder with exit-intent technology and A/B testing.',
		],

		// ── Live Chat / Support ───────────────────────────────────────────────
		[
			'slug'            => 'tawkto-live-chat',
			'name'            => 'Tawk.to Live Chat',
			'ability_count'   => 2,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'chat', 'support', 'customer-service', 'live-chat' ],
			'active_installs' => 300000,
			'description'     => 'Free live chat widget to communicate with visitors in real time.',
		],

		// ── Payments ──────────────────────────────────────────────────────────
		[
			'slug'            => 'stripe',
			'name'            => 'WooCommerce Stripe Payment Gateway',
			'ability_count'   => 3,
			'has_abilities'   => true,
			'has_blocks'      => false,
			'categories'      => [ 'payments', 'stripe', 'ecommerce', 'checkout' ],
			'active_installs' => 1000000,
			'description'     => 'Accept credit card payments via Stripe directly on your WooCommerce store.',
		],
	];

	/**
	 * Get all registry entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all(): array {
		return self::REGISTRY;
	}

	/**
	 * Get registry entries filtered by category.
	 *
	 * Matching is case-insensitive and checks if any of the plugin's categories
	 * contain the requested category as a substring.
	 *
	 * @param string $category The need category to filter by.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_by_category( string $category ): array {
		$category_lower = strtolower( trim( $category ) );

		return array_values(
			array_filter(
				self::REGISTRY,
				static function ( array $plugin ) use ( $category_lower ): bool {
					foreach ( $plugin['categories'] as $cat ) {
						if ( str_contains( strtolower( $cat ), $category_lower ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	/**
	 * Rank plugins by preference: has_abilities > has_blocks > active_installs.
	 *
	 * @param array<int, array<string, mixed>> $plugins Plugins to rank.
	 * @return array<int, array<string, mixed>> Sorted plugins, highest rank first.
	 */
	public static function rank( array $plugins ): array {
		usort(
			$plugins,
			static function ( array $a, array $b ): int {
				// Primary: has_abilities (true > false).
				$a_abilities = (int) ( $a['has_abilities'] ?? false );
				$b_abilities = (int) ( $b['has_abilities'] ?? false );
				if ( $a_abilities !== $b_abilities ) {
					return $b_abilities - $a_abilities;
				}

				// Secondary: has_blocks (true > false).
				$a_blocks = (int) ( $a['has_blocks'] ?? false );
				$b_blocks = (int) ( $b['has_blocks'] ?? false );
				if ( $a_blocks !== $b_blocks ) {
					return $b_blocks - $a_blocks;
				}

				// Tertiary: active_installs (higher > lower).
				$a_installs = (int) ( $a['active_installs'] ?? 0 );
				$b_installs = (int) ( $b['active_installs'] ?? 0 );
				return $b_installs - $a_installs;
			}
		);

		return $plugins;
	}

	/**
	 * Get all unique categories across the registry.
	 *
	 * @return string[]
	 */
	public static function get_categories(): array {
		$categories = [];
		foreach ( self::REGISTRY as $plugin ) {
			foreach ( $plugin['categories'] as $cat ) {
				$categories[ $cat ] = true;
			}
		}
		return array_keys( $categories );
	}
}
