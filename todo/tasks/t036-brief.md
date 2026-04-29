# t036: Add plugin download links for AI-modified plugins

**Session origin:** Interactive session, 2026-03-16
**Issue:** https://github.com/Ultimate-Multisite/ai-agent/issues/434
**Branch:** feature/t036-plugin-download-links
**Worktree:** /home/dave/ai-agent-feature-t036-plugin-download-links

## What

Add the ability to download a zip of any plugin that the AI agent has modified via its file abilities (write, edit). This lets site admins back up or deploy AI-modified plugins.

## Why

When the AI agent modifies plugin files (via `file-write` or `file-edit` abilities), there is currently no way to retrieve the modified plugin as a downloadable zip. This is a safety and deployment feature: admins need to be able to export what the AI changed.

## How

### Approach (independent of t033)

t036 is marked "blocked by t033" (GitTracker), but the core value can be delivered without git-blob tracking:

1. **New DB table** `sd_ai_agent_modified_files` — records every file write/edit by the AI agent (plugin_slug, file_path, session_id, modified_at). Populated by hooks in `FileWriteAbility` and `FileEditAbility`.

2. **New class** `includes/Abilities/PluginDownloadAbilities.php` — registers a `sd-ai-agent/download-modified-plugin` ability that zips a plugin directory and returns a signed download URL.

3. **New REST endpoints** in `RestController`:
   - `GET /sd-ai-agent/v1/modified-plugins` — returns list of plugins with AI-modified files
   - `GET /sd-ai-agent/v1/download-plugin/{slug}` — streams a zip of the plugin directory (admin-only, nonce-protected)

4. **DB version bump** from `8.0.0` to `9.0.0` to trigger schema migration.

### Files changed

- `includes/Core/Database.php` — add `modified_files_table_name()`, add table to schema, bump DB_VERSION
- `includes/Abilities/FileAbilities.php` — hook write/edit callbacks to record modifications
- `includes/Abilities/PluginDownloadAbilities.php` — new file, download ability
- `includes/REST/RestController.php` — add modified-plugins list + download endpoints
- `sd-ai-agent.php` — register PluginDownloadAbilities

## Acceptance criteria

- [ ] When AI writes/edits a file in `plugins/my-plugin/`, a record is inserted into `sd_ai_agent_modified_files`
- [ ] `GET /sd-ai-agent/v1/modified-plugins` returns JSON list of plugin slugs with modification counts and timestamps
- [ ] `GET /sd-ai-agent/v1/download-plugin/{slug}` returns a zip file of the plugin directory
- [ ] Download endpoint requires `manage_options` capability
- [ ] Download endpoint is nonce-protected (or uses WP REST auth)
- [ ] `sd-ai-agent/download-modified-plugin` ability is registered and callable by the AI agent
- [ ] DB version bumped and migration runs cleanly
- [ ] All existing tests pass

## Context

- FileAbilities write/edit are in `includes/Abilities/FileAbilities.php`
- DB schema pattern: see `includes/Core/Database.php`
- REST pattern: see `includes/REST/RestController.php`
- Ability pattern: see `includes/Abilities/MemoryAbilities.php`
