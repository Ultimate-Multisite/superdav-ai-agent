/**
 * E2E tests for the Gratis AI Agent Model Benchmark admin page.
 *
 * The benchmark page is a standalone React SPA registered under Tools:
 *   tools.php?page=gratis-ai-agent-benchmark
 *
 * It renders BenchmarkPageApp inside #gratis-ai-agent-benchmark-root and
 * provides:
 *   - A TabPanel with "New Benchmark" and "History" tabs.
 *   - A "Configure Benchmark" card with Run Name, Description, Test Suite,
 *     and Model Selector form fields.
 *   - A "Start Benchmark" button that is disabled while loading.
 *   - A run list (History tab) showing previous runs or an empty state.
 *
 * REST API calls are intercepted and mocked so tests are deterministic and
 * do not require a live AI provider.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const { loginToWordPress, goToBenchmarkPage } = require( './utils/wp-admin' );

// ─── Shared mock data ─────────────────────────────────────────────────────────

const MOCK_SUITES = [
	{
		slug: 'wp-core-v1',
		name: 'WordPress Core Knowledge',
		question_count: 20,
	},
	{
		slug: 'wp-coding-v1',
		name: 'WordPress Coding Standards',
		question_count: 15,
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

const MOCK_RUNS = {
	runs: [
		{
			id: 1,
			name: 'Test Run Alpha',
			description: 'First test run',
			test_suite: 'wp-core-v1',
			status: 'completed',
			questions_count: 20,
			completed_count: 20,
			started_at: '2026-01-01T10:00:00Z',
			completed_at: '2026-01-01T10:05:00Z',
		},
	],
};

/**
 * Intercept the three benchmark REST endpoints with mock responses.
 * Call this inside beforeEach (after loginToWordPress, before goToBenchmarkPage)
 * so the intercepts are active when the page loads and fires its useEffect fetches.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function mockBenchmarkApi( page ) {
	// Intercept suites endpoint.
	await page.route(
		( url ) =>
			decodeURIComponent( url.toString() ).includes(
				'gratis-ai-agent/v1/benchmark/suites'
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
				'gratis-ai-agent/v1/providers'
			),
		async ( route ) => {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( MOCK_PROVIDERS ),
			} );
		}
	);

	// Intercept runs list endpoint (GET only — not POST/DELETE).
	await page.route(
		( url ) => {
			const decoded = decodeURIComponent( url.toString() );
			return (
				decoded.includes( 'gratis-ai-agent/v1/benchmark/runs' ) &&
				! decoded.includes( '/run-next' )
			);
		},
		async ( route ) => {
			if ( route.request().method() === 'GET' ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( MOCK_RUNS ),
				} );
			} else {
				// Let POST/DELETE pass through (not exercised in these tests).
				await route.continue();
			}
		}
	);
}

// ─── Page render ──────────────────────────────────────────────────────────────

test.describe( 'Benchmark Page - Page Render', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApi( page );
		await goToBenchmarkPage( page );
		// goToBenchmarkPage waits for the React root non-fatally (.catch(()=>{})). Add
		// an explicit wait for the PHP-rendered .gratis-ai-agent-benchmark-wrap so the
		// test always sees the page before asserting on content inside it.
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-wrap' )
		).toBeVisible( { timeout: 30_000 } );
	} );

	test( 'benchmark page renders the wrap and heading', async ( { page } ) => {
		// ModelBenchmarkPage::render() outputs .gratis-ai-agent-benchmark-wrap
		// with an h1 "Model Benchmark".
		const wrap = page.locator( '.gratis-ai-agent-benchmark-wrap' );
		await expect( wrap ).toBeVisible();

		await expect(
			wrap.getByRole( 'heading', { name: /model benchmark/i, level: 1 } )
		).toBeVisible();
	} );

	test( 'benchmark React app mounts inside the root container', async ( {
		page,
	} ) => {
		// BenchmarkPageApp mounts into #gratis-ai-agent-benchmark-root and
		// renders .gratis-ai-agent-benchmark-page as its outer wrapper.
		await expect(
			page.locator( '#gratis-ai-agent-benchmark-root' )
		).toBeVisible();

		await expect(
			page.locator( '.gratis-ai-agent-benchmark-page' )
		).toBeVisible();
	} );

	test( 'tab panel renders New Benchmark and History tabs', async ( {
		page,
	} ) => {
		// BenchmarkPageApp renders a WordPress TabPanel with two tabs.
		const tabPanel = page.locator( '.gratis-ai-agent-benchmark-tabs' );
		await expect( tabPanel ).toBeVisible();

		// Tabs are rendered as role="tab" buttons.
		await expect(
			page.getByRole( 'tab', { name: /new benchmark/i } )
		).toBeVisible();

		await expect(
			page.getByRole( 'tab', { name: /history/i } )
		).toBeVisible();
	} );

	test( 'Configure Benchmark card is visible on the New Benchmark tab', async ( {
		page,
	} ) => {
		// NewRunTab renders a Card with heading "Configure Benchmark".
		await expect(
			page.getByRole( 'heading', { name: /configure benchmark/i } )
		).toBeVisible();
	} );
} );

// ─── Form inputs ──────────────────────────────────────────────────────────────

test.describe( 'Benchmark Page - Form Inputs', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApi( page );
		await goToBenchmarkPage( page );
		// Wait for the benchmark page root to be visible before interacting with form elements.
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-wrap' )
		).toBeVisible( { timeout: 30_000 } );
	} );

	test( 'Run Name field is present and accepts text input', async ( {
		page,
	} ) => {
		// TextControl for "Run Name" renders a labelled <input type="text">.
		const runNameInput = page.getByLabel( /run name/i );
		await expect( runNameInput ).toBeVisible();

		await runNameInput.fill( 'My Test Run' );
		await expect( runNameInput ).toHaveValue( 'My Test Run' );
	} );

	test( 'Description field is present and accepts text input', async ( {
		page,
	} ) => {
		// TextareaControl for "Description" renders a labelled <textarea>.
		const descInput = page.getByLabel( /description/i );
		await expect( descInput ).toBeVisible();

		await descInput.fill( 'Comparing Claude vs GPT-4' );
		await expect( descInput ).toHaveValue( 'Comparing Claude vs GPT-4' );
	} );

	test( 'Test Suite selector is present and shows mocked suites', async ( {
		page,
	} ) => {
		// SelectControl for "Test Suite" renders a labelled <select>.
		// Wait for the mocked suites to populate the options.
		const suiteSelect = page.getByLabel( /test suite/i );
		await expect( suiteSelect ).toBeVisible();

		// The mocked suites should appear as options.
		await expect(
			suiteSelect.locator( 'option', {
				hasText: /wordpress core knowledge/i,
			} )
		).toHaveCount( 1 );
	} );

	test( 'Model Selector renders provider groups with checkboxes', async ( {
		page,
	} ) => {
		// ModelSelector renders .gratis-ai-agent-model-selector with provider groups.
		// The built-in "WordPress AI Client" group is always present.
		const modelSelector = page.locator( '.gratis-ai-agent-model-selector' );
		await expect( modelSelector ).toBeVisible();

		// At least one provider group heading should be visible.
		const providerHeadings = modelSelector.locator( 'h4' );
		await expect( providerHeadings.first() ).toBeVisible();

		// At least one checkbox should be present.
		const checkboxes = modelSelector.locator( 'input[type="checkbox"]' );
		await expect( checkboxes.first() ).toBeVisible();
	} );

	test( 'Start Benchmark button is present', async ( { page } ) => {
		// NewRunTab renders a primary Button labelled "Start Benchmark".
		const startBtn = page.getByRole( 'button', {
			name: /start benchmark/i,
		} );
		await expect( startBtn ).toBeVisible();
	} );

	test( 'Start Benchmark button is enabled by default (validation is runtime, not disabled state)', async ( {
		page,
	} ) => {
		// The button is always enabled when not loading/running. The no-model
		// guard fires at runtime (handleCreateRun shows a notice) rather than
		// disabling the button. Verify it is clickable in the default state.
		const startBtn = page.getByRole( 'button', {
			name: /start benchmark/i,
		} );
		await expect( startBtn ).toBeEnabled();
	} );

	test( 'selecting a model enables it in the model selector', async ( {
		page,
	} ) => {
		// Click the first checkbox in the model selector to select a model.
		const modelSelector = page.locator( '.gratis-ai-agent-model-selector' );
		const firstCheckbox = modelSelector
			.locator( 'input[type="checkbox"]' )
			.first();
		await expect( firstCheckbox ).toBeVisible();

		// Ensure it starts unchecked.
		await expect( firstCheckbox ).not.toBeChecked();

		// Click to select.
		await firstCheckbox.click();
		await expect( firstCheckbox ).toBeChecked();

		// The "N models selected" counter should update to at least 1.
		const counter = page.locator( '.gratis-ai-agent-model-selector-actions span' );
		await expect( counter ).toContainText( '1' );
	} );

	test( 'Select All button selects all visible models', async ( { page } ) => {
		// Use exact match to avoid matching "Deselect All" (which contains "select all").
		const selectAllBtn = page.getByRole( 'button', {
			name: 'Select All',
			exact: true,
		} );
		await expect( selectAllBtn ).toBeVisible();
		await selectAllBtn.click();

		// All checkboxes should now be checked.
		const modelSelector = page.locator( '.gratis-ai-agent-model-selector' );
		const checkboxes = modelSelector.locator( 'input[type="checkbox"]' );
		const count = await checkboxes.count();
		expect( count ).toBeGreaterThan( 0 );
		for ( let i = 0; i < count; i++ ) {
			await expect( checkboxes.nth( i ) ).toBeChecked();
		}
	} );

	test( 'Deselect All button clears all selected models', async ( {
		page,
	} ) => {
		// First select all, then deselect all.
		// Use exact match to avoid strict mode violation (both buttons contain "all").
		const selectAllBtn = page.getByRole( 'button', {
			name: 'Select All',
			exact: true,
		} );
		await selectAllBtn.click();

		const deselectAllBtn = page.getByRole( 'button', {
			name: 'Deselect All',
			exact: true,
		} );
		await deselectAllBtn.click();

		// All checkboxes should be unchecked.
		const modelSelector = page.locator( '.gratis-ai-agent-model-selector' );
		const checkboxes = modelSelector.locator( 'input[type="checkbox"]' );
		const count = await checkboxes.count();
		expect( count ).toBeGreaterThan( 0 );
		for ( let i = 0; i < count; i++ ) {
			await expect( checkboxes.nth( i ) ).not.toBeChecked();
		}
	} );
} );

// ─── Run list (History tab) ───────────────────────────────────────────────────

test.describe( 'Benchmark Page - Run List', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockBenchmarkApi( page );
		await goToBenchmarkPage( page );
		// Wait for the benchmark page root to be visible before clicking tabs.
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-wrap' )
		).toBeVisible( { timeout: 30_000 } );
	} );

	test( 'History tab is clickable and shows the run list', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		// RunList renders .gratis-ai-agent-benchmark-run-list when runs exist.
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-run-list' )
		).toBeVisible( { timeout: 10_000 } );
	} );

	test( 'run list shows Benchmark History heading', async ( { page } ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		await expect(
			page.getByRole( 'heading', { name: /benchmark history/i } )
		).toBeVisible( { timeout: 10_000 } );
	} );

	test( 'run list table shows mocked run name', async ( { page } ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		// The mocked run "Test Run Alpha" should appear in the table.
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-run-list' )
		).toContainText( 'Test Run Alpha', { timeout: 10_000 } );
	} );

	test( 'run list table shows View and Delete action buttons', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		const runList = page.locator( '.gratis-ai-agent-benchmark-run-list' );
		await expect( runList ).toBeVisible( { timeout: 10_000 } );

		// Each run row has View and Delete buttons.
		await expect(
			runList.getByRole( 'button', { name: /view/i } ).first()
		).toBeVisible();

		await expect(
			runList.getByRole( 'button', { name: /delete/i } ).first()
		).toBeVisible();
	} );

	test( 'run list shows status badge for each run', async ( { page } ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		// RunList renders a .gratis-ai-agent-benchmark-status span for each run.
		const statusBadge = page
			.locator( '.gratis-ai-agent-benchmark-status' )
			.first();
		await expect( statusBadge ).toBeVisible( { timeout: 10_000 } );
		await expect( statusBadge ).toContainText( 'completed' );
	} );
} );

// ─── Empty run list ───────────────────────────────────────────────────────────

test.describe( 'Benchmark Page - Empty Run List', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );

		// Override runs endpoint to return empty list.
		await page.route(
			( url ) =>
				decodeURIComponent( url.toString() ).includes(
					'gratis-ai-agent/v1/benchmark/suites'
				),
			async ( route ) => {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( MOCK_SUITES ),
				} );
			}
		);

		await page.route(
			( url ) =>
				decodeURIComponent( url.toString() ).includes(
					'gratis-ai-agent/v1/providers'
				),
			async ( route ) => {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( MOCK_PROVIDERS ),
				} );
			}
		);

		await page.route(
			( url ) => {
				const decoded = decodeURIComponent( url.toString() );
				return (
					decoded.includes( 'gratis-ai-agent/v1/benchmark/runs' ) &&
					! decoded.includes( '/run-next' )
				);
			},
			async ( route ) => {
				if ( route.request().method() === 'GET' ) {
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( { runs: [] } ),
					} );
				} else {
					await route.continue();
				}
			}
		);

		await goToBenchmarkPage( page );
		// Wait for the benchmark page root to be visible before clicking tabs.
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-wrap' )
		).toBeVisible( { timeout: 30_000 } );
	} );

	test( 'History tab shows empty state when no runs exist', async ( {
		page,
	} ) => {
		const historyTab = page.getByRole( 'tab', { name: /history/i } );
		await historyTab.click();

		// RunList renders .gratis-ai-agent-benchmark-empty with "No benchmark runs yet."
		await expect(
			page.locator( '.gratis-ai-agent-benchmark-empty' )
		).toBeVisible( { timeout: 10_000 } );

		await expect(
			page.locator( '.gratis-ai-agent-benchmark-empty' )
		).toContainText( 'No benchmark runs yet.' );
	} );
} );
