<?php

declare(strict_types=1);
/**
 * Skill model — on-demand instruction guides for the AI agent.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

class Skill {

	/**
	 * Get the skills table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'gratis_ai_agent_skills';
	}

	/**
	 * Get all skills, optionally filtered by enabled status.
	 *
	 * @param bool|null $enabled Filter by enabled status (null = all).
	 * @return array<string, mixed>
	 */
	public static function get_all( ?bool $enabled = null ): array {
		global $wpdb;

		$table = self::table_name();

		if ( null !== $enabled ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE enabled = %d ORDER BY name ASC',
					$table,
					$enabled ? 1 : 0
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY name ASC',
				$table
			)
		);
	}

	/**
	 * Get a single skill by ID.
	 *
	 * @param int $id Skill ID.
	 * @return object|null
	 */
	public static function get( int $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::table_name(),
				$id
			)
		);
	}

	/**
	 * Get a single skill by slug.
	 *
	 * @param string $slug Skill slug.
	 * @return object|null
	 */
	public static function get_by_slug( string $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE slug = %s',
				self::table_name(),
				$slug
			)
		);
	}

	/**
	 * Create a new skill.
	 *
	 * @param array<string, mixed> $data Skill data: slug, name, description, content, is_builtin, enabled.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				'slug'        => sanitize_title( $data['slug'] ?? '' ),
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'content'     => wp_kses_post( $data['content'] ?? '' ),
				'is_builtin'  => ! empty( $data['is_builtin'] ) ? 1 : 0,
				'enabled'     => isset( $data['enabled'] ) ? ( $data['enabled'] ? 1 : 0 ) : 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing skill.
	 *
	 * @param int                  $id   Skill ID.
	 * @param array<string, mixed> $data Fields to update (name, description, content, enabled).
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = [ 'name', 'description', 'content', 'enabled' ];
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['content'] ) ) {
			$data['content'] = wp_kses_post( $data['content'] );
		}
		if ( isset( $data['enabled'] ) ) {
			$data['enabled'] = $data['enabled'] ? 1 : 0;
		}

		$data['updated_at'] = current_time( 'mysql', true );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( $key === 'enabled' ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a skill by ID (refuses built-in skills).
	 *
	 * @param int $id Skill ID.
	 * @return bool|string True on success, error message string if built-in.
	 */
	public static function delete( int $id ) {
		global $wpdb;

		$skill = self::get( $id );

		if ( ! $skill ) {
			return false;
		}

		if ( (int) $skill->is_builtin === 1 ) {
			return 'builtin';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Reset a built-in skill to its original content.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	public static function reset_builtin( int $id ): bool {
		$skill = self::get( $id );

		if ( ! $skill || (int) $skill->is_builtin !== 1 ) {
			return false;
		}

		$builtins = self::get_builtin_definitions();

		if ( ! isset( $builtins[ $skill->slug ] ) ) {
			return false;
		}

		$definition = $builtins[ $skill->slug ];

		return self::update(
			$id,
			[
				'name'        => $definition['name'],
				'description' => $definition['description'],
				'content'     => $definition['content'],
			]
		);
	}

	/**
	 * Get a compact skill index for the system prompt (enabled skills only).
	 *
	 * @return string Formatted index or empty string if no skills enabled.
	 */
	public static function get_index_for_prompt(): string {
		$skills = self::get_all( true );

		if ( empty( $skills ) ) {
			return '';
		}

		$lines = [];
		foreach ( $skills as $skill ) {
			$lines[] = "- {$skill->slug}: {$skill->description}";
		}

		return "## Available Skills\n"
			. "You have access to specialized skill guides. When a user's request matches a skill topic,\n"
			. "use the gratis-ai-agent/skill-load tool to load the full instructions before proceeding.\n\n"
			. "Available skills:\n"
			. implode( "\n", $lines );
	}

	/**
	 * Idempotent seeding of built-in skills (skips if slug exists).
	 */
	public static function seed_builtins(): void {
		foreach ( self::get_builtin_definitions() as $slug => $definition ) {
			$existing = self::get_by_slug( $slug );

			if ( $existing ) {
				continue;
			}

			self::create(
				[
					'slug'        => $slug,
					'name'        => $definition['name'],
					'description' => $definition['description'],
					'content'     => $definition['content'],
					'is_builtin'  => true,
					'enabled'     => $definition['enabled'],
				]
			);
		}
	}

	/**
	 * Return the built-in skill definitions.
	 *
	 * @return array<string, mixed> Keyed by slug.
	 */
	public static function get_builtin_definitions(): array {
		return [
			'wordpress-admin'      => [
				'name'        => 'WordPress Administration',
				'description' => 'General WordPress administration (settings, updates, users, options)',
				'enabled'     => true,
				'content'     => self::builtin_wordpress_admin(),
			],
			'content-management'   => [
				'name'        => 'Content Management',
				'description' => 'Managing posts, pages, media, taxonomies',
				'enabled'     => true,
				'content'     => self::builtin_content_management(),
			],
			'woocommerce'          => [
				'name'        => 'WooCommerce Store Management',
				'description' => 'WooCommerce store management (products, orders, coupons)',
				'enabled'     => false,
				'content'     => self::builtin_woocommerce(),
			],
			'site-troubleshooting' => [
				'name'        => 'Site Troubleshooting',
				'description' => 'Debugging errors, site health, performance diagnosis',
				'enabled'     => true,
				'content'     => self::builtin_site_troubleshooting(),
			],
			'multisite-management' => [
				'name'        => 'Multisite Network Management',
				'description' => 'WordPress Multisite network administration',
				'enabled'     => false,
				'content'     => self::builtin_multisite_management(),
			],
			'seo-optimization'     => [
				'name'        => 'SEO Optimization',
				'description' => 'SEO auditing, on-page optimization, meta tags, technical SEO checks',
				'enabled'     => true,
				'content'     => self::builtin_seo_optimization(),
			],
			'content-marketing'    => [
				'name'        => 'Content Marketing',
				'description' => 'Content strategy, editorial workflows, content audits, publishing analysis',
				'enabled'     => true,
				'content'     => self::builtin_content_marketing(),
			],
			'competitive-analysis' => [
				'name'        => 'Competitive Analysis',
				'description' => 'Analyzing competitor sites, tech stack discovery, content gap analysis',
				'enabled'     => false,
				'content'     => self::builtin_competitive_analysis(),
			],
			'analytics-reporting'  => [
				'name'        => 'Analytics & Reporting',
				'description' => 'Content performance reports, site growth metrics, publishing analytics',
				'enabled'     => true,
				'content'     => self::builtin_analytics_reporting(),
			],
			'gutenberg-blocks'     => [
				'name'        => 'Gutenberg Blocks',
				'description' => 'Creating content with Gutenberg blocks, converting markdown, building layouts',
				'enabled'     => true,
				'content'     => self::builtin_gutenberg_blocks(),
			],
			'full-site-editing'    => [
				'name'        => 'Full Site Editing',
				'description' => 'Block theme templates, template parts, site-wide layout customization',
				'enabled'     => false,
				'content'     => self::builtin_full_site_editing(),
			],
		];
	}

	// ─── Built-in skill content ─────────────────────────────────────

	/**
	 * Return the built-in WordPress Administration skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_wordpress_admin(): string {
		return <<<'MD'
# WordPress Administration

## When to Use
Use this skill when the user asks about general WordPress settings, updates, user management, or site configuration.

## Key WP-CLI Commands

### Settings & Options
- `wp option get <key>` — Read any option value
- `wp option update <key> <value>` — Update an option
- `wp option list --search=<pattern>` — Find options by name pattern

### User Management
- `wp user list --fields=ID,user_login,user_email,roles` — List users
- `wp user get <user> --fields=ID,user_login,user_email,roles` — Get user details
- `wp user create <login> <email> --role=<role>` — Create a user
- `wp user update <user> --role=<role>` — Change user role
- `wp user meta get <user> <key>` — Read user meta

### Updates
- `wp core version` — Current WordPress version
- `wp core check-update` — Check for core updates
- `wp plugin list --fields=name,status,version,update_version` — Check plugin updates
- `wp theme list --fields=name,status,version,update_version` — Check theme updates
- `wp plugin update <plugin>` — Update a plugin
- `wp theme update <theme>` — Update a theme

### Site Info
- `wp option get siteurl` — Site URL
- `wp option get home` — Home URL
- `wp option get blogname` — Site title
- `wp option get active_plugins --format=json` — Active plugins

## REST API Patterns
- `GET /wp/v2/settings` — Read site settings
- `POST /wp/v2/settings` — Update site settings
- `GET /wp/v2/users` — List users
- `GET /wp/v2/plugins` — List plugins (requires auth)

## Verification Steps
After making changes, always verify:
1. Read back the updated value with `wp option get` or the REST API
2. Check for errors in the response
3. Confirm the change had the expected effect
MD;
	}

	/**
	 * Return the built-in Content Management skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_content_management(): string {
		return <<<'MD'
# Content Management

## When to Use
Use this skill when the user asks about creating, editing, or managing posts, pages, media, categories, tags, or custom taxonomies.

## Key WP-CLI Commands

### Posts & Pages
- `wp post list --post_type=<type> --fields=ID,post_title,post_status,post_date` — List content
- `wp post get <id> --fields=ID,post_title,post_status,post_content` — Get single post
- `wp post create --post_type=<type> --post_title=<title> --post_status=<status>` — Create content
- `wp post update <id> --post_title=<title>` — Update content
- `wp post meta get <id> <key>` — Read post meta
- `wp post meta update <id> <key> <value>` — Update post meta

### Taxonomies
- `wp term list <taxonomy> --fields=term_id,name,slug,count` — List terms
- `wp term create <taxonomy> <name>` — Create a term
- `wp term update <taxonomy> <term_id> --name=<name>` — Update a term
- `wp post term list <post_id> <taxonomy>` — Terms assigned to a post
- `wp post term add <post_id> <taxonomy> <term>` — Assign term to post

### Media
- `wp media list --fields=ID,title,url,mime_type` — List media
- `wp media import <url>` — Import media from URL

### Search
- `wp post list --s=<query> --fields=ID,post_title,post_type` — Search content

## REST API Patterns
- `GET /wp/v2/posts?search=<query>&per_page=10` — Search posts
- `GET /wp/v2/pages` — List pages
- `POST /wp/v2/posts` — Create a post (requires title, content, status)
- `PUT /wp/v2/posts/<id>` — Update a post
- `GET /wp/v2/categories` — List categories
- `GET /wp/v2/tags` — List tags

## Verification Steps
After creating or updating content:
1. Retrieve the post/page to confirm changes saved
2. Check the post_status is as expected
3. Verify taxonomy assignments if relevant
MD;
	}

	/**
	 * Return the built-in WooCommerce skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_woocommerce(): string {
		return <<<'MD'
# WooCommerce Store Management

## When to Use
Use this skill when the user asks about WooCommerce products, orders, coupons, customers, or store settings.

## Key WP-CLI Commands

### Products
- `wp wc product list --fields=id,name,status,price,stock_status --user=1` — List products
- `wp wc product get <id> --user=1` — Get product details
- `wp wc product create --name=<name> --regular_price=<price> --user=1` — Create product
- `wp wc product update <id> --regular_price=<price> --user=1` — Update product

### Orders
- `wp wc order list --fields=id,status,total,date_created --user=1` — List orders
- `wp wc order get <id> --user=1` — Get order details
- `wp wc order update <id> --status=<status> --user=1` — Update order status

### Coupons
- `wp wc coupon list --fields=id,code,discount_type,amount --user=1` — List coupons
- `wp wc coupon create --code=<code> --discount_type=<type> --amount=<amount> --user=1` — Create coupon

### Store Settings
- `wp option get woocommerce_currency` — Store currency
- `wp option get woocommerce_store_address` — Store address
- `wp wc setting list general --user=1` — General settings

### Reports
- `wp wc report sales --period=month --user=1` — Sales report

## REST API Patterns
- `GET /wc/v3/products?search=<query>` — Search products
- `POST /wc/v3/products` — Create product
- `PUT /wc/v3/products/<id>` — Update product
- `GET /wc/v3/orders` — List orders
- `PUT /wc/v3/orders/<id>` — Update order
- `GET /wc/v3/coupons` — List coupons
- `POST /wc/v3/coupons` — Create coupon

Note: WooCommerce REST API requires authentication. WP-CLI commands need `--user=1` for admin context.

## Verification Steps
After making changes:
1. Retrieve the object to confirm updates
2. For products, verify price and stock status
3. For orders, confirm the status transition is valid
4. Check that WooCommerce is active before running wc commands
MD;
	}

	/**
	 * Return the built-in Site Troubleshooting skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_site_troubleshooting(): string {
		return <<<'MD'
# Site Troubleshooting

## When to Use
Use this skill when the user reports errors, performance issues, white screens, or needs help diagnosing site problems.

## Diagnostic Commands

### Error Investigation
- `wp option get siteurl` / `wp option get home` — Check for URL mismatches
- `wp eval "error_reporting(E_ALL); ini_set('display_errors', 1);"` — Check PHP error reporting
- `wp config get WP_DEBUG` — Check debug mode status
- `wp config get WP_DEBUG_LOG` — Check if debug logging is on

### Plugin Conflicts
- `wp plugin list --status=active --fields=name,version` — List active plugins
- `wp plugin deactivate --all` — Deactivate all plugins (for conflict testing)
- `wp plugin activate <plugin>` — Reactivate one at a time

### Theme Issues
- `wp theme list --status=active` — Current active theme
- `wp theme activate twentytwentyfive` — Switch to default theme

### Database
- `wp db check` — Check database tables
- `wp db query "SELECT COUNT(*) FROM wp_options WHERE autoload='yes'"` — Check autoloaded options
- `wp transient delete --all` — Clear transients
- `wp cache flush` — Flush object cache

### Performance
- `wp db query "SELECT option_name, LENGTH(option_value) as size FROM wp_options WHERE autoload='yes' ORDER BY size DESC LIMIT 20"` — Large autoloaded options
- `wp cron event list` — Check scheduled events
- `wp rewrite flush` — Flush rewrite rules

### Site Health
- `wp core verify-checksums` — Verify core file integrity
- `wp plugin verify-checksums --all` — Verify plugin file integrity

## Common Issues & Solutions

### White Screen of Death
1. Enable WP_DEBUG: `wp config set WP_DEBUG true --raw`
2. Check debug.log: `wp eval "echo file_get_contents(WP_CONTENT_DIR . '/debug.log');"`
3. Deactivate plugins to find conflict
4. Switch to default theme

### 500 Internal Server Error
1. Check PHP error logs
2. Verify .htaccess: `wp rewrite flush`
3. Check file permissions
4. Increase PHP memory: `wp config set WP_MEMORY_LIMIT 256M`

### Slow Site
1. Check autoloaded options size
2. Review active plugins count
3. Check for long-running cron jobs
4. Verify object caching

## Verification Steps
After applying a fix:
1. Test the specific scenario that was broken
2. Check debug.log for new errors
3. Verify site loads correctly
4. Confirm no regressions
MD;
	}

	/**
	 * Return the built-in Multisite Management skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_multisite_management(): string {
		return <<<'MD'
# Multisite Network Management

## When to Use
Use this skill when the user asks about managing a WordPress Multisite network — sites, users across the network, network settings, or super admin tasks.

## Key WP-CLI Commands

### Network Sites
- `wp site list --fields=blog_id,url,registered,last_updated` — List all sites
- `wp site create --slug=<slug> --title=<title>` — Create a new site
- `wp site activate <id>` — Activate a site
- `wp site deactivate <id>` — Deactivate a site
- `wp site archive <id>` — Archive a site

### Super Admins
- `wp super-admin list` — List super admins
- `wp super-admin add <user>` — Grant super admin
- `wp super-admin remove <user>` — Revoke super admin

### Network Plugins & Themes
- `wp plugin list --fields=name,status --url=<site>` — Plugins on specific site
- `wp theme list --fields=name,status --url=<site>` — Themes on specific site
- `wp plugin activate <plugin> --network` — Network activate plugin
- `wp theme enable <theme> --network` — Network enable theme

### Network Options
- `wp network meta get 1 <key>` — Read network option
- `wp network meta update 1 <key> <value>` — Update network option

### Cross-site Operations
- `wp site list --field=url | xargs -I {} wp option get blogname --url={}` — Run command across all sites
- `wp user list --network --fields=ID,user_login,user_email` — Network-wide user list

## REST API Patterns
- `GET /wp/v2/sites` — List network sites (WP 5.9+)
- Site-specific requests need `--url=<site-url>` flag in WP-CLI

## Verification Steps
After network changes:
1. Verify the site is accessible at its URL
2. Check that plugins/themes are correctly activated
3. Confirm user roles across relevant sites
4. Test network admin access
MD;
	}

	/**
	 * Return the built-in SEO Optimization skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_seo_optimization(): string {
		return <<<'MD'
# SEO Optimization

## When to Use
Use this skill for SEO audits, keyword optimization, meta tag management, and technical SEO checks.

## Available Tools
- `gratis-ai-agent/seo-audit-url` — Fetch any URL and analyze its SEO elements (title, meta description, headings, images, OG tags, structured data)
- `gratis-ai-agent/seo-analyze-content` — Analyze a specific post's SEO quality (keyword density, title length, heading structure, links, readability)

## Key WP-CLI Commands for SEO

### Yoast SEO Meta
- `wp post meta get <id> _yoast_wpseo_title` — SEO title
- `wp post meta get <id> _yoast_wpseo_metadesc` — Meta description
- `wp post meta get <id> _yoast_wpseo_focuskw` — Focus keyword
- `wp post meta update <id> _yoast_wpseo_metadesc "<description>"` — Set meta description

### RankMath Meta
- `wp post meta get <id> rank_math_title` — SEO title
- `wp post meta get <id> rank_math_description` — Meta description
- `wp post meta get <id> rank_math_focus_keyword` — Focus keyword

### Sitemap & Permalinks
- `wp option get permalink_structure` — Current permalink structure
- `wp rewrite flush` — Regenerate rewrite rules
- Check sitemap at: `/sitemap_index.xml` (Yoast) or `/sitemap.xml` (RankMath)

## On-Page SEO Checklist
1. **Title**: 50-60 characters, includes focus keyword
2. **Meta description**: 150-160 characters, compelling and keyword-rich
3. **One H1 tag**: Should match or relate to the page title
4. **Heading hierarchy**: Use H2, H3, H4 in logical order
5. **Focus keyword**: In first paragraph, in title, 0.5-2.5% density
6. **Images**: All images have descriptive alt text
7. **Internal links**: At least 2-3 links to related content
8. **External links**: Link to authoritative sources where relevant

## Technical SEO Checks
- Canonical URL is set and correct
- Meta robots is not accidentally set to "noindex"
- Sitemap exists and is accessible
- Permalink structure uses descriptive slugs (not `?p=123`)
- Pages load without redirect chains

## Common Workflows

### Audit a page
1. Use `gratis-ai-agent/seo-audit-url` with the page URL
2. Review the issues list for quick wins
3. Check title length, meta description, heading structure
4. Verify Open Graph tags are set for social sharing

### Optimize existing content for a keyword
1. Use `gratis-ai-agent/seo-analyze-content` with the post ID and focus keyword
2. Check keyword density and placement
3. Review heading structure for keyword inclusion
4. Ensure meta description includes the keyword
5. Add internal links to related content

### Check technical SEO across the site
1. Audit the homepage with `gratis-ai-agent/seo-audit-url`
2. Check top pages for missing meta descriptions
3. Verify sitemap accessibility
4. Confirm canonical URLs are correct
MD;
	}

	/**
	 * Return the built-in Content Marketing skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_content_marketing(): string {
		return <<<'MD'
# Content Marketing

## When to Use
Use this skill for content strategy planning, editorial workflows, content audits, and content repurposing.

## Available Tools
- `gratis-ai-agent/content-analyze` — Analyze content strategy across posts (frequency, word counts, categories, gaps)
- `gratis-ai-agent/content-performance-report` — Generate content performance summaries for a time period
- `gratis-ai-agent/import-stock-image` — Import stock images for content

## Content Strategy Patterns

### Topic Clusters
- Identify a pillar topic (broad, high-volume keyword)
- Create cluster content (specific, long-tail subtopics)
- Interlink cluster posts back to the pillar page
- Use `gratis-ai-agent/content-analyze` to identify category distribution and gaps

### Content Gaps
- Run content analysis to find categories with few posts
- Identify topics competitors cover that you don't
- Look for "thin content" (posts under 300 words) that could be expanded

### Content Calendar
- Use the performance report to understand publishing frequency
- Aim for consistent publishing (e.g., 2-3 posts per week)
- Plan content around seasonal trends and events

## Editorial Workflows

### Draft to Publish
1. Create draft: `wp post create --post_type=post --post_title="Title" --post_status=draft`
2. Review and edit content
3. Add featured image and meta description
4. Schedule or publish: `wp post update <id> --post_status=publish`

### Bulk Operations
- `wp post list --post_status=draft --fields=ID,post_title` — Review drafts
- `wp post update <id> --post_status=publish` — Publish a draft
- `wp post list --s="keyword" --fields=ID,post_title,post_status` — Find content about a topic

## Content Audit Checklist
1. **Thin content**: Posts under 300 words — expand or consolidate
2. **Stale content**: Posts older than 6 months — refresh with current information
3. **Missing categories**: Posts without category assignments
4. **Missing featured images**: Posts without thumbnails for social/archive display
5. **Missing meta descriptions**: Posts without SEO descriptions
6. **Orphan content**: Posts with no internal links pointing to them
MD;
	}

	/**
	 * Return the built-in Competitive Analysis skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_competitive_analysis(): string {
		return <<<'MD'
# Competitive Analysis

## When to Use
Use this skill for analyzing competitor websites, discovering their tech stack, comparing content strategies, and identifying opportunities.

**Note:** This skill is opt-in because it fetches external URLs. Enable it when you need competitive intelligence.

## Available Tools
- `gratis-ai-agent/fetch-url` — Fetch any URL and return headers, head content, title, meta description, generator tag
- `gratis-ai-agent/analyze-headers` — Analyze HTTP security and performance headers, detect CDN usage

## What to Look For

### Tech Stack Indicators
- **Generator meta tag**: Reveals CMS (WordPress, Shopify, Squarespace, etc.)
- **X-Powered-By header**: Server-side technology (PHP, ASP.NET, Express)
- **Server header**: Web server (nginx, Apache, LiteSpeed)
- **CDN headers**: cf-ray (Cloudflare), x-amz-cf-id (CloudFront), x-vercel-id (Vercel)

### Content Structure
- Page title format and length
- Heading hierarchy (H1, H2 usage)
- Meta description quality and length
- Open Graph / social sharing tags
- Structured data (JSON-LD schemas)

### SEO Indicators
- Canonical URL implementation
- Meta robots directives
- Sitemap presence (check /sitemap.xml, /sitemap_index.xml)

## Workflow

### Analyze a competitor
1. Fetch their homepage with `gratis-ai-agent/fetch-url`
2. Note the generator, server, and CDN from headers
3. Analyze their title and meta description quality
4. Check security headers with `gratis-ai-agent/analyze-headers`
5. Compare findings with your own site

### Content Gap Analysis
1. Fetch competitor's key pages to see their content focus
2. Note topics and keywords they target
3. Compare with your own content using `gratis-ai-agent/content-analyze`
4. Identify topics they cover that you don't

## Ethical Guidelines
- Respect robots.txt directives
- Rate limit your requests — don't send rapid-fire fetches
- Do not scrape or reproduce competitor content
- Use findings for strategic planning, not content copying
- This tool is for analysis, not automated scraping
MD;
	}

	/**
	 * Return the built-in Analytics & Reporting skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_analytics_reporting(): string {
		return <<<'MD'
# Analytics & Reporting

## When to Use
Use this skill for generating content reports, tracking publishing activity, measuring site growth, and understanding content performance.

## Available Tools
- `gratis-ai-agent/content-performance-report` — Content publishing summary with period comparisons
- `gratis-ai-agent/content-analyze` — Content health and strategy metrics

## WP-CLI Commands for Data

### Post Metrics
- `wp post list --post_type=post --post_status=publish --fields=ID,post_title,post_date,comment_count --orderby=date --order=DESC` — Recent posts with comment counts
- `wp post list --post_type=post --post_status=publish --date_query='{"after":"2024-01-01"}' --format=count` — Count posts since date

### Comments
- `wp comment list --status=approve --fields=comment_ID,comment_post_ID,comment_date --number=20` — Recent comments
- `wp comment count` — Comment counts by status

### Users
- `wp user list --fields=ID,user_login,user_registered --orderby=registered --order=DESC` — Recent registrations

## Report Types

### Content Velocity
- Posts published per week/month
- Comparison with previous period
- Publishing trend (increasing, stable, declining)

### Category Breakdown
- Posts per category
- Categories with most activity
- Underserved categories (content gaps)

### Author Productivity
- Posts per author in the period
- Word count averages per author

### Engagement Metrics
- Comments per post
- Posts with most comments
- Pending comments awaiting moderation

## Reporting Workflows

### Weekly Content Summary
1. Run `gratis-ai-agent/content-performance-report` with `days: 7`
2. Highlight posts published this week
3. Note drafts pending review
4. Compare with previous week

### Monthly Growth Report
1. Run `gratis-ai-agent/content-performance-report` with `days: 30`
2. Run `gratis-ai-agent/content-analyze` for content health
3. Report publishing velocity vs last month
4. Identify top categories and content gaps
5. List actionable recommendations
MD;
	}

	/**
	 * Return the built-in Gutenberg Blocks skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_gutenberg_blocks(): string {
		return <<<'MD'
# Gutenberg Blocks

## When to Use
Use this skill when creating content with Gutenberg blocks, converting markdown to blocks, or building custom layouts with columns, groups, buttons, and other block types.

## Available Tools
- `gratis-ai-agent/markdown-to-blocks` — Convert markdown text to serialized Gutenberg block HTML
- `gratis-ai-agent/list-block-types` — Browse and search registered block types
- `gratis-ai-agent/get-block-type` — Get full metadata for a specific block type (attributes, supports, styles)
- `gratis-ai-agent/list-block-patterns` — Browse and search registered block patterns
- `gratis-ai-agent/list-block-templates` — List block templates in the current theme
- `gratis-ai-agent/create-block-content` — Build block HTML from a structured block array
- `gratis-ai-agent/parse-block-content` — Parse existing block content into a structured tree

## Decision Guide

### Use `gratis-ai-agent/markdown-to-blocks` when:
- Creating text-heavy content (blog posts, articles, documentation)
- The content is primarily headings, paragraphs, lists, quotes, code blocks, images, and tables
- You want a fast, simple conversion from markdown

### Use `gratis-ai-agent/create-block-content` when:
- Building layouts that need columns, buttons, groups, or other structural blocks
- Creating landing pages or custom page layouts
- You need precise control over block attributes and nesting
- The content uses blocks that markdown cannot represent (buttons, spacers, covers)

## Block Format Reference
Gutenberg blocks are stored as HTML comments in post_content:
```
<!-- wp:paragraph -->
<p>Hello world</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Title</h3>
<!-- /wp:heading -->
```

## Common Block Types
| Block | Name | Use |
|-------|------|-----|
| Paragraph | core/paragraph | Regular text |
| Heading | core/heading | H1-H6 headings |
| List | core/list | Ordered/unordered lists |
| Image | core/image | Single images |
| Quote | core/quote | Blockquotes |
| Code | core/code | Code snippets |
| Table | core/table | Data tables |
| Columns | core/columns | Multi-column layouts |
| Group | core/group | Container for grouping blocks |
| Buttons | core/buttons | Button groups |
| Separator | core/separator | Horizontal rule |
| Spacer | core/spacer | Vertical spacing |
| Cover | core/cover | Image/color overlay with text |

## Workflows

### Create a blog post
1. Write the content in markdown
2. Use `gratis-ai-agent/markdown-to-blocks` to convert it
3. Use `site/create-post` with the block content

### Build a custom layout
1. Use `gratis-ai-agent/list-block-types` to discover available blocks
2. Use `gratis-ai-agent/get-block-type` to check attributes for specific blocks
3. Use `gratis-ai-agent/create-block-content` to build the layout
4. Use `site/create-page` with the block content

### Analyze existing content
1. Use `gratis-ai-agent/parse-block-content` with a post_id
2. Inspect the block structure and attributes
3. Modify and recreate with `gratis-ai-agent/create-block-content` if needed
MD;
	}

	/**
	 * Return the built-in Full Site Editing skill content.
	 *
	 * @return string Markdown skill content.
	 */
	private static function builtin_full_site_editing(): string {
		return <<<'MD'
# Full Site Editing

## When to Use
Use this skill when working with block themes, editing templates, template parts, or customizing site-wide layout with the Site Editor.

## Prerequisites
Full Site Editing requires a block theme (e.g. Twenty Twenty-Five). Check with `wp_is_block_theme()` or the block editor context.

## Key Concepts

### Block Themes vs Classic Themes
- **Block themes**: Use HTML templates with block markup, theme.json for configuration
- **Classic themes**: Use PHP template files, functions.php for configuration
- FSE features only work with block themes

### Template Hierarchy
Block themes use the same template hierarchy as classic themes but with HTML files:
- `templates/index.html` — Default template
- `templates/single.html` — Single post
- `templates/page.html` — Single page
- `templates/archive.html` — Archive pages
- `templates/404.html` — Not found page

### Template Parts
Reusable sections of templates:
- `parts/header.html` — Site header
- `parts/footer.html` — Site footer
- `parts/sidebar.html` — Sidebar

## Available Tools
- `gratis-ai-agent/list-block-templates` — List all templates with slugs and descriptions
- `gratis-ai-agent/list-block-patterns` — Browse patterns for page creation and templates

## WP-CLI Commands
- `wp theme list --status=active` — Current active theme
- `wp option get template` — Active theme slug
- `wp option get stylesheet` — Active child theme slug

## Theme.json Overview
The `theme.json` file controls global styles and settings:

### Settings
- `color.palette` — Custom color palette
- `typography.fontFamilies` — Custom fonts
- `spacing.spacingSizes` — Spacing presets
- `layout.contentSize` — Default content width

### Styles
- `color.background` — Global background color
- `typography.fontFamily` — Global font
- `elements.link.color` — Link colors

### Custom Templates
Define custom page templates in theme.json:
```json
{
  "customTemplates": [
    { "name": "blank", "title": "Blank", "postTypes": ["page"] }
  ]
}
```

## Block Patterns and FSE
- Page creation patterns appear when creating new pages
- Template patterns can be used in the Site Editor
- Use `gratis-ai-agent/list-block-patterns` to discover available patterns
- Synced patterns (reusable blocks) are stored as `wp_block` post type

## Workflows

### Inspect current theme templates
1. Use `gratis-ai-agent/list-block-templates` to see all templates
2. Use `gratis-ai-agent/parse-block-content` to analyze template structure

### Find patterns for page building
1. Use `gratis-ai-agent/list-block-patterns` with relevant category
2. Review pattern content for suitable layouts
3. Adapt patterns using `gratis-ai-agent/create-block-content`
MD;
	}
}
