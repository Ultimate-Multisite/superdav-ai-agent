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
