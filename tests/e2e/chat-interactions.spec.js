/**
 * E2E tests for core chat UI interactions.
 *
 * Tests message input, slash commands, and UI state transitions
 * that do not require a live AI provider response.
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
} = require( './utils/wp-admin' );

/**
 * Intercept the agent-run SSE endpoint and inject a `generated_title` into
 * the done event so the auto-title optimistic-update path fires without a
 * live AI provider.
 *
 * The store reads `doneMetadata.generated_title` from the final SSE `done`
 * event and calls `dispatch.updateSessionTitle()` to update the sidebar
 * immediately (see store/index.js lines ~1438-1443 and ~1769-1772).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          generatedTitle - Title to inject.
 */
async function interceptWithGeneratedTitle( page, generatedTitle ) {
	// The store POSTs to {wpApiSettings.root}gratis-ai-agent/v1/stream.
	// In wp-env this resolves to http://localhost:8888/wp-json/gratis-ai-agent/v1/stream.
	// The session_id is in the POST body JSON (not the URL), so we parse it from
	// the request body to inject it into the synthetic done event.
	await page.route(
		/gratis-ai-agent\/v1\/stream/,
		async ( route ) => {
			// Extract session_id from the POST body so the store's
			// updateSessionTitle() call targets the correct session.
			let sessionId = 1;
			try {
				const postBody = route.request().postDataJSON();
				if ( postBody?.session_id ) {
					sessionId = postBody.session_id;
				}
			} catch {
				// postDataJSON() throws if body is not valid JSON; fall back to 1.
			}

			// Minimal SSE stream: one token chunk + done with generated_title.
			// The store dispatches on `eventName` parsed from the `event:` line,
			// not from a `type` field inside the JSON payload.
			// Each SSE message is separated by a blank line (\n\n).
			const sseBody = [
				'event: token',
				`data: ${ JSON.stringify( {
					token: 'Hello! How can I help you?',
				} ) }`,
				'',
				'event: done',
				`data: ${ JSON.stringify( {
					session_id: sessionId,
					generated_title: generatedTitle,
				} ) }`,
				'',
				'',
			].join( '\n' );

			await route.fulfill( {
				status: 200,
				headers: {
					'Content-Type': 'text/event-stream',
					'Cache-Control': 'no-cache',
				},
				body: sseBody,
			} );
		}
	);
}

test.describe( 'Chat Input Interactions', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'textarea auto-resizes as text grows', async ( { page } ) => {
		const input = getMessageInput( page );
		const initialHeight = await input.evaluate( ( el ) => el.offsetHeight );

		// Type multiple lines.
		await input.fill( 'Line 1\nLine 2\nLine 3\nLine 4\nLine 5' );

		const newHeight = await input.evaluate( ( el ) => el.offsetHeight );
		expect( newHeight ).toBeGreaterThan( initialHeight );
	} );

	test( 'Shift+Enter inserts a newline instead of sending', async ( {
		page,
	} ) => {
		const input = getMessageInput( page );
		await input.fill( 'First line' );
		await input.press( 'Shift+Enter' );
		await input.type( 'Second line' );

		const value = await input.inputValue();
		expect( value ).toContain( '\n' );
	} );

	test( 'Enter key sends the message', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( 'Test message' );
		await input.press( 'Enter' );

		// Input should be cleared after send.
		await expect( input ).toHaveValue( '' );
	} );

	test( 'send button click sends the message', async ( { page } ) => {
		const input = getMessageInput( page );
		const sendButton = getSendButton( page );

		await input.fill( 'Test via button' );
		await sendButton.click();

		await expect( input ).toHaveValue( '' );
	} );

	test( 'stop button appears while sending', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( 'Trigger a response' );
		await input.press( 'Enter' );

		// Stop button should appear while the request is in flight.
		const stopButton = getStopButton( page );
		await expect( stopButton ).toBeVisible( { timeout: 5_000 } );
	} );
} );

test.describe( 'Slash Command Menu', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'slash menu appears when typing /', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();
	} );

	test( 'slash menu shows expected commands', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Core commands should be listed.
		await expect( slashMenu ).toContainText( '/new' );
		await expect( slashMenu ).toContainText( '/remember' );
		await expect( slashMenu ).toContainText( '/forget' );
		await expect( slashMenu ).toContainText( '/clear' );
		await expect( slashMenu ).toContainText( '/help' );
	} );

	test( 'slash menu filters as user types', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/rem' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Only /remember should match /rem.
		const items = page.locator( '.ai-agent-slash-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );

		await expect( slashMenu ).toContainText( '/remember' );
	} );

	test( 'slash menu closes when Escape is pressed', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		await input.press( 'Escape' );
		await expect( slashMenu ).not.toBeVisible();
	} );

	test( 'selecting /new from slash menu clears the session', async ( {
		page,
	} ) => {
		const input = getMessageInput( page );
		await input.fill( '/new' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Click the /new item.
		const newItem = page.locator( '.ai-agent-slash-item' ).filter( {
			hasText: '/new',
		} );
		await newItem.click();

		// Empty state should be visible.
		const emptyState = page.locator( '.ai-agent-empty-state' );
		await expect( emptyState ).toBeVisible();
	} );

	test( '/help slash command opens shortcuts dialog', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/help' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		const helpItem = page.locator( '.ai-agent-slash-item' ).filter( {
			hasText: '/help',
		} );
		await helpItem.click();

		// Shortcuts dialog should open.
		const shortcutsDialog = page.locator( '.ai-agent-shortcuts-overlay' );
		await expect( shortcutsDialog ).toBeVisible();
	} );
} );

test.describe( 'Provider Selector', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'provider selector is visible in the chat header', async ( {
		page,
	} ) => {
		const providerSelector = page.locator( '.ai-agent-provider-selector' );
		await expect( providerSelector ).toBeVisible();
	} );
} );

/**
 * Auto-title sessions (t099)
 *
 * After the first AI response the store reads `generated_title` from the SSE
 * done event and calls `updateSessionTitle()` to optimistically update the
 * sidebar item without a full fetchSessions round-trip.
 *
 * These tests intercept the run endpoint to inject a synthetic done event so
 * the auto-title path fires in CI without a live AI provider.
 */
test.describe( 'Auto-Title Sessions (t099)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'session title updates in sidebar after first AI response', async ( {
		page,
	} ) => {
		const expectedTitle = 'My Auto-Generated Title';

		// Intercept the run endpoint before sending the message.
		await interceptWithGeneratedTitle( page, expectedTitle );

		const input = getMessageInput( page );
		await input.fill( 'Tell me about WordPress' );
		await input.press( 'Enter' );

		// Wait for the session item to appear in the sidebar.
		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toBeVisible( { timeout: 10_000 } );

		// The sidebar item should display the generated title (not "Untitled").
		await expect( sessionItems.first() ).toContainText( expectedTitle, {
			timeout: 10_000,
		} );
	} );

	test( 'session title is not "Untitled" after auto-title fires', async ( {
		page,
	} ) => {
		const expectedTitle = 'WordPress Plugin Development';

		await interceptWithGeneratedTitle( page, expectedTitle );

		const input = getMessageInput( page );
		await input.fill( 'How do I build a WordPress plugin?' );
		await input.press( 'Enter' );

		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toBeVisible( { timeout: 10_000 } );

		// The title element inside the session item should not say "Untitled".
		const titleEl = sessionItems
			.first()
			.locator( '.ai-agent-session-title' );
		await expect( titleEl ).not.toContainText( 'Untitled', {
			timeout: 10_000,
		} );
		await expect( titleEl ).toContainText( expectedTitle, {
			timeout: 10_000,
		} );
	} );

	test( 'new session starts as Untitled before any AI response', async ( {
		page,
	} ) => {
		// Do NOT intercept — just send a message and check the initial state
		// before any done event arrives.
		const input = getMessageInput( page );
		await input.fill( 'Hello' );
		await input.press( 'Enter' );

		// The stop button confirms the session was created and the request is
		// in flight. At this point no done event has arrived yet.
		const stopButton = getStopButton( page );
		await expect( stopButton ).toBeVisible( { timeout: 10_000 } );

		// The sidebar item should exist but show "Untitled" (no title yet).
		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toBeVisible( { timeout: 10_000 } );

		const titleEl = sessionItems
			.first()
			.locator( '.ai-agent-session-title' );
		// Title is either empty or "Untitled" before the done event.
		const titleText = await titleEl.textContent();
		expect(
			titleText === '' ||
				titleText === 'Untitled' ||
				titleText?.includes( 'Untitled' )
		).toBe( true );
	} );
} );
