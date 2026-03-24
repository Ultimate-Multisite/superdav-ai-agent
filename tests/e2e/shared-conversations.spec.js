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
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAgentPage,
} = require( './utils/wp-admin' );

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
 * Decode a Playwright URL object to its full decoded string.
 *
 * wp-env uses the index.php?rest_route= format (pretty permalinks disabled),
 * so REST API paths appear URL-encoded in the URL string:
 *   http://localhost:8888/index.php?rest_route=%2Fgratis-ai-agent%2Fv1%2Fsessions&status=active
 *
 * Playwright's page.route() regex matches against the raw (encoded) URL, so
 * literal-slash regexes like /sessions\/shared/ never match. Using a function
 * matcher with decodeURIComponent() normalises the URL before matching.
 *
 * @param {URL} url - Playwright URL object.
 * @return {string} Fully decoded URL string.
 */
function decodeUrl( url ) {
	try {
		return decodeURIComponent( url.toString() );
	} catch {
		return url.toString();
	}
}

/**
 * Intercept the REST sessions list endpoint to return a single mock session.
 *
 * Matches /gratis-ai-agent/v1/sessions (with any query params) but NOT
 * /sessions/shared or /sessions/{id} sub-paths.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object[]} sessions - Sessions to return (defaults to [MOCK_SESSION]).
 */
async function interceptSessionsList( page, sessions = [ MOCK_SESSION ] ) {
	await page.route(
		( url ) => {
			const decoded = decodeUrl( url );
			return (
				decoded.includes( 'gratis-ai-agent/v1/sessions' ) &&
				! decoded.includes( 'gratis-ai-agent/v1/sessions/shared' ) &&
				! /gratis-ai-agent\/v1\/sessions\/\d/.test( decoded )
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

/**
 * Intercept the shared sessions list endpoint.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object[]} sessions - Shared sessions to return.
 */
async function interceptSharedSessionsList( page, sessions = [ MOCK_SESSION ] ) {
	await page.route(
		( url ) =>
			decodeUrl( url ).includes( 'gratis-ai-agent/v1/sessions/shared' ),
		async ( route ) => {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( sessions ),
			} );
		}
	);
}

/**
 * Intercept the share endpoint (POST /sessions/{id}/share).
 *
 * @param {import('@playwright/test').Page} page
 * @param {boolean}                         success - Whether to simulate success.
 */
async function interceptShareEndpoint( page, success = true ) {
	await page.route(
		( url ) =>
			/gratis-ai-agent\/v1\/sessions\/\d+\/share/.test(
				decodeUrl( url )
			),
		async ( route ) => {
			if ( route.request().method() === 'POST' ) {
				await route.fulfill( {
					status: success ? 200 : 500,
					contentType: 'application/json',
					body: JSON.stringify( { shared: success } ),
				} );
			} else if ( route.request().method() === 'DELETE' ) {
				await route.fulfill( {
					status: success ? 200 : 500,
					contentType: 'application/json',
					body: JSON.stringify( { shared: false } ),
				} );
			} else {
				await route.continue();
			}
		}
	);
}

/**
 * Intercept the stream endpoint so message sending completes without a real AI
 * provider. Returns a minimal SSE response (one token + done event).
 *
 * @param {import('@playwright/test').Page} page
 * @param {number}                          sessionId
 */
async function interceptStream( page, sessionId = MOCK_SESSION.id ) {
	await page.route(
		( url ) => decodeUrl( url ).includes( 'gratis-ai-agent/v1/stream' ),
		async ( route ) => {
			let sid = sessionId;
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
				`data: ${ JSON.stringify( { token: 'Hello from shared session!' } ) }`,
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

/**
 * Open the context menu for the first session item in the sidebar.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openFirstSessionContextMenu( page ) {
	const sessionItem = page.locator( '.ai-agent-session-item' ).first();
	await expect( sessionItem ).toBeVisible();
	// Hover to reveal the ⋯ button, then click it.
	await sessionItem.hover();
	await sessionItem.locator( '.ai-agent-session-more' ).click();
}

/**
 * Click the "Shared" tab in the session sidebar.
 *
 * @param {import('@playwright/test').Page} page
 */
async function clickSharedTab( page ) {
	const sharedTab = page.getByRole( 'tab', { name: /shared/i } );
	await expect( sharedTab ).toBeVisible();
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
			await interceptSessionsList( page );
			await interceptSharedSessionsList( page, [] );
			await interceptShareEndpoint( page );
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
			// Pre-populate shared sessions so the store considers this session shared.
			await page.route(
				( url ) =>
					decodeURIComponent( url.toString() ).includes(
						'gratis-ai-agent/v1/sessions/shared'
					),
				async ( route ) => {
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( [ MOCK_SESSION ] ),
					} );
				}
			);

			// Reload so the store fetches shared sessions with the new intercept.
			await goToAgentPage( page );

			await openFirstSessionContextMenu( page );

			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );
			await expect( unshareOption ).toBeVisible();
		} );

		test( 'shared session shows shared badge icon in sidebar', async ( {
			page,
		} ) => {
			// The beforeEach already intercepts sessions returning MOCK_SESSION
			// (is_shared: true). Just reload the page so the sidebar renders
			// with the shared session from the existing intercept.
			await goToAgentPage( page );

			// Wait for the session item to appear before checking the badge.
			await expect(
				page.locator( '.ai-agent-session-item' ).first()
			).toBeVisible();

			const sharedIcon = page
				.locator( '.ai-agent-session-item.is-shared .ai-agent-shared-icon' )
				.first();
			await expect( sharedIcon ).toBeVisible();
		} );
	} );

	test.describe( 'Owner — revoke share', () => {
		test.beforeEach( async ( { page } ) => {
			// Register mocks BEFORE login so the floating widget's fetchSessions()
			// call (made during the admin dashboard load after login) is intercepted.
			// Session is already shared.
			await interceptSessionsList( page, [ MOCK_SESSION ] );
			await interceptSharedSessionsList( page, [ MOCK_SESSION ] );
			await interceptShareEndpoint( page );
			await loginToWordPress( page );
			await goToAgentPage( page );
		} );

		test( 'clicking Unshare calls the DELETE share endpoint', async ( {
			page,
		} ) => {
			// Set up the request promise BEFORE clicking so we don't miss it.
			const deleteRequestPromise = page.waitForRequest(
				( req ) =>
					decodeURIComponent( req.url() ).includes( '/share' ) &&
					req.method() === 'DELETE',
				{ timeout: 5_000 }
			);

			await openFirstSessionContextMenu( page );

			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );
			await expect( unshareOption ).toBeVisible();
			await unshareOption.click();

			// Wait for the DELETE to actually fire (reliable vs. fixed timeout).
			await deleteRequestPromise;
		} );

		test( 'after revoking, shared sessions list is refreshed', async ( {
			page,
		} ) => {
			// Remove the beforeEach route so our counting handler takes precedence.
			await page.unroute( /gratis-ai-agent\/v1\/sessions\/shared/ );

			let sharedListCallCount = 0;
			await page.route(
				/gratis-ai-agent\/v1\/sessions\/shared/,
				async ( route ) => {
					sharedListCallCount++;
					// After first call (initial load), return empty list to simulate revocation.
					const sessions =
						sharedListCallCount === 1 ? [ MOCK_SESSION ] : [];
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( sessions ),
					} );
				}
			);

			await goToAgentPage( page );

			// Initial load should have fetched shared sessions once.
			expect( sharedListCallCount ).toBeGreaterThanOrEqual( 1 );

			await openFirstSessionContextMenu( page );
			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );

			// Wait for the refetch response after clicking Unshare.
			const refetchPromise = page.waitForResponse(
				( resp ) =>
					resp.url().includes( '/sessions/shared' ) &&
					resp.status() === 200,
				{ timeout: 5_000 }
			);
			await unshareOption.click();
			await refetchPromise;

			expect( sharedListCallCount ).toBeGreaterThanOrEqual( 2 );
		} );
	} );

	test.describe( 'Shared sessions list — "Shared" tab', () => {
		test.beforeEach( async ( { page } ) => {
			// Register mocks BEFORE login so the floating widget's fetchSessions()
			// call (made during the admin dashboard load after login) is intercepted.
			await interceptSessionsList( page );
			await interceptSharedSessionsList( page, [ MOCK_SESSION ] );
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
			const sharedResponsePromise = page.waitForResponse(
				( resp ) =>
					resp.url().includes( '/sessions/shared' ) &&
					resp.status() === 200,
				{ timeout: 5_000 }
			);

			await clickSharedTab( page );
			await sharedResponsePromise;
		} );

		test( 'shared session appears in "Shared" tab list', async ( {
			page,
		} ) => {
			// Wait for the shared sessions response before asserting the list.
			const sharedResponsePromise = page.waitForResponse(
				( resp ) =>
					resp.url().includes( '/sessions/shared' ) &&
					resp.status() === 200,
				{ timeout: 5_000 }
			);

			await clickSharedTab( page );
			await sharedResponsePromise;

			// The shared session title should appear in the sidebar.
			const sessionTitle = page.locator( '.ai-agent-session-item' ).filter( {
				hasText: MOCK_SESSION.title,
			} );
			await expect( sessionTitle.first() ).toBeVisible();
		} );

		test( 'empty state shown when no shared sessions', async ( { page } ) => {
			// Override to return empty list.
			await page.route(
				/gratis-ai-agent\/v1\/sessions\/shared/,
				async ( route ) => {
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( [] ),
					} );
				}
			);

			await goToAgentPage( page );
			await clickSharedTab( page );

			const emptyState = page.locator( '.ai-agent-session-empty' );
			await expect( emptyState ).toBeVisible();
			await expect( emptyState ).toContainText( /no shared conversations/i );
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
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSessionsList( secondPage, [] );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );

				// Wait for the shared sessions response before asserting.
				const sharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						resp.url().includes( '/sessions/shared' ) &&
						resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

				const sessionTitle = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } );
				await expect( sessionTitle.first() ).toBeVisible();
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
				// Return the shared session in both lists.
				await interceptSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );

				// Switch to the Shared tab so isSharedTab=true in SessionItem.
				// When isSharedTab=true, isOwner = (session.user_id === currentUserId).
				// MOCK_SESSION has no user_id, so isOwner=false → Share/Unshare hidden.
				const sharedResponsePromise = secondPage.waitForResponse(
					( resp ) =>
						resp.url().includes( '/sessions/shared' ) &&
						resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

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
				// Intercept sessions so the shared session is available.
				await interceptSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );

				// Intercept the stream so the message send completes.
				await interceptStream( secondPage, MOCK_SESSION.id );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				// Intercept the single-session GET (openSession thunk fetches
				// /sessions/{id} to load messages and tool calls).
				await secondPage.route(
					/gratis-ai-agent\/v1\/sessions\/42(\?|$)/,
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

				// Click the shared session to load it.
				const sessionItem = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } )
					.first();
				await expect( sessionItem ).toBeVisible();
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
				await interceptSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );

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
						resp.url().includes( '/sessions/shared' ) &&
						resp.status() === 200,
					{ timeout: 5_000 }
				);
				await clickSharedTab( secondPage );
				await sharedResponsePromise;

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
				// Start with the session shared.
				let isRevoked = false;
				await secondPage.route(
					( url ) =>
						decodeUrl( url ).includes(
							'gratis-ai-agent/v1/sessions/shared'
						),
					async ( route ) => {
						await route.fulfill( {
							status: 200,
							contentType: 'application/json',
							body: JSON.stringify(
								isRevoked ? [] : [ MOCK_SESSION ]
							),
						} );
					}
				);
				await interceptSessionsList( secondPage, [] );

				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await goToAgentPage( secondPage );
				await clickSharedTab( secondPage );

				// Session should be visible before revocation.
				const sessionTitle = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } );
				await expect( sessionTitle.first() ).toBeVisible();

				// Simulate revocation by the owner (toggle the flag and trigger a refetch).
				isRevoked = true;

				// Trigger a refetch by clicking the Shared tab again.
				const refetchPromise = secondPage.waitForResponse(
					( resp ) =>
						resp.url().includes( '/sessions/shared' ) &&
						resp.status() === 200,
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
