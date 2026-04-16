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
