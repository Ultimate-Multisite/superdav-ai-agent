# Native tool-search continuity

## Scope

The SD AI provider's experimental Responses model can continue an authenticated,
persisted agent session using server-managed `previous_response_id`. This is a
plugin-only implementation: it requires no PHP AI Client, WordPress core, or
OpenAI connector changes. Other providers are unchanged.

The service must support **both `/responses` with hosted `tool_search` and stored
response continuation**. A managed model alias is not evidence of support. Keep
the existing `sd_ai_agent_openai_tool_search_enabled` opt-in disabled for managed
aliases until their service deployment supports this contract.

## State and request behavior

`AgentLoop::configure_model()` binds the server-owned session ID to the native
model. `ResponsesContinuation` stores a one-day WordPress transient scoped to the
site, session and current user. Its contents are only the response ID,
acknowledged input count, history fingerprint and configuration fingerprint.
It does not store prompts, function results, credentials or raw native output.

The configuration fingerprint includes provider, model, endpoint, function
descriptions/schemas and a site-salted HMAC of the bound API credential. Unknown
authentication implementations do not get automatic persisted continuation.

After a successful completed response, the cursor acknowledges the normalized
local input plus the returned model message. On the next call, an identical
prefix allows the model to send:

- `previous_response_id` referencing that response;
- only new function results or the new user turn in `input`;
- current instructions and tool definitions, as required by Responses.

The ordinary SDK history remains authoritative and continues to be serialized by
the existing session/confirmation/browser-tool paths. No response ID is accepted
from browser history. Fresh model objects can load the transient after a request
boundary. Existing session concurrency controls remain responsible for serializing
agent jobs; the transient is not a new lock or atomic job checkpoint.

## Invalidation and fallback

- Changed/compacted history, changed tools/model/endpoint/credential, missing or
  expired cursors, and user/session mismatches cannot reuse the old cursor.
- If tool history requires native state but no matching cursor is available,
  send ordinary full history through the existing Chat Completions adapter.
  Do not invent missing native discovery, namespace or reasoning items.
- `store: false` clears the cursor and uses Chat Completions. This implementation
  does not provide stateless native replay for zero-retention deployments.
- Recognized Responses/tool-search/response-ID rejection clears the cursor and
  falls back. Server failures propagate without advancing the acknowledgment.
- An explicit SDK custom `previous_response_id` remains caller-managed; it does
  not update the automatic session cursor.

Falling back cannot reproduce hidden native state, but retains the ordinary
messages and tool results. Native continuity remains an optimization, not a
replacement for permission checks or durable local conversation storage.

## Deterministic verification

```sh
php bin/run-wp-phpunit.php --filter=ResponsesContinuationTest --no-coverage
php bin/run-wp-phpunit.php --filter='ResponsesContinuationTest|SuperdavAiProviderTest|AgentLoopTest|AgentLoopClientToolsTest' --no-coverage
vendor/bin/phpcs includes/Core/AgentLoop.php includes/Infrastructure/AiClient/Superdav/ResponsesContinuation.php includes/Infrastructure/AiClient/Superdav/SuperdavAiResponsesToolSearchTextGenerationModel.php
```

The regression suite exercises cursor reconstruction, private-content exclusion,
identity/history/configuration invalidation, serialized tool-result boundaries,
new user turns, missing/rejected cursors, storage opt-out, credential rotation,
and retry behavior. A real `AgentLoop` with a recording mocked HTTP boundary
proves session binding, tool execution, suffix-only requests and response-ID
rotation across a second loop instance. Inference is mocked in these tests.

## Live experiment: 2026-09-09

Real inference was sent through `AgentLoop` to the configured SD development
service, using its advertised `superdav-chat-pro` alias. The experiment used the
existing disposable WordPress test database and a fixture post, not production
content. Only the actual `list-posts` and `get-post` abilities were allowed.
Credentials stayed in process memory; shared site activation/settings/schema
were not changed. Provider traces were inspected in the disposable environment;
only aggregate measurements and synthetic answers are reported here.

Prompts:

1. Find the published post titled “Continuity Observatory,” read its content,
   and report its calibration code and measurement window without guessing.
2. Using that window, calculate the duration of two windows without another
   tool call.

Both runs correctly returned **7341**, **19 minutes**, then **38 minutes**, using
three agent iterations on the first turn and one on the second.

| Measurement | Native flag off | Native flag on, actual fallback |
| --- | ---: | ---: |
| Successful Chat Completions requests | 4 | 4 |
| Responses requests | 0 | 1, HTTP 404 |
| First-turn wall time | 7.694 s | 8.200 s |
| Second-turn wall time | 3.190 s | 1.607 s |
| Successful inference request-body bytes | 205,083 | 190,106 |
| Extra failed Responses request-body bytes | 0 | 46,432 |
| Provider-reported input tokens | 42,886 | 40,054 |
| Provider-reported output tokens | 182 | 196 |
| Provider-reported cached input tokens | 19,456 | 18,432 |
| Requests containing `previous_response_id` | 0 | 0 |

Token measurements come from HTTP response usage, not the agent's aggregate
usage result (which was zero in this environment). SDK trace rows were excluded
to avoid double-counting the same inference.

**This initial run is fallback evidence, not a native performance benchmark.**
The service returned HTTP 404 for `/v1/responses`. No live hosted discovery or
stored response continuation occurred in that run. The native flag also changes prompt construction,
so even the differing token counts cannot be attributed to native tool search.
One sample per mode and non-deterministic inference do not establish a latency
improvement. The two-tool workload is only a functional smoke test, not a
representative large-catalog benchmark.

An initial raw `gpt-5.5` probe failed model selection after a tool call because
the service advertises managed aliases rather than that raw model ID. Switching
to the advertised alias resolved that test setup issue.

## Follow-up: native discovery verified through an isolated dev wrapper

A later investigation located the deployment wrapper and confirmed that its
public edge had no Responses route, although the Sub2API backend supports
Responses. An isolated dev implementation was tested without replacing the
running service. Its route preserves edge authentication/accounting and returns
encrypted, site-bound response cursors rather than exposing a cross-installation
continuation primitive through a shared upstream pool.

The plugin now accepts bounded opaque response IDs up to 2,048 bytes for that
wrapper. The live test also exposed and fixed an existing adapter defect:
`tool_search` must be omitted when every configured function is eager. Upstream
explicitly rejects tool search without at least one deferred tool.

The expanded fixture supplied `list-posts`, `get-post`, and deferred `list-terms`.
It asked first for the description of a synthetic category, then for the fixture
post's calibration values, then for twice its measurement window. Traces showed:

1. HTTP **200** from `/v1/responses`, with actual **`tool_search_call`**,
   **`tool_search_output`** and **`function_call`** output items.
2. The next request contained the matching `previous_response_id` and exactly
   one new **`function_call_output`**, with no acknowledged history replay.
3. Sub2API rejected that continuation: **“previous_response_id requires an
   OpenAI API-key account for HTTP requests.”** The dev backend's aggregate
   account-type check found one active OpenAI OAuth account and no API-key account.
4. A sanitized HTTP 400 from the wrapper activated the plugin's Chat Completions
   fallback. All three answers were correct: **CERULEAN-842**, **7341 / 19 minutes**,
   and **38 minutes**. The live fixture passed 12 assertions.

The first native response reported 10,736 input tokens and 252 output tokens.
These observations prove **hosted native discovery and correct continuation
request construction**, but not successful server-stored continuation or a
performance gain. The existing OAuth account can perform tool search; the
restriction concerns HTTP stored-response continuation in this Sub2API routing
configuration, not tool search itself.

Completing native continuity therefore requires either an explicitly approved
API-key-backed upstream or a separate implementation preserving/replaying the
complete native state for OAuth. Do not silently switch billing routes, drop
native discovery/reasoning state, or present fallback timings as native results.

## Before enabling or declaring live verification complete

1. Use an SD service deployment and credential that support hosted tool search,
   stored response IDs and the chosen advertised model.
2. Repeat the two-turn fixture through a real agent session with tracing enabled
   in a development environment. Verify each ID matches the immediately preceding
   response, tool follow-ups contain only new `function_call_output`, and later
   user turns contain only new user input. Check the native discovery output too.
3. Repeat across an actual confirmation/browser pause and a new PHP request,
   including cursor eviction and expired server state.
4. Run repeated matched tasks over a representative permission-filtered catalog
   with native search on/off. Compare correctness, tool selection, iterations,
   HTTP bytes, usage/cache tokens and wall time. Do not assume less upload data
   means fewer billed cumulative input tokens.

Until those gates pass, keep the change experimental and the PR in draft.
