/**
 * E2E tests for the Gratis AI Agent floating widget.
 *
 * Tests the launcher button and floating panel that appear on all admin pages.
 * Requires a running wp-env environment with the plugin active.
 *
 * The chat widget was redesigned in #1157. Class names changed:
 *   .gratis-ai-agent-fab         → .gaa-w-launcher  (WidgetLauncher)
 *   .gratis-ai-agent-floating-panel → .gaa-w-panel  (WidgetPanel)
 *   .gratis-ai-agent-chat-panel  → .gaa-w-body-wrap (panel body area)
 *   .gratis-ai-agent-input       → .gaa-w-input-textarea
 *   .gratis-ai-agent-send-btn    → .gaa-cr-send-btn
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

		// Panel should not be visible initially (ChatWidget renders launcher OR
		// panel, never both — so the panel element is absent from the DOM).
		await expect( panel ).not.toBeVisible();

		await fab.click();

		await expect( panel ).toBeVisible();
	} );

	test( 'floating panel contains the chat UI', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		// The redesigned widget panel body is .gaa-w-body-wrap (WidgetPanel,
		// widget-panel.js). It contains WidgetEmpty or WidgetMessageList.
		const bodyWrap = panel.locator( '.gaa-w-body-wrap' );
		await expect( bodyWrap ).toBeVisible();

		// The input area is always present when the panel is open.
		// .gaa-w-input-textarea is the textarea inside WidgetInput.
		const input = panel.locator( '.gaa-w-input-textarea' );
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

		// Launcher should reappear after closing.
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

		// Body wrap is conditionally rendered: { !isMinimized && <div.gaa-w-body-wrap> }
		// so when minimized it is removed from the DOM entirely.
		const bodyWrap = panel.locator( '.gaa-w-body-wrap' );
		await expect( bodyWrap ).not.toBeVisible();
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

		// Body wrap should be visible again after expanding.
		const bodyWrap = panel.locator( '.gaa-w-body-wrap' );
		await expect( bodyWrap ).toBeVisible();
	} );

	// All tests below scope locators to the floating panel to avoid
	// strict-mode violations when screen-meta also renders a chat panel.

	test( 'message input accepts text', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		// .gaa-w-input-textarea is the textarea in WidgetInput (widget-input.js).
		const input = panel.locator( '.gaa-w-input-textarea' );
		await input.fill( 'Hello, AI Agent!' );

		await expect( input ).toHaveValue( 'Hello, AI Agent!' );
	} );

	test( 'send button is enabled when input has text', async ( { page } ) => {
		const fab = getFloatingButton( page );
		await fab.click();

		const panel = getFloatingPanel( page );
		// .gaa-w-input-textarea is the textarea in WidgetInput.
		const input = panel.locator( '.gaa-w-input-textarea' );
		// .gaa-cr-send-btn is the send button in WidgetInput (widget-input.js).
		const sendButton = panel.locator( '.gaa-cr-send-btn' );

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
		// .gaa-w-input-textarea is the textarea in WidgetInput.
		const input = panel.locator( '.gaa-w-input-textarea' );
		await input.fill( 'Test message via Enter' );
		await input.press( 'Enter' );

		// Input should be cleared after submission.
		await expect( input ).toHaveValue( '' );
	} );
} );
