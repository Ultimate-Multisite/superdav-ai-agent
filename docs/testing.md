# Testing

For full browser-accessible local WordPress testing without Docker, including
multi-worktree site provisioning, see
[`local-wordpress-worktree-testing.md`](local-wordpress-worktree-testing.md).

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
`sd_ai_agent_process_background_job` cron events and no chat-completion traces
indicate the queue has not reached the provider yet. Check loopback connectivity
before diagnosing inference latency.

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
