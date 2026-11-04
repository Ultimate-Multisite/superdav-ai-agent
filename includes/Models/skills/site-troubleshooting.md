# Site Troubleshooting

## When to Use
Use this skill when the user reports errors, performance issues, white screens, or needs help diagnosing site problems.

## Diagnostic Commands

### Availability First
- Run `sd-ai-agent/site-health-summary` first and inspect its `availability` result. A public frontend HTTP 4xx/5xx or WordPress `wp_die` page is critical even when REST, login, and admin routes work.
- Compare the exact public frontend host with REST, login, and admin responses; do not infer frontend health from an API-only check.
- On Multisite, target the mapped site URL exactly (`wp --url=<mapped-site-url> ...`) and verify the returned blog/domain context before inspecting tenant limits.
- For Ultimate Multisite tenants, call `multisite-ultimate/site-availability-diagnose` when available for active/primary domain mapping, site/customer/membership identity, monthly visits, limits, and lock reasons. If that ability/API is unavailable, say authoritative tenant lock diagnostics are unavailable and use the Ultimate Multisite admin/API; do not infer lock reasons from raw SQL, `wu_*` meta, or Superdav-owned visit calculations.

### Error Investigation
- `wp option get siteurl` / `wp option get home` — Check for URL mismatches
- `wp eval "error_reporting(E_ALL); ini_set('display_errors', 1);"` — Check PHP error reporting
- `wp config get WP_DEBUG` — Check debug mode status
- `wp config get WP_DEBUG_LOG` — Check if debug logging is on
- `wp eval "echo file_exists(WP_CONTENT_DIR . '/debug.log') ? file_get_contents(WP_CONTENT_DIR . '/debug.log') : '';"` — Read the runtime debug log without assuming the file exists
- `wp plugin path <plugin-slug>` — Confirm each checked-out plugin resolves through its expected canonical plugin directory or symlink before debugging activation fatals

### Plugin Conflicts
- `wp plugin list --status=active --fields=name,version` — List active plugins
- `wp plugin deactivate --all` — Deactivate all plugins (for conflict testing)
- `wp plugin activate <plugin>` — Reactivate one at a time

### Theme Issues
- `wp theme list --status=active` — Current active theme
- `wp theme activate twentytwentyfive` — Switch to default theme

### Database
- `wp db check` — Check database tables
- `wp db query "SELECT COUNT(*) FROM wp_options WHERE autoload='yes'"` — Check autoloaded options
- `wp transient delete --all` — Clear transients
- `wp cache flush` — Flush object cache

### Performance
- `wp db query "SELECT option_name, LENGTH(option_value) as size FROM wp_options WHERE autoload='yes' ORDER BY size DESC LIMIT 20"` — Large autoloaded options
- `wp cron event list` — Check scheduled events
- `wp rewrite flush` — Flush rewrite rules

### Site Health
- `wp core verify-checksums` — Verify core file integrity
- `wp plugin verify-checksums --all` — Verify plugin file integrity

## Common Issues & Solutions

### White Screen of Death
1. Enable WP_DEBUG: `wp config set WP_DEBUG true --raw`
2. Check debug.log: `wp eval "echo file_exists(WP_CONTENT_DIR . '/debug.log') ? file_get_contents(WP_CONTENT_DIR . '/debug.log') : '';"`
3. Deactivate plugins to find conflict
4. Switch to default theme

### Plugin Activation Fatal Error
1. Verify `WP_DEBUG_LOG` is enabled and reproduce the activation once.
2. Read `../wordpress/wp-content/debug.log` or use the guarded `wp eval` command
   above; after a missing repository path such as `vendor/`, make this runtime
   log the next evidence source and do not assume Composer dependencies are the
   root cause until it is checked.
   If the failed tool call was literally `read vendor`, treat the missing read as
   a path/layer mistake: inspect the WordPress debug log before checking
   `vendor/`, `composer install`, or autoload state.
3. Confirm the shared WordPress install symlinks every checked-out plugin worktree
   into each plugin's canonical directory name before treating the fatal as an
   application-code regression. Use `wp plugin path <plugin-slug>` or inspect
   `../wordpress/wp-content/plugins/`; do not only verify the active worktree
   basename. When several local plugins are involved, enumerate each canonical
   plugin slug and confirm all resolve to their current checkout.
4. Activate the plugin by canonical slug, then re-check debug.log for the first
   new fatal stack trace.

### Frontend Layout Overlap or Corrupted Layout
Use when controls, headings, or labels overlap, overflow, or are mis-sized on a
public page (for example a WooCommerce product summary on a nonstandard theme).
A successful tool call is not evidence the layout is correct.

1. Reproduce with a scoped capture: a viewport or element screenshot at desktop
   (about 1365px) and mobile (about 390px). Avoid tall `fullPage` captures for
   local overlap problems; a successful 768x8603 image does not show whether the
   affected controls are laid out correctly.
2. If `capture-screenshot` returns `success:false` such as
   `Element not found for selector: ...`, do not patch CSS for that selector or
   for a wrapper class guessed from a plugin name. Treat the selector as
   nonexistent on this theme. Inspect the real rendered DOM (page source, or
   `screenshot-url` of the visible region plus markup of the affected sections),
   identify the visible affected elements, and walk up their ancestors with the
   classes and computed styles that apply (width/height, display, overflow,
   position). If the DOM cannot be inspected, report an explicit verification
   blocker instead of making another unverified change.
3. Trace the responsible code before editing styles. Search for the injected
   class names and inline styles in theme and plugin JS/CSS, and include
   must-use plugins: list `wp-content/mu-plugins/` (and `wp plugin list
   --status=must-use`). Deactivating normal plugins does not remove MU-plugin
   frontend output, and a glob such as `plugins/*<vendor>*/*` will miss it.
4. Look for scripts that locate an ancestor of the affected nodes (for example
   walking up from social links until a node has three or more links) and apply
   fixed-size normalization classes. A wrong ancestor, such as the whole
   product-details column, forces fixed heights onto headings and labels.
   Prefer fixing or excluding that script over layering overriding CSS on an
   unrelated wrapper.
5. Verify the changed elements, not just that a capture succeeded: re-capture the
   same scoped region at desktop and mobile widths, and confirm no overlap or
   horizontal overflow before claiming the layout is fixed. State what was
   observed. Missing evidence or layout symptoms are not proof of an SDK or
   provider failure.

### 500 Internal Server Error
1. Check PHP error logs
2. Verify .htaccess: `wp rewrite flush`
3. Check file permissions
4. Increase PHP memory: `wp config set WP_MEMORY_LIMIT 256M`

### Slow Site
1. Check autoloaded options size
2. Review active plugins count
3. Check for long-running cron jobs
4. Verify object caching

## Verification Steps
After applying a fix:
1. Test the specific scenario that was broken
2. Check debug.log for new errors
3. Verify site loads correctly
4. Confirm no regressions
