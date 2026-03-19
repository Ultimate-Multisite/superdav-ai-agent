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
	getChatPanel,
	getMessageList,
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

		// Wait for the user message bubble to appear in the message list.
		// This is synchronous — the store appends the user message to the UI
		// before any async REST calls, so it appears regardless of WP version
		// or API availability. We use this instead of waiting for the stop
		// button, which depends on sending=true persisting long enough to
		// render — unreliable on WP trunk where REST calls may resolve faster.
		const userBubble = page.locator( '.ai-agent-bubble.ai-agent-user' );
		await expect( userBubble.first() ).toBeVisible( { timeout: 5_000 } );

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

		// Wait for the user message bubble to appear — this is synchronous and
		// confirms the message was submitted to the UI. The store appends the
		// user message before any async REST calls, so this is reliable across
		// all WP versions. We use this instead of waiting for the stop button,
		// which depends on sending=true persisting long enough to render and is
		// unreliable on WP trunk where REST calls may resolve faster.
		const userBubble = page.locator( '.ai-agent-bubble.ai-agent-user' );
		await expect( userBubble.first() ).toBeVisible( { timeout: 5_000 } );

		// At least one session item should appear in the sidebar.
		// POST /sessions is called after the user message is appended, so the
		// session item may take a moment to appear. Use toBeVisible() on the
		// first item rather than toHaveCount(1) because prior tests in the same
		// run may have created sessions that persist in the wp-env database.
		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toBeVisible( { timeout: 15_000 } );
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

		// Wait for the user message bubble to appear — this is synchronous and
		// confirms the message was submitted to the UI. The store appends the
		// user message before any async REST calls, so this is reliable across
		// all WP versions. We use this instead of waiting for the stop button,
		// which depends on sending=true persisting long enough to render and is
		// unreliable on WP trunk where REST calls may resolve faster.
		const userBubble = page.locator( '.ai-agent-bubble.ai-agent-user' );
		await expect( userBubble.first() ).toBeVisible( { timeout: 5_000 } );

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
