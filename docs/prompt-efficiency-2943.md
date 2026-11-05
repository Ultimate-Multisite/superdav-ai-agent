# GH#2943: capability-scoped prompt guidance

## Implementation

`SystemInstructionBuilder` now distinguishes the actual resolved tool catalog
from the compact names used for routing prose. This preserves skill and memory
instructions for deferred native tools without advertising tools that an
explicitly restricted run cannot reach. Authorized ability-search/ability-call
bridges retain discovery-target guidance.

- Keep stored user memory facts, authorization and diagnostic policies,
  clarification/consent rules, Advanced capability-status guidance, tool routing,
  and output-verification rules.
- Advertise only reachable memory operations; retain `auto_memory=false`.
- Omit the skill index and explicit-run skill hints when skill-load is
  unreachable. Keep them when the actual catalog or authorized discovery bridge
  can reach skill-load.
- Omit plugin recommendations when directory search is unreachable.
- Put frontend-preview operating rules in fresh frontend turn context, not every
  session's static instructions. An admin-started chat can subsequently acquire
  frontend context without changing its cached instruction snapshot.

Existing persisted session instructions are deliberately not invalidated or
migrated. The static-context reduction applies to fresh instruction snapshots;
already-open chats retain their original snapshots. No provider/model cache,
permission callback, secret scrubber, or write-approval mechanism is changed.

## Paid before/after experiment

Baseline: `8e0c1d67`, using immutable baseline overlays for the two changed
production files. Candidate: implementation commit `02b5320f`. Both use the same
owned QA database, service/account, native managed Responses transport,
`superdav-chat-standard`, medium reasoning and 1,800 output tokens.

Two repetitions per task and version, alternating order. Facts, generated
Gutenberg and plugin source use an explicitly restricted file-read profile.
The real draft-creation control exposes create-post, get-post, block validation,
skill-load and memory-save, preserving useful content-workflow guidance.

Counts below sum both repetitions; elapsed times are means in seconds and include
fresh CLI/container startup. Correctness validation time is excluded.

| Task | Input tokens before → after | Provider calls before / after | Request bytes before → after | Adapter seconds before → after |
| --- | --- | --- | --- | --- |
| File facts | 16,728 → 11,672 | 4 / 4 | 75,718 → 50,826 | 12.897 → 11.267 |
| Gutenberg output | 7,204 → 4,676 | 2 / 2 | 33,792 → 21,346 | 9.297 → 10.628 |
| Plugin source | 7,282 → 4,762 | 2 / 2 | 34,214 → 21,752 | 9.357 → 12.052 |
| Real Gutenberg draft | 28,113 → 23,490 | 6 / 6 | 144,521 → 121,921 | 19.911 → 18.390 |

The three restricted-profile tasks use **32.4% fewer input tokens** and
**34.7% fewer request bytes**. Including the real content control, total input
falls from **59,327 to 44,600 tokens (24.8%)** with unchanged provider-call counts.

This is a verified context-size improvement, **not a demonstrated general
latency or billing improvement**. Some candidate timings were slower, and the
three-task mean increased from 10.517s to 11.316s. Cache warmth was not controlled:
the baseline had 41,344 cached input tokens across all four tasks, versus 17,536
for the candidate. Consequently uncached input increased from 17,983 to 27,064;
raw input reduction must not be described as measured cost savings. Two samples
per case, variable generated output and cold container startup limit timing
conclusions. The proxy buffers responses, so no first-token claim is possible.

## Correctness and exclusions

- Both versions pass all eight final **semantic/functional** cases: two each for
  exact file facts, valid Gutenberg output, plugin behavior and real draft saves.
- All four generated plugins were manually reviewed and hash-gated before
  loading into separate ephemeral WordPress CLI processes. All **20 shortcode
  assertions** passed: registration, default/custom/empty messages and escaping
  script-tag input. No plugin was installed or activated.
- Each corrected content control saved exactly one draft containing three valid
  core paragraph blocks, 76–80 words, and the supplied workshop details. All four
  drafts were deleted and their absence verified.
- One candidate plugin answer included fences and suggestion comments despite a
  source-only request. The existing benchmark normalizes fences before PHP
  validation; this does **not** satisfy strict raw-source compliance. Follow-up:
  https://github.com/Ultimate-Multisite/superdav-ai-agent/issues/2944
  The follow-up suggestion instruction was unchanged here; this small sample
  does not establish that #2943 caused the formatting defect.
- The first four draft controls failed the block-only criterion on both
  versions. Their prompt asked only for ordinary paragraphs. A separate
  deterministic check confirmed that ordinary three-paragraph draft content can
  return the validator's “no Gutenberg blocks” warning without a save failure.
  Those controls remain recorded as failed/excluded; they are not reclassified
  as passing. Only those four cases were rerun, with an explicit core-Gutenberg
  paragraph requirement and retained content/validation details before cleanup.
- This task used 40 metered inference calls: 28 scored calls and 12 calls for the
  excluded initial draft controls. Prior successful readiness was reused.

## Local regression coverage

The focused prompt suite passes **27 tests / 171 assertions**, covering restricted
catalogs, resolved native tools, memory settings, retained skills, diagnostic and
consent guidance, and frontend transitions across cached sessions. The existing
JavaScript suite passes **719 tests / six snapshots in 71 suites**.

`corepack pnpm run verify` passed: PHP/JS/CSS lint, PHPStan (zero errors), the
full PHP suite, production build and configured bundle budgets. PHPUnit reported:

```text
Tests: 4700, Assertions: 22126, Skipped: 148, Incomplete: 3.
```

There were zero test failures/errors; skipped and incomplete cases remain
coverage gaps. The build retained existing Browserslist/webpack advisory warnings
but passed the configured budgets and produced no tracked artifact changes.

This change does not include unmerged PR #2928 or claim that the separately
reported plugin-installation safety defects are fixed.
