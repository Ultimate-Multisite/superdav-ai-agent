# t089: MCP server — expose abilities as MCP tools for external AI clients

**Task ID:** t089  
**Status:** in-progress  
**Estimate:** ~6h  
**Logged:** 2026-03-15  
**Issue:** https://github.com/Ultimate-Multisite/ai-agent/issues/399  

## Session Origin

Requested via `/full-loop` in OpenCode session 2026-03-15. Implementation was
previously completed in PR #263 (merged to main). This PR adds the missing
PHPUnit integration tests for `McpController`.

## What

Add `tests/SdAiAgent/REST/McpControllerTest.php` — a PHPUnit integration
test suite for the MCP REST endpoint (`POST /wp-json/sd-ai-agent/v1/mcp`).

The implementation (`includes/REST/McpController.php`) was merged in PR #263.
Tests were not included in that PR.

## Why

- The MCP endpoint is the primary integration surface for external AI clients
  (Claude Desktop, Cursor, etc.). It must be covered by automated tests.
- Without tests, regressions in `list_tools` / `call_tool` dispatch, name
  mapping, or error handling would go undetected.
- Consistent with the project standard: every REST endpoint has a corresponding
  integration test (see `RestControllerTest.php`).

## How

1. Create `tests/SdAiAgent/REST/McpControllerTest.php` using the
   `WP_UnitTestCase` + `WP_REST_Server` pattern established in
   `RestControllerTest.php`.
2. Register a mock ability via `wp_abilities_api_init` hook in `set_up()` so
   tests are self-contained and don't depend on real abilities.
3. Cover: unauthenticated access, `list_tools`, `call_tool` success,
   `call_tool` with unknown tool, `call_tool` with missing name, unknown method,
   name mapping helpers.
4. Run `vendor/bin/phpunit tests/SdAiAgent/REST/McpControllerTest.php` to
   verify all tests pass.

## Acceptance Criteria

- [x] `McpControllerTest.php` exists at `tests/SdAiAgent/REST/`
- [x] All 20 tests pass under `wp-env` PHPUnit (verified 2026-03-15)
- [x] Covers: unauthenticated → 401, subscriber → 403, `list_tools` returns
      tools array with protocol_version, `call_tool` executes ability and
      returns MCP result format, `call_tool` with unknown tool → 404,
      `call_tool` missing name → 400, `call_tool` empty name → 400,
      unknown method → 400, missing method → 400,
      `ability_name_to_mcp_name` / `mcp_name_to_ability_name` round-trip

## Context

- Implementation: `includes/REST/McpController.php`
- Route registration: `includes/REST/RestController.php` line ~52
- Compat layer (mock abilities): `compat/load.php`
- Test pattern reference: `tests/SdAiAgent/REST/RestControllerTest.php`
- MCP protocol version: `2024-11-05`
- Auth: `manage_options` capability required
