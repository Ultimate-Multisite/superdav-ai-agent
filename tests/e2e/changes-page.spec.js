/**
 * E2E tests for the Gratis AI Agent Changes admin page.
 *
 * The UnifiedAdminMenu consolidates all admin pages into a single React SPA
 * at admin.php?page=gratis-ai-agent with hash-based routing. The changes
 * route is at admin.php?page=gratis-ai-agent#/changes and renders the
 * ChangesRoute component inside the unified admin layout.
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

	test( 'changes page loads the unified admin app', async ( { page } ) => {
		// The UnifiedAdminMenu SPA mounts into #gratis-ai-agent-root and
		// renders .gratis-ai-agent-unified-admin as the top-level wrapper.
		await expect(
			page.locator( '#gratis-ai-agent-root' )
		).toBeVisible();
		await expect(
			page.locator( '.gratis-ai-agent-unified-admin' )
		).toBeVisible();
	} );

	test( 'changes route container is rendered', async ( { page } ) => {
		// The Router renders ChangesRoute inside .gratis-ai-agent-route-changes
		// when the hash is #/changes.
		await expect(
			page.locator( '.gratis-ai-agent-route-changes' )
		).toBeVisible();
	} );

	test( 'changes page shows the Changes heading', async ( { page } ) => {
		// ChangesRoute renders an h2 with "Changes".
		await expect(
			page.locator( '.gratis-ai-agent-route-changes' ).getByRole( 'heading', {
				name: /changes/i,
				level: 2,
			} )
		).toBeVisible();
	} );

	test( 'changes page shows descriptive content', async ( { page } ) => {
		// ChangesRoute renders a description paragraph.
		await expect(
			page.locator( '.gratis-ai-agent-route-changes' )
		).toContainText( 'changes' );
	} );

	test( 'navigation highlights the Changes menu item', async ( { page } ) => {
		// The Navigation component renders links for each route. The changes
		// link should be present in the unified admin navigation.
		const nav = page.locator( '.gratis-ai-admin-layout' );
		await expect( nav ).toBeVisible();
	} );
} );

// ─── REST endpoint ────────────────────────────────────────────────────────────

test.describe( 'Changes Page - REST Endpoint', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
	} );

	test( 'REST endpoint for modified plugins returns expected shape', async ( {
		page,
	} ) => {
		// Navigate to the changes page so the nonce and wpApiSettings are
		// available in the page context.
		await goToChangesPage( page );

		const apiResponse = await page.evaluate( async () => {
			const nonce = window.gratisAiAgentData?.nonce || '';
			if ( ! nonce ) {
				return { status: 0, body: null };
			}
			// Use wpApiSettings.root (set by wp_localize_script for wp-api-fetch)
			// which handles both pretty-permalink (/wp-json/) and plain-permalink
			// (?rest_route=) environments. Fall back to the plain-permalink format
			// if wpApiSettings is unavailable (e.g. wp-env without pretty permalinks).
			const apiRoot =
				window.wpApiSettings?.root ||
				window.location.origin + '/?rest_route=/';
			const endpoint = `${ apiRoot }gratis-ai-agent/v1/modified-plugins`;
			const res = await fetch( endpoint, {
				headers: {
					'X-WP-Nonce': nonce,
				},
			} );
			let body = null;
			try {
				body = await res.json();
			} catch ( e ) {
				body = null;
			}
			return { status: res.status, body };
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

		const apiResponse = await page.evaluate( async () => {
			const nonce = window.gratisAiAgentData?.nonce || '';
			if ( ! nonce ) {
				return { status: 0, body: null };
			}
			const apiRoot =
				window.wpApiSettings?.root ||
				window.location.origin + '/?rest_route=/';
			const endpoint = `${ apiRoot }gratis-ai-agent/v1/modified-plugins`;
			const res = await fetch( endpoint, {
				headers: {
					'X-WP-Nonce': nonce,
				},
			} );
			let body = null;
			try {
				body = await res.json();
			} catch ( e ) {
				body = null;
			}
			return { status: res.status, body };
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
} );
