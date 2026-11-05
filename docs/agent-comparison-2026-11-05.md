# SD AI Agent / OpenCode comparison — 2026-11-05

## Scope

Release-base SD AI Agent 1.25.0 at `8e0c1d67` and OpenCode 2.2.5, using
the same managed `superdav-chat-standard` alias, service and account. This is a
small descriptive benchmark, not evidence of general superiority.

The Core `AgentLoop.php` loaded by the QA site matched the checkout SHA-256:
`946186cf1bb952703aa2b475ccaba80518572f87146a2dffd79078412de3323e`.
Advanced was loaded and the file-read and block-validation abilities existed.
The unmerged context-efficiency PR #2928 is **not** included in this baseline.

## Method

- Two fresh sessions per engine per task, alternating engine order.
- Synthetic tasks: extract seven facts from a file; produce a Gutenberg workshop
  notice; generate a single-file escaped-shortcode plugin.
- Medium reasoning and an 1,800-token output limit. SD uses its native managed
  Responses path; OpenCode uses an OpenAI-compatible Chat route. Consequently
  this compares the agents' configured native stacks, not identical wire formats.
- Read-only fixture access; no model-driven content publication, plugin
  activation, filesystem writes, or production access.
- Private metering proxy records request bytes, request counts, provider elapsed
  time and service-reported usage. Credentials are not included in the report.
- Proxy buffers responses: **no time-to-first-token or streaming UX conclusion**.
- End-to-end time includes a fresh WordPress CLI container or OpenCode process.
  This is not warm dashboard latency. WordPress engine time is reported separately
  where available; there is no equivalent isolated OpenCode engine timer.
- Initial bounds: 64 provider attempts, twenty minutes overall, and bounded
  individual subprocesses. Successful readiness checks are reused.

## Correctness

Facts must match the fixture, including numeric price/seats. Gutenberg output is
checked using the shipped `sd-ai-agent/validate-block-content` ability and the
requested content. Generated PHP is syntax-checked and inspected before any
separate functional execution; syntax and required-string matches alone do not
prove a functioning plugin.

All four final plugin outputs were manually reviewed, fingerprinted, then loaded
only in separate ephemeral WordPress CLI processes with hash-gated verification.
All **20 functional checks passed**: shortcode registration, default message,
custom message with an ampersand, script-tag escaping and an empty message.
Temporary source files were removed; no plugin was installed or activated.
All four Gutenberg outputs also matched the requested H2, time and two list
items on manual inspection and passed the shipped block validator.

One setup attempt stopped before inference because the custom model was not
available. Explicit configuration and a catalog readiness check resolved it.
The first OpenCode fact reads resolved relative to the parent repository
instead of the fixture workspace and returned null facts. That inherited
working-directory hint also enlarged OpenCode's prompt. The entire exploratory
matrix is **excluded** from the results below. Corrected runs set both the
process working directory and `PWD`, use explicit configuration, and give
OpenCode an absolute fixture path. The fixture content is identical for both
engines. Corrected facts and no-tool tasks were measured in separate batches.

## Results

Both engines passed **6/6** corrected tasks. Each table row covers two runs.
Times are seconds; request counts exclude metadata discovery and readiness.

| Task | SD adapter times | OpenCode adapter times | SD mean | OpenCode mean | Provider requests per engine |
| --- | --- | --- | --- | --- | --- |
| File facts | 12.369, 10.427 | 8.898, 7.038 | 11.398 | 7.968 | 4 |
| Gutenberg notice | 10.860, 10.298 | 6.994, 8.452 | 10.579 | 7.723 | 2 |
| Shortcode plugin source | 9.727, 10.593 | 7.296, 7.731 | 10.160 | 7.514 | 2 |

OpenCode's mean adapter time was **7.735s** versus SD's **10.712s**, approximately
28% less in this sample. These timings exclude the separate correctness checker.
SD's in-engine means were 7.566s for facts, 6.088s for blocks and 5.837s for
plugin source. Its average adapter-minus-engine difference was approximately
4.216s. Do not attribute all of the adapter gap to model reasoning or apply it
to a warm WordPress web request.

### Context and provider measurements

Input tokens include cache hits. Values are summed across both repetitions;
they are service usage measurements, not local token estimates.

| Task | SD input tokens | OpenCode input tokens | SD cached input | OpenCode cached input | SD request bytes | OpenCode request bytes |
| --- | --- | --- | --- | --- | --- | --- |
| File facts | 16,728 | 4,730 | 14,336 | 1,536 | 75,718 | 23,108 |
| Gutenberg notice | 7,204 | 1,358 | 3,456 | 0 | 33,792 | 7,162 |
| Shortcode plugin source | 7,282 | 1,444 | 3,072 | 0 | 34,214 | 7,576 |
| Total | 31,214 | 7,532 | 20,864 | 1,536 | 143,724 | 37,846 |

- Same request counts: eight scored provider calls per engine.
- SD sent approximately **4.14 times** the service-reported input tokens and
  **3.80 times** the request bytes. Uncached input was 10,350 versus 5,996 tokens;
  raw-input ratios must not be presented as billing ratios.
- Total provider elapsed time: SD 36.843s; OpenCode 27.184s. This includes network
  and complete response transfer, not just model compute.
- Output usage: SD 845 tokens; OpenCode 668 tokens, including reported reasoning.
- The final telemetry confirms medium reasoning and 1,800 output tokens on both
  the native Responses and compatible Chat routes.
- Across setup, exploratory runs and corrected runs: 33 metered inference calls;
  16 contributed to the final comparison. Successful readiness was not repeated.

### Interpretation and next optimization

This baseline does **not** establish SD as faster or generally better than
OpenCode. It identifies fixed prompt context as an actionable efficiency target,
while cold container startup is a separate contributor to adapter latency.
Different transports, cache warmth, platform instructions and two samples per
task limit causal conclusions. No load, concurrency, long-session, multi-file
plugin-generation or warm-browser performance conclusion follows from this test.

Worker-ready implementation issue:
https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2943

Inspect `includes/Core/SystemInstructionBuilder.php` and its existing tests for
conditionally applicable frontend-preview, memory, skill-index and companion
guidance. Preserve authorization, secret protection, approval boundaries and
applicable workflow guidance. Measure correctness plus actual token use before
and after; do not optimize by silently dropping required context. This is
separate from PR #2928's tool-result/skill-continuation/history-compaction work.

## Current-base regression verification

`php bin/run-wp-phpunit.php` completed in 8m17.872s:

```text
Tests: 4696, Assertions: 22099, Skipped: 148, Incomplete: 3.
Errors: 0, Failures: 0.
```

The runner's successful summary omitted zero-valued error/failure fields; those
zeros above make the result explicit. Skipped/incomplete tests remain coverage
gaps, not passing assertions. Scripted failure-path log messages during this run
are not themselves failed tests. No production code or build artifacts changed
in this reporting branch; JS/browser/build evidence from earlier audits is not
represented as a fresh run here.

## Release risks still open at this audit

- #2930: block-comment injection through HTML mutations; repair PR #2942 open.
- #2932: generated-plugin slug collisions; repair PR #2936 open.
- #2933: leaf symlinks escaping generated-plugin write boundaries.
- #2934: partial generated-plugin writes on failure.
- Plugin-generation smoke improvements (#2916) and context-efficiency fixes
  (#2928) remain open PRs, not shipped improvements.

Passing a short benchmark does not clear these safety defects for release.
