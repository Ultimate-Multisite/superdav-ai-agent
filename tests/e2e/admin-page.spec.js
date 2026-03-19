/**
 * E2E tests for the Gratis AI Agent full admin page.
 *
 * Tests the two-column layout with session sidebar and chat panel
 * at /wp-admin/tools.php?page=gratis-ai-agent.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAgentPage,
	getMessageInput,
	getSendButton,
	getStopButton,
	getChatPanel,
	getMessageList,
	waitForMessageSubmitted,
} = require( './utils/wp-admin' );

test.describe( 'Admin Page - Chat UI', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'admin page loads with correct layout', async ( { page } ) => {
		// Two-column layout.
		await expect( page.locator( '.gratis-ai-agent-layout' ) ).toBeVisible();
		await expect( page.locator( '.ai-agent-sidebar' ) ).toBeVisible();
		await expect( page.locator( '.gratis-ai-agent-main' ) ).toBeVisible();
	} );

	test( 'chat panel is visible on admin page', async ( { page } ) => {
		const chatPanel = getChatPanel( page );
		await expect( chatPanel ).toBeVisible();
	} );

	test( 'message input is present and focusable', async ( { page } ) => {
		const input = getMessageInput( page );
		await expect( input ).toBeVisible();
		await input.focus();
		await expect( input ).toBeFocused();
	} );

	test( 'message list container is present', async ( { page } ) => {
		const messageList = getMessageList( page );
		await expect( messageList ).toBeVisible();
	} );

	test( 'empty state is shown when no messages', async ( { page } ) => {
		const emptyState = page.locator( '.ai-agent-empty-state' );
		await expect( emptyState ).toBeVisible();
	} );

	test( 'send button is disabled when input is empty', async ( { page } ) => {
		const sendButton = getSendButton( page );
		await expect( sendButton ).toBeDisabled();
	} );

	test( 'send button enables when input has text', async ( { page } ) => {
		const input = getMessageInput( page );
		const sendButton = getSendButton( page );

		await input.fill( 'Hello' );
		await expect( sendButton ).toBeEnabled();
	} );

	test( 'clearing input disables send button', async ( { page } ) => {
		const input = getMessageInput( page );
		const sendButton = getSendButton( page );

		await input.fill( 'Hello' );
		await expect( sendButton ).toBeEnabled();

		await input.fill( '' );
		await expect( sendButton ).toBeDisabled();
	} );

	test( 'message input placeholder text is correct', async ( { page } ) => {
		const input = getMessageInput( page );
		await expect( input ).toHaveAttribute(
			'placeholder',
			'Type a message or / for commands…'
		);
	} );

	test( 'sidebar has new chat button', async ( { page } ) => {
		const newChatButton = page.locator( '.ai-agent-new-chat-btn' );
		await expect( newChatButton ).toBeVisible();
	} );

	test( 'sidebar has session search input', async ( { page } ) => {
		const searchInput = page.locator( '.gratis-ai-agent-sidebar-search' );
		await expect( searchInput ).toBeVisible();
	} );
} );

test.describe( 'Admin Page - Session Management', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'new chat button clears the current session', async ( { page } ) => {
		// Type a message to create a session context.
		const input = getMessageInput( page );
		await input.fill( 'Test message' );
		await input.press( 'Enter' );

		// Wait for the user message row to appear — this confirms the message
		// was submitted and appended to the chat. The message row is added
		// synchronously before any async REST calls, so it is a stable signal
		// on all WP versions (including trunk where the stop button may
		// disappear quickly if the backend returns an error fast).
		await waitForMessageSubmitted( page );

		// Click new chat.
		const newChatButton = page.locator( '.ai-agent-new-chat-btn' );
		await newChatButton.click();

		// Empty state should reappear.
		const emptyState = page.locator( '.ai-agent-empty-state' );
		await expect( emptyState ).toBeVisible( { timeout: 5_000 } );
	} );

	test( 'session list shows sessions after a message is sent', async ( {
		page,
	} ) => {
		const input = getMessageInput( page );
		await input.fill( 'Create a session' );
		await input.press( 'Enter' );

		// Wait for the user message row to appear — this confirms the message
		// was submitted. The session is created via POST /sessions before the
		// background job is spawned, and the session list is refreshed after
		// session creation. Using the message row (appended synchronously) is
		// more reliable than the stop button, which may disappear quickly on
		// WP trunk if the backend returns an error response fast.
		await waitForMessageSubmitted( page );

		// At least one session item should appear in the sidebar.
		// Use toBeVisible() on the first item rather than toHaveCount(1) because
		// prior tests in the same run may have created sessions that persist in
		// the wp-env database across tests.
		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toBeVisible( { timeout: 10_000 } );
	} );
} );

test.describe( 'Admin Page - Keyboard Shortcuts', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'Ctrl+N / Cmd+N starts a new chat', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( 'Some text' );
		await input.press( 'Enter' );

		// Wait for the user message row to appear — this confirms the message
		// was submitted. The message row is appended synchronously before any
		// async REST calls, making it a stable signal on all WP versions. The
		// stop button is transient and may disappear quickly on WP trunk if the
		// backend returns an error response fast (no AI provider in CI).
		await waitForMessageSubmitted( page );

		// Trigger new chat shortcut.
		await page.keyboard.press( 'ControlOrMeta+n' );

		const emptyState = page.locator( '.ai-agent-empty-state' );
		await expect( emptyState ).toBeVisible( { timeout: 5_000 } );
	} );

	test( 'Ctrl+K / Cmd+K focuses the sidebar search', async ( { page } ) => {
		await page.keyboard.press( 'ControlOrMeta+k' );

		const searchInput = page.locator( '.gratis-ai-agent-sidebar-search' );
		await expect( searchInput ).toBeFocused();
	} );
} );
