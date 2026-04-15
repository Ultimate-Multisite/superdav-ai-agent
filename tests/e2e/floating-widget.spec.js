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
} = require( './utils/wp-admin' );

test.describe( 'Floating Widget', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAdminDashboard( page );
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

		const panel = getFloatingPanel( page );
		// Scope to the floating panel to avoid strict-mode violations when
		// screen-meta also renders a chat panel on the same page.
		const chatPanel = panel.locator( '.gratis-ai-agent-chat-panel' );
		await expect( chatPanel ).toBeVisible();

		const input = panel.locator( '.gratis-ai-agent-input' );
		await expect( input ).toBeVisible();
	} );

	test( 'close button hides the floating panel', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		await expect( panel ).toBeVisible();

		// Click the Close button in the floating panel title bar.
		const closeButton = panel.getByLabel( 'Close' );
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

		const minimizeButton = panel.getByLabel( 'Minimize' );
		await minimizeButton.click();

		// Panel element stays in DOM but body is hidden (is-minimized class).
		await expect( panel ).toHaveClass( /is-minimized/ );

		// Chat panel body should not be visible.
		const chatPanel = panel.locator( '.gratis-ai-agent-chat-panel' );
		await expect( chatPanel ).not.toBeVisible();
	} );

	test( 'expand button restores minimized panel', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );

		// Minimize first.
		const minimizeButton = panel.getByLabel( 'Minimize' );
		await minimizeButton.click();

		await expect( panel ).toHaveClass( /is-minimized/ );

		// Expand.
		const expandButton = panel.getByLabel( 'Expand' );
		await expandButton.click();

		await expect( panel ).not.toHaveClass( /is-minimized/ );

		const chatPanel = panel.locator( '.gratis-ai-agent-chat-panel' );
		await expect( chatPanel ).toBeVisible();
	} );

	// All tests below scope locators to the floating panel to avoid
	// strict-mode violations when screen-meta also renders a chat panel.

	test( 'message input accepts text', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		const input = panel.locator( '.gratis-ai-agent-input' );
		await input.fill( 'Hello, AI Agent!' );

		await expect( input ).toHaveValue( 'Hello, AI Agent!' );
	} );

	test( 'send button is enabled when input has text', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		const input = panel.locator( '.gratis-ai-agent-input' );
		const sendButton = panel.locator( '.gratis-ai-agent-send-btn' );

		// Send button should be disabled (or absent) when input is empty.
		await expect( sendButton ).toBeDisabled();

		await input.fill( 'Test message' );

		// Send button should become enabled.
		await expect( sendButton ).toBeEnabled();
	} );

	test( 'pressing Enter submits the message', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		const input = panel.locator( '.gratis-ai-agent-input' );
		await input.fill( 'Test message via Enter' );
		await input.press( 'Enter' );

		// Input should be cleared after submission.
		await expect( input ).toHaveValue( '' );
	} );

	test( 'slash command menu appears when typing /', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		const input = panel.locator( '.gratis-ai-agent-input' );
		await input.fill( '/' );

		// Slash command menu should appear.
		const slashMenu = panel.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();
	} );

	test( 'slash command menu disappears when typing a space', async ( {
		page,
	} ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		const input = panel.locator( '.gratis-ai-agent-input' );
		await input.fill( '/' );

		const slashMenu = panel.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		await input.fill( '/remember something' );
		await expect( slashMenu ).not.toBeVisible();
	} );
} );
