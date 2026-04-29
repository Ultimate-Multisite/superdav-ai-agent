# t143: Fix wp.org plugin review blockers: SSL, uninstall, i18n, permissions

## Origin

- **Created:** 2026-04-03
- **Session:** opencode:unknown-2026-04-03
- **Created by:** ai-interactive
- **Parent task:** t124 (WordPress.org submission prep)
- **Conversation context:** Comprehensive wp.org plugin check audit identified 9 critical/high issues that will cause rejection during WordPress.org manual review. This task addresses all blockers.

## What

Fix all issues that would cause the WordPress.org Plugin Review Team to reject the plugin submission. The plugin must pass manual review without requiring back-and-forth with reviewers.

Deliverables:
1. Remove all `sslverify => false` from HTTP calls (8 locations)
2. Create `uninstall.php` that cleans up all plugin data
3. Add `load_plugin_textdomain()` and `wp_set_script_translations()` for all entry points
4. Move authentication logic into `permission_callback` for webhook/resale endpoints
5. Harden CLI tool execution or add `escapeshellarg()` on user-controlled command parts
6. Wrap `error_log()` calls in WP_DEBUG check
7. Add `MODELS.md` to `.distignore`
8. Fix blueprint slugs to use current `sd-ai-agent` naming
9. Update PHPCS `minimum_supported_wp_version` to 6.9

## Why

WordPress.org plugin review is a manual process with 1-4 week turnaround. Each rejection-and-resubmit cycle costs weeks. Fixing all known blockers before submission avoids multiple review rounds and gets the plugin listed faster.

The SSL verification issue alone is an automatic rejection — reviewers have tooling that flags `sslverify => false`. Missing `uninstall.php` is explicitly called out in the Plugin Review Handbook as a requirement for plugins that create database tables.

## How (Approach)

### SSL Verification (Critical)
Remove `'sslverify' => false` from all `wp_remote_post()`/`wp_remote_request()` calls. Remove `'verify_peer' => false` and `'verify_peer_name' => false` from the `stream_context_create()` call. WordPress's HTTP API handles SSL correctly — disabling it is unnecessary and insecure.

Files:
- `includes/Core/AgentLoop.php:1146,1205-1206` — streaming and non-streaming calls
- `includes/Core/OpenAIProxy.php:117`
- `includes/REST/RestController.php:2241,2431,6390`
- `includes/REST/WebhookController.php:691`

### Uninstall (Critical)
Create `uninstall.php` at plugin root. Must:
- Check `defined('WP_UNINSTALL_PLUGIN')` guard
- Drop all 10 `{$wpdb->prefix}sd_ai_agent_*` tables
- Delete all `sd_ai_agent_*` options from `wp_options`
- Delete user meta with `sd_ai_agent_*` prefix
- Reference `includes/Core/Database.php` for table names

### i18n (Critical)
- Add `load_plugin_textdomain('sd-ai-agent', false, dirname(plugin_basename(__FILE__)) . '/languages')` on `init` hook in `sd-ai-agent.php`
- Add `wp_set_script_translations()` calls for all 8 JS entry points (admin-page, floating-widget, screen-meta, settings-page, changes-page, abilities-explorer, benchmark-page, unified-admin)

### Permission Callbacks (High)
Move API key validation from `handle_proxy()` into the `permission_callback` for `/resale/proxy` in `ResaleApiController.php:58`.
Move webhook secret validation from `handle_trigger()` into the `permission_callback` for `/webhook/trigger` in `WebhookController.php:68`.

### CLI Hardening (High)
In `CustomToolExecutor.php:265-276`, the `$command` variable after placeholder replacement should have each argument passed through `escapeshellarg()`. The current `preg_replace('/[;&|`$]/', '', $command)` denylist is insufficient.

### error_log (High)
Wrap `error_log()` calls in `NotificationDispatcher.php:337,356` with `if (defined('WP_DEBUG') && WP_DEBUG)`.

### Minor Fixes (Medium)
- Add `MODELS.md` to `.distignore`
- Update `.wordpress-org/blueprints/blueprint.json` line 9: `page=ai-agent` → `page=sd-ai-agent`, line 46: `ai_agent_settings` → `sd_ai_agent_settings`
- Update `phpcs.xml` line 24: `minimum_supported_wp_version` from `6.7` to `6.9`

## Acceptance Criteria

- [ ] Zero instances of `sslverify => false` or `verify_peer => false` in plugin PHP files (excluding vendor/)
  ```yaml
  verify:
    method: bash
    run: "! rg -q 'sslverify.*false|verify_peer.*false' -g '*.php' -g '!vendor/*' -g '!tests/*'"
  ```
- [ ] `uninstall.php` exists at plugin root with `WP_UNINSTALL_PLUGIN` guard
  ```yaml
  verify:
    method: bash
    run: "test -f uninstall.php && rg -q 'WP_UNINSTALL_PLUGIN' uninstall.php"
  ```
- [ ] `load_plugin_textdomain()` called on `init` hook
  ```yaml
  verify:
    method: codebase
    pattern: "load_plugin_textdomain.*sd-ai-agent"
    path: "sd-ai-agent.php"
  ```
- [ ] `wp_set_script_translations()` called for all 8 JS entry points
  ```yaml
  verify:
    method: bash
    run: "count=$(rg -c 'wp_set_script_translations' -g '*.php' -g '!vendor/*' -g '!tests/*' | awk -F: '{s+=$2}END{print s}'); [ \"$count\" -ge 8 ]"
  ```
- [ ] No `__return_true` in permission_callback for REST routes
  ```yaml
  verify:
    method: bash
    run: "! rg -q \"permission_callback.*__return_true\" -g '*.php' -g '!vendor/*' -g '!tests/*'"
  ```
- [ ] `error_log()` calls wrapped in WP_DEBUG check
  ```yaml
  verify:
    method: bash
    run: "! rg -q '^[^/]*error_log\\(' includes/Automations/NotificationDispatcher.php"
  ```
- [ ] `MODELS.md` listed in `.distignore`
  ```yaml
  verify:
    method: codebase
    pattern: "MODELS\\.md"
    path: ".distignore"
  ```
- [ ] Blueprint uses `sd-ai-agent` slug
  ```yaml
  verify:
    method: bash
    run: "! rg -q 'page=ai-agent[^-]|ai_agent_settings' .wordpress-org/blueprints/blueprint.json"
  ```
- [ ] PHPCS passes clean
  ```yaml
  verify:
    method: bash
    run: "composer phpcs 2>&1 | tail -1 | grep -q 'Time:'"
  ```
- [ ] PHPStan passes clean
  ```yaml
  verify:
    method: bash
    run: "composer phpstan 2>&1 | grep -q 'No errors'"
  ```
- [ ] Tests pass
- [ ] Lint clean

## Context & Decisions

- The audit was performed manually because `wp plugin check` could not run (another plugin on the site has a fatal error).
- `sslverify => false` was likely added during development with self-signed certs. It must be removed for production/wp.org.
- The `fopen()` streaming approach in AgentLoop is acceptable with documentation — WordPress HTTP API doesn't support SSE streaming natively. The SSL verification on that stream context must still be enabled.
- `__return_true` on webhook/resale endpoints is functionally fine (they do their own auth), but wp.org reviewers flag it automatically. Moving auth into the callback is a cosmetic fix that satisfies the review.
- The `exec()` in CustomToolExecutor is an admin-only feature (CLI tools). Full hardening is important but the denylist approach is the minimum viable fix. Long-term, `WP_CLI::runcommand()` would be better.

## Relevant Files

- `sd-ai-agent.php` — main plugin file, add i18n loading
- `includes/Core/AgentLoop.php:1146,1205` — SSL verification
- `includes/Core/OpenAIProxy.php:117` — SSL verification
- `includes/REST/RestController.php:2241,2431,6390` — SSL verification
- `includes/REST/WebhookController.php:68,691` — permission callback + SSL
- `includes/REST/ResaleApiController.php:58` — permission callback
- `includes/Tools/CustomToolExecutor.php:265-276` — CLI command hardening
- `includes/Automations/NotificationDispatcher.php:337,356` — error_log
- `includes/Admin/*.php` — wp_set_script_translations additions
- `includes/Core/Database.php` — table names reference for uninstall.php
- `.distignore` — add MODELS.md
- `.wordpress-org/blueprints/blueprint.json` — fix slugs
- `phpcs.xml:24` — min WP version

## Dependencies

- **Blocked by:** none
- **Blocks:** WordPress.org submission (can't submit until these are fixed)
- **External:** none

## Estimate Breakdown

| Phase | Time | Notes |
|-------|------|-------|
| SSL removal | 30m | 8 locations, straightforward removal |
| uninstall.php | 45m | Create file, enumerate tables/options |
| i18n loading | 30m | Add textdomain + script translations |
| Permission callbacks | 30m | Move auth logic into callbacks |
| CLI hardening | 30m | escapeshellarg on command parts |
| error_log wrapping | 10m | 2 locations |
| Minor fixes | 15m | distignore, blueprint, phpcs |
| Testing/verification | 30m | Run all linters, verify |
| **Total** | **~4h** | |
