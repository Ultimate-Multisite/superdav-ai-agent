# Contact Forms

## When to Use
Use this skill when the user asks to:
- Create or add a contact form to a page
- Set up a lead capture, feedback, or inquiry form
- Manage form notifications or submission settings
- Embed a form into an existing page or post

## Recommended Plugin: WPForms Lite

**WPForms Lite** (`wpforms-lite`) is the recommended contact form plugin. It is the only major free form plugin with confirmed WordPress Abilities API integration (added in v1.9.9.2), making it natively AI-ready for form management.

- **Slug**: `wpforms-lite`
- **Active installs**: 6 million+
- **WP Abilities**: confirmed (v1.9.9.2+)
- **Gutenberg block**: yes
- **Spam protection**: built-in, plus reCAPTCHA/hCaptcha/Turnstile
- **Email notifications**: yes, with smart tags
- **Free tier covers**: simple contact, feedback, and lead capture forms

## Setup Workflow

### Step 1 — Detect an active form plugin

Use `gratis-ai-agent/get-plugins` to list installed plugins, then look for:

| Plugin slug | Abilities API | Notes |
|---|---|---|
| `wpforms-lite` / `wpforms` | **Yes** | Preferred |
| `contact-form-7` | No | 10M installs, common fallback |
| `fluentform` | No | Developer-friendly, no Abilities yet |

### Step 2 — Install WPForms if no supported plugin is active

Use `gratis-ai-agent/install-plugin`:
```json
{ "slug": "wpforms-lite", "activate": true }
```

This installs from wordpress.org and immediately activates the plugin.

### Step 3 — Discover WPForms abilities

After WPForms Lite is active, use `gratis-ai-agent/ability-search` to discover its registered abilities:
```json
{ "query": "wpforms" }
```

WPForms registers its own abilities under the `wpforms/*` namespace. Use those abilities for all form creation and management tasks — do not attempt to duplicate form creation in custom code.

### Step 4 — Embed the form in a page

WPForms forms are embedded via shortcode: `[wpforms id="FORM_ID"]`

To create a new page with the form, use `ai-agent/create-post`:
```json
{
  "title": "Contact Us",
  "content": "[wpforms id=\"FORM_ID\"]",
  "status": "publish",
  "post_type": "page"
}
```

Or to append a form to an existing page, use `ai-agent/update-post` and append the shortcode to the existing content.

## WP-CLI Patterns

WPForms Lite does not ship WP-CLI commands. Use WP-CLI for plugin management only:

```bash
# Check if WPForms is active
wp plugin list --status=active --format=csv | grep wpforms

# Get the site admin email (useful for form notification setup)
wp option get admin_email

# Verify shortcode renders (returns HTML)
wp post get <PAGE_ID> --field=post_content | grep wpforms
```

## Contact Form 7 Fallback

If CF7 (`contact-form-7`) is already installed and the user does not want to switch plugins, CF7 can create forms programmatically — but **it does not register WordPress Abilities**, so the agent must use direct PHP via `gratis-ai-agent/run-php` or WP-CLI.

CF7 shortcode format: `[contact-form-7 id="FORM_ID" title="FORM_TITLE"]`

Note: Avoid installing CF7 on new sites. Prefer WPForms Lite for all new setups.

## Verification Steps

After creating and embedding a form:
1. Confirm WPForms is active: `gratis-ai-agent/get-plugins`
2. Confirm the form ID exists via WPForms abilities (list forms)
3. Confirm the page is published: `wp post get <PAGE_ID> --field=post_status`
4. Confirm the shortcode is in the page content: `wp post get <PAGE_ID> --field=post_content`
5. Confirm the admin notification email is set to the site owner's address
