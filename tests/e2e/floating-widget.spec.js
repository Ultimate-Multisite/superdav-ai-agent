/**
 * E2E tests for the Gratis AI Agent floating widget.
 *
 * Tests the FAB button and floating panel that appear on all admin pages.
 * Requires a running wp-env environment with the plugin active.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAdminDashboard,
	getFloatingButton,
	getFloatingPanel,
	getMessageInput,
	getSendButton,
	getChatPanel,
} = require( './utils/wp-admin' );

test.describe( 'Floating Widget', () => {
	test.beforeEach( async ( { page } ) => {
		// Capture JS console errors and uncaught exceptions for CI visibility.
		// The ErrorBoundary catches React crashes silently — this surfaces them.
		const consoleErrors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				consoleErrors.push( msg.text() );
			}
		} );
		page.on( 'pageerror', ( err ) => {
			consoleErrors.push( err.message );
		} );

		await loginToWordPress( page );
		await goToAdminDashboard( page );

		// Diagnostic: dump page state to CI output so we can see what's
		// actually rendered when the FAB is expected but not found.
		const diag = await page.evaluate( () => {
			const scripts = [ ...document.querySelectorAll( 'script[src]' ) ]
				.map( ( s ) => s.src )
				.filter( ( s ) => s.includes( 'gratis-ai-agent' ) );
			const styles = [ ...document.querySelectorAll( 'link[rel="stylesheet"]' ) ]
				.map( ( l ) => l.href )
				.filter( ( h ) => h.includes( 'gratis-ai-agent' ) );
			const root = document.getElementById( 'gratis-ai-agent-floating-root' );
			const fab = document.querySelector( '.gratis-ai-agent-fab' );
			const overlay = document.querySelector( '.ai-agent-site-builder-overlay' );
			const errorBoundary = document.querySelector( '.ai-agent-error-boundary' );
			return {
				scripts,
				styles,
				hasRoot: !! root,
				rootHTML: root ? root.innerHTML.substring( 0, 500 ) : null,
				hasFab: !! fab,
				hasOverlay: !! overlay,
				hasErrorBoundary: !! errorBoundary,
				url: window.location.href,
			};
		} );
		// eslint-disable-next-line no-console
		console.log( 'FLOATING-WIDGET DIAG:', JSON.stringify( diag ) );

		// Log any JS errors so they appear in CI output even when tests fail.
		if ( consoleErrors.length ) {
			// eslint-disable-next-line no-console
			console.log( 'JS errors on page:', JSON.stringify( consoleErrors ) );
		}
	} );

	test( 'FAB button is visible on admin pages', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await expect( fab ).toBeVisible();
	} );

	test( 'clicking FAB opens the floating panel', async ( { page } ) => {
		const fab = getFloatingButton( page );
		const panel = getFloatingPanel( page );

		// Panel should not be visible initially.
		await expect( panel ).not.toBeVisible();

		await fab.click();

		await expect( panel ).toBeVisible();
	} );

	test( 'floating panel contains the chat UI', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const chatPanel = getChatPanel( page );
		await expect( chatPanel ).toBeVisible();

		const input = getMessageInput( page );
		await expect( input ).toBeVisible();
	} );

	test( 'close button hides the floating panel', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		await expect( panel ).toBeVisible();

		// Click the Close button in the title bar.
		const closeButton = page.getByLabel( 'Close' );
		await closeButton.click();

		await expect( panel ).not.toBeVisible();

		// FAB should reappear.
		await expect( fab ).toBeVisible();
	} );

	test( 'minimize button collapses the panel body', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		await expect( panel ).toBeVisible();

		const minimizeButton = page.getByLabel( 'Minimize' );
		await minimizeButton.click();

		// Panel element stays in DOM but body is hidden (is-minimized class).
		await expect( panel ).toHaveClass( /is-minimized/ );

		// Chat panel body should not be visible.
		const chatPanel = getChatPanel( page );
		await expect( chatPanel ).not.toBeVisible();
	} );

	test( 'expand button restores minimized panel', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		// Minimize first.
		const minimizeButton = page.getByLabel( 'Minimize' );
		await minimizeButton.click();

		const panel = getFloatingPanel( page );
		await expect( panel ).toHaveClass( /is-minimized/ );

		// Expand.
		const expandButton = page.getByLabel( 'Expand' );
		await expandButton.click();

		await expect( panel ).not.toHaveClass( /is-minimized/ );

		const chatPanel = getChatPanel( page );
		await expect( chatPanel ).toBeVisible();
	} );

	test( 'message input accepts text', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const input = getMessageInput( page );
		await input.fill( 'Hello, AI Agent!' );

		await expect( input ).toHaveValue( 'Hello, AI Agent!' );
	} );

	test( 'send button is enabled when input has text', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const input = getMessageInput( page );
		const sendButton = getSendButton( page );

		// Send button should be disabled (or absent) when input is empty.
		await expect( sendButton ).toBeDisabled();

		await input.fill( 'Test message' );

		// Send button should become enabled.
		await expect( sendButton ).toBeEnabled();
	} );

	test( 'pressing Enter submits the message', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const input = getMessageInput( page );
		await input.fill( 'Test message via Enter' );
		await input.press( 'Enter' );

		// Input should be cleared after submission.
		await expect( input ).toHaveValue( '' );
	} );

	test( 'slash command menu appears when typing /', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const input = getMessageInput( page );
		await input.fill( '/' );

		// Slash command menu should appear.
		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();
	} );

	test( 'slash command menu disappears when typing a space', async ( {
		page,
	} ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		await input.fill( '/remember something' );
		await expect( slashMenu ).not.toBeVisible();
	} );
} );
