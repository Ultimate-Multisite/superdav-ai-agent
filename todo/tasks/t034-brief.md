# t034: Port GitTrackerManager — manage trackers across plugins/themes

**Task ID:** t034
**Status:** complete
**Estimate:** ~3h
**Logged:** 2026-03-14
**Completed:** 2026-03-16

## Session Origin

Identified during the t033 porting session. The original PHP codebase had a
`GitTrackerManager` class that acted as a registry/factory for `GitTracker`
instances. t033 ported `GitTracker`; t034 was filed to port the manager layer.

## What

Port `GitTrackerManager` — a static registry and factory that manages multiple
`GitTracker` instances across all installed plugins and themes. Provides
site-wide operations: snapshot before modify, record modification, list all
tracked files, revert a whole package, and get a package summary.

## Why

`GitTracker` (t033) tracks files for a single package. The manager layer is
needed so callers (FileAbilities hooks, GitAbilities, REST endpoints) don't
need to know which package a file belongs to — they pass an absolute path and
the manager resolves the correct tracker automatically.

## How

- `includes/Models/GitTrackerManager.php` — static class with:
  - `for_plugin(string $plugin_file): GitTracker|WP_Error`
  - `for_theme(string $theme_slug): GitTracker|WP_Error`
  - `for_file(string $absolute_path): GitTracker|WP_Error` — auto-resolves
  - `snapshot_before_modify(string $absolute_path): true|WP_Error`
  - `record_modification(string $absolute_path): true|WP_Error`
  - `get_modified_packages(): array`
  - `get_all_tracked_files(?string $status): array`
  - `revert_package(string $slug, string $type): array`
  - `get_package_summary(string $slug, string $type): array|WP_Error`
  - `register(): void` — hooks into `gratis_ai_agent_before/after_file_write/edit`
  - `clear_cache(): void` — for tests
- In-memory cache keyed by package slug (plugins: slug, themes: `theme:{slug}`)
- `resolve_plugin_file()` uses `get_plugins()` to find the main plugin file

## Acceptance Criteria

- [x] `GitTrackerManager::for_plugin()` returns a `GitTracker` for a valid plugin
- [x] `GitTrackerManager::for_theme()` returns a `GitTracker` for a valid theme
- [x] `GitTrackerManager::for_file()` resolves the correct tracker from an absolute path
- [x] `snapshot_before_modify()` silently succeeds for files outside plugins/themes
- [x] `get_modified_packages()` returns packages with modified files
- [x] `get_all_tracked_files()` supports optional status filter
- [x] `revert_package()` reverts all modified files and returns counts
- [x] `get_package_summary()` returns total/modified counts and by-status breakdown
- [x] `register()` hooks into FileAbilities before/after write/edit actions
- [x] Unit tests cover all public methods

## Context

The implementation was included in the t033 PR (#442) since the two classes are
tightly coupled. This PR (t034) adds the missing unit tests for `GitTrackerManager`
and the task brief, then formally closes issue #432.
