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
