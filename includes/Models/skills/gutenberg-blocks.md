# Gutenberg Blocks

## Critical Rule

Content passed to `ai-agent/create-post` or `ai-agent/update-post` must be EITHER:
- **All markdown** — for blog posts and articles (auto-converted to blocks)
- **All serialized block markup** — for pages with visual layouts

**NEVER mix raw markdown with block markup.** Mixed content renders incorrectly.

## When to Use What

| Content type | Format | Example |
|---|---|---|
| Blog post, article, documentation | Markdown | `## Heading\n\nParagraph text` |
| Landing page, about page, services | Block markup | `<!-- wp:cover -->...<!-- /wp:cover -->` |
| Page with columns, buttons, hero sections | Block markup | See layout patterns below |
| Simple page with only text | Either | Markdown is simpler |

## Available Tools

- `sd-ai-agent/create-block-content` — Build block HTML from a structured block array (best for complex nested layouts)
- `sd-ai-agent/parse-block-content` — Parse existing block content into a structured tree
- `sd-ai-agent/validate-block-content` — Validate block content before insertion (catches mixed content, malformed markup)
- `sd-ai-agent/list-block-types` — Browse and search registered block types
- `sd-ai-agent/get-block-type` — Get full metadata for a specific block type
- `sd-ai-agent/list-block-patterns` — Browse and search registered block patterns

## Block Markup Reference

Blocks are HTML comments wrapping standard HTML. Attributes are JSON in the opening comment.

### Paragraph

```html
<!-- wp:paragraph -->
<p>This is a paragraph of text with <strong>bold</strong> and <em>italic</em> formatting.</p>
<!-- /wp:paragraph -->
```

With custom text color:

```html
<!-- wp:paragraph {"style":{"color":{"text":"#555555"}}} -->
<p class="has-text-color" style="color:#555555">Muted paragraph text.</p>
<!-- /wp:paragraph -->
```

### Heading

Default (h2):

```html
<!-- wp:heading -->
<h2 class="wp-block-heading">Section Title</h2>
<!-- /wp:heading -->
```

H3 with custom size:

```html
<!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">Subsection Title</h3>
<!-- /wp:heading -->
```

### Image

```html
<!-- wp:image {"id":42,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/photo.jpg" alt="Description" class="wp-image-42"/></figure>
<!-- /wp:image -->
```

With caption and alignment:

```html
<!-- wp:image {"align":"wide","id":42,"sizeSlug":"full"} -->
<figure class="wp-block-image alignwide size-full"><img src="https://example.com/photo.jpg" alt="Description" class="wp-image-42"/><figcaption class="wp-element-caption">Photo caption here</figcaption></figure>
<!-- /wp:image -->
```

### List (unordered)

```html
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>First item</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Second item</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Third item</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
```

### List (ordered)

```html
<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Step one</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Step two</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->
```

### Buttons

Single button:

```html
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">Get in Touch</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
```

Two buttons (primary + outline):

```html
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/start">Get Started</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/learn-more">Learn More</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
```

### Separator

```html
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->
```

Wide line:

```html
<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->
```

### Spacer

```html
<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
```

### Quote

```html
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>The best way to predict the future is to create it.</p>
<!-- /wp:paragraph --><cite>Peter Drucker</cite></blockquote>
<!-- /wp:quote -->
```

## Layout Blocks

### Columns (two column)

```html
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Left Column</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Content for the left side.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Right Column</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Content for the right side.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
```

Custom column widths (2/3 + 1/3):

```html
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:paragraph -->
<p>Wider column content.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:paragraph -->
<p>Narrower column.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
```

### Three columns

```html
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Feature One</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Description of the first feature.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Feature Two</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Description of the second feature.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Feature Three</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Description of the third feature.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
```

### Group (container with background)

```html
<!-- wp:group {"style":{"color":{"background":"#f0f0f1"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#f0f0f1"><!-- wp:heading -->
<h2 class="wp-block-heading">Section in a colored container</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This content has a light grey background.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

Full-width group with padding:

```html
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"color":{"background":"#1e1e1e","text":"#ffffff"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-text-color has-background" style="color:#ffffff;background-color:#1e1e1e;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textColor":"white"} -->
<h2 class="wp-block-heading has-white-color has-text-color">Dark Section</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>White text on dark background for contrast sections.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
```

### Cover (image/color overlay with text)

```html
<!-- wp:cover {"url":"https://example.com/hero.jpg","dimRatio":50,"align":"full"} -->
<div class="wp-block-cover alignfull"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="https://example.com/hero.jpg" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color">Welcome to Our Site</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"white"} -->
<p class="has-text-align-center has-white-color has-text-color">Your tagline or value proposition goes here.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">Get Started</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->
```

Color-only cover (no image):

```html
<!-- wp:cover {"overlayColor":"primary","align":"wide"} -->
<div class="wp-block-cover alignwide"><span aria-hidden="true" class="wp-block-cover__background has-primary-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large","textColor":"white"} -->
<p class="has-text-align-center has-white-color has-text-color has-large-font-size">Call to action text in a colored banner.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover -->
```

## Page Layout Patterns

### Landing Page Structure

A typical landing page uses this block sequence:

1. **Hero** — Cover block with heading, tagline, and CTA button
2. **Features** — Three-column layout with heading + paragraph in each
3. **About section** — Two-column (text + image) in a Group
4. **Testimonial** — Quote block in a Group with background
5. **CTA** — Group with background, heading, paragraph, and buttons

### About Page Structure

1. **Page title** — Heading (h1 or h2)
2. **Introduction** — Paragraph
3. **Story/Mission** — Two-column (image + text)
4. **Values/Team** — Three-column feature grid
5. **CTA** — Group with contact button

### Services Page Structure

1. **Introduction** — Heading + paragraph
2. **Service cards** — Three-column layout, each with heading + paragraph + button
3. **Process** — Ordered list or numbered steps
4. **CTA** — Cover or Group with contact button

## Common Attributes Reference

### Alignment

```json
{"align": "left"}     // Float left
{"align": "center"}   // Center
{"align": "right"}    // Float right
{"align": "wide"}     // Wide width
{"align": "full"}     // Full width (edge to edge)
```

### Colors

Using theme presets:

```json
{"backgroundColor": "primary"}
{"textColor": "secondary"}
```

Using custom hex:

```json
{"style": {"color": {"background": "#f0f0f1", "text": "#333333"}}}
```

### Typography

```json
{"fontSize": "small"}           // Theme preset
{"fontSize": "medium"}
{"fontSize": "large"}
{"fontSize": "x-large"}
{"style": {"typography": {"fontSize": "18px"}}}  // Custom
```

### Spacing

Using theme presets:

```json
{"style": {"spacing": {"padding": {"top": "var:preset|spacing|50", "bottom": "var:preset|spacing|50"}}}}
```

Using custom values:

```json
{"style": {"spacing": {"padding": {"top": "2rem", "bottom": "2rem", "left": "2rem", "right": "2rem"}}}}
```

### Text alignment

```json
{"textAlign": "center"}
{"textAlign": "left"}
{"textAlign": "right"}
```

## Workflows

### Create a blog post (use markdown)

Write content in markdown format and pass to `ai-agent/create-post`. The markdown is automatically converted to blocks.

### Build a page with layout (use block markup)

1. Write the full page content as serialized block markup following the examples above
2. Pass the block markup string to `ai-agent/create-post` with `post_type: page`
3. Use `sd-ai-agent/validate-block-content` to check for errors before creating

### Analyze and improve existing content

1. Use `sd-ai-agent/parse-block-content` with a post_id to see the current structure
2. Identify issues (missing layout blocks, unstyled sections)
3. Build improved content using block markup
4. Use `ai-agent/update-post` to replace the content
