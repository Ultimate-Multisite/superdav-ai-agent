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
 * How the agent job flow works (current implementation):
 *   1. The store POSTs to gratis-ai-agent/v1/run with the message and session_id.
 *      The server enqueues the agent job and returns { job_id }.
 *   2. The store polls gratis-ai-agent/v1/job/{job_id} every 3 s until the job
 *      status is 'complete', 'error', or 'awaiting_confirmation'.
 *   3. On 'complete' it reloads the session from the DB, optionally records
 *      generated_title via updateSessionTitle(), and calls fetchSessions() to
 *      refresh the sidebar. The SET_SESSIONS reducer merges pendingTitles into
 *      the incoming list so the generated title survives any fetchSessions()
 *      round-trip.
 *
 * When `options.generatedTitle` is provided the job-complete payload includes
 * `generated_title`, exercising the full job-result handling path in the store.
 *
 * No sessions stub is needed: the store's pendingTitles mechanism preserves the
 * optimistic title through any fetchSessions() round-trip, so the real server
 * response (which carries "Untitled") does not overwrite the generated title.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object} [options]
 * @param {string} [options.generatedTitle] - When set, included as
 *   `generated_title` in the job-complete payload so the store's job-result
 *   parsing path is exercised end-to-end.
 * @return {Promise<void>}
 */
async function interceptStream( page, options = {} ) {
	const { generatedTitle } = options;

	// Track the session_id from the /run POST body so the job-complete payload
	// carries the correct session ID. The store creates the session first via
	// POST /sessions and passes the id in the /run body.
	let capturedSessionId = null;

	// Intercept POST /run — the store sends the message here and receives a job_id.
	// Capture session_id from the request body; return a synthetic job_id.
	// Use a predicate function instead of a regex because wp-env uses plain
	// permalinks (?rest_route=%2F...) where slashes are URL-encoded — a regex
	// against the raw URL would never match.
	await page.route(
		( url ) => decodeURIComponent( url.toString() ).includes( 'gratis-ai-agent/v1/run' ),
		async ( route ) => {
		try {
			const postBody = route.request().postDataJSON();
			if ( postBody?.session_id ) {
				capturedSessionId = postBody.session_id;
			}
		} catch {
			// Ignore parse failures — capturedSessionId stays null.
		}

		await route.fulfill( {
			status: 202,
			contentType: 'application/json',
			body: JSON.stringify( {
				job_id: 'e2e-test-job-1',
				status: 'processing',
			} ),
		} );
	} );

	// Intercept GET /job/:id — the store polls here every 3 s until 'complete'.
	// Return 'complete' immediately so tests don't wait for the poll interval.
	// capturedSessionId is guaranteed set before the first poll: the browser
	// sends POST /run synchronously before fetching GET /job/:id.
	await page.route(
		( url ) => decodeURIComponent( url.toString() ).includes( 'gratis-ai-agent/v1/job/' ),
		async ( route ) => {
		const result = {
			status: 'complete',
			session_id: capturedSessionId,
			reply: 'Hello from the AI!',
			...( generatedTitle ? { generated_title: generatedTitle } : {} ),
		};

		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( result ),
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
		// Intercept the stream endpoint BEFORE sending so the request stays
		// in-flight long enough for the stop button to be visible. Without this
		// mock the backend returns an error immediately (no AI provider in CI),
		// setting sending=false before the 5 s assertion window.
		await interceptStream( page );

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

		const slashMenu = page.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();
	} );

	test( 'slash menu shows expected commands', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.gratis-ai-agent-slash-menu' );
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

		const slashMenu = page.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Only /remember should match /rem.
		const items = page.locator( '.gratis-ai-agent-slash-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );

		await expect( slashMenu ).toContainText( '/remember' );
	} );

	test( 'slash menu closes when Escape is pressed', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		await input.press( 'Escape' );
		await expect( slashMenu ).not.toBeVisible();
	} );

	test( 'selecting /new from slash menu clears the session', async ( {
		page,
	} ) => {
		const input = getMessageInput( page );
		await input.fill( '/new' );

		const slashMenu = page.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Click the /new item.
		const newItem = page.locator( '.gratis-ai-agent-slash-item' ).filter( {
			hasText: '/new',
		} );
		await newItem.click();

		// Empty state should be visible. Scope to the non-compact chat panel to
		// avoid matching the floating widget's hidden empty state element.
		const emptyState = page.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-empty-state'
		);
		await expect( emptyState ).toBeVisible();
	} );

	test( '/help slash command opens shortcuts dialog', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/help' );

		const slashMenu = page.locator( '.gratis-ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		const helpItem = page.locator( '.gratis-ai-agent-slash-item' ).filter( {
			hasText: '/help',
		} );
		await helpItem.click();

		// Shortcuts dialog should open.
		const shortcutsDialog = page.locator( '.gratis-ai-agent-shortcuts-overlay' );
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
		// Scope to the non-compact (admin page) chat panel to avoid matching
		// the floating widget's hidden provider selector (is-compact).
		const providerSelector = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-provider-selector'
			)
			.first();
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
		const activeItem = page.locator( '.gratis-ai-agent-session-item.is-active' );
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
		const activeItem = page.locator( '.gratis-ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 10_000 } );

		// The title element inside the active session item should not say "Untitled".
		// The title arrives via the SSE done event, not a direct store dispatch.
		const titleEl = activeItem.locator( '.gratis-ai-agent-session-title' );
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
		const activeItem = page.locator( '.gratis-ai-agent-session-item.is-active' );
		await expect( activeItem ).toBeVisible( { timeout: 15_000 } );

		// The intercepted done event carries no generated_title, so the title
		// should still be "Untitled" (or empty) at this point.
		const titleEl = activeItem.locator( '.gratis-ai-agent-session-title' );
		const titleText = await titleEl.textContent();
		// Title is either empty or "Untitled" — no auto-title was injected.
		expect(
			titleText === '' ||
				titleText === 'Untitled' ||
				titleText?.includes( 'Untitled' )
		).toBe( true );
	} );
} );
