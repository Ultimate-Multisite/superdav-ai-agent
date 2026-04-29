# t168 — Playwright E2E spec for client-side abilities

## Session origin

Filed 2026-04-08 after the #806 → #815 → #821 → #822 loop. The entire
client-side abilities feature shipped, failed at runtime for three
separate reasons, and each round of fixes required a manual browser
session to confirm. CI never once caught any of the failures because
there was no end-to-end spec — PHPUnit synthetically injects
`client_abilities` into `AgentLoop` options, bypassing the whole
browser pipeline.

## What

Write a Playwright E2E spec that exercises the real browser pipeline
for `sd-ai-agent-js/*` client-side abilities and make it run in
the existing `npm run test:e2e:playwright` job.

Start from the ad-hoc smoke test script that lives at
`/tmp/t165-smoke.mjs` on the dev workstation (used to diagnose #821
and #822). Port it to a proper spec in `tests/e2e/` following the
conventions of the existing specs (e.g. `tests/e2e/unified-admin-menu.spec.js`).

## Why

Every bug in the #806 chain would have been caught by a real browser
spec on the first PR. Specifically:

| Bug | What would have caught it |
|---|---|
| Entry points never imported `src/abilities` (t165 Gap 1) | Asserting `wp.abilities.getAbilities()` contains `sd-ai-agent-js/navigate-to` |
| `@wordpress/abilities` script module never enqueued (t165 Gap 2) | Asserting `wp.abilities` is defined on the page |
| `registerAbility()` wrong signature (t166 Bug A) | pageerror assertion on `Ability name is required` |
| `registerAbilityCategory()` missing description (t166 Bug B) | pageerror assertion on `must contain a description string` |
| Sync calls to async API (t166 Bug C) | Asserting the ability actually exists after a short wait |
| `snapshotDescriptors` reading wrong store (t166 bonus) | Asserting the descriptor count returned by the function |

All of these are cheap to assert in a Playwright spec. The spec will
also protect future `@wordpress/abilities` API changes from silently
breaking us between WP 7.0 and 7.1.

## How

### New file

**NEW**: `tests/e2e/client-abilities.spec.js` — Playwright spec
covering the browser pipeline.

### Test structure

Model on `tests/e2e/unified-admin-menu.spec.js` for:
- the `test.describe` + login `beforeEach` block (use the existing
  Playwright helper, don't reinvent login)
- the console-error assertion helper (existing specs already capture
  `page.on('console')` and `page.on('pageerror')`)
- the wp-env vs live-site base URL selection

### Required test cases

1. **`registers on dashboard`** — log in, navigate to
   `/wp-admin/index.php`, wait for async registration, assert
   `wp.abilities.getAbilityCategory('sd-ai-agent-js')` returns an
   object with the expected label and description.

2. **`navigate-to and insert-block appear in getAbilities()`** — on
   the dashboard, assert
   `wp.abilities.getAbilities()` includes both
   `sd-ai-agent-js/navigate-to` and `sd-ai-agent-js/insert-block`
   with the expected schemas and annotations.

3. **`executeAbility navigate-to actually navigates`** — from the
   dashboard, call
   `await wp.abilities.executeAbility('sd-ai-agent-js/navigate-to', { path: 'plugins.php' })`,
   assert the return is `{ navigated: true, path: 'plugins.php' }`,
   and assert the final URL is `.../wp-admin/plugins.php`.

4. **`executeAbility insert-block inserts on editor screen`** —
   navigate to `/wp-admin/post-new.php`, wait for the block editor
   to mount, call
   `await wp.abilities.executeAbility('sd-ai-agent-js/insert-block', { blockName: 'core/paragraph', attributes: { content: 'hello from playwright' } })`,
   assert the return is `{ inserted: true, clientId: <string>, blockName: 'core/paragraph' }`,
   and assert the block actually appears in the editor DOM.

5. **`insert-block no-ops on non-editor screen`** — from the
   dashboard, call `executeAbility` for `insert-block` and assert it
   returns `{ inserted: false, ... }` without throwing.

6. **`snapshotDescriptors returns the expected list`** — on the
   dashboard, evaluate a tiny inline helper that mirrors
   `src/abilities/registry.js#snapshotDescriptors` (or imports it
   from the built bundle via the exposed global if there is one) and
   assert the returned array has length 2 and the expected shape
   (name, label, description, input_schema, output_schema,
   annotations).

7. **`no relevant console errors`** — capture all console errors and
   page errors on each screen and assert that none contain the
   strings:
   - `Ability name is required`
   - `must contain a \`description\` string`
   - `references non-existent category`
   - `Category not found: sd-ai-agent-js`
   - `Failed to resolve module specifier "@wordpress/abilities"`

### Reference patterns

- **`tests/e2e/unified-admin-menu.spec.js`** — login helper, describe
  block structure, screen-capture conventions.
- **`/tmp/t165-smoke.mjs`** (dev workstation only) — the manual smoke
  test used to debug #821 and #822. Copy its `page.evaluate` blocks
  verbatim into test 1-6.
- **WP 7.0 dev note** (already vendored in t165/t166 briefs) for the
  authoritative API shape.

### CI integration

The spec should run as part of the existing Playwright E2E workflow
(`npm run test:e2e:playwright`). Verify:

1. The new spec is picked up by the default test glob in
   `playwright.config.js`.
2. It passes when run locally against `wp-env`
   (`npx wp-env start && npm run test:e2e:playwright -- --grep client-abilities`).
3. It passes in the CI matrix shards (WP trunk + WP 6.9, 3 shards each).

## Acceptance criteria

1. `tests/e2e/client-abilities.spec.js` exists with all 7 cases above.
2. `npm run test:e2e:playwright -- --grep client-abilities` passes
   locally.
3. CI on the resulting PR has all 6 Playwright shards passing
   (3 × WP trunk + 3 × WP 6.9).
4. The spec is self-contained — does not depend on existing data in
   the dev site (post-new.php is used so no pre-existing post is
   needed).
5. The spec intentionally breaks (verified by temporarily reverting
   a t166 fix) — if you comment out the `await` on
   `registerAbilityCategory` in registry.js, the spec must go red.
   Document this verification in the PR body.

## Verification

```bash
npx wp-env start
npm run test:e2e:playwright -- --grep client-abilities
```

Then trigger CI and confirm all shards are green. If the spec is
flaky in one shard but passes in others, that's a blocker — flaky E2E
specs are worse than no spec.

## Context

- Precursor specs: `tests/e2e/unified-admin-menu.spec.js`,
  `tests/e2e/benchmark-page.spec.js` (both were added as PRs to
  retroactively cover code that shipped without tests, same pattern)
- Ad-hoc precursor: `/tmp/t165-smoke.mjs`
- Related: t167 (test debt cleanup — should be done first or in
  parallel, so this new spec doesn't land into a red baseline)

## Size estimate

~3h — the hard work was already done diagnosing what to assert; this
is mostly a port of the smoke script into the project's test
conventions plus a local + CI verification pass.
