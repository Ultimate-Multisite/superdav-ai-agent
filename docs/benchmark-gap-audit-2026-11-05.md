# Native benchmark audit — 2026-11-05

## Summary

All **99 questions across ten suites** were exercised on frozen merged commit
`ed6b002f747909d53abcf2ba4e25161fd873126a`, using `sd-ai-agent-cloud` /
`superdav-chat-standard` through the production `BenchmarkCommand` and native
assertion engine. Final native results: **88 passing questions, 11 partial
questions, 159/180 assertions passing**. There were no remaining runner errors,
timeouts or budget-unrun cases.

These are native assertion results, **not an 88.9% verified capability score**:
77/99 questions have only invocation-sufficient assertions. A denied call can
pass those assertions. The remaining 22 questions have additional assertion
types, but are not necessarily comprehensive correctness tests either.

The catalog contains 99 questions, not the initially miscounted 109.
This audit does not clear the release or establish superiority over OpenCode.

## Method and isolation

- One disposable WordPress/database state per suite, preserving state between
  that suite's questions. One question per suite ran first, then the remainder.
- Tested Core/Advanced source was mounted read-only and not updated mid-run.
  Existing runtime images were reused; no public ports were published.
- Prompts, tool logs, authentication data and database snapshots remained in
  private operator storage, outside the web root. They are not report artifacts.
- Bounds: 600 metered provider attempts, 90 minutes from the original start,
  480 seconds per question. Final total: **460 provider attempts**.
- Docker default subnet exhaustion was recovered using collision-checked
  explicit private subnets, without pruning other workers' resources.
- Two questions initially failed before inference because a restarted suite's
  database was not ready. After repairing readiness checks, only `ac-002` and
  `au-002` were retried; both passed. Original failed-attempt evidence was kept.
  No completed model case was repeated. These retries occurred later in their
  suite state, so this is not a strict single-pass ordering experiment.
- All audit-owned WordPress/database services were verified stopped afterward.

The underlying runner uses an administrator, `yolo_mode`, a custom action prompt
and an explicit Tier-1 ability catalog. It is not the normal automatic native
catalog/browser execution profile. Its approval option also did not eliminate
every confirmation requirement. Profile parity is tracked in #2950.

## Results by suite

Latency is the median native question `elapsed_ms`, excluding container startup
but including agent/tool work. It is not first-token latency.

| Suite | Questions passing / total | Assertions passing / total | Invocation-only questions | Median seconds |
| --- | --- | --- | --- | --- |
| content-v1 | 5/7 | 11/16 | 0 | 28.854 |
| functional-v1 | 7/8 | 31/33 | 0 | 54.652 |
| abilities-content-v1 | 12/12 | 17/17 | 10 | 16.127 |
| abilities-media-v1 | 7/7 | 7/7 | 7 | 12.436 |
| abilities-structure-v1 | 10/10 | 20/20 | 6 | 15.050 |
| abilities-design-v1 | 13/13 | 17/17 | 13 | 15.919 |
| abilities-developer-v1 | 7/9 | 14/18 | 8 | 20.628 |
| abilities-plugin-mgmt-v1 | 9/13 | 14/19 | 13 | 14.182 |
| abilities-utility-v1 | 11/13 | 21/26 | 13 | 17.297 |
| abilities-credentialed-v1 | 7/7 | 7/7 | 7 | 15.335 |
| **Total** | **88/99** | **159/180** | **77** | **16.203** |

The seven non-WooCommerce functional cases (`fn-001` through `fn-006`, plus
`fn-008`) passed all **31 assertions**. They include generated-plugin functional
checks, but do not establish comprehensive security, frontend rendering,
cross-version compatibility or safe sandbox lifecycle coverage. In particular,
`plugin_no_php_errors` checks syntax; it is not itself a boot/runtime check.

## Failures and confidence limits

| Cases | Observed result | Interpretation / follow-up |
| --- | --- | --- |
| `ct-005`, `fn-007` | WooCommerce product assertions failed; `wc` command unavailable | Missing prerequisite, not demonstrated model inability; #2952 |
| `adev-001` | Option write refused; requested option remained absent | Positive fixture violates default option write policy; #2952 |
| `apm-001`, `au-001`, `apm-005` | Requested inventory/diagnostics incomplete | Read-only progress nudge can terminate legitimate inspection; #2951 |
| `ct-007` | Contact Form 7 installation/activation unconfirmed; no plugin directory afterward | Installer reported installed-but-main-file-unresolved; exact cause unresolved; #2955 |
| `adev-006` | File write required confirmation; edit/delete not reached | Approval/profile contract gap, not a reason to bypass permissions; #2956 |
| `apm-009`, `apm-010` | Source generated without sandbox staging; dependent update lacked source | Sandbox workflow/setup contract gap; #2956 |
| `au-007` | Raw-text SEO task did not match post-ID-based callable schema | Benchmark input/output contract gap; #2956 |

Important native passes that must not be described as successful outcomes:

- `am-006` passed its invocation assertion, but the agent reported that the
  configured image model did not support image generation. No image or attachment
  ID was produced. Media's 7/7 is not verified image-generation capability.
- `acr-001` and `acr-002` accurately reported missing Google Analytics
  configuration, yet received 100% and ranking eligibility. Credentialed-suite
  invocation coverage is not proof of live GA/GSC or other external data access.
- `adev-002` can pass a delete invocation after the preceding positive write
  fixture failed. A call does not prove an owned fixture was created or deleted.
- Design's 13/13 has no independent rendered-page assertions. Browser quality,
  visual fidelity and accessibility remain unestablished by that score.

## Confirmed safety and scoring defects

No-model probes used newly created audit-owned canaries in a disposable site;
all canaries were removed afterward.

1. A pre-existing marker plugin was deleted by benchmark cleanup after a
   deliberately unregistered-provider failure. No generation had occurred.
2. A symlink inside an owned fixture caused cleanup to delete its external
   canary target, leaving the dangling link and a directory-removal warning.
3. A harmless marker in the default benchmark-log location under uploads was
   retrievable with HTTP 200 and an exact body match. Real private logs were not
   downloaded for the probe.
4. A synthetic denied create-post call still passed the invocation assertion.
   This matches the current matcher, but does not justify functional rankings.

Tracked repairs:

- [#2947 — private benchmark logs](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2947)
- [#2948 — invocation versus verified rankings](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2948)
- [#2949 — ownership/symlink-safe cleanup](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2949)
- [#2950 — execution profiles and production parity](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2950)
- [#2951 — read-only progress nudges](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2951)
- [#2952 — allowed option fixtures and prerequisites](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2952)
- [#2955 — installation result verification](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2955)
- [#2956 — sandbox, SEO and file execution contracts](https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2956)

These are filed implementation briefs, not claims that the fixes have shipped.
Live issue/PR state must be rechecked before starting overlapping work.

## Performance and context measurements

- Native question logs: **2,154,416 prompt tokens**, **34,343 completion tokens**,
  **436 agent turns**. Native median question duration: **16.203 seconds**.
- Sum of final case adapter durations: **2,587.625 seconds**. This excludes
  inter-suite setup, original failed startup attempts and diagnostic probes;
  it is not total audit wall time.
- Metered transport: **460 POST attempts** and **23 metadata GETs**; all 483
  forwarded requests returned HTTP 200. HTTP success does not prove tool success.
- Usage was present on 455 POST responses: **2,161,801 input tokens** and
  **42,190 output tokens**, total **2,203,991**. This transport total includes
  additional calls beyond the native agent counters and has five POST responses
  without usage metadata. It must not be substituted for a complete invoice.

Native and transport counters have different scopes and are reported separately.
No billed-cost saving, time-to-first-token, cache-controlled speedup or OpenCode
comparison is established here. Only one managed chat alias was exercised.
The metering proxy buffers responses; its route allowlist is not a general
speech/transcription test harness. No speech coverage is claimed.

The clearest efficiency repair is to stop confusing useful read-only progress
with spinning: completing requested diagnostics avoids premature answers and
follow-up turns. After #2951, remeasure equivalent completed outcomes, not merely
shorter failed runs. Broader context optimizations need matched repeated controls;
see the separate [prompt-efficiency experiment](prompt-efficiency-2943.md) and
[initial engine comparison](agent-comparison-2026-11-05.md) for their own limits.

## Verification and next release gates

Existing benchmark-engine regression command:

```sh
corepack pnpm run test:php -- --filter='BenchmarkSuiteTest|AssertionEngineTest'
```

Result: **11 tests, 30 assertions, all passed**. This audit did not change
production code or run a new full-repository verification cycle. Earlier release
verification is separate evidence and must not be represented as checks run here.

Native live entry point, for an explicitly disposable, appropriately configured
site with a genuinely private log directory:

```sh
wp sd-ai-agent benchmark run --suite=functional-v1 \
  --provider=sd-ai-agent-cloud --model=superdav-chat-standard \
  --log-dir=/ABSOLUTE/PRIVATE/DIRECTORY
```

Do not run mutating suites on a valuable site: cleanup ownership is defective
on the frozen revision, and task execution changes site/database state even
though result persistence uses files.

Before release: repair the confirmed safety defects, fix the read-only nudge,
align fixtures/contracts, then rerun independently verified plugin lifecycle,
real image generation, credentialed integrations and browser-rendered outcomes.
Preserve negative permission controls and run the repository release gates on
the actual release candidate. Invocation-only suite passes cannot replace them.
