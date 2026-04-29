# t167 — Test debt cleanup (JS snapshots + PHPUnit failures)

## Session origin

Filed 2026-04-08 after the #806 / #822 smoke-test session. t162 was the
original CI-failures task and was marked complete with the stabilising
PRs that landed before #815 merged, but a fresh baseline run on main
(commit after #822) still shows a large block of pre-existing failures
that nothing on the #806 chain touched. Untracked test debt will keep
masking real regressions — this task is a dedicated cleanup pass.

## What

Get `npm run test:js` and `vendor/bin/phpunit` (via `npm run test:php`)
both green on `main`, OR explicitly skip/delete the tests that are
asserting against intentionally-removed behaviour. No partial fixes —
a flaky green is worse than a stable red.

### Baseline failure counts (measured on main after #822)

- **`npm run test:js`** — 214 pass / 31 fail across 5 failing suites:
  - `src/components/__tests__/MessageInput.test.js`
  - `src/components/__tests__/ChatPanel.test.js`
  - `src/components/__tests__/OnboardingWizard.test.js`
  - `src/components/__tests__/ProviderSelector.test.js`
  - `src/components/__tests__/ContextIndicator.test.js`
  - 8 of the 31 are snapshot mismatches; the rest are assertion
    failures (need to inspect each suite to classify).

- **`vendor/bin/phpunit`** — 1738 tests / 31 errors / 20 failures
  (baseline taken from PR #821 CI run 24160589153):
  - `SdAiAgent\Tests\Core\AgentLoopTest::*` — at least
    `test_run_increments_iterations_used`,
    `test_run_accumulates_token_usage`,
    `test_run_appends_user_message_to_history`,
    `test_deserialize_history_round_trip`,
    `test_classify_ability_readonly_*` (3 variants).
  - `SdAiAgent\Tests\Core\CredentialResolverTest::*` — ~20+ methods
    all related to `openai_compat` credentials and `ai_experiments`
    credentials, still asserting against the methods that #805 is
    supposed to remove.

## Why

1. Red CI on every PR gives both the pulse-wrapper merger and human
   reviewers no signal at all. A PR that actually breaks something
   looks the same as one that's clean, which is how #815 shipped with
   three runtime-fatal bugs despite PHPUnit passing (the failing tests
   mask the assertions that WOULD have caught the bug).
2. Snapshot mismatches rot fastest — each component refactor widens
   the gap and makes the eventual fix harder.
3. `CredentialResolverTest` failures are pinned to a scheduled refactor
   (#805). The tests should be updated atomically with that refactor,
   or deleted now if the methods are genuinely dead.

## How

Two parallel tracks — each can be its own commit within the same PR,
or split into sub-PRs if the JS and PHP halves diverge in complexity.

### Track A — JavaScript

1. **Run the failing suites in isolation first**:
   ```bash
   npx jest src/components/__tests__/MessageInput.test.js
   ```
   Classify each failure as one of:
   - **Snapshot-stale**: component output drifted, the new output is
     correct → regenerate with `jest -u` and commit the updated snap.
   - **Assertion regression**: the component actually changed behaviour
     unintentionally → fix the component (separate commit per root
     cause).
   - **Assertion asserting dead behaviour**: the component was
     intentionally changed and the test is obsolete → delete the
     individual `it()` block (not the whole file).

2. For each suite, write a one-line rationale in the commit message
   for why a snapshot was regenerated or a test was deleted. Never
   mass-regenerate snapshots without reading the diff.

3. Verify `npm run test:js` is 214 + N passing, 0 failing, where N is
   the number of tests that were rescoped/regenerated.

### Track B — PHP

1. **`AgentLoopTest`** — the failures suggest fixtures drifted when
   the WP 7.0 Abilities API stubs changed or when the `WP_Ability`
   category constraint landed. Inspect each failing test:
   ```bash
   vendor/bin/phpunit --filter test_run_increments_iterations_used --testdox-text -
   ```
   Fix the fixture (usually a missing category registration or an
   outdated return-type expectation). Model on the fixture pattern in
   `AgentLoopClientToolsTest` which was added in #815 and is green.

2. **`CredentialResolverTest`** — check whether issue #805 is still
   the plan of record for removing the `openai_compat` /
   `ai_experiments` methods. If yes:
   - Either remove the methods AND the tests as part of this task
     (absorbing #805), or
   - Skip the affected tests with `$this->markTestSkipped('See #805')`
     and leave a comment linking back.
   If no (the methods are staying), update the test fixtures to
   match the current implementation.

3. Verify `npm run test:php` is 1738 - M errors, 0 failures, where M
   is the number of tests that were skipped or removed with documented
   rationale.

### Files likely touched

- **EDIT**: `src/components/__tests__/*.test.js` (5 files listed above)
- **EDIT**: `src/components/__tests__/__snapshots__/*.snap`
- **EDIT**: `tests/SdAiAgent/Core/AgentLoopTest.php`
- **EDIT**: `tests/SdAiAgent/Core/CredentialResolverTest.php`
- **POSSIBLY EDIT**: `includes/Core/CredentialResolver.php` (if #805
  is absorbed into this task)
- **POSSIBLY EDIT**: `includes/stubs/*.php` (if the PHPUnit failures
  trace back to WP 7.0 stubs drifting from the real API)

### Reference patterns

- For snapshot regeneration decisions: diff the current output against
  the stored `.snap` — if the only changes are whitespace, attribute
  ordering, or class-name churn from `@wordpress/components` upgrades,
  regeneration is safe. If content or structure changed, investigate
  first.
- For PHPUnit fixtures: `tests/SdAiAgent/Core/AgentLoopClientToolsTest.php`
  is a green fixture written against the current API shape; use it
  as the template for updating AgentLoopTest.

## Acceptance criteria

1. `npm run test:js` exits 0 with no `FAIL` lines.
2. `npm run test:php` (or `vendor/bin/phpunit` in wp-env) exits 0 with
   no errors or failures.
3. The number of DELETED or SKIPPED tests is documented in the PR
   body with a one-line rationale for each.
4. No snapshot is regenerated without its own diff being inspected —
   the PR body must explicitly state "N snapshots regenerated; all
   diffs reviewed as cosmetic/safe".
5. CI on the resulting PR passes both PHPUnit jobs (including
   `PHPUnit (WP trunk)` and `PHPUnit Coverage`).

## Verification

```bash
npm run test:js                              # expect 0 fails
vendor/bin/phpunit                           # expect 0 errors, 0 fails
composer phpstan                             # regression guard
composer phpcs                               # regression guard
```

## Context

- Parent tracker: t162 (closed, but failures remained on main)
- Related: #805 (dead openai_compat method cleanup — may be absorbed)
- Baseline measurement: smoke-test session 2026-04-08, main @ post-#822
- Out of scope: adding new tests (that's t168 for E2E); changing CI
  infrastructure; any non-test code changes that aren't direct fixture
  or method-removal work required to make existing tests pass.

## Size estimate

~4h. Most of the work is classification and careful review; the
actual fixes are mechanical.
