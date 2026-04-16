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
