/**
 * Playwright E2E test configuration for Gratis AI Agent.
 *
 * Tests run against a wp-env WordPress environment.
 * Start the environment with `npm run wp-env:start` before running tests.
 *
 * @see https://playwright.dev/docs/test-configuration
 */

const { defineConfig, devices } = require( '@playwright/test' );

const ciWorkers = Number.parseInt( process.env.PLAYWRIGHT_WORKERS || '2', 10 );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	testMatch: '**/*.spec.js',

	/* Maximum time one test can run for.
	 * 60 s gives goToAgentPage (30 s wait for AdminPageApp) enough headroom
	 * on CI runners under load with 2 parallel workers. The suite is split
	 * across 3 shards in e2e.yml (--shard N/3), so each shard runs ~4-5 of
	 * the 13 spec files within the 45-min per-shard timeout. */
	timeout: 60_000,

	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,

	/* Retry on CI only — 1 retry keeps total time bounded. */
	retries: process.env.CI ? 1 : 0,

	/* Worker count on CI is tunable via PLAYWRIGHT_WORKERS env var.
	 * Both WP 6.9 and trunk use 2 workers (set in e2e.yml) to avoid
	 * overloading the wp-env environment — more workers cause resource
	 * contention that manifests as login and SPA-render timeouts on CI
	 * runners. The suite is sharded (3 shards per WP version) so 2 workers
	 * per shard is sufficient to complete within the 45-min shard timeout. */
	workers: process.env.CI
		? ( Number.isFinite( ciWorkers ) && ciWorkers > 0 ? ciWorkers : 2 )
		: undefined,

	/* Reporter to use. */
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { open: 'never' } ] ]
		: [ [ 'list' ], [ 'html', { open: 'on-failure' } ] ],

	use: {
		/* wp-env default URL. */
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',

		/* Collect trace when retrying the failed test. */
		trace: 'on-first-retry',

		/* Take screenshot on failure. */
		screenshot: 'only-on-failure',

		/* Video on first retry. */
		video: 'on-first-retry',
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
