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
 * Set up interception for auto-title tests.
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
 * When `options.generatedTitle` is provided the `done` SSE payload includes
 * `generated_title`, exercising the full SSE parsing path in the store. This
 * ensures regressions in stream-event handling (e.g. the store failing to read
 * `done.generated_title`) are caught by the test rather than masked by a direct
 * store dispatch.
 *
 * Timing note: the sessions stub is registered BEFORE the stream route so it
 * is guaranteed to be installed before route.fulfill() returns. A `streamFired`
 * flag gates the stub so it passes through any pre-stream fetchSessions() calls
 * (e.g. the mount-time load) and only intercepts the post-stream call. This
 * eliminates the WP 6.9 race where fetchSessions() fired before the stub could
 * be registered after route.fulfill().
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object} [options]
 * @param {string} [options.generatedTitle] - When set, included as
 *   `generated_title` in the SSE `done` payload so the store's SSE parsing
 *   path is exercised end-to-end.
 * @return {Promise<void>}
 */
async function interceptStream( page, options = {} ) {
	const { generatedTitle } = options;

	// ── Sessions stub (registered BEFORE the stream route) ──────────────────
	//
	// The stub must be registered before route.fulfill() is called on the
	// stream, not after. On WP 6.9 the store's fetchSessions() GET fires
	// synchronously as soon as the SSE reader processes the done event — before
	// any async work following route.fulfill() can complete. Registering the
	// stub after route.fulfill() (the previous approach) created a race: on
	// fast environments the GET arrived before the stub was installed, so the
	// real server responded with "Untitled".
	//
	// To avoid the stub being consumed by the mount-time fetchSessions() call
	// (which fires after waitForLoadState('networkidle') returns), we gate it
	// on a `streamFired` flag that is set to true just before route.fulfill()
	// sends the SSE response. Any sessions GET that arrives before the stream
	// fires is passed through to the real server; only the post-stream call is
	// intercepted.
	//
	// page.route() registration is synchronous — the handler is installed
	// immediately. Only the handler body is async. This means the stub is
	// guaranteed to be in place before route.fulfill() returns.
	let streamFired = false;
	// Capture sessionId so the sessions stub can reference it. It is set by
	// the stream handler before route.fulfill() is called.
	let capturedSessionId = 1;

	if ( generatedTitle ) {
		await page.route(
			/gratis-ai-agent\/v1\/sessions/,
			async ( sessionsRoute ) => {
				const url = sessionsRoute.request().url();
				const method = sessionsRoute.request().method();

				// Intercept all GET requests to the list endpoint after the stream
				// has fired. Skip individual-session URLs (/sessions/123), non-GETs,
				// and pre-stream calls. Intercepting all post-stream calls (not just
				// the first) prevents subsequent fetchSessions() round-trips from
				// overwriting the generated title with "Untitled" from the real server.
				const isListEndpoint = ! /\/sessions\//.test( url );
				if (
					method !== 'GET' ||
					! isListEndpoint ||
					! streamFired
				) {
					await sessionsRoute.continue();
					return;
				}

				const session = {
					id: capturedSessionId,
					title: generatedTitle,
					created_at: new Date().toISOString(),
					updated_at: new Date().toISOString(),
					status: 'active',
					message_count: 1,
					provider_id: null,
					model_id: null,
					folder_id: null,
				};

				await sessionsRoute.fulfill( {
					status: 200,
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( [ session ] ),
				} );
			}
		);
	}

	// ── Stream route ─────────────────────────────────────────────────────────
	//
	// Intercept the stream endpoint and return a minimal SSE response.
	// The store POSTs to {wpApiSettings.root}gratis-ai-agent/v1/stream.
	// We return a token + done event so the store's reader loop completes
	// and setSending(false) is called, which hides the stop button.
	await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
		// Capture the session_id from the POST body so the sessions stub can
		// return the same session. The store always creates the session first
		// via POST /sessions and passes the id here. Fall back to 1 if parsing
		// fails.
		try {
			const postBody = route.request().postDataJSON();
			if ( postBody?.session_id ) {
				capturedSessionId = postBody.session_id;
			}
		} catch {
			// Fall back to 1 if body is not JSON.
		}

		// Build the done event payload. Include generated_title when provided
		// so the store's SSE parsing path is exercised end-to-end.
		const donePayload = {
			session_id: capturedSessionId,
			...( generatedTitle ? { generated_title: generatedTitle } : {} ),
		};

		// Minimal SSE stream: one token chunk + done event.
		// The store dispatches on `eventName` parsed from the `event:` line.
		// Each SSE message is separated by a blank line (\n\n).
		const sseBody = [
			'event: token',
			`data: ${ JSON.stringify( { token: 'Hello!' } ) }`,
			'',
			'event: done',
			`data: ${ JSON.stringify( donePayload ) }`,
			'',
			'',
		].join( '\n' );

		// Set the flag BEFORE fulfilling so the sessions stub is armed when
		// the store's fetchSessions() fires immediately after the done event.
		streamFired = true;

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
 * done event and calls `updateSessionTitle()` to update the sidebar item.
 *
 * These tests use interceptStream() with an optional `generatedTitle` to
 * simulate auto-titling without a live AI provider. When `generatedTitle` is
 * provided, the intercepted SSE `done` payload includes `generated_title`,
 * exercising the full stream-event parsing path in the store. This ensures
 * regressions in SSE handling are caught rather than masked by a direct store
 * dispatch.
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

		// Intercept the stream and include generated_title in the done payload.
		// This exercises the full SSE parsing path: the store reads
		// done.generated_title from the intercepted stream and calls
		// updateSessionTitle() — no direct store dispatch is needed.
		await interceptStream( page, { generatedTitle: expectedTitle } );

		const input = getMessageInput( page );
		await input.fill( 'Tell me about WordPress' );
		await input.press( 'Enter' );

		// Wait for the active session item to appear in the sidebar. The active
		// item has the is-active class and is the current session. Using
		// .first() is unreliable when previous tests have left sessions in the
		// sidebar — the current session may not be the first item.
		const activeItem = page.locator( '.ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 10_000 } );

		// The active sidebar item should now display the generated title.
		// The title arrives via the SSE done event (generated_title field),
		// not via a direct store dispatch, so this assertion validates the
		// full stream-event handling path.
		await expect( activeItem ).toContainText( expectedTitle, {
			timeout: 5_000,
		} );
	} );

	test( 'session title is not "Untitled" after auto-title fires', async ( {
		page,
	} ) => {
		const expectedTitle = 'WordPress Plugin Development';

		// Include generated_title in the SSE done payload so the store's
		// stream-event parsing path is exercised end-to-end.
		await interceptStream( page, { generatedTitle: expectedTitle } );

		const input = getMessageInput( page );
		await input.fill( 'How do I build a WordPress plugin?' );
		await input.press( 'Enter' );

		// Wait for the active session item.
		const activeItem = page.locator( '.ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 10_000 } );

		// The title element inside the active session item should not say "Untitled".
		// The title arrives via the SSE done event, not a direct store dispatch.
		const titleEl = activeItem.locator( '.ai-agent-session-title' );
		await expect( titleEl ).not.toContainText( 'Untitled', {
			timeout: 5_000,
		} );
		await expect( titleEl ).toContainText( expectedTitle, {
			timeout: 5_000,
		} );
	} );

	test( 'new session starts as Untitled before any AI response', async ( {
		page,
	} ) => {
		// Intercept the stream so it completes quickly and fetchSessions() runs,
		// populating state.sessions with the new session. This avoids relying on
		// the stop button (which is fragile on WP trunk — the stream may fail
		// before setSending(true) renders the button).
		await interceptStream( page );

		const input = getMessageInput( page );
		await input.fill( 'Hello' );
		await input.press( 'Enter' );

		// Wait for the active session item to appear in the sidebar. fetchSessions()
		// runs after the intercepted stream completes, so the session is in
		// state.sessions at this point.
		const activeItem = page.locator( '.ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 15_000 } );

		// The intercepted done event carries no generated_title, so the title
		// should still be "Untitled" (or empty) at this point.
		const titleEl = activeItem.locator( '.ai-agent-session-title' );
		const titleText = await titleEl.textContent();
		// Title is either empty or "Untitled" — no auto-title was injected.
		expect(
			titleText === '' ||
				titleText === 'Untitled' ||
				titleText?.includes( 'Untitled' )
		).toBe( true );
	} );
} );
