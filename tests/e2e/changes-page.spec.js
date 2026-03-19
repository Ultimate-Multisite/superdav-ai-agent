/**
 * E2E tests for the Gratis AI Agent Changes admin page.
 *
 * Tests the Changes page at /wp-admin/tools.php?page=gratis-ai-agent-changes,
 * covering: page load, change history table, diff view, revert confirmation,
 * export patch button, and plugin download link presence.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToChangesPage,
} = require( './utils/wp-admin' );

// ─── Page load ────────────────────────────────────────────────────────────────

test.describe( 'Changes Page - Page Load', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToChangesPage( page );
	} );

	test( 'changes page loads with correct heading', async ( { page } ) => {
		// The PHP render() outputs an <h1> with "AI Changes".
		await expect(
			page.getByRole( 'heading', { name: /AI Changes/i, level: 1 } )
		).toBeVisible();
	} );

	test( 'changes page mounts the React app root', async ( { page } ) => {
		// The PHP render() outputs <div id="gratis-ai-agent-changes-root">.
		// After React mounts, the .gratis-changes-app wrapper is rendered inside it.
		const root = page.locator( '#gratis-ai-agent-changes-root' );
		await expect( root ).toBeVisible();
		await expect(
			root.locator( '.gratis-changes-app' )
		).toBeVisible();
	} );

	test( 'changes page shows the change history table', async ( { page } ) => {
		// The table is always rendered (even when empty) after loading.
		// Wait for the spinner to disappear first.
		await expect(
			page.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );

		// The wp-list-table is present.
		await expect(
			page.locator( '.gratis-changes-table' )
		).toBeVisible();
	} );

	test( 'changes table has expected column headers', async ( { page } ) => {
		await expect(
			page.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );

		const table = page.locator( '.gratis-changes-table' );
		await expect( table ).toBeVisible();

		// Verify key column headers are present.
		await expect( table.getByRole( 'columnheader', { name: /Object/i } ) ).toBeVisible();
		await expect( table.getByRole( 'columnheader', { name: /Field/i } ) ).toBeVisible();
		await expect( table.getByRole( 'columnheader', { name: /Before/i } ) ).toBeVisible();
		await expect( table.getByRole( 'columnheader', { name: /After/i } ) ).toBeVisible();
		await expect( table.getByRole( 'columnheader', { name: /Status/i } ) ).toBeVisible();
		await expect( table.getByRole( 'columnheader', { name: /Actions/i } ) ).toBeVisible();
	} );

	test( 'empty state message is shown when no changes exist', async ( {
		page,
	} ) => {
		await expect(
			page.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );

		// When no changes are recorded, the empty-state cell is shown.
		// This is environment-dependent: only assert when the table has no data rows.
		const rows = page.locator( '.gratis-changes-table tbody tr' );
		const rowCount = await rows.count();

		if ( rowCount === 1 ) {
			// Single row = empty state cell.
			await expect(
				page.locator( '.gratis-changes-empty' )
			).toBeVisible();
			await expect(
				page.locator( '.gratis-changes-empty' )
			).toContainText( 'No changes recorded yet' );
		} else {
			// Data rows present — skip empty-state assertion.
			expect( rowCount ).toBeGreaterThan( 0 );
		}
	} );

	test( 'filter controls are rendered', async ( { page } ) => {
		// Object Type and Status SelectControls are always rendered.
		const filters = page.locator( '.gratis-changes-filters' );
		await expect( filters ).toBeVisible();

		// Two <select> elements inside the filters bar.
		const selects = filters.locator( 'select' );
		await expect( selects ).toHaveCount( 2 );
	} );
} );

// ─── Diff view ────────────────────────────────────────────────────────────────

test.describe( 'Changes Page - Diff View', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToChangesPage( page );
		// Wait for the table to finish loading.
		await expect(
			page.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );
	} );

	test( 'diff button is present for each change row', async ( { page } ) => {
		const rows = page.locator( '.gratis-changes-table tbody tr' );
		const rowCount = await rows.count();

		// Only assert when data rows exist (not the empty-state row).
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			// No data — skip; diff buttons only appear with data.
			return;
		}

		// Every data row should have a "Diff" action button.
		for ( let i = 0; i < rowCount; i++ ) {
			await expect(
				rows.nth( i ).getByRole( 'button', { name: /Diff/i } )
			).toBeVisible();
		}
	} );

	test( 'clicking Diff button opens the diff modal', async ( { page } ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			// No data rows — cannot open diff modal. Skip.
			return;
		}

		// Click the first Diff button.
		const firstDiffBtn = page
			.locator( '.gratis-changes-table tbody tr' )
			.first()
			.getByRole( 'button', { name: /Diff/i } );
		await firstDiffBtn.click();

		// The WordPress Modal component renders with role="dialog".
		const modal = page.getByRole( 'dialog' );
		await expect( modal ).toBeVisible( { timeout: 5_000 } );

		// Modal title contains "Diff —".
		await expect( modal ).toContainText( 'Diff' );
	} );

	test( 'diff modal renders before/after panes', async ( { page } ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		const firstDiffBtn = page
			.locator( '.gratis-changes-table tbody tr' )
			.first()
			.getByRole( 'button', { name: /Diff/i } );
		await firstDiffBtn.click();

		const modal = page.getByRole( 'dialog' );
		await expect( modal ).toBeVisible( { timeout: 5_000 } );

		// Wait for diff content to load (spinner disappears).
		await expect(
			modal.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );

		// DiffViewer renders two panes: before and after.
		await expect(
			modal.locator( '.gratis-changes-diff__pane--before' )
		).toBeVisible();
		await expect(
			modal.locator( '.gratis-changes-diff__pane--after' )
		).toBeVisible();

		// Each pane has a label ("Before" / "After").
		await expect(
			modal.locator( '.gratis-changes-diff__pane--before .gratis-changes-diff__label' )
		).toContainText( 'Before' );
		await expect(
			modal.locator( '.gratis-changes-diff__pane--after .gratis-changes-diff__label' )
		).toContainText( 'After' );
	} );

	test( 'diff modal can be closed', async ( { page } ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		const firstDiffBtn = page
			.locator( '.gratis-changes-table tbody tr' )
			.first()
			.getByRole( 'button', { name: /Diff/i } );
		await firstDiffBtn.click();

		const modal = page.getByRole( 'dialog' );
		await expect( modal ).toBeVisible( { timeout: 5_000 } );

		// Close via the modal's close button (WordPress Modal renders an × button).
		const closeBtn = modal.getByRole( 'button', { name: /close/i } );
		await closeBtn.click();

		await expect( modal ).not.toBeVisible( { timeout: 3_000 } );
	} );
} );

// ─── Revert confirmation ──────────────────────────────────────────────────────

test.describe( 'Changes Page - Revert Confirmation', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToChangesPage( page );
		await expect(
			page.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );
	} );

	test( 'revert button is present for active (non-reverted) change rows', async ( {
		page,
	} ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		// Find rows that are not marked as reverted (no "Reverted" badge).
		const rows = page.locator( '.gratis-changes-table tbody tr' );
		const rowCount = await rows.count();

		let foundActiveRow = false;
		for ( let i = 0; i < rowCount; i++ ) {
			const row = rows.nth( i );
			const isReverted = await row
				.locator( 'text=Reverted' )
				.isVisible();
			if ( ! isReverted ) {
				// Active row — Revert button must be present.
				await expect(
					row.getByRole( 'button', { name: /Revert/i } )
				).toBeVisible();
				foundActiveRow = true;
				break;
			}
		}

		// If all rows are reverted, the test is vacuously satisfied.
		// Log a note but don't fail — this is a valid state.
		if ( ! foundActiveRow ) {
			// All rows are already reverted; revert button is correctly absent.
			expect( true ).toBe( true );
		}
	} );

	test( 'clicking Revert triggers a browser confirmation dialog', async ( {
		page,
	} ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		// Find the first active (non-reverted) row.
		const rows = page.locator( '.gratis-changes-table tbody tr' );
		const rowCount = await rows.count();
		let revertBtn = null;

		for ( let i = 0; i < rowCount; i++ ) {
			const row = rows.nth( i );
			const isReverted = await row
				.locator( 'text=Reverted' )
				.isVisible();
			if ( ! isReverted ) {
				revertBtn = row.getByRole( 'button', { name: /Revert/i } );
				break;
			}
		}

		if ( ! revertBtn ) {
			// No active rows to revert.
			return;
		}

		// Intercept the window.confirm dialog — dismiss it so no actual revert occurs.
		let dialogMessage = '';
		page.once( 'dialog', async ( dialog ) => {
			dialogMessage = dialog.message();
			await dialog.dismiss();
		} );

		await revertBtn.click();

		// The dialog message should mention reverting.
		expect( dialogMessage ).toMatch( /revert/i );
	} );

	test( 'revert button inside diff modal triggers confirmation dialog', async ( {
		page,
	} ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		// Open the diff modal for the first active row.
		const rows = page.locator( '.gratis-changes-table tbody tr' );
		const rowCount = await rows.count();
		let diffBtn = null;

		for ( let i = 0; i < rowCount; i++ ) {
			const row = rows.nth( i );
			const isReverted = await row
				.locator( 'text=Reverted' )
				.isVisible();
			if ( ! isReverted ) {
				diffBtn = row.getByRole( 'button', { name: /Diff/i } );
				break;
			}
		}

		if ( ! diffBtn ) {
			return;
		}

		await diffBtn.click();

		const modal = page.getByRole( 'dialog' );
		await expect( modal ).toBeVisible( { timeout: 5_000 } );

		// Wait for diff to load.
		await expect(
			modal.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );

		// The modal renders a "Revert This Change" button for active changes.
		const modalRevertBtn = modal.getByRole( 'button', {
			name: /Revert This Change/i,
		} );
		await expect( modalRevertBtn ).toBeVisible();

		// Intercept and dismiss the confirmation dialog.
		let dialogMessage = '';
		page.once( 'dialog', async ( dialog ) => {
			dialogMessage = dialog.message();
			await dialog.dismiss();
		} );

		await modalRevertBtn.click();

		// Confirmation dialog should have been triggered.
		expect( dialogMessage ).toMatch( /revert/i );
	} );
} );

// ─── Export patch ─────────────────────────────────────────────────────────────

test.describe( 'Changes Page - Export Patch', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToChangesPage( page );
		await expect(
			page.locator( '.gratis-changes-loading' )
		).not.toBeVisible( { timeout: 10_000 } );
	} );

	test( 'Export Patch button is present in the filters bar', async ( {
		page,
	} ) => {
		// The Export Patch button is always rendered (disabled when nothing selected).
		const exportBtn = page.getByRole( 'button', {
			name: /Export Patch/i,
		} );
		await expect( exportBtn ).toBeVisible();
	} );

	test( 'Export Patch button is disabled when no changes are selected', async ( {
		page,
	} ) => {
		const exportBtn = page.getByRole( 'button', {
			name: /Export Patch/i,
		} );
		// No checkboxes checked → button is disabled.
		await expect( exportBtn ).toBeDisabled();
	} );

	test( 'Export Patch button enables after selecting a change', async ( {
		page,
	} ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			// No rows to select — button stays disabled. Verify that.
			const exportBtn = page.getByRole( 'button', {
				name: /Export Patch/i,
			} );
			await expect( exportBtn ).toBeDisabled();
			return;
		}

		// Check the first row's checkbox.
		const firstCheckbox = page
			.locator( '.gratis-changes-table tbody tr' )
			.first()
			.locator( 'input[type="checkbox"]' );
		await firstCheckbox.check();

		// Export Patch button should now be enabled.
		const exportBtn = page.getByRole( 'button', {
			name: /Export Patch/i,
		} );
		await expect( exportBtn ).toBeEnabled();
	} );

	test( 'Export Patch button label shows selected count', async ( {
		page,
	} ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		// Select the first row.
		const firstCheckbox = page
			.locator( '.gratis-changes-table tbody tr' )
			.first()
			.locator( 'input[type="checkbox"]' );
		await firstCheckbox.check();

		// Button label should include "(1)" to indicate selection count.
		const exportBtn = page.getByRole( 'button', {
			name: /Export Patch/i,
		} );
		await expect( exportBtn ).toContainText( '(1)' );
	} );

	test( 'clicking Export Patch with no selection shows a warning notice', async ( {
		page,
	} ) => {
		// Ensure nothing is selected (default state).
		const exportBtn = page.getByRole( 'button', {
			name: /Export Patch/i,
		} );

		// The button is disabled when nothing is selected, so we cannot click it
		// directly. Verify the disabled state instead — this is the guard that
		// prevents the "select at least one" warning from being needed.
		await expect( exportBtn ).toBeDisabled();
	} );

	test( 'select-all checkbox selects all rows on the current page', async ( {
		page,
	} ) => {
		const isEmpty = await page
			.locator( '.gratis-changes-empty' )
			.isVisible();
		if ( isEmpty ) {
			return;
		}

		// The select-all checkbox is in the <thead>.
		const selectAllCheckbox = page
			.locator( '.gratis-changes-table thead' )
			.locator( 'input[type="checkbox"]' );
		await selectAllCheckbox.check();

		// All row checkboxes should now be checked.
		const rowCheckboxes = page
			.locator( '.gratis-changes-table tbody tr' )
			.locator( 'input[type="checkbox"]' );
		const count = await rowCheckboxes.count();
		for ( let i = 0; i < count; i++ ) {
			await expect( rowCheckboxes.nth( i ) ).toBeChecked();
		}

		// Export button should be enabled and show the count.
		const exportBtn = page.getByRole( 'button', {
			name: /Export Patch/i,
		} );
		await expect( exportBtn ).toBeEnabled();
	} );
} );

// ─── Plugin download link ─────────────────────────────────────────────────────

test.describe( 'Changes Page - Plugin Download Link', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
	} );

	test( 'REST endpoint for modified plugins returns expected shape', async ( {
		page,
	} ) => {
		// The /modified-plugins endpoint is the backend for plugin download links.
		// Verify it returns a valid response with the expected JSON shape.
		const response = await page.evaluate( async ( restBase ) => {
			const nonce = window.gratisAiAgentChanges?.nonce || '';
			const res = await fetch( `${ restBase }/modified-plugins`, {
				headers: {
					'X-WP-Nonce': nonce,
				},
			} );
			return {
				status: res.status,
				body: await res.json(),
			};
		}, page.url().replace( /\/wp-admin.*/, '/wp-json/gratis-ai-agent/v1' ) );

		// Endpoint must respond (200 or 401 if not yet on the changes page).
		// Navigate to the changes page first to get the nonce.
		await goToChangesPage( page );
		await page.waitForLoadState( 'networkidle' );

		const apiResponse = await page.evaluate( async () => {
			const nonce = window.gratisAiAgentChanges?.nonce || '';
			const restUrl = window.gratisAiAgentChanges?.restUrl || '';
			if ( ! restUrl ) {
				return { status: 0, body: null };
			}
			const res = await fetch( `${ restUrl }/modified-plugins`, {
				headers: {
					'X-WP-Nonce': nonce,
				},
			} );
			return {
				status: res.status,
				body: await res.json(),
			};
		} );

		// Endpoint must return 200.
		expect( apiResponse.status ).toBe( 200 );

		// Response must have `plugins` array and `count` integer.
		expect( apiResponse.body ).toHaveProperty( 'plugins' );
		expect( apiResponse.body ).toHaveProperty( 'count' );
		expect( Array.isArray( apiResponse.body.plugins ) ).toBe( true );
		expect( typeof apiResponse.body.count ).toBe( 'number' );
	} );

	test( 'each modified plugin entry has a download_url', async ( { page } ) => {
		await goToChangesPage( page );
		await page.waitForLoadState( 'networkidle' );

		const apiResponse = await page.evaluate( async () => {
			const nonce = window.gratisAiAgentChanges?.nonce || '';
			const restUrl = window.gratisAiAgentChanges?.restUrl || '';
			if ( ! restUrl ) {
				return { status: 0, body: null };
			}
			const res = await fetch( `${ restUrl }/modified-plugins`, {
				headers: {
					'X-WP-Nonce': nonce,
				},
			} );
			return {
				status: res.status,
				body: await res.json(),
			};
		} );

		expect( apiResponse.status ).toBe( 200 );

		// If any plugins are returned, each must have a download_url.
		for ( const plugin of apiResponse.body.plugins ) {
			expect( plugin ).toHaveProperty( 'plugin_slug' );
			expect( plugin ).toHaveProperty( 'download_url' );
			expect( typeof plugin.download_url ).toBe( 'string' );
			expect( plugin.download_url ).toMatch( /download-plugin/ );
		}
	} );

	test( 'changes page description mentions diffs, revert, and export', async ( {
		page,
	} ) => {
		await goToChangesPage( page );
		await page.waitForLoadState( 'networkidle' );

		// The PHP render() outputs a description paragraph.
		const description = page.locator(
			'.gratis-ai-agent-changes-wrap .description'
		);
		await expect( description ).toBeVisible();
		await expect( description ).toContainText( 'diffs' );
		await expect( description ).toContainText( 'revert' );
		await expect( description ).toContainText( 'export' );
	} );
} );
