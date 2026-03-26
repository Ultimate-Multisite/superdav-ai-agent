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

	/* Maximum time one test can run for. */
	timeout: 30_000,

	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,

	/* Retry on CI only — 1 retry keeps total time bounded. */
	retries: process.env.CI ? 1 : 0,

	/* 2 workers on CI: halves runtime (~57 min → ~30 min) while staying
	 * conservative about wp-env resource contention. 1 worker caused the
	 * 202-test suite to exceed the 60-minute job timeout consistently. */
	workers: process.env.CI ? 2 : undefined,

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
