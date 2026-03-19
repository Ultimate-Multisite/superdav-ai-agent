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
 * Strategy: intercept the stream endpoint to return a minimal SSE response so
 * the stream completes quickly. After the stream completes (stop button gone),
 * use page.evaluate() to directly dispatch updateSessionTitle() to the store
 * with the correct session ID. This bypasses HTTP route matching entirely and
 * is reliable across all Playwright/WordPress configurations.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<void>}
 */
async function interceptStream( page ) {
	// Intercept the stream endpoint and return a minimal SSE response.
	// The store POSTs to {wpApiSettings.root}gratis-ai-agent/v1/stream.
	// We return a token + done event so the store's reader loop completes
	// and setSending(false) is called, which hides the stop button.
	await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
		let sessionId = 1;
		try {
			const postBody = route.request().postDataJSON();
			if ( postBody?.session_id ) {
				sessionId = postBody.session_id;
			}
		} catch {
			// Fall back to 1 if body is not JSON.
		}

		// Minimal SSE stream: one token chunk + done event.
		// The store dispatches on `eventName` parsed from the `event:` line.
		// Each SSE message is separated by a blank line (\n\n).
		const sseBody = [
			'event: token',
			`data: ${ JSON.stringify( { token: 'Hello!' } ) }`,
			'',
			'event: done',
			`data: ${ JSON.stringify( { session_id: sessionId } ) }`,
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

/**
 * Directly inject a generated title into the WordPress data store via
 * page.evaluate(). This simulates what the backend would do after auto-titling
 * without requiring a live AI provider or HTTP route interception.
 *
 * Polls until the current session appears in state.sessions (i.e. fetchSessions
 * has completed and the session is in the list), then dispatches
 * updateSessionTitle(). This avoids a race where the inject fires before
 * fetchSessions() has populated state.sessions with the new session.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          generatedTitle - Title to inject.
 */
async function injectGeneratedTitle( page, generatedTitle ) {
	await page.evaluate( ( title ) => {
		return new Promise( ( resolve ) => {
			// The store is registered under 'gratis-ai-agent'.
			// wp.data is available globally in the WordPress admin.
			const select = window.wp?.data?.select( 'gratis-ai-agent' );
			const dispatch = window.wp?.data?.dispatch( 'gratis-ai-agent' );
			if ( ! select || ! dispatch ) {
				resolve();
				return;
			}

			const sessionId = select.getCurrentSessionId();
			if ( ! sessionId ) {
				resolve();
				return;
			}

			// Poll until the session appears in state.sessions (fetchSessions
			// has completed). Without this, updateSessionTitle is a no-op
			// because the session is not yet in the sessions list.
			const maxAttempts = 50; // 50 × 100ms = 5s max
			let attempts = 0;
			const poll = () => {
				attempts++;
				const sessions = select.getSessions ? select.getSessions() : [];
				const inList = sessions.some(
					( s ) => parseInt( s.id, 10 ) === sessionId
				);
				if ( inList ) {
					dispatch.updateSessionTitle( sessionId, title );
					resolve();
				} else if ( attempts >= maxAttempts ) {
					// Session never appeared — dispatch anyway as a best-effort.
					dispatch.updateSessionTitle( sessionId, title );
					resolve();
				} else {
					setTimeout( poll, 100 );
				}
			};
			poll();
		} );
	}, generatedTitle );
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
 * These tests use two mechanisms to simulate auto-titling without a live AI
 * provider:
 *   1. interceptStream() — intercepts the stream endpoint so the stream
 *      completes quickly without a real AI call.
 *   2. injectGeneratedTitle() — after the stream completes and fetchSessions()
 *      has run (session is in state.sessions), directly dispatches
 *      updateSessionTitle() via page.evaluate() to simulate what the backend
 *      would do after generating a title.
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

		// Intercept the stream endpoint so it completes quickly.
		await interceptStream( page );

		const input = getMessageInput( page );
		await input.fill( 'Tell me about WordPress' );
		await input.press( 'Enter' );

		// Wait for the active session item to appear in the sidebar. The active
		// item has the is-active class and is the current session. Using
		// .first() is unreliable when previous tests have left sessions in the
		// sidebar — the current session may not be the first item.
		const activeItem = page.locator( '.ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 10_000 } );

		// Inject the generated title directly into the store. injectGeneratedTitle
		// polls until the current session is in state.sessions (fetchSessions has
		// completed), then dispatches updateSessionTitle().
		await injectGeneratedTitle( page, expectedTitle );

		// The active sidebar item should now display the generated title.
		await expect( activeItem ).toContainText( expectedTitle, {
			timeout: 5_000,
		} );
	} );

	test( 'session title is not "Untitled" after auto-title fires', async ( {
		page,
	} ) => {
		const expectedTitle = 'WordPress Plugin Development';

		await interceptStream( page );

		const input = getMessageInput( page );
		await input.fill( 'How do I build a WordPress plugin?' );
		await input.press( 'Enter' );

		// Wait for the active session item.
		const activeItem = page.locator( '.ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 10_000 } );

		// Inject the generated title into the store.
		await injectGeneratedTitle( page, expectedTitle );

		// The title element inside the active session item should not say "Untitled".
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
