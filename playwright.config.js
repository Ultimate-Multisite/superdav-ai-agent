/**
 * Playwright E2E test configuration for Gratis AI Agent.
 *
 * Tests run against a wp-env WordPress environment.
 * Start the environment with `npm run wp-env:start` before running tests.
 *
 * @see https://playwright.dev/docs/test-configuration
 */

const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	testMatch: '**/*.spec.js',

	/* Maximum time one test can run for.
	 * 60 s gives goToAgentPage (30 s wait for AdminPageApp) enough headroom
	 * on CI runners under load with 3 parallel workers. The 90-min job timeout
	 * is still respected — 202 tests × 60 s / 3 workers ≈ 67 min worst case,
	 * but most tests complete in < 5 s so the real runtime stays ~35 min. */
	timeout: 60_000,

	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,

	/* Retry on CI only — 1 retry keeps total time bounded. */
	retries: process.env.CI ? 1 : 0,

	/* 3 workers on CI: reduces runtime to ~35 min, well within the 90-min
	 * job timeout. 2 workers still hit ~59 min (PR #671). 1 worker caused
	 * the 202-test suite to exceed the 60-minute job timeout consistently. */
	workers: process.env.CI ? 3 : undefined,

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
