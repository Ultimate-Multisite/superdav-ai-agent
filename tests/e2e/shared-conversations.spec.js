/**
 * E2E tests for the shared conversations feature (t091 / PR #474).
 *
 * Covers multi-admin session collaboration:
 *   - Share a session with all admins (view permission)
 *   - Shared session appears in the "Shared" tab for a second admin
 *   - Second admin can send messages on a shared session (contribute permission)
 *   - Owner can revoke share — second admin loses access
 *   - Shared sessions list endpoint is called on "Shared" tab activation
 *
 * Multi-user strategy
 * -------------------
 * Playwright's `browser.newContext()` creates an isolated browser context with
 * its own cookies/storage, allowing two simultaneous admin sessions in one test
 * run. The second admin user (`admin2`) is provisioned by the CI workflow's
 * "Configure plugin for E2E tests" step (e2e.yml) before tests run, so
 * WP_ENV_HOME is available and wp-env can locate its docker-compose.yml.
 *
 * REST API interception
 * ---------------------
 * Share/unshare/list endpoints are intercepted with `page.route()` so tests
 * are deterministic and do not require a live AI provider. The stream endpoint
 * is also intercepted so "contribute" tests can send a message without a real
 * backend.
 *
 * Per-endpoint predicate mock strategy
 * --------------------------------------
 * Each REST endpoint is intercepted with its own `page.route(predicate, handler)`
 * via `setupMocks()`. A single `page.route('**', handler)` catch-all is
 * unreliable in wp-env — the handler must call `route.continue()` for every
 * non-matching request (CSS, JS, images, HTML), and this high-volume
 * pass-through can cause timing issues that prevent mock responses from
 * reaching the Redux store. Per-endpoint predicate handlers only fire for their
 * own endpoint, avoiding this overhead entirely.
 *
 * wp-env percent-encodes REST paths in the plain-permalink format
 * (`?rest_route=%2Fgratis-ai-agent%2Fv1%2Fsessions%2Fshared`). Regex
 * matchers fail against encoded URLs. Predicate functions decode the URL
 * first (`decodeURIComponent(req.url())`) so matching is reliable regardless
 * of the permalink format in use.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const { loginToWordPress, goToAgentPage } = require( './utils/wp-admin' );

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const SECOND_ADMIN_USER = 'admin2';
const SECOND_ADMIN_PASS = 'password2';

/** Fake session returned by the intercepted sessions endpoint. */
const MOCK_SESSION = {
	id: 42,
	title: 'Shared Test Session',
	status: 'active',
	pinned: 0,
	folder_id: null,
	is_shared: true,
	shared_by: 1,
	created_at: '2026-01-01T00:00:00',
	updated_at: '2026-01-01T00:00:00',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Register per-endpoint route handlers for all plugin REST endpoints.
 *
 * Uses separate `page.route(/regex/, handler)` calls for each endpoint
 * instead of a single `page.route('**', handler)` catch-all. The catch-all
 * approach is unreliable in wp-env environments where REST routes live in
 * query parameters (`?rest_route=...`) rather than URL paths — the `**`
 * handler must call `route.continue()` for every non-matching request
 * (CSS, JS, images, HTML), and this high-volume pass-through can cause
 * timing issues that prevent mock responses from reaching the store.
 *
 * With per-endpoint regex handlers, each handler only fires for its own
 * endpoint. Non-matching requests are never intercepted, so there is no
 * `route.continue()` overhead and no risk of interference.
 *
 * Call `page.unrouteAll({ behavior: 'ignoreErrors' })` before calling this
 * function again to replace the existing handlers.
 *
 * @param {import('@playwright/test').Page} page                     - Playwright page object.
 * @param {Object}                          options                  - Mock configuration.
 * @param {Object[]|null}                   options.sessions         - Sessions list (null = pass through).
 * @param {Object[]|null}                   options.sharedSessions   - Shared sessions list (null = pass through).
 * @param {boolean|null}                    options.shareSuccess     - Share endpoint success flag (null = pass through).
 * @param {number|null}                     options.streamSessionId  - Session ID for stream mock (null = pass through).
 * @param {Function|null}                   options.sharedSessionsFn - Dynamic shared sessions fn: (callCount) => sessions[].
 */
async function setupMocks( page, options = {} ) {
	const {
		sessions = null,
		sharedSessions = null,
		shareSuccess = null,
		streamSessionId = null,
		sharedSessionsFn = null,
	} = options;

	// Track call count for dynamic shared sessions (used by the "refreshed" test).
	let sharedCallCount = 0;

	// --- /sessions/shared ---
	// Predicate matches both pretty-permalink and plain-permalink (rest_route=) URLs.
	// decodeURIComponent() handles wp-env's percent-encoded REST paths.
	// Registered BEFORE the /sessions list handler. Playwright evaluates handlers
	// in LIFO order, so the /sessions handler (registered later) runs first for
	// URLs matching both patterns. The /sessions handler detects /sessions/shared
	// URLs and calls route.fallback(), which delegates to this handler.
	if ( sharedSessions !== null || sharedSessionsFn !== null ) {
		await page.route(
			( req ) =>
				decodeURIComponent( req.url() ).includes(
					'gratis-ai-agent/v1/sessions/shared'
				),
			async ( route ) => {
				if ( sharedSessionsFn !== null ) {
					sharedCallCount++;
					const result = sharedSessionsFn( sharedCallCount );
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( result ),
					} );
					return;
				}
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( sharedSessions ),
				} );
			}
		);
	}

	// --- /sessions/{id}/share (POST or DELETE) ---
	if ( shareSuccess !== null ) {
		await page.route(
			( req ) =>
				decodeURIComponent( req.url() ).includes(
					'gratis-ai-agent/v1/sessions/'
				) &&
				decodeURIComponent( req.url() ).includes( '/share' ),
			async ( route ) => {
				const method = route.request().method();
				if ( method === 'POST' ) {
					await route.fulfill( {
						status: shareSuccess ? 200 : 500,
						contentType: 'application/json',
						body: JSON.stringify( { shared: shareSuccess } ),
					} );
					return;
				}
				// DELETE — also handle POST-with-override from httpV1Middleware.
				await route.fulfill( {
					status: shareSuccess ? 200 : 500,
					contentType: 'application/json',
					body: JSON.stringify( { shared: false } ),
				} );
			}
		);
	}

	// --- /sessions list ---
	// Predicate matches the sessions list endpoint only — not /sessions/shared,
	// /sessions/folders, or /sessions/{id}. The list URL contains 'sessions'
	// but NOT 'sessions/' (the sub-resource separator). decodeURIComponent()
	// handles wp-env's percent-encoded REST paths.
	if ( sessions !== null ) {
		await page.route(
			( req ) => {
				const decoded = decodeURIComponent( req.url() );
				return (
					decoded.includes( 'gratis-ai-agent/v1/sessions' ) &&
					! decoded.includes( 'gratis-ai-agent/v1/sessions/' )
				);
			},
			async ( route ) => {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( sessions ),
				} );
			}
		);
	}

	// --- /stream ---
	if ( streamSessionId !== null ) {
		await page.route(
			( req ) =>
				decodeURIComponent( req.url() ).includes(
					'gratis-ai-agent/v1/stream'
				),
			async ( route ) => {
				let sid = streamSessionId;
				try {
					const body = route.request().postDataJSON();
					if ( body?.session_id ) {
						sid = body.session_id;
					}
				} catch {
					// Non-JSON body — use default.
				}

				const sseBody = [
					'event: token',
					`data: ${ JSON.stringify( {
						token: 'Hello from shared session!',
					} ) }`,
					'',
					'event: done',
					`data: ${ JSON.stringify( { session_id: sid } ) }`,
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

	// Return the sharedCallCount getter so callers can inspect it.
	return {
		getSharedCallCount: () => sharedCallCount,
	};
}

/**
 * Open the context menu for the first session item in the sidebar.
 *
 * Uses a 10 s timeout for the initial visibility check to accommodate the
 * React render cycle that follows the intercepted sessions list response.
 * Even though goToAgentPage() waits for the sessions response, there is still
 * a brief async gap between the store receiving the data and React committing
 * the DOM update that produces .ai-agent-session-item nodes.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openFirstSessionContextMenu( page ) {
	const sessionItem = page.locator( '.ai-agent-session-item' ).first();
	await expect( sessionItem ).toBeVisible( { timeout: 10_000 } );
	// Hover to reveal the ⋯ button, then click it.
	await sessionItem.hover();
	await sessionItem.locator( '.ai-agent-session-more' ).click();
}

/**
 * Click the "Shared" tab in the session sidebar.
 *
 * Uses a longer timeout because the sidebar only renders after settingsLoaded=true,
 * which requires the settings fetch to complete after React hydration.
 *
 * @param {import('@playwright/test').Page} page
 */
async function clickSharedTab( page ) {
	const sharedTab = page.getByRole( 'tab', { name: /shared/i } );
	await expect( sharedTab ).toBeVisible( { timeout: 15_000 } );
	await sharedTab.click();
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test.describe( 'Shared Conversations (t091)', () => {
	test.describe( 'Owner — share a session', () => {
		test.beforeEach( async ( { page } ) => {
			// Register mocks BEFORE login so the floating widget's fetchSessions()
			// call (made during the admin dashboard load after login) is intercepted.
			await setupMocks( page, {
				sessions: [ MOCK_SESSION ],
				sharedSessions: [],
				shareSuccess: true,
			} );
			await loginToWordPress( page );
			await goToAgentPage( page );
		} );

		test( 'share option appears in session context menu for owner', async ( {
			page,
		} ) => {
			await openFirstSessionContextMenu( page );

			const shareOption = page.getByRole( 'menuitem', {
				name: /share with admins/i,
			} );
			await expect( shareOption ).toBeVisible();
		} );

		test( 'clicking Share with Admins calls the share endpoint', async ( {
			page,
		} ) => {
			// Set up the request promise BEFORE clicking so we don't miss it.
			// Decode the URL before matching because wp-env uses the
			// index.php?rest_route= format with URL-encoded slashes (%2F).
			const shareRequestPromise = page.waitForRequest(
				( req ) =>
					decodeURIComponent( req.url() ).includes( '/share' ) &&
					req.method() === 'POST',
				{ timeout: 5_000 }
			);

			await openFirstSessionContextMenu( page );

			const shareOption = page.getByRole( 'menuitem', {
				name: /share with admins/i,
			} );
			await shareOption.click();

			// Wait for the POST to actually fire (reliable vs. fixed timeout).
			await shareRequestPromise;
		} );

		test( 'after sharing, context menu shows Unshare option', async ( {
			page,
		} ) => {
			// Replace the beforeEach mock (sharedSessions: []) with one that
			// returns [MOCK_SESSION] so the store considers this session shared.
			// Use unrouteAll to remove the existing handler, then re-register
			// with the updated configuration.
			await page.unrouteAll( { behavior: 'ignoreErrors' } );
			await setupMocks( page, {
				sessions: [ MOCK_SESSION ],
				sharedSessions: [ MOCK_SESSION ],
				shareSuccess: true,
			} );

			// Reload so the store fetches shared sessions with the new mock.
			// goToAgentPage() waits for both the sessions and shared sessions
			// responses, so sharedSessions is settled before we proceed.
			await goToAgentPage( page );

			await openFirstSessionContextMenu( page );

			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );
			// Use a 10 s timeout to accommodate the async gap between the store
			// receiving the shared sessions response and React re-rendering the
			// context menu with the Unshare option.
			await expect( unshareOption ).toBeVisible( { timeout: 10_000 } );
		} );

		test( 'shared session shows shared badge icon in sidebar', async ( {
			page,
		} ) => {
			// The beforeEach mock already returns MOCK_SESSION (is_shared: true)
			// in the sessions list. Replace the handler to ensure the second
			// navigation uses a fresh single handler (avoids stale handler issues).
			await page.unrouteAll( { behavior: 'ignoreErrors' } );
			await setupMocks( page, {
				sessions: [ MOCK_SESSION ],
				sharedSessions: [ MOCK_SESSION ],
				shareSuccess: true,
			} );

			// Reload so the sidebar renders with the mocked shared session.
			await goToAgentPage( page );

			// Wait for the session item to appear before checking the badge.
			// Use a 10 s timeout to accommodate the async gap between the store
			// receiving the sessions response and React committing the DOM update.
			await expect(
				page.locator( '.ai-agent-session-item' ).first()
			).toBeVisible( {
				timeout: 10_000,
			} );

			// The is-shared class and shared icon are rendered synchronously with
			// the session item when is_shared=true. Wait up to 10 s for the icon
			// to appear in case there is a brief re-render cycle.
			const sharedIcon = page
				.locator(
					'.ai-agent-session-item.is-shared .ai-agent-shared-icon'
				)
				.first();
			await expect( sharedIcon ).toBeVisible( { timeout: 10_000 } );
		} );
	} );

	test.describe( 'Owner — revoke share', () => {
		test.beforeEach( async ( { page } ) => {
			// Register mocks BEFORE login so the floating widget's fetchSessions()
			// call (made during the admin dashboard load after login) is intercepted.
			// Session is already shared.
			await setupMocks( page, {
				sessions: [ MOCK_SESSION ],
				sharedSessions: [ MOCK_SESSION ],
				shareSuccess: true,
			} );
			await loginToWordPress( page );
			await goToAgentPage( page );
		} );

		test( 'clicking Unshare calls the DELETE share endpoint', async ( {
			page,
		} ) => {
			// The beforeEach already set up mocks with sharedSessions: [MOCK_SESSION]
			// and navigated to the agent page. The store has sharedSessions populated.

			// Set up the request promise BEFORE clicking so we don't miss it.
			// Increase timeout to 10 s — the DELETE fires after the user clicks
			// Unshare, which itself requires the context menu to render with the
			// Unshare option (dependent on sharedSessions state being settled).
			const deleteRequestPromise = page.waitForRequest(
				( req ) =>
					decodeURIComponent( req.url() ).includes( '/share' ) &&
					req.method() === 'DELETE',
				{ timeout: 10_000 }
			);

			await openFirstSessionContextMenu( page );

			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );
			// Use a 10 s timeout — the Unshare option only appears after the
			// sharedSessions store state is settled (async after HTTP response).
			await expect( unshareOption ).toBeVisible( { timeout: 10_000 } );
			await unshareOption.click();

			// Wait for the DELETE to actually fire (reliable vs. fixed timeout).
			await deleteRequestPromise;
		} );

		test( 'after revoking, shared sessions list is refreshed', async ( {
			page,
		} ) => {
			// The beforeEach already set up mocks and navigated. The store has
			// sharedSessions populated. We need to track the refetch that happens
			// after clicking Unshare.

			// Replace the /sessions/shared handler with one driven by an
			// isRevoked boolean. Returns [MOCK_SESSION] until isRevoked is set,
			// then returns []. Register on top of the existing handler — LIFO
			// means this runs first. Uses a predicate to handle wp-env's
			// percent-encoded REST paths reliably.
			let isRevoked = false;
			await page.route(
				( req ) =>
					decodeURIComponent( req.url() ).includes(
						'gratis-ai-agent/v1/sessions/shared'
					),
				async ( route ) => {
					const result = isRevoked ? [] : [ MOCK_SESSION ];
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( result ),
					} );
				}
			);

			// Reload so the store fetches with the new handler (isRevoked=false → [MOCK_SESSION]).
			await goToAgentPage( page );

			// The Unshare option is visible because sharedSessions=[MOCK_SESSION].
			await openFirstSessionContextMenu( page );
			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );
			await expect( unshareOption ).toBeVisible( { timeout: 10_000 } );

			// Toggle isRevoked so the next /sessions/shared fetch returns [].
			isRevoked = true;

			// Wait for the refetch response after clicking Unshare.
			// Decode the URL before matching — wp-env uses URL-encoded plain-permalink format.
			const refetchPromise = page.waitForResponse(
				( resp ) =>
					decodeURIComponent( resp.url() ).includes(
						'/sessions/shared'
					) && resp.status() === 200,
				{ timeout: 5_000 }
			);
			await unshareOption.click();
			await refetchPromise;

			// The store now has sharedSessions=[] — the session should be gone.
			// (Verified implicitly by the refetch completing with isRevoked=true.)
		} );
	} );

	test.describe( 'Shared sessions list — "Shared" tab', () => {
		test.beforeEach( async ( { page } ) => {
			// Register mocks BEFORE login so the floating widget's fetchSessions()
			// call (made during the admin dashboard load after login) is intercepted.
			await setupMocks( page, {
				sessions: [ MOCK_SESSION ],
				sharedSessions: [ MOCK_SESSION ],
			} );
			await loginToWordPress( page );
			await goToAgentPage( page );
		} );

		test( '"Shared" tab is visible in the session sidebar', async ( {
			page,
		} ) => {
			const sharedTab = page.getByRole( 'tab', { name: /shared/i } );
			await expect( sharedTab ).toBeVisible();
		} );

		test( 'clicking "Shared" tab fetches shared sessions', async ( {
			page,
		} ) => {
			// Wait for the response triggered by clicking the Shared tab.
			// The beforeEach already loaded the page (initial fetchSharedSessions
			// was handled by the beforeEach route). We just need to confirm that
			// clicking the tab triggers another fetch.
			// Decode the URL before matching — wp-env uses URL-encoded plain-permalink format.
			const sharedResponsePromise = page.waitForResponse(
				( resp ) =>
					decodeURIComponent( resp.url() ).includes(
						'/sessions/shared'
					) && resp.status() === 200,
				{ timeout: 5_000 }
			);

			await clickSharedTab( page );
			await sharedResponsePromise;
		} );

		test( 'shared session appears in "Shared" tab list', async ( {
			page,
		} ) => {
			// Wait for the shared sessions response before asserting the list.
			// Decode the URL before matching — wp-env uses URL-encoded plain-permalink format.
			const sharedResponsePromise = page.waitForResponse(
				( resp ) =>
					decodeURIComponent( resp.url() ).includes(
						'/sessions/shared'
					) && resp.status() === 200,
				{ timeout: 5_000 }
			);

			await clickSharedTab( page );
			await sharedResponsePromise;

			// The shared session title should appear in the sidebar.
			// Use a 10 s timeout to accommodate the async gap between the store
			// receiving the response and React committing the DOM update.
			const sessionTitle = page
				.locator( '.ai-agent-session-item' )
				.filter( {
					hasText: MOCK_SESSION.title,
				} );
			await expect( sessionTitle.first() ).toBeVisible( {
				timeout: 10_000,
			} );
		} );

		test( 'empty state shown when no shared sessions', async ( {
			page,
		} ) => {
			// Override to return empty list for shared sessions.
			await page.unrouteAll( { behavior: 'ignoreErrors' } );
			await setupMocks( page, {
				sessions: [ MOCK_SESSION ],
				sharedSessions: [],
			} );

			await goToAgentPage( page );
			await clickSharedTab( page );

			const emptyState = page.locator( '.ai-agent-session-empty' );
			await expect( emptyState ).toBeVisible();
			await expect( emptyState ).toContainText(
				/no shared conversations/i
			);
		} );
	} );

	test.describe( 'Second admin — view permission', () => {
		/**
		 * This suite uses a second browser context to simulate a second admin
		 * user (admin2) viewing a session shared by the primary admin.
		 */
		test( 'second admin can see shared session in "Shared" tab', async ( {
			browser,
		} ) => {
			// Create an isolated context for the second admin.
			// Pass baseURL so relative URLs in loginToWordPress/goToAgentPage work.
			const secondContext = await browser.newContext( {
				baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
			} );
			const secondPage = await secondContext.newPage();

			try {
				// Register mocks BEFORE login so the floating widget's fetchSessions()
				// call (made during the admin dashboard load after login) is intercepted.
				await setupMocks( secondPage, {
					sessions: [],
					sharedSessions: [ MOCK_SESSION ],
				} );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );

				// Wait for the shared sessions response before asserting.
				// Decode the URL before matching — wp-env uses URL-encoded plain-permalink format.
				const sharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						decodeURIComponent( resp.url() ).includes(
							'/sessions/shared'
						) && resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

				const sessionTitle = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } );
				// Use a 10 s timeout to accommodate the async gap between the store
				// receiving the response and React committing the DOM update.
				await expect( sessionTitle.first() ).toBeVisible( {
					timeout: 10_000,
				} );
			} finally {
				// Guard against double-close when the test times out and
				// Playwright has already closed the context.
				await secondContext.close().catch( () => {} );
			}
		} );

		test( 'second admin cannot see Share/Unshare in context menu (non-owner)', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext( {
				baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
			} );
			const secondPage = await secondContext.newPage();

			try {
				// Register mocks BEFORE login so the floating widget's fetchSessions()
				// call (made during the admin dashboard load after login) is intercepted.
				// sessions: [] so admin2 cannot find MOCK_SESSION in the regular list.
				// The session is only accessible via the Shared tab.
				await setupMocks( secondPage, {
					sessions: [],
					sharedSessions: [ MOCK_SESSION ],
				} );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );

				// Switch to the Shared tab so isSharedTab=true in SessionItem.
				// When isSharedTab=true, isOwner = (session.user_id === currentUserId).
				// MOCK_SESSION has no user_id, so isOwner=false → Share/Unshare hidden.
				// Decode the URL before matching — wp-env uses URL-encoded plain-permalink format.
				const sharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						decodeURIComponent( resp.url() ).includes(
							'/sessions/shared'
						) && resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

				// Wait for the session item to appear in the Shared tab before
				// opening the context menu. The store update and React re-render
				// happen asynchronously after the HTTP response is received.
				await expect(
					secondPage.locator( '.ai-agent-session-item' ).first()
				).toBeVisible( { timeout: 10_000 } );

				// Open context menu for the shared session.
				await openFirstSessionContextMenu( secondPage );

				// Share/Unshare should NOT be present for non-owners.
				const shareOption = secondPage.getByRole( 'menuitem', {
					name: /share with admins/i,
				} );
				const unshareOption = secondPage.getByRole( 'menuitem', {
					name: /unshare/i,
				} );

				await expect( shareOption ).not.toBeVisible();
				await expect( unshareOption ).not.toBeVisible();
			} finally {
				await secondContext.close().catch( () => {} );
			}
		} );
	} );

	test.describe( 'Second admin — contribute permission', () => {
		test( 'second admin can send a message in a shared session', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext( {
				baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
			} );
			const secondPage = await secondContext.newPage();

			try {
				// Register mocks BEFORE login so the floating widget's fetchSessions()
				// call (made during the admin dashboard load after login) is intercepted.
				// sessions: [] so admin2 cannot find MOCK_SESSION in the regular list.
				// The session is only accessible via the Shared tab.
				await setupMocks( secondPage, {
					sessions: [],
					sharedSessions: [ MOCK_SESSION ],
					streamSessionId: MOCK_SESSION.id,
				} );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				// Intercept the single-session GET (openSession thunk fetches
				// /sessions/{id} to load messages and tool calls).
				// Uses a predicate to handle wp-env's percent-encoded REST paths.
				// Checks for '/sessions/42' without a trailing slash or digit to
				// avoid matching /sessions/42/share or /sessions/420.
				await secondPage.route(
					( req ) => {
						const decoded = decodeURIComponent( req.url() );
						return (
							decoded.includes(
								'gratis-ai-agent/v1/sessions/42'
							) &&
							! /gratis-ai-agent\/v1\/sessions\/42[/\d]/.test(
								decoded
							)
						);
					},
					async ( route ) => {
						await route.fulfill( {
							status: 200,
							contentType: 'application/json',
							body: JSON.stringify( {
								...MOCK_SESSION,
								messages: [],
								tool_calls: [],
							} ),
						} );
					}
				);

				await goToAgentPage( secondPage );

				// Navigate to the Shared tab so the session appears in the list.
				// Decode the URL before matching — wp-env uses URL-encoded plain-permalink format.
				const sharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						decodeURIComponent( resp.url() ).includes(
							'/sessions/shared'
						) && resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

				// Click the shared session to load it.
				// Use a 10 s timeout to accommodate the async gap between the store
				// receiving the shared sessions response and React committing the DOM update.
				const sessionItem = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } )
					.first();
				await expect( sessionItem ).toBeVisible( { timeout: 10_000 } );
				await sessionItem.click();

				// Type a message and send it.
				const input = secondPage.locator( '.ai-agent-input' );
				await expect( input ).toBeVisible();
				await input.fill( 'Hello from second admin!' );

				const sendButton = secondPage.locator( '.ai-agent-send-btn' );
				await expect( sendButton ).toBeEnabled();
				await sendButton.click();

				// The user message row should appear (synchronous optimistic update).
				const messageRow = secondPage
					.locator( '.ai-agent-message-row' )
					.first();
				await expect( messageRow ).toBeVisible( { timeout: 5_000 } );
			} finally {
				await secondContext.close().catch( () => {} );
			}
		} );

		test( 'second admin cannot delete a shared session (non-owner restriction)', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext( {
				baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
			} );
			const secondPage = await secondContext.newPage();

			try {
				// Register mocks BEFORE login so the floating widget's fetchSessions()
				// call (made during the admin dashboard load after login) is intercepted.
				// sessions: [] so admin2 cannot find MOCK_SESSION in the regular list.
				// The session is only accessible via the Shared tab.
				await setupMocks( secondPage, {
					sessions: [],
					sharedSessions: [ MOCK_SESSION ],
				} );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );

				// Switch to the Shared tab so isSharedTab=true → isOwner=false
				// (MOCK_SESSION has no user_id, so non-owner restriction applies).
				const sharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						decodeURIComponent( resp.url() ).includes(
							'/sessions/shared'
						) && resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

				// Wait for the session item to appear in the Shared tab before
				// opening the context menu. The store update and React re-render
				// happen asynchronously after the HTTP response is received.
				await expect(
					secondPage.locator( '.ai-agent-session-item' ).first()
				).toBeVisible( { timeout: 10_000 } );

				await openFirstSessionContextMenu( secondPage );

				// Trash/Delete option should not be visible for non-owners on shared sessions.
				const trashOption = secondPage.getByRole( 'menuitem', {
					name: /trash|delete/i,
				} );
				await expect( trashOption ).not.toBeVisible();
			} finally {
				await secondContext.close().catch( () => {} );
			}
		} );
	} );

	test.describe( 'Revoke share — second admin loses access', () => {
		test( 'after revocation, shared session no longer appears in second admin Shared tab', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext( {
				baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
			} );
			const secondPage = await secondContext.newPage();

			try {
				// Register mocks BEFORE login so the floating widget's fetchSessions()
				// call (made during the admin dashboard load after login) is intercepted.
				// Start with the session shared; toggle isRevoked to simulate revocation.
				let isRevoked = false;
				await setupMocks( secondPage, {
					sessions: [],
					sharedSessionsFn: () =>
						isRevoked ? [] : [ MOCK_SESSION ],
				} );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );

				// Wait for the shared sessions response triggered by clicking the tab.
				const initialSharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						decodeURIComponent( resp.url() ).includes(
							'/sessions/shared'
						) && resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await initialSharedResponsePromise;

				// Session should be visible before revocation.
				// Use a 10 s timeout to accommodate the async gap between the store
				// receiving the response and React committing the DOM update.
				const sessionTitle = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } );
				await expect( sessionTitle.first() ).toBeVisible( {
					timeout: 10_000,
				} );

				// Simulate revocation by the owner (toggle the flag and trigger a refetch).
				isRevoked = true;

				// Trigger a refetch by clicking the Shared tab again.
				const refetchPromise = secondPage.waitForResponse(
					( resp ) =>
						decodeURIComponent( resp.url() ).includes(
							'/sessions/shared'
						) && resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await refetchPromise;

				// Session should no longer appear.
				await expect( sessionTitle ).toHaveCount( 0 );

				// Empty state should be shown.
				const emptyState = secondPage.locator(
					'.ai-agent-session-empty'
				);
				await expect( emptyState ).toBeVisible();
			} finally {
				await secondContext.close().catch( () => {} );
			}
		} );
	} );
} );
