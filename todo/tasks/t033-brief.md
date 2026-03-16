# t033: Port GitTracker class — store original files as git blobs, track changes

**Task ID:** t033  
**Status:** in_progress  
**Estimate:** ~6h  
**Logged:** 2026-03-14  
**Tags:** feature  
**Ref:** GH#431  

## Session Origin

Dispatched via `/full-loop` from issue #431 (Ultimate-Multisite/ai-agent).

## What

Implement a `GitTracker` class that:
1. Stores the original content of files (before AI edits) as git-style blob objects in the database.
2. Tracks changes made by the AI agent to files in `wp-content`.
3. Exposes WordPress Abilities (tools) so the AI can: snapshot a file before editing, diff current vs original, restore original, and list tracked files.

## Why

The `FileAbilities` class can write/edit arbitrary files in `wp-content`. Without a snapshot mechanism, there is no way to undo AI-made changes or audit what was modified. GitTracker provides a lightweight, database-backed undo/diff layer — no git binary required.

## How

### Core class: `includes/Core/GitTracker.php`
- `snapshot(string $path): int|false` — reads current file content, stores as a blob row, returns row ID.
- `diff(string $path): string` — returns a unified diff between the stored blob and current file content.
- `restore(string $path): bool` — writes the stored blob content back to the file.
- `list_tracked(): array` — returns all tracked file paths with metadata.
- `get_blob(int $id): array|null` — returns a single blob record.
- `delete_blob(int $id): bool` — removes a blob record.

### Database table: `wp_gratis_ai_agent_git_blobs`
- `id`, `path` (relative to wp-content), `content_hash` (SHA-1), `content` (longtext), `created_at`
- DB_VERSION bumped to `10.0.0`.

### Abilities: `includes/Abilities/GitAbilities.php`
- `gratis-ai-agent/git-snapshot` — snapshot a file before editing.
- `gratis-ai-agent/git-diff` — show diff between snapshot and current.
- `gratis-ai-agent/git-restore` — restore file to snapshot.
- `gratis-ai-agent/git-list` — list all tracked files.

### Registration
- `GitAbilities::register()` called in `gratis-ai-agent.php`.
- `gratis-ai-agent/git-` prefix added to `developer` tool profile.

## Acceptance Criteria

1. `GitTracker::snapshot()` stores file content in the DB and returns a valid row ID.
2. `GitTracker::diff()` returns a non-empty unified diff string when the file has changed.
3. `GitTracker::restore()` writes the original content back and the file matches the snapshot.
4. All four abilities are registered and callable via the Abilities API.
5. DB schema installs cleanly via `Database::install()` with the new version.
6. PHP syntax is valid (no parse errors).
7. PHPCS passes with zero violations.

## Context

- `FileAbilities` (in `includes/Abilities/FileAbilities.php`) is the write/edit surface — GitTracker is its undo layer.
- No git binary dependency — all storage is in the WordPress database.
- `AbstractAbility` base class is in `includes/Abilities/AbstractAbility.php`.
- DB install pattern follows `includes/Core/Database.php`.
