# t164 — Seamless PHP+JS Abilities: Agent Loop Pause/Resume

Parent: #806. Depends on: t163 (foundation must be merged first).

## Session origin

Interactive session on 2026-04-08, split out of #806 so the risky agent-loop
control-flow surgery ships in its own reviewable PR after the foundation
(t163) is verified in a browser.

## What

Teach the agent loop to execute client-side abilities by pausing after the
model emits a tool call for a `sd-ai-agent-js/*` ability, returning the
pending call to the browser, having the client dispatch it via
`executeAbility(name, args)`, and POSTing the result back to a new
`/chat/tool-result` endpoint that resumes the loop.

## Why

Foundation (t163) gets client-side abilities discoverable and executable from
the browser, but the model itself still does not see them. This slice merges
the two registries at request time, so the model's tool list is the union of
PHP abilities + `sd-ai-agent-js/*` descriptors, and tool calls are routed
to the right executor.

## How

### PHP

- **EDIT**: `includes/Core/AgentLoop.php`
  - Constructor: accept `client_abilities` (array of descriptors) in
    `$options`. Validate each against `JsAbilityCatalog::get_descriptors()`;
    drop any name not in the catalog.
  - `resolve_abilities()`: build synthetic `WP_Ability` objects from the
    accepted client descriptors (or feed them directly into a thin adapter
    the resolver can consume) and append to the tier-1 list so they reach
    `using_abilities(...)`.
  - `run_loop()`: when the model's assistant message contains a tool call
    whose name matches a client descriptor, do **not** call
    `execute_abilities()` for those parts. Instead, collect them into a
    `pending_client_tool_calls` list, persist loop state keyed by
    `$this->session_id`, and return
    `{ pending_client_tool_calls, history, iterations_remaining, ... }`.
    Mixed messages (some PHP, some JS calls) execute the PHP ones inline
    and still return the JS ones as pending.
  - New `resume_after_client_tools(array $results, int $remaining)` method
    that reconstructs a tool-response `Message` from the client results,
    appends it to history via `append_tool_response_to_history()`, and calls
    `run_loop($remaining)`. Mirrors `resume_after_confirmation()`.
  - Loop-state persistence: reuse the existing session row (sessions table)
    — store serialized history, tool_call_log, iterations_remaining,
    token_usage in a new `paused_state` JSON column (or
    `session_meta` key-value row, whichever matches the existing schema).
- **EDIT**: `includes/REST/RestController.php`
  - `handle_chat()`: accept `client_abilities` in the request body and
    pass through to `AgentLoop` options.
  - New route: `POST /sd-ai-agent/v1/chat/tool-result` with args
    `{ session_id, tool_results: [{ id, name, result|error }...] }`.
    Handler loads paused state from the session row, instantiates
    `AgentLoop` with the persisted history, and calls
    `resume_after_client_tools()`.
- **NEW**: `tests/SdAiAgent/Core/AgentLoopClientToolsTest.php` —
  focused PHPUnit coverage:
  - Posting a fake `client_abilities` descriptor causes a model tool call
    for that name to return `pending_client_tool_calls` instead of
    executing.
  - Resume path correctly appends results and continues the loop.
  - Mixed PHP+JS tool calls in one assistant message execute the PHP ones
    inline and return only the JS ones as pending.
  - Unknown (non-catalog) descriptor names are rejected.

### JS

- **EDIT**: `src/store/slices/sessionsSlice.js`
  - `sendMessage` thunk: before POSTing to `/chat`, call
    `snapshotDescriptors()` from `src/abilities/registry.js` and include the
    result as `client_abilities` in the request body.
  - When the response contains `pending_client_tool_calls`, iterate them,
    dispatch each via `wp.data.dispatch('core/abilities').executeAbility(name, args)`,
    collect `{ id, name, result|error }` entries, and POST to
    `/chat/tool-result` with the session id. Repeat until the response
    has no more pending calls, then treat it as a normal final reply.
  - Surface client tool execution in the existing tool-call-details UI
    (add a "Ran in browser" badge passed through as an annotation on the
    log entry).
- **EDIT**: `src/components/tool-call-details.js` — accept and render the
  "Ran in browser" annotation.
- **EDIT**: `src/abilities-explorer/abilities-explorer-app.js` — list
  `sd-ai-agent-js/*` abilities alongside PHP ones with a "client"
  badge (fetched from the local `core/abilities` store filtered by
  category).

### Reference patterns

- Existing `AgentLoop::resume_after_confirmation()` for the resume shape.
- Existing `awaiting_confirmation` return branch for the pause shape.
- Existing REST controller route registration in `handle_chat()` for the
  new `/chat/tool-result` endpoint.

## Acceptance criteria

1. On a block-editor screen, asking the floating widget "insert a paragraph
   that says hello" inserts the block without a page reload. Network panel
   shows exactly one `/chat` call and one `/chat/tool-result` call; no
   full-page refresh.
2. On the dashboard, "take me to plugins" navigates via the client-side
   ability; network panel shows `/chat` + `/chat/tool-result` and no
   server-side tool execution for `navigate-to`.
3. On the unified admin page, "list my recent memories" still runs entirely
   server-side; no `/chat/tool-result` call is made.
4. Mixed request ("save the current draft and create a memory about it")
   interleaves a JS save and a PHP memory write across a single pause/resume
   cycle and returns one final summary reply.
5. On a non-editor page, `client_abilities` posted to `/chat` omits the
   `insert-block` descriptor (context-aware filter works).
6. Abilities explorer lists both PHP and JS abilities; JS ones are badged
   "client".
7. `vendor/bin/phpunit` passes, including the new
   `AgentLoopClientToolsTest` coverage.
8. `npm run test:js` passes; new tests cover the `sessionsSlice`
   round-trip shape.

## Verification commands

```bash
composer phpstan
vendor/bin/phpunit --filter AgentLoopClientToolsTest
npm run build && npm run test:js
npm run test:e2e:playwright -- --grep "client abilities"  # new spec
```

Plus the browser smoke tests from the acceptance criteria above.

## Context

- Parent plan: #806
- Dependency: t163 (foundation)
- Key files: `includes/Core/AgentLoop.php` (around the tool-call branch in
  `run_loop()`, ~line 337), `includes/REST/RestController.php` (`handle_chat`),
  `src/store/slices/sessionsSlice.js` (`sendMessage` thunk), `src/abilities/registry.js`
  (`snapshotDescriptors` — added in t163).
- Risks: loop-state persistence shape (new column vs meta row), schema
  collision between server abilities mirrored via `core-abilities` and our
  `sd-ai-agent-js/*` namespace (distinct namespace prevents this), and
  the edit-state being stale by the time the resume POST arrives (mitigated
  by persisting full history on pause).
