# Native tool-search continuity with OAuth

## Contract

The SD provider keeps its existing OAuth-backed upstream. Native requests use
`/v1/responses`, `store: false`, and `include: ["reasoning.encrypted_content"]`.
They do **not** use `previous_response_id` or require an OpenAI API key.

No PHP AI Client, WordPress core, or OpenAI connector changes are needed. The SD
service wrapper must expose the stateless Responses forwarding route; the
previously deployed wrapper lacked it. Managed aliases retain their existing
`sd_ai_agent_openai_tool_search_enabled` opt-in until the matching service is
deployed and reviewed. Other providers are unchanged.

## Complete native replay

`AgentLoop::configure_model()` binds the authenticated session to the SD model.
After each completed response, `ResponsesContinuation` records:

- the normalized SDK history's acknowledged prefix count and fingerprint;
- a provider/model/endpoint/function-schema/credential scope fingerprint;
- an **encrypted snapshot of the complete native input plus raw output items**.

On the next call, the SDK prefix must match. The model then replays the saved
native sequence and appends only the new local user input or function results.
It never duplicates the reconstructed SDK assistant messages on top of native
output. Reasoning items (including `encrypted_content`), discovery calls/results,
function calls, namespaces, IDs, and provider-specific fields remain intact.
Current instructions and tool declarations are sent on every request.

The ordinary SDK history remains authoritative for UI, persistence, compaction,
and confirmation/browser-tool boundaries. A snapshot is not a concurrency lock;
existing job/session serialization remains responsible for parallel requests.

## Retention and fallback

- Snapshots use one-day WordPress transients scoped to site/session/user.
- Raw native history is encrypted with AES-256-GCM using a domain-separated key
  derived from WordPress's auth salt. Authenticated metadata binds ciphertext to
  the session key, scope, prefix hash and count. Public metadata contains no
  plaintext prompt/tool result or credential value.
- The snapshot's JSON is capped at 1 MiB. Missing OpenSSL, oversized snapshots,
  corrupted ciphertext, salt/credential changes, expired state, or changed local
  history/catalog cannot produce a partial native replay.
- If tool history exists but no valid native snapshot remains, use full ordinary
  SDK history through Chat Completions. Recognized native request rejection also
  clears the snapshot and falls back. Server failures do not advance it.
- `store: false` controls upstream retention, **not zero local retention**: this
  feature deliberately retains an encrypted local copy for up to one day, in
  addition to the agent's ordinary session history. Deleting/compacting a session
  makes old state unusable; transient expiry handles the auxiliary retention.
- Explicit custom `previous_response_id` is incompatible with this OAuth path and
  uses the compatible fallback. The implementation does not silently switch to
  API-key billing or fabricate missing native state.
- `tool_search` is emitted only when at least one function is deferred. Upstream
  rejects eager-only catalogs containing a tool-search declaration.

## Live verification: 2026-09-09

A temporary dev service used the existing SD OAuth backend and the advertised
`superdav-chat-pro` alias. Actual `AgentLoop` calls used a disposable WordPress
fixture and only `list-terms`, `list-posts`, and `get-post`. No production
deployment, account change, or shared-site activation/schema change was made.

The tasks retrieved a category description (**CERULEAN-842**), then a published
post's calibration values (**7341**, **19 minutes**), then calculated **38 minutes**.

Verified from HTTP traces and assertions:

1. Actual `tool_search_call`, `tool_search_output`, and function-call output.
2. All six calls returned HTTP 200 through `/v1/responses`; **no fallback**.
3. Every follow-up replayed the exact preceding native input plus output as its
   prefix, including encrypted reasoning. Every call used `store: false` and no
   `previous_response_id`.
4. The second and third user turns ran in **fresh PHP processes**, reading the
   encrypted snapshot from the database rather than relying on object memory.
5. A separate run explicitly paused `list-terms` for confirmation and approved
   that read-only fixture in a fresh PHP process. Native replay continued across
   the pause and subsequent turns; all six requests stayed native and successful.

The fresh-process run passed 49 assertions; the confirmation run passed 54.
The latter is confirmation-path evidence, not a claim that browser UI E2E ran.

## Small-workload comparison

One matched three-tool run per mode, with the same prompts/model and fresh PHP
processes on turns two and three:

| Measurement | OAuth native replay | Eager Chat Completions |
| --- | ---: | ---: |
| Correct tasks | 3/3 | 3/3 |
| Provider calls | 6 | 6 |
| Total wall time | 20.735 s | 18.087 s |
| Provider input tokens | 63,838 | 66,667 |
| Provider output tokens | 332 | 428 |
| Cached input tokens | 19,968 | 31,232 |
| Total HTTP request-body bytes | 321,946 | 320,396 |

**No speedup is established.** Native replay was slower in this small sample,
with fewer input tokens and slightly more upload bytes. Cache state, stochastic
output, and mode-specific prompt construction differ. This is a functional
three-tool smoke comparison, not a statistically meaningful large-catalog
benchmark. Use repeated representative workloads before making performance claims.

Usage comes from HTTP responses; SDK trace rows are excluded to avoid double
counting. The agent's aggregate token result was zero in this environment.
Trace collection uses a per-run high-water mark to exclude older fixture rows.

## Regression checks

```sh
php bin/run-wp-phpunit.php --filter='ResponsesContinuationTest|SuperdavAiProviderTest' --no-coverage
php bin/run-wp-phpunit.php --filter='ResponsesContinuationTest|SuperdavAiProviderTest|AgentLoopTest|AgentLoopClientToolsTest' --no-coverage
vendor/bin/phpcs includes/Infrastructure/AiClient/Superdav/ResponsesContinuation.php includes/Infrastructure/AiClient/Superdav/SuperdavAiResponsesToolSearchTextGenerationModel.php
vendor/bin/phpstan analyse --no-progress --memory-limit=1G includes/Infrastructure/AiClient/Superdav/ResponsesContinuation.php includes/Infrastructure/AiClient/Superdav/SuperdavAiResponsesToolSearchTextGenerationModel.php
```

Tests cover complete native replay, exact prefix matching, real agent-loop binding
with mocked HTTP, encryption/tampering/session isolation, bounded retention,
credential/catalog changes, eviction, storage opt-out, retries and eager-only tools.

## Delivery boundary

Keep the PR experimental until the matching service route is deployed and its
accounting/security review is complete. Browser-tool UI E2E and representative
large-catalog performance evaluation remain follow-ups. The OAuth continuation
blocker itself is resolved by replay; no new billing route is needed.
