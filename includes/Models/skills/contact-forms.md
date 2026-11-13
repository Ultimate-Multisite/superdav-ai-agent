# Contact Forms

## When to Use
Use this skill when the user asks to:
- Create or add a contact form to a page
- Set up a lead capture, feedback, or inquiry form
- Manage form notifications or submission settings
- Embed a form into an existing page or post

## Recommended Path: `sd-ai-agent/create-contact-form`

Use the `sd-ai-agent/create-contact-form` ability for new contact pages. It creates a Ninja Forms form when Ninja Forms is active, otherwise a WPForms Lite form when WPForms is active, otherwise a Contact Form 7 form when CF7 is active, and finally returns a dependency-free Gutenberg HTML fallback. Do not assume the form plugin registers WordPress Abilities on the target site.

Ninja Forms (`ninja-forms`) is the recommended form plugin when a plugin-backed form is needed:

- **Slug**: `ninja-forms`
- **WP Abilities**: not required
- **Gutenberg block**: yes
- **Spam protection**: built-in honeypot, plus reCAPTCHA/hCaptcha/Turnstile
- **Email notifications**: yes, with merge tags such as `{field:email}` and `{all_fields_table}`
- **Free tier covers**: contact, registration and inquiry forms, repeatable fieldsets, typed or drawn signatures, calculations, and saved submissions

WPForms Lite (`wpforms-lite`) is supported when it is already active on a site; do not install it on new sites.

## Setup Workflow

### Step 1 — Detect an active form plugin

Use `sd-ai-agent/get-plugins` to list installed plugins, then look for:

| Plugin slug | Abilities API | Notes |
|---|---|---|
| `ninja-forms` | No | Preferred; use `sd-ai-agent/create-contact-form` |
| `wpforms-lite` / `wpforms` | Varies | Supported when already active; use `sd-ai-agent/create-contact-form` |
| `contact-form-7` | No | 10M installs, common fallback |
| `fluentform` | No | Developer-friendly, no Abilities yet |

### Step 2 — Install Ninja Forms if the user wants plugin-backed submissions

Use `sd-ai-agent/install-plugin`:
```json
{ "slug": "ninja-forms", "activate": true }
```

This installs from wordpress.org and immediately activates the plugin.

### Step 3 — Create the form

Call `sd-ai-agent/create-contact-form` after activating Ninja Forms:
```json
{
  "title": "Contact Form",
  "recipient_email": "owner@example.com",
  "submit_label": "Send Message"
}
```

Use the returned `block` or `shortcode` exactly. If the result provider is `ninja-forms`, the shortcode is `[ninja_form id="FORM_ID"]`. The form saves every submission and emails the recipient; only send the recipient a confirmed address.

### Step 4 — Embed the form in a page

Ninja Forms forms are embedded via shortcode: `[ninja_form id="FORM_ID"]`

To create a new page with the form, use `sd-ai-agent/create-post`:
```json
{
  "title": "Contact Us",
  "content": "[ninja_form id=\"FORM_ID\"]",
  "status": "publish",
  "post_type": "page"
}
```

Or to append a form to an existing page, use `sd-ai-agent/update-post` and append the shortcode to the existing content.

## WP-CLI Patterns

Ninja Forms does not ship form-management WP-CLI commands. Use WP-CLI for plugin management and checks only:

```bash
# Check if Ninja Forms is active
wp plugin list --status=active --format=csv | grep ninja-forms

# Get the site admin email (useful for form notification setup)
wp option get admin_email

# Verify the shortcode is in a page
wp post get <PAGE_ID> --field=post_content | grep ninja_form

# List forms (id and title)
wp eval 'foreach ( Ninja_Forms()->form()->get_forms() as $f ) { echo $f->get_id() . " " . $f->get_setting( "title" ) . "\n"; }'
```

## Sites That Already Use WPForms

If WPForms Lite is active and the user does not want to switch plugins, `sd-ai-agent/create-contact-form` still creates a WPForms form (shortcode `[wpforms id="FORM_ID"]`). Do not install WPForms on a new site.

## Contact Form 7 Fallback

If CF7 (`contact-form-7`) is already installed and the user does not want to switch plugins, CF7 can create forms programmatically — but **it does not register WordPress Abilities**, so the agent must use direct PHP via `sd-ai-agent/run-php` or WP-CLI.

CF7 shortcode format: `[contact-form-7 id="FORM_ID" title="FORM_TITLE"]`

Note: Avoid installing CF7 on new sites. Prefer Ninja Forms for all new setups.

## Verification Steps

After creating and embedding a form:
1. Confirm the form plugin is active for the returned provider (`ninja-forms`, `wpforms-lite`, or `contact-form-7`): `sd-ai-agent/get-plugins`
2. Confirm the returned form exists. For Ninja Forms: `wp eval 'echo Ninja_Forms()->form( FORM_ID )->get()->get_setting( "title" );'`. For WPForms: `wp post get <FORM_ID> --post_type=wpforms --field=post_status`
3. Confirm the page is published: `wp post get <PAGE_ID> --field=post_status`
4. Confirm the shortcode is in the page content: `wp post get <PAGE_ID> --field=post_content`
5. Confirm the notification email recipient is the site owner's confirmed address
6. Visit the frontend page or fetch it anonymously and confirm the form markup renders, not just the shortcode text
