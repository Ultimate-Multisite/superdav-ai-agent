/**
 * E2E tests for the UnifiedAdminMenu — the React SPA that consolidates all
 * admin pages into a single hash-based router at:
 *   admin.php?page=gratis-ai-agent
 *
 * Routes:
 *   Chat:      admin.php?page=gratis-ai-agent          (or #/chat)
 *   Abilities: admin.php?page=gratis-ai-agent#/abilities
 *   Changes:   admin.php?page=gratis-ai-agent#/changes
 *   Settings:  admin.php?page=gratis-ai-agent#/settings
 *
 * Test coverage:
 *   1. Menu renders with all nav items for admin users
 *   2. Navigation between routes updates the URL hash
 *   3. Active nav item is highlighted on the current route
 *   4. Hash-based routing renders the correct route component
 *   5. Direct hash URL navigation loads the correct route
 *   6. Unknown hash route shows a 404 / not-found message
 *   7. Non-admin users cannot access the admin menu page
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAgentPage,
	goToChangesPage,
	goToSettingsPage,
	goToAbilitiesPage,
} = require( './utils/wp-admin' );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Navigate to the unified admin root and wait for the SPA to mount.
 *
 * @param {import('@playwright/test').Page} page
 */
async function goToUnifiedAdmin( page ) {
	await page.goto( '/wp-admin/admin.php?page=gratis-ai-agent' );
	await page.waitForLoadState( 'domcontentloaded' );
	await page
		.locator( '.gratis-ai-agent-unified-admin' )
		.waitFor( { state: 'visible', timeout: 45_000 } );
}

/**
 * Navigate to a specific hash route and wait for the SPA to reflect it.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          hash e.g. '#/settings'
 */
async function goToHashRoute( page, hash ) {
	await page.goto( `/wp-admin/admin.php?page=gratis-ai-agent${ hash }` );
	await page.waitForLoadState( 'domcontentloaded' );
	await page
		.locator( '.gratis-ai-agent-unified-admin' )
		.waitFor( { state: 'visible', timeout: 45_000 } );
}

// ---------------------------------------------------------------------------
// Test suite: Menu rendering
// ---------------------------------------------------------------------------

// Menu Rendering, Navigation, and Active State suites are skipped because
// the unified admin SPA no longer renders a custom navigation sidebar —
// routing is handled via WordPress's standard admin menu and hash URLs.
// These tests referenced .gratis-ai-nav-* elements that don't exist.
test.describe.skip( 'UnifiedAdminMenu - Menu Rendering', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToUnifiedAdmin( page );
	} );

	test( 'renders the unified admin wrapper and navigation sidebar', async ( {
		page,
	} ) => {
		// Outer SPA wrapper.
		await expect( page.locator( '.gratis-ai-agent-unified-admin' ) ).toBeVisible();

		// Navigation sidebar.
		await expect( page.locator( '.gratis-ai-admin-nav' ) ).toBeVisible();

		// Main content area.
		await expect( page.locator( '.gratis-ai-admin-main' ) ).toBeVisible();
	} );

	test( 'navigation sidebar contains at least the core menu items', async ( {
		page,
	} ) => {
		// The nav menu is a <ul role="menubar"> with <li> items.
		const navMenu = page.locator( '.gratis-ai-nav-menu' );
		await expect( navMenu ).toBeVisible();

		// There must be at least 4 items (chat, abilities, changes, settings).
		const items = navMenu.locator( '.gratis-ai-nav-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 4 );
	} );

	test( 'nav header shows the AI Agent logo and title', async ( { page } ) => {
		const header = page.locator( '.gratis-ai-nav-header' );
		await expect( header ).toBeVisible();

		// Logo icon.
		await expect( header.locator( '.gratis-ai-nav-logo' ) ).toBeVisible();

		// Title text.
		await expect( header.locator( 'h1' ) ).toContainText( 'AI Agent' );
	} );
} );

// ---------------------------------------------------------------------------
// Test suite: Hash-based routing
// ---------------------------------------------------------------------------

test.describe( 'UnifiedAdminMenu - Hash-Based Routing', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
	} );

	test( 'default route (no hash) renders the chat container', async ( {
		page,
	} ) => {
		await goToUnifiedAdmin( page );

		// ChatRoute mounts the chat app into #gratis-ai-agent-chat-container.
		await expect(
			page.locator( '#gratis-ai-agent-chat-container' )
		).toBeVisible( { timeout: 15_000 } );
	} );

	test( '#/chat hash renders the chat container', async ( { page } ) => {
		await goToHashRoute( page, '#/chat' );

		await expect(
			page.locator( '#gratis-ai-agent-chat-container' )
		).toBeVisible( { timeout: 15_000 } );
	} );

	test( '#/settings hash renders the settings route', async ( { page } ) => {
		await goToSettingsPage( page );

		await expect(
			page.locator( '.gratis-ai-agent-route-settings' )
		).toBeVisible();
	} );

	test( '#/changes hash renders the changes route', async ( { page } ) => {
		await goToChangesPage( page );

		await expect(
			page.locator( '.gratis-ai-agent-route-changes' )
		).toBeVisible();
	} );

	test( '#/abilities hash renders the abilities manager', async ( {
		page,
	} ) => {
		await goToAbilitiesPage( page );

		await expect(
			page.locator( '.gratis-ai-agent-abilities-manager' )
		).toBeVisible();
	} );

	test( 'unknown hash route shows a not-found message', async ( { page } ) => {
		await goToHashRoute( page, '#/this-route-does-not-exist' );

		// Router renders .gratis-ai-agent-route-not-found for unrecognised routes.
		await expect(
			page.locator( '.gratis-ai-agent-route-not-found' )
		).toBeVisible( { timeout: 15_000 } );
	} );
} );

// ---------------------------------------------------------------------------
// Test suite: Navigation — clicking nav items updates the hash
// ---------------------------------------------------------------------------

test.describe.skip( 'UnifiedAdminMenu - Navigation', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToUnifiedAdmin( page );
	} );

	test( 'clicking a nav item updates the URL hash', async ( { page } ) => {
		// Find the settings nav link (aria-label or text "Settings").
		// The nav renders items from window.gratisAiAgentData.menuItems, so we
		// locate by the nav-link role and label rather than a hardcoded slug.
		const settingsLink = page
			.locator( '.gratis-ai-nav-link' )
			.filter( { hasText: /settings/i } )
			.first();

		await expect( settingsLink ).toBeVisible();
		await settingsLink.click();

		// URL hash must update to #/settings.
		await expect( page ).toHaveURL( /#\/settings/ );
	} );

	test( 'clicking a nav item renders the corresponding route', async ( {
		page,
	} ) => {
		const changesLink = page
			.locator( '.gratis-ai-nav-link' )
			.filter( { hasText: /changes/i } )
			.first();

		await expect( changesLink ).toBeVisible();
		await changesLink.click();

		// Wait for the changes route container.
		await expect(
			page.locator( '.gratis-ai-agent-route-changes' )
		).toBeVisible( { timeout: 30_000 } );
	} );

	test( 'navigating back to chat from another route renders the chat container', async ( {
		page,
	} ) => {
		// Go to settings first.
		const settingsLink = page
			.locator( '.gratis-ai-nav-link' )
			.filter( { hasText: /settings/i } )
			.first();
		await settingsLink.click();
		await expect( page.locator( '.gratis-ai-agent-route-settings' ) ).toBeVisible( {
			timeout: 30_000,
		} );

		// Navigate back to chat.
		const chatLink = page
			.locator( '.gratis-ai-nav-link' )
			.filter( { hasText: /chat/i } )
			.first();
		await chatLink.click();

		await expect(
			page.locator( '#gratis-ai-agent-chat-container' )
		).toBeVisible( { timeout: 15_000 } );
	} );
} );

// ---------------------------------------------------------------------------
// Test suite: Active state highlighting
// ---------------------------------------------------------------------------

test.describe.skip( 'UnifiedAdminMenu - Active State', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
	} );

	test( 'chat nav item has is-active class on the default route', async ( {
		page,
	} ) => {
		await goToUnifiedAdmin( page );

		// The active <li> gets the is-active class.
		const activeItem = page.locator( '.gratis-ai-nav-item.is-active' );
		await expect( activeItem ).toBeVisible();

		// The active item's link text should contain "Chat" (or similar).
		await expect( activeItem ).toContainText( /chat/i );
	} );

	test( 'settings nav item has is-active class when on the settings route', async ( {
		page,
	} ) => {
		await goToSettingsPage( page );

		const activeItem = page.locator( '.gratis-ai-nav-item.is-active' );
		await expect( activeItem ).toBeVisible();
		await expect( activeItem ).toContainText( /settings/i );
	} );

	test( 'active nav link has aria-current="page"', async ( { page } ) => {
		await goToSettingsPage( page );

		// The active Button renders aria-current="page".
		const activeLink = page.locator(
			'.gratis-ai-nav-item.is-active .gratis-ai-nav-link'
		);
		await expect( activeLink ).toHaveAttribute( 'aria-current', 'page' );
	} );

	test( 'inactive nav links do not have aria-current attribute', async ( {
		page,
	} ) => {
		await goToSettingsPage( page );

		// All nav links that are NOT in the active item should lack aria-current.
		const inactiveLinks = page.locator(
			'.gratis-ai-nav-item:not(.is-active) .gratis-ai-nav-link'
		);
		const count = await inactiveLinks.count();
		expect( count ).toBeGreaterThan( 0 );

		for ( let i = 0; i < count; i++ ) {
			await expect( inactiveLinks.nth( i ) ).not.toHaveAttribute(
				'aria-current'
			);
		}
	} );

	test( 'active state updates when navigating between routes', async ( {
		page,
	} ) => {
		await goToUnifiedAdmin( page );

		// Initially chat is active.
		let activeItem = page.locator( '.gratis-ai-nav-item.is-active' );
		await expect( activeItem ).toContainText( /chat/i );

		// Click settings.
		const settingsLink = page
			.locator( '.gratis-ai-nav-link' )
			.filter( { hasText: /settings/i } )
			.first();
		await settingsLink.click();

		// Active item should now be settings.
		activeItem = page.locator( '.gratis-ai-nav-item.is-active' );
		await expect( activeItem ).toContainText( /settings/i );
	} );
} );

// ---------------------------------------------------------------------------
// Test suite: Access control
// ---------------------------------------------------------------------------

test.describe( 'UnifiedAdminMenu - Access Control', () => {
	test( 'non-admin user cannot access the admin menu page', async ( {
		page,
	} ) => {
		// Log in as a subscriber (no admin capabilities).
		// wp-env creates a subscriber account with username 'subscriber' and
		// password 'password'. If the account does not exist the test is skipped.
		const WP_SUBSCRIBER_USER =
			process.env.WP_SUBSCRIBER_USER || 'subscriber';
		const WP_SUBSCRIBER_PASSWORD =
			process.env.WP_SUBSCRIBER_PASSWORD || 'password';

		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', WP_SUBSCRIBER_USER );
		await page.fill( '#user_pass', WP_SUBSCRIBER_PASSWORD );
		await page.click( '#wp-submit' );

		// If login fails (subscriber account not set up), skip gracefully.
		const loginFailed = await page
			.locator( '#login_error' )
			.isVisible()
			.catch( () => false );
		if ( loginFailed ) {
			test.skip( true, 'Subscriber account not available in this environment' );
			return;
		}

		// Wait for redirect after login.
		await page.waitForURL( /wp-admin|wp-login/, { timeout: 30_000 } );

		// Attempt to access the plugin admin page.
		await page.goto( '/wp-admin/admin.php?page=gratis-ai-agent' );
		await page.waitForLoadState( 'domcontentloaded' );

		// WordPress should redirect to the dashboard or show an error.
		// The unified admin SPA must NOT be rendered for non-admin users.
		const spaVisible = await page
			.locator( '.gratis-ai-agent-unified-admin' )
			.isVisible()
			.catch( () => false );
		expect( spaVisible ).toBe( false );

		// WordPress typically redirects to /wp-admin/ or shows "Sorry, you are
		// not allowed to access this page."
		const url = page.url();
		const bodyText = await page.locator( 'body' ).textContent();
		const isBlocked =
			url.includes( 'wp-admin/index.php' ) ||
			url.includes( 'wp-login.php' ) ||
			( bodyText || '' ).includes( 'not allowed' ) ||
			( bodyText || '' ).includes( 'Sorry' );
		expect( isBlocked ).toBe( true );
	} );
} );
