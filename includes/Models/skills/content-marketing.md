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
