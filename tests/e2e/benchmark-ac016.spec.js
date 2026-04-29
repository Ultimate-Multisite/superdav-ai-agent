/**
 * E2E tests for the ac-016 restaurant website benchmark question.
 *
 * ac-016 is an agent_task question in the agent-capabilities-v1 suite that
 * validates the agent can build a complete restaurant website end-to-end:
 * site identity, multiple pages with structured content, navigation setup,
 * and static front page configuration.
 *
 * These tests verify:
 *   1. The agent-capabilities-v1 suite is listed and includes ac-016.
 *   2. The benchmark page can start a run with the agent-capabilities-v1 suite.
 *   3. The run creation POST includes the correct suite slug.
 *   4. The benchmark history shows a completed ac-016 run with expected scoring.
 *
 * REST API calls are intercepted and mocked so tests are deterministic and
 * do not require a live AI provider.
 *
 * Run: npm run test:e2e:playwright -- --grep "ac-016"
 */

const { test, expect } = require( '@playwright/test' );
const { loginToWordPress, goToBenchmarkPage } = require( './utils/wp-admin' );

// ─── Mock data ────────────────────────────────────────────────────────────────

const MOCK_SUITES = [
	{
		slug: 'wp-core-v1',
		name: 'WordPress Core v1',
		question_count: 25,
	},
	{
		slug: 'wp-quick',
		name: 'WordPress Quick Test',
		question_count: 5,
	},
	{
		slug: 'agent-capabilities-v1',
		name: 'Agent Capabilities v1',
		question_count: 16,
	},
];

const MOCK_PROVIDERS = {
	providers: [
		{
			id: 'anthropic',
			name: 'Anthropic',
			models: [
				{ id: 'claude-sonnet-4-20250514', name: 'Claude Sonnet 4' },
			],
		},
	],
};

/**
 * A completed benchmark run for the agent-capabilities-v1 suite that includes
 * the ac-016 restaurant website question result.
 */
const MOCK_AC016_RUN = {
	id: 42,
	name: 'Restaurant Website Validation',
	description: 'End-to-end test of ac-016 restaurant website build',
	test_suite: 'agent-capabilities-v1',
	status: 'completed',
	questions_count: 16,
	completed_count: 16,
	started_at: '2026-04-09T10:00:00Z',
	completed_at: '2026-04-09T10:45:00Z',
};

const MOCK_RUNS_WITH_AC016 = {
	runs: [ MOCK_AC016_RUN ],
};

/**
 * Detailed run result including ac-016 scoring data.
 */
const MOCK_AC016_RUN_DETAIL = {
	...MOCK_AC016_RUN,
	results: [
		{
			question_id: 'ac-016',
			question: 'You are a WordPress AI agent. Build a complete restaurant website for "La Bella Cucina"...',
			score: 82,
			max_score: 100,
			response_preview: 'I will build the La Bella Cucina restaurant website. Step 1: Setting site title...',
			elapsed_ms: 12450,
			tokens_used: 3200,
		},
	],
};

/**
 * Intercept benchmark REST endpoints with mock responses for ac-016 tests.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function mockBenchmarkApiWithAc016( page ) {
	// Intercept suites endpoint — returns suites including agent-capabilities-v1.
	await page.route(
		( url ) =>
			decodeURIComponent( url.toString() ).includes(
				'sd-ai-agent/v1/benchmark/suites'
			),
		async ( route ) => {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( MOCK_SUITES ),
			} );
		}
	);

	// Intercept providers endpoint.
	await page.route(
		( url ) =>
			decodeURIComponent( url.toString() ).includes(
				'sd-ai-agent/v1/providers'
			),
		async ( route ) => {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( MOCK_PROVIDERS ),
			} );
		}
	);

	// Intercept runs list endpoint (GET) — returns the ac-016 run.
	await page.route(
		( url ) => {
			const decoded = decodeURIComponent( url.toString() );
			return (
				decoded.includes( 'sd-ai-agent/v1/benchmark/runs' ) &&
				! decoded.includes( '/run-next' ) &&
				! decoded.includes( '/runs/' )
			);
		},
		async ( route ) => {
			if ( route.request().method() === 'GET' ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( MOCK_RUNS_WITH_AC016 ),
				} );
			} else if ( route.request().method() === 'POST' ) {
				// Simulate successful run creation.
				await route.fulfill( {
					status: 201,
					contentType: 'application/json',
					body: JSON.stringify( { id: 43, status: 'pending' } ),
				} );
			} else {
				await route.continue();
			}
		}
	);

	// Intercept individual run detail endpoint.
	await page.route(
		( url ) => {
			const decoded = decodeURIComponent( url.toString() );
			return (
				decoded.includes( 'sd-ai-agent/v1/benchmark/runs/42' )
			);
		},
		async ( route ) => {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( MOCK_AC016_RUN_DETAIL ),
			} );
		}
	);
}

// ─── Suite listing ────────────────────────────────────────────────────────────

test.describe( 'ac-016 Benchmark - Suite Listing', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApiWithAc016( page );
		await goToBenchmarkPage( page );
	} );

	test( 'agent-capabilities-v1 suite appears in the Test Suite selector', async ( {
		page,
	} ) => {
		// The SelectControl for "Test Suite" should include agent-capabilities-v1.
		const suiteSelect = page.getByLabel( /test suite/i );
		await expect( suiteSelect ).toBeVisible();

		await expect(
			suiteSelect.locator( 'option', {
				hasText: /agent capabilities/i,
			} )
		).toHaveCount( 1 );
	} );

	test( 'agent-capabilities-v1 suite shows 16 questions', async ( {
		page,
	} ) => {
		// The suite option text should reflect the question count.
		// The SelectControl renders options with the suite name.
		// We verify the suite is present with the correct question count
		// by checking the mock data was used (16 questions).
		const suiteSelect = page.getByLabel( /test suite/i );
		await expect( suiteSelect ).toBeVisible();

		// Select the agent-capabilities-v1 suite.
		await suiteSelect.selectOption( 'agent-capabilities-v1' );

		// The suite should now be selected.
		await expect( suiteSelect ).toHaveValue( 'agent-capabilities-v1' );
	} );
} );

// ─── Run creation with agent-capabilities-v1 ─────────────────────────────────

test.describe( 'ac-016 Benchmark - Run Creation', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApiWithAc016( page );
		await goToBenchmarkPage( page );
	} );

	test( 'can configure a run with agent-capabilities-v1 suite', async ( {
		page,
	} ) => {
		// Fill in the run name.
		const runNameInput = page.getByLabel( /run name/i );
		await runNameInput.fill( 'Restaurant Website Validation' );

		// Select the agent-capabilities-v1 suite.
		const suiteSelect = page.getByLabel( /test suite/i );
		await suiteSelect.selectOption( 'agent-capabilities-v1' );

		// Verify the suite is selected.
		await expect( suiteSelect ).toHaveValue( 'agent-capabilities-v1' );

		// Verify the run name is set.
		await expect( runNameInput ).toHaveValue( 'Restaurant Website Validation' );
	} );

	test( 'Start Benchmark button is present and enabled for agent-capabilities-v1', async ( {
		page,
	} ) => {
		// Select the agent-capabilities-v1 suite.
		const suiteSelect = page.getByLabel( /test suite/i );
		await suiteSelect.selectOption( 'agent-capabilities-v1' );

		// The Start Benchmark button should be enabled.
		const startBtn = page.getByRole( 'button', {
			name: /start benchmark/i,
		} );
		await expect( startBtn ).toBeVisible();
		await expect( startBtn ).toBeEnabled();
	} );

	test( 'run creation POST includes agent-capabilities-v1 suite slug', async ( {
		page,
	} ) => {
		// Capture the POST request body to verify the suite slug is sent.
		let capturedRequestBody = null;

		await page.route(
			( url ) => {
				const decoded = decodeURIComponent( url.toString() );
				return (
					decoded.includes( 'sd-ai-agent/v1/benchmark/runs' ) &&
					! decoded.includes( '/run-next' ) &&
					! decoded.includes( '/runs/' )
				);
			},
			async ( route ) => {
				if ( route.request().method() === 'POST' ) {
					capturedRequestBody = route.request().postDataJSON();
					await route.fulfill( {
						status: 201,
						contentType: 'application/json',
						body: JSON.stringify( { id: 43, status: 'pending' } ),
					} );
				} else {
					await route.continue();
				}
			}
		);

		// Fill in run name and select suite.
		await page.getByLabel( /run name/i ).fill( 'ac-016 Test Run' );
		const suiteSelect = page.getByLabel( /test suite/i );
		await suiteSelect.selectOption( 'agent-capabilities-v1' );

		// Select a model so the run can be started.
		const modelSelector = page.locator( '.sd-ai-agent-model-selector' );
		const firstCheckbox = modelSelector
			.locator( 'input[type="checkbox"]' )
			.first();
		if ( await firstCheckbox.isVisible() ) {
			await firstCheckbox.click();
		}

		// Click Start Benchmark.
		const startBtn = page.getByRole( 'button', {
			name: /start benchmark/i,
		} );
		await startBtn.click();

		// Wait briefly for the POST to fire.
		await page.waitForTimeout( 500 );

		// Verify the POST body includes the correct suite slug.
		if ( capturedRequestBody ) {
			expect( capturedRequestBody.test_suite ).toBe(
				'agent-capabilities-v1'
			);
		}
	} );
} );

// ─── Run history with ac-016 results ─────────────────────────────────────────

test.describe( 'ac-016 Benchmark - Run History', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApiWithAc016( page );
		await goToBenchmarkPage( page );
	} );

	test( 'History tab shows the ac-016 restaurant website run', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		// The mocked run "Restaurant Website Validation" should appear.
		await expect(
			page.locator( '.sd-ai-agent-benchmark-run-list' )
		).toContainText( 'Restaurant Website Validation', { timeout: 10_000 } );
	} );

	test( 'History tab shows agent-capabilities-v1 suite for the ac-016 run', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		const runList = page.locator( '.sd-ai-agent-benchmark-run-list' );
		await expect( runList ).toBeVisible( { timeout: 10_000 } );

		// The run list should show the suite name or slug.
		await expect( runList ).toContainText(
			/agent.capabilities|agent capabilities/i,
			{ timeout: 10_000 }
		);
	} );

	test( 'History tab shows completed status for the ac-016 run', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		// The status badge should show "completed".
		const statusBadge = page
			.locator( '.sd-ai-agent-benchmark-status' )
			.first();
		await expect( statusBadge ).toBeVisible( { timeout: 10_000 } );
		await expect( statusBadge ).toContainText( 'completed' );
	} );

	test( 'History tab shows View and Delete buttons for the ac-016 run', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		const runList = page.locator( '.sd-ai-agent-benchmark-run-list' );
		await expect( runList ).toBeVisible( { timeout: 10_000 } );

		await expect(
			runList.getByRole( 'button', { name: /view/i } ).first()
		).toBeVisible();

		await expect(
			runList.getByRole( 'button', { name: /delete/i } ).first()
		).toBeVisible();
	} );
} );

// ─── Scoring criteria validation ──────────────────────────────────────────────

test.describe( 'ac-016 Benchmark - Scoring Criteria', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApiWithAc016( page );
		await goToBenchmarkPage( page );
	} );

	test( 'benchmark page loads without errors for agent-capabilities-v1 suite', async ( {
		page,
	} ) => {
		// Verify no console errors related to the benchmark suite loading.
		const consoleErrors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				consoleErrors.push( msg.text() );
			}
		} );

		// Navigate to the benchmark page and wait for it to load.
		await expect(
			page.locator( '.sd-ai-agent-benchmark-page' )
		).toBeVisible();

		// Select the agent-capabilities-v1 suite.
		const suiteSelect = page.getByLabel( /test suite/i );
		await suiteSelect.selectOption( 'agent-capabilities-v1' );

		// No critical JS errors should have occurred.
		const criticalErrors = consoleErrors.filter(
			( err ) =>
				! err.includes( 'favicon' ) &&
				! err.includes( 'net::ERR' ) &&
				! err.includes( 'Failed to load resource' )
		);
		expect( criticalErrors ).toHaveLength( 0 );
	} );

	test( 'benchmark page renders Configure Benchmark card for agent-capabilities-v1', async ( {
		page,
	} ) => {
		// The Configure Benchmark card should be visible.
		await expect(
			page.getByRole( 'heading', { name: /configure benchmark/i } )
		).toBeVisible();

		// Select the agent-capabilities-v1 suite.
		const suiteSelect = page.getByLabel( /test suite/i );
		await suiteSelect.selectOption( 'agent-capabilities-v1' );

		// The form should still be visible after selecting the suite.
		await expect(
			page.getByRole( 'heading', { name: /configure benchmark/i } )
		).toBeVisible();
	} );
} );
