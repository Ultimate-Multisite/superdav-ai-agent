# Plugin transaction and permission audit — 2026-11-06 UTC

## Scope and baseline

Tested source remains the merged fixes through `5e4e947e`, on audit branch
`test/benchmark-gap-audit`. The prior delivery commit is `72e4460d`; no production
code changed between that verification and this pass. The objective was to test
failure/interleaving outcomes rather than repeat the 99-question invocation audit.

This pass used actual production services on the existing disposable WordPress
clone and **no model inference**. Unique inactive plugin directories, synthetic
accounts/attachments and an explicitly private backup root were owned by the
probe. Source mounts remained read-only. Raw evidence remains outside Git.

## New data-integrity defect

[#2967 — isolate sandbox update stages and preserve immutable backups](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2967)
contains two related reproductions in `PluginUpdater`:

1. Back up original plugin bytes, modify the owned live main file, then back up
   again within the same UTC second. Both calls returned the same successful
   path. The second backup overwrote the first snapshot's contents.
2. Updater A stages candidate A; updater B stages candidate B for the same slug.
   Both successful calls returned the same path. A's candidate was replaced with
   B's bytes before A could test/deploy it.

The first follows second-resolution backup names; the second follows a fixed
per-slug staging directory whose contents are deleted on each stage request.
No wrong-code deployment was attempted in the second control. It demonstrates
deterministically interleaved public service calls, not a completed concurrent
agent-job exploit. The repair brief requires inspection of outer serialization
and operation-owned storage plus safe live-swap coordination.

This is separate from #2934's partial per-file writes in `PluginInstaller`.

## Successful transactional controls

The real runtime verified:

- Invalid PHP syntax is rejected without changing live main-file or asset bytes.
- A syntactically valid runtime-fatal candidate is rejected while live bytes
  remain unchanged and the parent probe survives.
- A valid inactive-plugin update writes the expected source and preserves an
  unmodified asset.
- The recovery snapshot is outside the web root; explicit rollback succeeds and
  restores the original main file byte-for-byte.
- A staging traversal path is rejected; the external marker is unchanged.
- Backing up a plugin with a leaf symlink is rejected; its target is unchanged.
- Interleaved staging itself does not modify live plugin bytes.

These controls do not prove active-plugin reactivation rollback, multisite
behavior, interrupted writes, or full concurrent deployment safety.

## Cross-user media permissions

Two initial positive controls for author/editor deletion were denied. Source
inspection showed that ordinary WordPress roles alone are insufficient: the
plugin intentionally requires its per-tool grant as well as WordPress core and
object capabilities. Those two initial controls were underspecified, not evidence
of product regressions. Their raw failed results were retained.

A separate matrix granted the exact per-tool capability only to synthetic users
and ran **six checks, all passing**:

- Author has the required per-tool/core/object grants for its own attachment.
- That authorized author still cannot delete another author's attachment.
- The denied attachment remains present.
- The authorized author can delete its own attachment.
- An authorized editor has the object capability and can delete another author's
  attachment.
- A subscriber with the plugin grant is still denied because it lacks the
  required core capability; its target remains present.

Some rows above combine outcomes; the recorded matrix has six Boolean checks.
No real role definitions were changed, and synthetic user grants disappeared
when the users were removed.

## Context budget regression reconfirmed

The production `ConversationSerializer::truncate_tool_results()` path retained:

- A **20,000-byte** plain string response unchanged.
- A **20,014-byte** JSON-encoded string response unchanged.

Both exceed `ToolResultTruncator::MAX_RESULT_BYTES` (**4,096 bytes**). The
equivalent structured-array response stayed within the byte budget; small
structured data remained unchanged. This reconfirms existing
[#2920](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2920);
no duplicate issue was opened.

A multibyte result remained encodable through `wp_json_encode()`. That check
does not establish original UTF-8/content preservation, because encoding can
sanitize malformed strings. It does not clear existing
[#2923](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2923).

## Counts and existing test coverage

The initial runtime set recorded **29 checks: 21 true, eight false**:

- Four false checks describe the two new transaction-identity defects.
- Two false checks reconfirm the known string byte-cap bypass.
- Two false checks are the missing-per-tool-grant positive controls described
  above, superseded by the separate six-check authorized matrix, not hidden.

Existing regression command:

```sh
corepack pnpm run test:php -- \
  --filter='PluginUpdaterTest|PluginSandboxTest|MediaAbilitiesTest|ToolResultTruncatorTest|ToolCapabilitiesTest'
```

Result: **114 tests, 714 assertions, all passing**, 56.165 seconds. This is
additional focused coverage, not a newly rerun full suite. The prior full PHP/JS,
lint, PHPStan, build and bundle results are recorded in
[the outcome follow-up](outcome-followup-2026-11-05.md).

Passing existing tests alongside these reproductions demonstrates coverage gaps;
it is not evidence that the new failure conditions are safe. The worker brief
requires focused same-second backup and interleaved-stage regressions within the
existing PHPUnit runner, followed by full repository verification.

## Cleanup and release status

All synthetic plugin directories, private test backups/stages, users and
attachments were removed. The audit clone and separately started owned PHPUnit
database were returned to their stopped state. Other workers' containers,
networks and databases were not pruned or modified.

Release readiness is not established. Immutable recovery data, staging isolation
and bounded tool history remain required repairs before claiming safe plugin
update/recovery and efficient long-context operation.
