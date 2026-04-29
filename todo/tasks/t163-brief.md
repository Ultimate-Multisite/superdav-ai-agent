# t163 — Seamless PHP+JS Abilities: Foundation Slice

Parent: #806 (Plan: Seamless PHP + JS Abilities Integration). Follow-up: t164.

## Session origin

Interactive session on 2026-04-08. User asked to start on #806 and split it into
two PRs so the risky agent-loop pause/resume work ships separately from the
plumbing. This is PR 1 of 2.

## What

Land the foundation for client-side abilities in the Superdav AI Agent plugin:

- A PHP `JsAbilityCatalog` class that mirrors the metadata of the `sd-ai-agent-js/*`
  abilities the plugin registers in the browser. Pure metadata; no execute callback.
  Used later by AgentLoop (t164) to validate client-posted descriptors and by the
  abilities-explorer UI to list client abilities. Populated statically from a
  single source of truth that the JS side also reads, so PHP and JS never drift.
- A JS registry module that registers a `sd-ai-agent-js` category and two
  initial abilities into the core `core/abilities` store:
  - `sd-ai-agent-js/navigate-to` — client-side admin navigation (no reload
    required when the target is inside the admin SPA). Read-only w.r.t. data,
    annotated with `readonly: true` since it does not mutate site state.
  - `sd-ai-agent-js/insert-block` — inserts a block into the active block
    editor. Guarded on `select('core/block-editor')` being defined; no-ops cleanly
    on screens where the editor is not mounted.
- Wiring: every plugin entry point (`unified-admin`, `floating-widget`,
  `screen-meta`, `admin-page`) imports `src/abilities/index.js` at the top so
  registration happens before the chat mounts. `editor.js` self-guards, so it is
  safe to import everywhere.
- PHP bootstrap enqueues `@wordpress/abilities` as a script module (WP 7.0 API)
  on the admin and frontend contexts where our entry points run. Core already
  enqueues `@wordpress/core-abilities` on all admin pages, so server abilities
  are mirrored into the JS store automatically.

This slice **does not** touch `AgentLoop`, the REST controller, the sessions
slice, or introduce loop pause/resume. That lands in t164.

## Why

`#806` calls for a single unified tool list where some abilities run in PHP and
some in the browser (editor insert-block, navigate, read selection) so the chat
UX does not require a save+reload every time the model wants to edit a post.
The WordPress 7.0 `@wordpress/abilities` and `@wordpress/core-abilities`
packages (see https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/)
give us the primitive to register client-side abilities into the same store
that mirrors server-side ones, so the eventual agent-loop round-trip can treat
all abilities uniformly.

Splitting foundation from the loop surgery lets us verify the script-module
enqueue, category registration, and ability discoverability in a browser session
before we change the agent loop's control flow — which is the riskiest part of
the plan.

## How

### Files to add

- **NEW**: `includes/Abilities/Js/JsAbilityCatalog.php` — class under
  `SdAiAgent\Abilities\Js`. Single static method `get_descriptors(): array`
  returning a list of `{name,label,description,category,input_schema,output_schema,annotations,screens}`
  entries for the `sd-ai-agent-js/*` namespace. Entries are hard-coded in
  PHP (mirrored by the JS source), so t164 can validate client-posted descriptors
  against this list without trusting the client. No hooks registered in this
  slice; purely a data class.
- **NEW**: `src/abilities/registry.js` — thin wrapper around `@wordpress/abilities`.
  Exports `registerCategory()` (idempotent), `registerClientAbility(def)` which
  shapes the definition with `meta.annotations`, `category: 'sd-ai-agent-js'`,
  and guards against double-registration, and `snapshotDescriptors()` which
  returns the current `sd-ai-agent-js/*` ability definitions as plain objects
  for posting to the server in t164 (included now so t164 does not have to
  touch this file).
- **NEW**: `src/abilities/navigation.js` — registers
  `sd-ai-agent-js/navigate-to`. Input schema: `{ path: string }` where
  `path` is a wp-admin-relative path (e.g. `plugins.php`, `edit.php?post_type=page`).
  Uses `window.location.assign()` for now (full-page nav) — we can upgrade to
  SPA navigation once core ships a router primitive; this is still a UX win
  because the model does not have to ask the user to click.
  `meta.annotations.readonly: true`.
- **NEW**: `src/abilities/editor.js` — registers `sd-ai-agent-js/insert-block`.
  Guards on `wp.data && wp.data.select('core/block-editor')` (using dynamic
  access so the module is safe to import on non-editor screens). Uses
  `dispatch('core/block-editor').insertBlocks(createBlock(name, attrs))`.
  Input schema: `{ blockName: string, attributes?: object, innerHTML?: string }`.
  `meta.annotations.readonly: false` (writes to the editor state).
- **NEW**: `src/abilities/index.js` — imports registry, navigation, editor
  (in that order), registers the category, then delegates each module to
  register its abilities. Exported `ensureRegistered()` is idempotent so entry
  points can call it multiple times.

### Files to modify

- **EDIT**: `sd-ai-agent.php` — add a new bootstrap action that calls
  `wp_enqueue_script_module('@wordpress/abilities')` on the hooks where our
  entry points enqueue their own bundles (unified admin page, floating widget,
  screen meta, front end). Single helper function to avoid repetition. Model
  on the existing `wp_enqueue_script` calls in this file.
- **EDIT**: `src/unified-admin/index.js` — top-of-file
  `import { ensureRegistered } from '../abilities';` and call it before
  `createRoot().render(...)`.
- **EDIT**: `src/floating-widget/index.js` — same pattern.
- **EDIT**: `src/screen-meta/index.js` — same pattern.
- **EDIT**: `src/admin-page/index.js` — same pattern.
- **EDIT**: `package.json` — add `@wordpress/abilities` and
  `@wordpress/core-abilities` to `devDependencies` (WP packages are externalised
  by `@wordpress/scripts`, so they are not bundled; they live in devDeps purely
  to satisfy the resolver and provide types).
- **EDIT**: `composer.json` autoload — no change needed (PSR-4 already covers
  `includes/Abilities/Js`), but run `composer dump-autoload` after adding the
  class.

### Reference patterns

- Script-module enqueue: follow the WP 7.0 dev note verbatim
  (`wp_enqueue_script_module('@wordpress/abilities')` inside a hook callback).
- Ability registration shape: follow the dev note's `registerAbility` examples
  (name, label, description, category, callback, input_schema, output_schema,
  meta.annotations).
- Existing JS entry points in `src/*/index.js` for import ordering and the
  mount pattern.
- Existing PHP enqueue helper(s) in `sd-ai-agent.php` for the action-hook
  priority and script-handle conventions.

### Out of scope for this slice

- No `AgentLoop` changes — the server still sees only PHP abilities. The model
  will not call client abilities until t164 lands.
- No REST route changes — `/chat/tool-result` is t164.
- No `sessionsSlice` changes — client descriptors are not posted yet; the
  `snapshotDescriptors()` helper is in place for t164 to call.
- No abilities-explorer UI changes — t164 will add the "client" badge.
- No PHPUnit changes — AgentLoop is untouched, so existing tests still pass.

## Acceptance criteria

1. `npm run build` succeeds with no new warnings.
2. `composer phpcs` passes on the new PHP file with zero violations.
3. `composer phpstan` passes.
4. On any admin page (e.g. the plugins list) after a fresh reload,
   `wp.data.select('core/abilities').getAbilities({ category: 'sd-ai-agent-js' })`
   in the browser console returns at least `navigate-to` (and, on a block-editor
   screen, also `insert-block`).
5. Calling `wp.data.dispatch('core/abilities').executeAbility('sd-ai-agent-js/navigate-to', { path: 'plugins.php' })`
   from the dashboard navigates to the plugins page.
6. On a non-editor screen, `getAbility('sd-ai-agent-js/insert-block')` returns
   `undefined` (self-guard works) and no console errors are emitted.
7. The floating-widget chat still mounts and responds normally (regression check
   — no behaviour change expected since the agent loop is untouched).
8. `JsAbilityCatalog::get_descriptors()` returns a PHP array whose ability names
   match the JS-registered ones exactly (manually verified via `wp shell`).

## Verification commands

```bash
npm run build
composer phpcs includes/Abilities/Js/JsAbilityCatalog.php
composer phpstan
wp plugin activate ai-agent
wp shell <<< 'var_dump(SdAiAgent\\Abilities\\Js\\JsAbilityCatalog::get_descriptors());'
```

Then in the browser console on a fresh admin page load:

```js
wp.data.select('core/abilities').getAbilities({ category: 'sd-ai-agent-js' });
await wp.data.dispatch('core/abilities').executeAbility(
  'sd-ai-agent-js/navigate-to',
  { path: 'plugins.php' }
);
```

## Context

- Dev note: https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/
- Parent plan: #806
- Follow-up: t164 (AgentLoop pause/resume + `/chat/tool-result` + sessionsSlice
  round-trip + PHPUnit coverage)
- Key existing files: `includes/Core/AgentLoop.php` (unchanged this slice),
  `includes/REST/RestController.php` (unchanged this slice),
  `src/store/slices/sessionsSlice.js` (unchanged this slice),
  `sd-ai-agent.php` (bootstrap edit only).
