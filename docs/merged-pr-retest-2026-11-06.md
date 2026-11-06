# Merged PR retest — 2026-11-06 UTC

## Baseline and scope

Freshly fetched `origin/main` still ends at `5e4e947e`; there were no additional
merged production fixes since the previous audit. The session checkout includes
those production commits plus audit documentation, through `3539ba56` before this
report. No production source or dependencies changed during this retest.

The five targeted merged PRs are:

- [#2954](https://github.com/Ultimate-Multisite/superdav-ai-agent/pull/2954),
  for #2949: plugin fixture ownership and symlink-safe cleanup.
- [#2957](https://github.com/Ultimate-Multisite/superdav-ai-agent/pull/2957),
  for #2951: read-only progress guidance.
- [#2958](https://github.com/Ultimate-Multisite/superdav-ai-agent/pull/2958),
  for #2955: truthful installation-result verification.
- [#2960](https://github.com/Ultimate-Multisite/superdav-ai-agent/pull/2960),
  for #2956: executable benchmark contracts and owned SEO fixtures.
- [#2962](https://github.com/Ultimate-Multisite/superdav-ai-agent/pull/2962),
  for #2948: invocation/postcondition classification and rankings.

PR #2963 for private benchmark logs remained open at the initial status check;
it was deliberately not treated as merged or included as a repaired default.

## Independent runtime controls

**16/16 fresh checks passed** on the existing disposable WordPress clone:

- A newly created synthetic plugin was activated before the benchmark collision
  attempt. The runner refused to adopt it, preserved its exact main-file bytes
  and left its activation state unchanged.
- A command-owned fixture containing file, directory and dangling symlinks was
  removed. The external owned marker and directory remained intact.
- Tampering with the fixture ownership marker caused cleanup to fail closed;
  the fixture's marker file remained untouched by the cleanup attempt.
- Unsupported `apm-009` sandbox sequencing returned `deferred` with zero
  assertions and no tool calls before any inference, rather than fabricating a
  working staging flow or awarding a capability pass.
- A fresh compatible **Classic Editor** installation returned
  `installed_and_activated`; the reported main file existed and WordPress
  independently reported it active.
- The current **Contact Form 7** package was incompatible with this fixture's
  PHP runtime. It returned `incompatible_php_required_version` and no main
  plugin file was installed. This is an accurate refusal, not a successful
  installation control.

All collision plugins, symlink targets and installed test packages were owned by
this retest. Packages already present would have been skipped, not deleted.
Cleanup deactivated and removed the owned packages afterward. Logs and copied
test authentication remained outside Git and outside the web root.

## Fresh release checks

- JavaScript: **71 suites / 719 tests / six snapshots passed**.
- PHP/JS/CSS lint: passed.
- PHPStan: zero errors.
- Production build and strict-types postbuild: succeeded.
- Configured bundle budgets: all passed.
- Full PHP suite: **4,725 tests / 22,226 assertions; no errors or failures;
  148 skipped and three incomplete**, in 8m02.801s. Skipped/incomplete cases
  are not claimed as verified behavior.

The build still emits stale Browserslist and generic webpack size warnings.
The PHP run also emitted a Requests PSR-0 deprecation diagnostic. These did not
fail the configured gates. No dependencies or thresholds were modified to
suppress warnings. Rebuilding produced no tracked bundle differences.

## Native workflow retests

The five-case batch completed with **26 metered provider POST attempts**, all
returning HTTP 200, within its 60-attempt/20-minute bound. HTTP success is not
treated as proof of a tool outcome. The production runner/scorer was unchanged.

| Case | Native result | Independent interpretation |
| --- | --- | --- |
| `fn-001` | 5/5 postconditions; functionally verified | Generated PHP passed syntax/activation, registered the `event` post type and expected REST route, and rejected an unauthenticated request with HTTP 401 |
| `apm-001` | 2/2 invocation assertions; ranking-ineligible | Both requested inventory abilities executed beyond the old three-round stopping point; full inventory completeness is not established by invocation checks |
| `acr-001` | 1/1 invocation assertion; graceful degradation; ranking-ineligible | Missing analytics configuration is not presented as verified live analytics |
| `au-007` | 1/2 assertions; false-negative postcondition | SEO execution succeeded and its dispatcher result contained all required fields, but the output matcher still missed that envelope |
| `apm-009` | Deferred; zero assertions; inconclusive; ranking-ineligible | Correct deferral of an unsupported staging sequence, not a working sandbox lifecycle |

The generated `event-manager` fixture was independently confirmed absent after
cleanup. Its five checks do not cover authenticated valid-event creation or
arbitrary generated-plugin correctness.

The SEO failure reproduces existing
[#2965](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2965), which
remained open at the retest status check. Recorded output had `success: true`
and `post_id`, `word_count`, `keyword_density`, `recommendations` inside `result`;
the assertion nevertheless returned `no matching tool output found`. No duplicate
issue was filed and no assertion was weakened to hide the failure.

## Per-PR verdicts

| Merged PR | Retest verdict |
| --- | --- |
| #2954 cleanup | Targeted fix verified: active collision preservation, symlink boundaries, ownership-tamper refusal and successful generated-fixture removal |
| #2957 read-only guidance | Targeted fix verified: native inventory ran both tools; broader output completeness and iteration budgeting remain separate limitations |
| #2958 installer reporting | Targeted fix verified: fresh compatible package really installed/activated; incompatible package accurately refused |
| #2960 benchmark contracts | Partial: explicit sandbox deferral and SEO fixture execution work, but the added SEO postcondition still has matcher defect #2965 |
| #2962 scoring/rankings | Targeted fix verified: real postconditions rank, invocation-only/degradation and deferred cases do not masquerade as verified capability |

This does not clear other open issues, including user-fixture ownership (#2964),
theme inventory truncation (#2966) or update transaction isolation (#2967).

These cases remain privileged CLI-profile tests, not a browser, lower-privilege
approval or live credentialed-integration audit. No first-token, cost-saving or
matched before/after performance claim is made.

The audit clone's services were verified stopped after this batch. Owned retest
plugin paths, including Classic Editor, were absent afterward; other workers'
resources were left untouched. The separately started owned PHPUnit database
was also returned to its stopped state after the full suite completed.

**Conclusion:** four targeted merged fixes are independently verified; #2960
remains partial due to the already tracked #2965 matcher defect. This retest
does not establish release readiness while other confirmed safety/integrity
findings remain unresolved.
