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
