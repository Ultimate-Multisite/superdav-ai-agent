# Testing

For full browser-accessible local WordPress testing without Docker, including
multi-worktree site provisioning, see
[`local-wordpress-worktree-testing.md`](local-wordpress-worktree-testing.md).

## Context efficiency safeguards

Run the existing focused suites before comparing provider latency:

```bash
pnpm run test:php -- --filter='ToolResultTruncatorTest|ConversationSerializerTest|ConversationTrimmerTest|CompletedTurnCompactorTest|RunLocalReadonlyToolCacheTest|SkillContextDeliveryTest|SkillAbilitiesTest|SkillAutoInjectorTest'
```

Ordinary tool results, including plain and JSON-encoded strings, have a 4 KiB
encoded-history cap. Truncation must preserve UTF-8. Completed-turn argument
receipts have a 2 KiB cap; active-turn arguments and durable/UI history are not
rewritten by that provider-view optimization. Repeated cached file reads count
toward spin detection, while known registry/skill discovery does not invalidate
safe file-read reuse. Writes and unknown tools still invalidate it.

Skill guidance uses a separate explicit contract rather than an opaque 500-byte
excerpt: each versioned page is at most 6 KiB encoded, and a dispatcher envelope
with one skill page is at most 16 KiB (including JSON-string transport escaping).
The first page contains guidance plus a
section index. Use `skill-load` with `slug` and an exact `section` when a detail is
missing; continue the same section with `offset=next_offset` and
`content_version`. A changed version or invalid offset is rejected. Do not fetch
every section automatically: load the overview once and retrieve only necessary
details. Explicit loads, automatic attachments and full prompt injection share
run-local delivery state; repeated pages return `already_loaded` and continuation
metadata instead of duplicating instructions. Disabled-skill/capability gates
still apply before retrieval. If recovery compaction removed previously delivered
guidance, request the needed section with `reload=true`; this is an explicit
recovery path, not a reason to reload every page.

Paid comparisons require explicit operator authorization, configured credentials
on a disposable installed-package site, finite request/time budgets, correctness
checks and cleanup. Keep models, permissions, prompts and fixtures matched across
before/after runs; record every provider request's elapsed time, body size and
reported input/output/cached-input tokens, without raw requests or credentials.
Warm-cache differences and noisy small samples are not proof of a speedup. A
lower token count obtained by losing facts, invalidating blocks or truncating
necessary guidance is not an acceptable optimization.

## Opt-in packaged plugin-creation smoke tests

`pnpm run test:plugin-creation -- --help` describes the live release check. It
uses the existing WP-CLI and configured provider credentials, not another test
framework. It is **not** included in `verify` or automatic CI: it spends provider
credit and executes generated PHP, so both `--live` and `--disposable-site` are
mandatory. Never use a shared browser-demo, customer, or production database.

Prepare a disposable, single-site WordPress install with environment type
`development` or `local`. Build the packages with `bash bin/build.sh --target=both`
in a separate packaging worktree: that script temporarily prunes dev dependencies.
Install and activate the actual Core and Advanced ZIPs under their canonical
directories. Configure the chosen provider using the site's normal credential
settings; do not pass keys as flags or put them in report files.

Do not install over a source symlink or Docker bind mount. The target's canonical
`superdav-ai-agent` and `superdav-ai-agent-advanced` directories must be independent
installed packages, not this checkout. The runner checks package identity, equal
versions, absence of the source-bundled companion, administrator permissions, and
the exact expected site URL before creating anything. After `wp-env start`, also
check that it has not reactivated a source copy alongside the packaged Core.

For an already prepared wp-env **test** site:

```bash
pnpm run test:plugin-creation -- --live --disposable-site \
  --site-url=http://localhost:8893 --wp-env=tests-cli \
  --provider=sd-ai-agent-cloud --model=superdav-chat-standard \
  --scenarios=single,multi,settings,rest --max-seconds=1200 \
  --report=/private/existing-directory/new-release-smoke.json
```

For an independently provisioned host-native disposable site, replace
`--wp-env=tests-cli` with `--wp-path=/absolute/path/to/disposable/wordpress` and
use that site's exact URL. The selected WP-CLI user defaults to `admin`; override
with `--user=LOGIN` when needed.

Scenarios:

- `single`, `multi` (defaults): exactly one or two generated PHP files, working
  shortcode, actual installation, syntax/include sandbox and activation.
- `settings`: Settings API registration, sanitization and persistence across
  fresh requests and an update.
- `rest`: authenticated REST behavior and rejection of anonymous requests.
- `agent` (optional): natural-language orchestration through the existing
  production CLI, including discovery/generation/sandbox/activation. This CLI
  auto-approves write tools; use only the disposable site and a larger explicit
  request budget such as `--max-requests=12`.

Each operation boots a fresh WordPress process. Every scenario verifies a valid
update's changed behavior, rejects malformed PHP while checking all original file
hashes, verifies behavior again after rejection, and denies unauthenticated
generation. A `finally` cleanup runs in a fresh process with only the generated QA
plugin skipped, so activation errors do not prevent normal recovery. Cleanup
checks the generated record, directory, activation status and known QA settings,
and removes matching staging/backups. A failed cleanup fails the overall result.
Only uniquely named `sd-release-smoke-*` QA artifacts are targeted; collisions
fail before ownership or cleanup is assumed.

Defaults are 600 seconds total, 180 per operation, and four external HTTP POSTs
per operation (`--max-seconds`, `--phase-seconds`, `--max-requests`). These are
request/time bounds, **not a currency or token spending cap**. Provider retry
attempts consume the request budget. wp-env also runs `timeout` inside the
container; allow up to 15 seconds transport grace and 60 seconds extra for cleanup.
Interrupting or killing the runner cannot guarantee cleanup: inspect a failed receipt's unique
slug before disposing of the site. Never automatically retry a paid failure.

The final stdout JSON records each operation and cleanup, never raw provider
responses, source code, credentials, or stack traces. `requestAttempts` includes
rejected attempts; `blockedRequests` shows how many were stopped before HTTP
transport, so an exhausted budget is not mistaken for extra paid calls.
`--report` reserves a new
mode-0600 file and refuses overwrites. Exit 0 means every selected scenario and
cleanup passed; exit 1 means a check failed; exit 2 means setup/arguments failed.
This does not prove arbitrary generated code safe or cover every provider,
dependency, multisite topology, or browser approval flow.

## Plugin Check findings are a release gate

Do not trust the scanner's exit code alone. Capture its `--format=json` output,
then run `pnpm run check:plugin-report -- /path/to/report`. The parser supports
per-file JSON arrays with wrapper headers; ERROR findings or malformed/missing
evidence fail even when the scanner returned zero. Warnings remain visible and
can be gated with `--fail-on-warnings`. An explicit empty JSON array or the
scanner's explicit clean-result message is required to prove a clean result.

`bin/wporg-review.sh` now applies this gate as well as preserving scanner process
failures. That existing review command still requests AI review; the report parser
itself makes no network or paid requests. Document skipped/incomplete tests and
review scanner findings individually; never relabel an entire report as passing
because one detector has a demonstrated limitation.

## wp-env loopbacks

The browser sites use published ports 8890 and 8893, but Apache listens on port
80 inside Docker. `tests/mu-plugins/wp-env-loopback.php` routes WordPress HTTP
requests to its own local site through the Docker service configured by
`SD_AI_AGENT_WP_ENV_LOOPBACK_HOST` in `.wp-env.json`: `wordpress` for development
and `tests-wordpress` for tests. It preserves the public Host header and leaves
external API requests unchanged. The helper requires the explicit wp-env constant
and the development environment; it is not part of the released plugin.

Run `pnpm exec wp-env start` after changing the configuration. Check the cron
loopback from the separate CLI container with:

```bash
pnpm exec wp-env run tests-cli wp eval '$r = wp_remote_get( site_url( "/wp-cron.php" ), array( "timeout" => 5 ) ); echo is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r );'
```

Expect HTTP 200. A connection failure to `localhost:8893` means the helper or
constant is missing. Jobs stuck in `processing` with overdue
`sd_ai_agent_process_background_job` cron events and no provider traces indicate
the queue has not reached inference yet. Check loopback connectivity before
diagnosing inference latency.

## Plugin-builder private storage

Plugin-builder backups and staged plugin files are stored in a site-specific,
restricted sibling of the WordPress installation, not below WordPress uploads
or another web-served WordPress directory. Hosts can relocate it with the
`sd_ai_agent_plugin_builder_private_root` filter. The selected absolute
directory must be writable, accessible only to its owner, and neither inside
nor an ancestor of WordPress core, content, plugins, or uploads paths; unsafe
locations fail closed.

On `init`, historical `sd-ai-backups` and `sd-ai-staging` directories below
uploads (plus the older content-directory backup location) are copied into
private storage. Relative paths, entry types, byte sizes, and SHA-256 hashes are
compared before a public source is removed. Symlinks, unexpected entries, or a
failed comparison leave the public source intact so recovery data is never
discarded without a verified private copy.

For an installed-package disclosure check, create only a synthetic plugin and
marker, run `PluginUpdater::backup()` and `PluginUpdater::stage()`, and confirm
that neither returned filesystem path maps to an uploads URL. An anonymous HTTP
request for the former `/sd-ai-backups/` or `/sd-ai-staging/` marker location
must return 401, 403, or 404 and must not return the marker. Remove all synthetic
fixtures after the check.

## Native tool-search validation

Use the installed site's existing connector credentials and managed model. Check
actual outgoing provider routes rather than the browser's WordPress REST route.
Discovery, a follow-up message, an approved confirmation, and browser navigation
should all remain on `/v1/responses` when native search is eligible. Repeat the
browser handoff in fresh sessions; compare the replayed input prefix with the
prior native input plus output, including JSON tool-result strings and empty
schema objects. Record hashes and request metadata, never credentials or full
conversation payloads.

Native tool search sends deferred schemas over HTTP without loading them all into
model context. Keep the configured HTTP body cap, but do not apply the managed
Chat Completions byte-as-token context clamp to native deferred catalogs. The
service must exclude deferred definitions from context admission while retaining
loaded `tool_search_output` and conservative quota reservations. Deploy that
service support before releasing the client change that admits the full catalog.

For benchmarks, record upstream input, output, and cached input tokens for every
request in a complete task, including retries and discovery. Compare the same
model, permissions, fixtures, and catalog; distinguish prompt-manifest removal
from schema-deferral savings. A first-request control is not a complete task.
Run paired repeated samples and report correctness, total input context, uncached
input, and elapsed time separately. Deferred catalog bytes and a cold/warm cache
difference alone do not establish token or performance gains. Report rejected
requests and bounded-catalog conditions explicitly; full-catalog deployment is
not verified by a smaller-catalog test.

## PHP unit tests without wp-env

`pnpm run test:php` runs PHPUnit against a shared WordPress core and
`wordpress-tests-lib` checkout instead of starting `wp-env`.

Default shared paths:

- WordPress core: `~/.cache/wordpress-phpunit/wordpress-trunk`
- WordPress tests: `~/.cache/wordpress-phpunit/wordpress-tests-lib-trunk`

Provision the shared files and a dedicated test database once. Never point this
at the browser-demo or any other live WordPress database:

```bash
WP_TESTS_DB_NAME=sd_ai_agent_tests pnpm run test:php:setup
```

Then run the suite from any checkout:

```bash
pnpm run test:php
```

### Shared-cache safety

Setup serializes provisioning for each cache root and WordPress version. Each
process extracts into its own staging directory, validates both
`wp-settings.php` and `includes/functions.php`, then atomically publishes the
complete core and test-library directories. A second worktree using the same
cache waits for the active setup instead of reusing its staging files.

If setup is interrupted, rerun the same setup command. An existing cache that
lacks either sentinel is treated as incomplete and rebuilt. Database creation
runs only after the file cache has been validated, so a database failure does
not invalidate a completed shared cache.

The test database name must visibly contain `test`, `tests`, `phpunit`, or
`ci`. Setup marks its generated `wp-tests-config.php` as isolated; PHPUnit
refuses an unmarked cache before it boots WordPress. The shared
`../wordpress/wp-config.php` database is checked automatically.
If its database name uses a computed declaration, set `WP_LIVE_DB_NAME`
explicitly. For any other live install, set `WP_LIVE_DB_NAME` alongside setup
to reject a collision:

```bash
WP_LIVE_DB_NAME=local_wordpress \
WP_TESTS_DB_NAME=sd_ai_agent_tests \
pnpm run test:php:setup
```

Only unconditional literal `define()` declarations are supported for database
settings and the isolation marker; comments, duplicate declarations, and
computed values are rejected. Dynamic `define()` names are also rejected.
Alternate `WP_TESTS_CONFIG_FILE_PATH` locations
are rejected; configure `WP_TESTS_DIR` instead.

Use a fresh `WP_PHPUNIT_CACHE_DIR` instead of repurposing an old or unknown
cache. A different table prefix does not make a live database safe for tests.

The database-guard regressions can also run without WordPress or MySQL:

```bash
vendor/bin/phpunit --no-configuration tests/SdAiAgent/Core/PhpunitDatabaseSafetyTest.php
```

Useful overrides:

```bash
WP_VERSION=7.0 pnpm run test:php:setup
WP_VERSION=7.0 pnpm run test:php

WP_TESTS_DB_NAME=my_plugin_tests \
WP_TESTS_DB_USER=root \
WP_TESTS_DB_PASS='your-password' \
WP_TESTS_DB_HOST=127.0.0.1 \
pnpm run test:php:setup

WP_PHPUNIT_CACHE_DIR=~/.cache/wp-tests pnpm run test:php
WP_TESTS_DIR=/path/to/wordpress-tests-lib WP_CORE_DIR=/path/to/wordpress pnpm run test:php
PHPUNIT_BIN=phpunit pnpm run test:php
```

`wp-env` is still available for browser and integration testing, but PHP unit
tests should not depend on the `tests-wordpress` container being started.
