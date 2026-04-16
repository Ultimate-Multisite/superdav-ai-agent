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
