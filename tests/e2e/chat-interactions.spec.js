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
 * Intercept the stream endpoint and the sessions list endpoint to simulate
 * auto-title behaviour without a live AI provider.
 *
 * How the auto-title flow works:
 *   1. The store POSTs to gratis-ai-agent/v1/stream and reads the SSE response.
 *   2. On the `done` event it calls updateSessionTitle(sessionId, generatedTitle)
 *      — an optimistic update that patches the session in state.sessions.
 *   3. It then calls fetchSessions() which GETs gratis-ai-agent/v1/sessions and
 *      replaces state.sessions with the server response.
 *
 * For a brand-new session the optimistic update in step 2 is a no-op because
 * the session is not yet in state.sessions (it was only added to the store via
 * setCurrentSession, not via setSessions). The title therefore comes from step 3.
 *
 * We intercept both endpoints:
 *   - The stream endpoint returns a minimal SSE response with generated_title so
 *     the store's done-event handler fires correctly.
 *   - The sessions GET endpoint (called by fetchSessions after the stream) returns
 *     a single session whose title matches generatedTitle, so the sidebar renders
 *     the expected title after the round-trip.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          generatedTitle - Title to inject.
 */
async function interceptWithGeneratedTitle( page, generatedTitle ) {
	// Track the session ID created by POST /sessions so we can echo it back
	// in both the stream done event and the sessions list response.
	let capturedSessionId = 1;

	// Intercept POST /sessions to capture the real session ID, and intercept
	// GET /sessions (fetchSessions) to return the session with the generated title.
	//
	// URL matching: the sessions list endpoint is /gratis-ai-agent/v1/sessions
	// (with optional query params). Individual session endpoints are
	// /gratis-ai-agent/v1/sessions/123 — we must NOT intercept those.
	// The regex matches /sessions followed by end-of-path or a query string,
	// but not /sessions/ followed by more path segments.
	// Intercept POST /sessions to capture the real session ID, and intercept
	// GET /sessions (fetchSessions) to return the session with the generated title.
	//
	// Two separate routes are used:
	//   1. POST /sessions — pass through to the real backend and capture the ID.
	//   2. GET /sessions — return a fake response with the generated title.
	//
	// The glob '**/gratis-ai-agent/v1/sessions' matches the exact sessions list
	// URL without query params. A second glob handles the query-string variant.

	// Route 1: POST /sessions — pass through and capture session ID.
	await page.route(
		'**/gratis-ai-agent/v1/sessions',
		async ( route ) => {
			if ( route.request().method() !== 'POST' ) {
				// GET /sessions — return fake response with generated title.
				await route.fulfill( {
					status: 200,
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( [
						{
							id: capturedSessionId,
							title: generatedTitle,
							created_at: new Date().toISOString(),
							updated_at: new Date().toISOString(),
							message_count: 1,
						},
					] ),
				} );
				return;
			}
			// POST /sessions — pass through to the real backend.
			try {
				const response = await route.fetch();
				const body = await response.json();
				if ( body?.id ) {
					capturedSessionId = body.id;
				}
				await route.fulfill( { response } );
			} catch {
				// If fetch fails, continue the request normally.
				await route.continue();
			}
		}
	);

	// Intercept the stream endpoint and return a minimal SSE response with
	// generated_title so the store's done-event handler fires correctly.
	// The store POSTs to {wpApiSettings.root}gratis-ai-agent/v1/stream.
	await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
		// Extract session_id from the POST body so the done event carries the
		// correct session ID (matches capturedSessionId set above).
		let sessionId = capturedSessionId;
		try {
			const postBody = route.request().postDataJSON();
			if ( postBody?.session_id ) {
				sessionId = postBody.session_id;
			}
		} catch {
			// postDataJSON() throws if body is not valid JSON; fall back.
		}

		// Minimal SSE stream: one token chunk + done with generated_title.
		// The store dispatches on `eventName` parsed from the `event:` line.
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
	} );
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
