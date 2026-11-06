# Outcome and safety follow-up — 2026-11-05

## Scope

Tested source includes merged fixes through `5e4e947e`; session audit merge
`020e419bc63be02d0641148ba31fe42a16c016e5`. The prior 99-question audit remains
separate evidence on its original frozen source. These results do not silently
replace its scores.

This pass prioritizes actual preservation/deletion outcomes, permission denials,
and previously broken workflows rather than more invocation-only smoke passes.
No production source code was changed in this follow-up.

## Independent runtime controls

On the existing disposable WordPress control clone, **13/15 preservation and
permission checks passed**. The two failed checks reproduce one new ownership
defect, not two unrelated product bugs.

| Runtime control | Result |
| --- | --- |
| Claim a new plugin fixture, then remove it normally | Passed |
| Preserve external target of a file symlink during cleanup | Passed |
| Preserve external target of a directory symlink during cleanup | Passed |
| Remove owned fixture containing dangling and external links | Passed |
| Refuse an existing plugin-directory collision | Passed |
| Preserve an unowned plugin after a cleanup attempt | Passed |
| Reject a traversal slug before fixture claim | Passed |
| Preserve a pre-existing user matching the benchmark login | **Failed: account deleted** |
| Preserve that user's draft author | **Failed: reassigned to current admin** |
| Deny subscriber attachment deletion and preserve attachment | Passed |
| Deny subscriber option writes | Passed |
| Deny admin reads of a secret option | Passed |
| Deny admin writes to a protected core option | Passed |
| Permit an administrator attachment-deletion control | Passed |

Rows group some checks for readability; 15 is the underlying Boolean-check
count. All accounts, attachments, posts, plugin directories and symlink targets
used here were newly created audit-owned canaries. No real user/file was deleted.
Remaining canaries were removed in cleanup.

### Newly confirmed defect

[#2964](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2964):
`BenchmarkCommand::cleanup_question_fixtures()` deletes a user based solely on
the configured login `as010_user`, without proving that the command owns that
account. `cleanup_user_fixture()` also reassigns its content to the administrator.
The account existed before the runner's fixture setup; matching a benchmark-like
login is not ownership.

This is distinct from the now-repaired plugin-directory cleanup in #2949.
The issue brief specifies collision refusal/run-scoped ownership, preservation
of existing roles/metadata/content, and focused tests within the existing runner.

## Paid workflow retests

Four targeted native questions consumed **22 provider POST attempts**, within the
60-attempt/20-minute bound. The native logs use UTC date 2026-11-06. Runtime
probes ran first without inference. Results are not a matched latency experiment.

| Case | Native assertions | Outcome |
| --- | --- | --- |
| `apm-001` installed plugins/themes | 2/2 | Both requested abilities executed; invocation-only score correctly excluded from rankings |
| `au-001` five health checks | 4/5 | Four requested checks ran; standalone security check omitted after the iteration budget was exhausted |
| `ct-007` install Contact Form 7 | 0/2 | Correctly reported `incompatible_php_required_version`; no false installation/activation claim |
| `au-007` analyze owned SEO draft | 1/2 | Analysis succeeded, but output assertion failed to recognize the dispatcher envelope |

The disposable runtime image is PHP **8.2.35**. The Contact Form 7 refusal is an
external package/runtime compatibility limitation, not proof that the installer
still falsely reports success. A successful live install remains unverified by
this case. Package prerequisites should be handled explicitly alongside #2952.

The health-check task's six-round budget must accommodate skill loading,
discovery, five requested calls and the final response. This retest no longer
stopped after three read-only rounds, but still omitted one requested check under
its iteration bound. No general completion or latency improvement is claimed.

### SEO matcher false negative

[#2965](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2965):
`tool_output_contains` matches direct tool names only. The successful live call
was logged as `ability-call` with the target in `input.ability`; output had
`success: true` and the required fields inside `result`. The assertion reported
`no matching tool output found` despite the valid result.

A no-model control with identical synthetic fields passed for a direct tool name
and failed for a dispatcher target. The issue requires consistent target
matching, successful-envelope decoding and negative controls for denied/failed,
wrong-target and incomplete results. It does not weaken the outcome assertion.

### Inventory context loss

[#2966](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2966):
the successful theme inventory reported 15 installed themes but retained only
six theme objects plus a truncation marker. The model disclosed the omission;
it did not invent the missing names. The ability exposes no pagination or
projection input, so the agent cannot retrieve the missing entries through that
tool. Thus `apm-001`'s 2/2 invocation score still does not mean the user's full
inventory request was completed.

The worker brief calls for compact identity/status fields before discarding
entries, preserving the existing byte ceiling and providing deterministic
continuation for larger inventories. Increasing context limits is not the fix.

## Existing test and release checks

- `corepack pnpm run test:js -- --runInBand`: **71 suites, 719 tests and six
  snapshots passed**.
- `corepack pnpm run test:php`: **4,725 tests, 22,225 assertions; zero errors or
  failures, 148 skipped and three incomplete**. Completed in 9m40.711s. Skipped
  and incomplete cases are not claimed as verified behavior.
- `corepack pnpm run lint`: PHP, JS and CSS passed.
- `composer phpstan`: zero errors.
- `corepack pnpm run build`: succeeded, including strict-types postbuild.
- `corepack pnpm run check:bundle`: all configured budgets passed. Rebuilding
  produced no tracked bundle differences.

Build diagnostics still warn about stale Browserslist data and webpack's generic
asset/entrypoint recommendations; configured project budgets pass. The full PHP
run also emits a Requests PSR-0 deprecation diagnostic. No dependency or budget
configuration was changed just to suppress warnings.

The follow-up reused private metering and the production benchmark runner, not
a replacement scorer. Raw logs, proxy authentication and copied site data remain
outside Git. The control clone's services were stopped after the paid run.
The separately started owned PHPUnit database was also returned to its stopped
state. Other workers' containers were not stopped or pruned.

## Limits

This pass is not a browser-rendered accessibility/visual audit and does not
establish live credentialed integrations or successful image generation. The
benchmark's privileged CLI profile remains distinct from normal interactive
approval/browser operation. Passing regression assertions is not release
clearance while confirmed data-loss/privacy findings remain unresolved.
