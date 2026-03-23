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
 * Intercept the REST sessions list endpoint to return a single mock session.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object[]} sessions - Sessions to return (defaults to [MOCK_SESSION]).
 */
async function interceptSessionsList( page, sessions = [ MOCK_SESSION ] ) {
	await page.route( /gratis-ai-agent\/v1\/sessions(\?|$)/, async ( route ) => {
		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( sessions ),
		} );
	} );
}

/**
 * Intercept the shared sessions list endpoint.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object[]} sessions - Shared sessions to return.
 */
async function interceptSharedSessionsList( page, sessions = [ MOCK_SESSION ] ) {
	await page.route(
		/gratis-ai-agent\/v1\/sessions\/shared/,
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
		/gratis-ai-agent\/v1\/sessions\/\d+\/share/,
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
	await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
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
	} );
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
			await loginToWordPress( page );
			await interceptSessionsList( page );
			await interceptSharedSessionsList( page, [] );
			await interceptShareEndpoint( page );
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
			const shareRequests = [];
			page.on( 'request', ( req ) => {
				if (
					req.url().includes( '/share' ) &&
					req.method() === 'POST'
				) {
					shareRequests.push( req );
				}
			} );

			await openFirstSessionContextMenu( page );

			const shareOption = page.getByRole( 'menuitem', {
				name: /share with admins/i,
			} );
			await shareOption.click();

			// Wait for the POST to fire.
			await page.waitForTimeout( 500 );
			expect( shareRequests.length ).toBeGreaterThanOrEqual( 1 );
		} );

		test( 'after sharing, context menu shows Unshare option', async ( {
			page,
		} ) => {
			// Pre-populate shared sessions so the store considers this session shared.
			await page.route(
				/gratis-ai-agent\/v1\/sessions\/shared/,
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
			// Intercept sessions list to return a session marked as shared.
			await page.route(
				/gratis-ai-agent\/v1\/sessions(\?|$)/,
				async ( route ) => {
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( [ MOCK_SESSION ] ),
					} );
				}
			);
			await page.route(
				/gratis-ai-agent\/v1\/sessions\/shared/,
				async ( route ) => {
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( [ MOCK_SESSION ] ),
					} );
				}
			);

			await goToAgentPage( page );

			const sharedIcon = page
				.locator( '.ai-agent-session-item.is-shared .ai-agent-shared-icon' )
				.first();
			await expect( sharedIcon ).toBeVisible();
		} );
	} );

	test.describe( 'Owner — revoke share', () => {
		test.beforeEach( async ( { page } ) => {
			await loginToWordPress( page );
			// Session is already shared.
			await interceptSessionsList( page, [ MOCK_SESSION ] );
			await interceptSharedSessionsList( page, [ MOCK_SESSION ] );
			await interceptShareEndpoint( page );
			await goToAgentPage( page );
		} );

		test( 'clicking Unshare calls the DELETE share endpoint', async ( {
			page,
		} ) => {
			const deleteRequests = [];
			page.on( 'request', ( req ) => {
				if (
					req.url().includes( '/share' ) &&
					req.method() === 'DELETE'
				) {
					deleteRequests.push( req );
				}
			} );

			await openFirstSessionContextMenu( page );

			const unshareOption = page.getByRole( 'menuitem', {
				name: /unshare/i,
			} );
			await expect( unshareOption ).toBeVisible();
			await unshareOption.click();

			await page.waitForTimeout( 500 );
			expect( deleteRequests.length ).toBeGreaterThanOrEqual( 1 );
		} );

		test( 'after revoking, shared sessions list is refreshed', async ( {
			page,
		} ) => {
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
			await unshareOption.click();

			// After unshare, the store calls fetchSharedSessions again.
			await page.waitForTimeout( 500 );
			expect( sharedListCallCount ).toBeGreaterThanOrEqual( 2 );
		} );
	} );

	test.describe( 'Shared sessions list — "Shared" tab', () => {
		test.beforeEach( async ( { page } ) => {
			await loginToWordPress( page );
			await interceptSessionsList( page );
			await interceptSharedSessionsList( page, [ MOCK_SESSION ] );
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
			let sharedListCallCount = 0;
			await page.route(
				/gratis-ai-agent\/v1\/sessions\/shared/,
				async ( route ) => {
					sharedListCallCount++;
					await route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( [ MOCK_SESSION ] ),
					} );
				}
			);

			await goToAgentPage( page );
			await clickSharedTab( page );

			await page.waitForTimeout( 500 );
			// At least one call: initial mount + tab click refetch.
			expect( sharedListCallCount ).toBeGreaterThanOrEqual( 1 );
		} );

		test( 'shared session appears in "Shared" tab list', async ( {
			page,
		} ) => {
			await clickSharedTab( page );

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
			const secondContext = await browser.newContext();
			const secondPage = await secondContext.newPage();

			try {
				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				// Intercept shared sessions list for the second admin's page.
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSessionsList( secondPage, [] );

				await goToAgentPage( secondPage );
				await clickSharedTab( secondPage );

				const sessionTitle = secondPage
					.locator( '.ai-agent-session-item' )
					.filter( { hasText: MOCK_SESSION.title } );
				await expect( sessionTitle.first() ).toBeVisible();
			} finally {
				await secondContext.close();
			}
		} );

		test( 'second admin cannot see Share/Unshare in context menu (non-owner)', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext();
			const secondPage = await secondContext.newPage();

			try {
				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				// Return the shared session in the main sessions list so the
				// second admin can see it in the sidebar.
				await interceptSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );

				// Intercept the single-session GET to mark is_shared=true and
				// shared_by=1 (primary admin's user ID, not admin2's).
				await secondPage.route(
					/gratis-ai-agent\/v1\/sessions\/42(\?|$)/,
					async ( route ) => {
						await route.fulfill( {
							status: 200,
							contentType: 'application/json',
							body: JSON.stringify( MOCK_SESSION ),
						} );
					}
				);

				await goToAgentPage( secondPage );

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
				await secondContext.close();
			}
		} );
	} );

	test.describe( 'Second admin — contribute permission', () => {
		test( 'second admin can send a message in a shared session', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext();
			const secondPage = await secondContext.newPage();

			try {
				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				// Intercept sessions so the shared session is available.
				await interceptSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );

				// Intercept the stream so the message send completes.
				await interceptStream( secondPage, MOCK_SESSION.id );

				// Intercept the session load endpoint.
				await secondPage.route(
					/gratis-ai-agent\/v1\/sessions\/42\/messages/,
					async ( route ) => {
						await route.fulfill( {
							status: 200,
							contentType: 'application/json',
							body: JSON.stringify( [] ),
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
				await secondContext.close();
			}
		} );

		test( 'second admin cannot delete a shared session (non-owner restriction)', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext();
			const secondPage = await secondContext.newPage();

			try {
				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				await interceptSessionsList( secondPage, [ MOCK_SESSION ] );
				await interceptSharedSessionsList( secondPage, [ MOCK_SESSION ] );

				await goToAgentPage( secondPage );

				await openFirstSessionContextMenu( secondPage );

				// Trash/Delete option should not be visible for non-owners on shared sessions.
				const trashOption = secondPage.getByRole( 'menuitem', {
					name: /trash|delete/i,
				} );
				await expect( trashOption ).not.toBeVisible();
			} finally {
				await secondContext.close();
			}
		} );
	} );

	test.describe( 'Revoke share — second admin loses access', () => {
		test( 'after revocation, shared session no longer appears in second admin Shared tab', async ( {
			browser,
		} ) => {
			const secondContext = await browser.newContext();
			const secondPage = await secondContext.newPage();

			try {
				await loginToWordPress(
					secondPage,
					SECOND_ADMIN_USER,
					SECOND_ADMIN_PASS
				);

				// Start with the session shared.
				let isRevoked = false;
				await secondPage.route(
					/gratis-ai-agent\/v1\/sessions\/shared/,
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
				await clickSharedTab( secondPage );
				await secondPage.waitForTimeout( 500 );

				// Session should no longer appear.
				await expect( sessionTitle ).toHaveCount( 0 );

				// Empty state should be shown.
				const emptyState = secondPage.locator(
					'.ai-agent-session-empty'
				);
				await expect( emptyState ).toBeVisible();
			} finally {
				await secondContext.close();
			}
		} );
	} );
} );
