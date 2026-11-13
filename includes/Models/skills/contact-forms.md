# Contact Forms

## When to Use
Use this skill when the user asks to:
- Create or add a contact form to a page
- Set up a lead capture, registration, feedback, or inquiry form
- Manage form fields, notifications, or submission settings
- Read, export, or manage form submissions
- Embed a form into an existing page or post

## Step 1 — Detect the form plugin

Use `sd-ai-agent/get-plugins` to list installed plugins, then pick the path:

| Active plugin | Path |
|---|---|
| `ninja-forms` | **Ninja Forms abilities** (below). Do not use `sd-ai-agent/create-contact-form`. |
| `wpforms-lite` / `wpforms` | `sd-ai-agent/create-contact-form` (see "Other form plugins") |
| `contact-form-7` | `sd-ai-agent/create-contact-form` (see "Other form plugins") |
| none | Install Ninja Forms (Step 2) for plugin-backed submissions, or use `sd-ai-agent/create-contact-form` for a dependency-free HTML block |

Ninja Forms is the recommended form plugin for new sites: it is free, saves submissions, supports repeatable fieldsets, typed or drawn signatures, calculations, and registers its own abilities.

## Step 2 — Install Ninja Forms if needed

Use `sd-ai-agent/install-plugin`:
```json
{ "slug": "ninja-forms", "activate": true }
```

## Ninja Forms abilities

Ninja Forms registers its own abilities under the `ninjaforms/` namespace (32 at the time of writing). They are not loaded into the model directly: find them with `sd-ai-agent/ability-search` (for example query `ninjaforms form`, or `select:ninjaforms/create-form,ninjaforms/add-field`), read the returned schema, then run them with `sd-ai-agent/ability-call`. Each ability's own description is authoritative: follow its workflow and field names exactly.

| Task | Abilities |
|---|---|
| Discover what exists | `ninjaforms/list-forms`, `ninjaforms/get-form`, `ninjaforms/list-field-types` |
| Build a form | `ninjaforms/create-form` (accepts a `fields` array, so a complete form can be built in one call), `ninjaforms/add-field`, `ninjaforms/update-field`, `ninjaforms/reorder-fields`, `ninjaforms/update-form`, `ninjaforms/duplicate-form` |
| Notifications and behaviour | `ninjaforms/add-action`, `ninjaforms/update-action`, `ninjaforms/list-actions` (email, success message, redirect, save) |
| Calculations | `ninjaforms/add-calculation`, `ninjaforms/update-calculation`, `ninjaforms/list-calculations` |
| Put it on a page | `ninjaforms/embed-form` (two steps: discover the page, then confirm the method and placement with the user), `ninjaforms/get-public-link` |
| Submissions | `ninjaforms/get-submissions`, `ninjaforms/get-submission`, `ninjaforms/get-submission-fields`, `ninjaforms/update-submission`, `ninjaforms/export-submissions`, `ninjaforms/process-submission` |
| Move forms between sites | `ninjaforms/export-form-definition`, `ninjaforms/import-form` |
| Global settings | `ninjaforms/get-plugin-settings`, `ninjaforms/update-plugin-settings` |

### Typical workflow: a contact form on a page

1. `ninjaforms/create-form` with the title and a `fields` array. It adds a submit button and default actions (success message, admin email, record submission) unless you pass custom `actions`.
2. `ninjaforms/get-form` to read the exact field keys, then `ninjaforms/update-action` to set the email recipient to the site owner's confirmed address.
3. `ninjaforms/embed-form` to place the form on a page, asking the user which embed method and placement they want.
4. Fetch the page anonymously and confirm the form markup renders, not just the shortcode text.

### Rules that matter

- **Merge tags need spaces.** Write `Thank you, {field:name} !`, not `{field:name}!`; a tag touching other characters does not render. Use the exact field keys from `ninjaforms/get-form`.
- **Action field names differ.** Email actions use `message`; success-message actions use `success_msg`.
- **Destructive abilities need confirmation.** `ninjaforms/delete-action`, `delete-calculation` and `remove-field` (and `delete-form` / `delete-submission` where enabled) are destructive; ask the user and wait.
- **`remove-field` is a two-step workflow when the field holds submission data.** The first call exports the data and returns `workflow_step: export_required` plus a `next_step` containing the exact phrase (for example `DELETE SPARE DATA FROM 1 SUBMISSIONS`). Show the user the export and the phrase, and only after they agree call again with `export_completed: true` and that `user_confirmation_phrase`. Never invent the phrase.
- **Three abilities are off by default in Ninja Forms.** `ninjaforms/delete-form`, `ninjaforms/update-submission` and `ninjaforms/delete-submission` are marked not-for-AI by Ninja Forms, so they will not appear in `ability-search`. Do not work around this with `run-php` or direct SQL. Tell the user, and that a developer can enable one with the `ninjaforms_mcp_public_delete_form`, `ninjaforms_mcp_public_update_submission` or `ninjaforms_mcp_public_delete_submission` filter returning `true`.
- **Read the response shape.** `ninjaforms/get-form` returns the fields under `form.fields` (use each field's `id` for `update-field`, `remove-field` and `reorder-fields`); `reorder-fields` takes `field_order` as an object of field id to position.
- **Exports return content, not a file.** For `export-submissions` and `export-form-definition`, write the returned content to a file with the requested name and tell the user where it is.
- **Settings and keys.** When showing `ninjaforms/get-plugin-settings`, report API keys as configured or not set; never print secret values.
- **Registration and children's data.** Do not put health, custody, or other sensitive details in notification emails. Keep them in Submissions and point the notification at them.
- **Notifications go to a confirmed address only.**

### WP-CLI patterns

```bash
# Is Ninja Forms active?
wp plugin list --status=active --format=csv | grep ninja-forms

# List forms (id and title)
wp eval 'foreach ( Ninja_Forms()->form()->get_forms() as $f ) { echo $f->get_id() . " " . $f->get_setting( "title" ) . "\n"; }'

# Is a shortcode on a page?
wp post get <PAGE_ID> --field=post_content | grep ninja_form
```

## Other form plugins

If WPForms Lite or Contact Form 7 is already active and the user does not want to switch, use `sd-ai-agent/create-contact-form`. It creates a WPForms form when WPForms is active (shortcode `[wpforms id="FORM_ID"]`), a Contact Form 7 form when CF7 is active (shortcode `[contact-form-7 id="FORM_ID" title="FORM_TITLE"]`), and otherwise returns a dependency-free Gutenberg HTML block:
```json
{
  "title": "Contact Form",
  "recipient_email": "owner@example.com",
  "submit_label": "Send Message"
}
```
Use the returned `block` or `shortcode` exactly, and embed it with `sd-ai-agent/create-post` or `sd-ai-agent/update-post`. Do not install WPForms or Contact Form 7 on a new site; prefer Ninja Forms. CF7 does not register WordPress Abilities, so use `sd-ai-agent/run-php` or WP-CLI for any further CF7 changes.

## Verification Steps

After creating and embedding a form:
1. Confirm the form plugin is active: `sd-ai-agent/get-plugins`
2. Confirm the form exists (`ninjaforms/get-form` for Ninja Forms; `wp post get <FORM_ID> --post_type=wpforms --field=post_status` for WPForms)
3. Confirm the page is published: `wp post get <PAGE_ID> --field=post_status`
4. Confirm the shortcode is in the page content: `wp post get <PAGE_ID> --field=post_content`
5. Confirm the notification recipient is the site owner's confirmed address
6. Visit the frontend page or fetch it anonymously and confirm the form markup renders
