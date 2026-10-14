# Provider context-size audit

This tool measures what the agent actually sends to the provider on each request of a multi-turn chat. It is repository-only: `/tools` is excluded by `.distignore`, `composer.json` `archive.exclude`, and the zip check in `bin/build.sh`.

It runs the real `AgentLoop`, real abilities, and real session persistence (`append_to_session` → reload → `ConversationSerializer::deserialize`). By default, the provider is faked at `pre_http_request`, so no credits are used and runs are deterministic.

## Run

From the repository root, with a shared WordPress install that has the plugin active:

```bash
SD_AUDIT_PLUGIN="$PWD" \
SD_AUDIT_MODE=native \
SD_AUDIT_SCENARIO="$PWD/tools/audit/context-size/scenarios/onboarding.json" \
SD_AUDIT_OUT="$HOME/.aidevops/.agent-workspace/tmp/ctx-audit/native" \
wp --path="${WP_PATH:-../wordpress}" --skip-plugins=superdav-ai-agent \
  --require="$PWD/tools/audit/context-size/load-plugin.php" \
  eval-file tools/audit/context-size/harness.php
```

| Variable | Meaning |
|---|---|
| `SD_AUDIT_PLUGIN` | Checkout to load instead of the installed plugin (any worktree). |
| `SD_AUDIT_MODE` | `native` (Responses tool search) or `chat` (Chat Completions; forces `sd_ai_agent_openai_tool_search_enabled` off). |
| `SD_AUDIT_SCENARIO` | JSON list of turns: `{"user": "...", "steps": [{"call": "ability/name", "args": {}}, {"text": "final reply"}]}`. |
| `SD_AUDIT_OUT` | Output directory for `req-NN-tT.json` request bodies and `summary.json`. Defaults to the system temp directory. Bodies contain site data; never commit them. |
| `SD_AUDIT_DROP_CURSOR_TURN` | Delete the native replay cursor before this turn, to simulate expiry or eviction. |
| `SD_AUDIT_LIVE` | `1` sends real requests (uses credits) and only records them. The scenario `steps` are ignored, and replies are printed to stderr. |

Each `summary.json` row reports `bytes` (full HTTP body) and `model_visible` (body minus deferred tool schemas, which the gateway does not load into context). It also reports the instructions size and a per-item-type breakdown of `input` (Responses) or `messages` (Chat Completions).

Quick table:

```bash
jq -r '.[] | [.turn, .endpoint, .bytes, .model_visible, (.instructions // .messages.system), ((.input // .messages) | tostring), (.rejected // "")] | @tsv' "$SD_AUDIT_OUT/summary.json"
```

## Notes

- Scenarios execute abilities for real, so use read-only abilities only. The fake model calls `sd-ai-agent/ability-call` when a schema is not directly available on the Chat Completions path.
- Fake native responses include an encrypted-reasoning-sized blob and a `tool_search_call`/`tool_search_output` pair that loads the called function's namespace, matching the managed gateway's output shape.
- The managed gateway counts roughly one token per byte of model-visible input against `context_window - max_output_tokens`.
