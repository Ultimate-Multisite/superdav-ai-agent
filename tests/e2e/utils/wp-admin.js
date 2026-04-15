/**
 * WordPress admin helpers for Playwright E2E tests.
 *
 * Provides login, navigation, and common assertion utilities
 * for testing the Gratis AI Agent plugin in a wp-env environment.
 */

const WP_ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const WP_ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || 'password';

/**
 * Log in to the WordPress admin dashboard.
 *
 * @param {import('@playwright/test').Page} page       - Playwright page object.
 * @param {string}                          [username] - WordPress admin username.
 * @param {string}                          [password] - WordPress admin password.
 */
async function loginToWordPress(
	page,
	username = WP_ADMIN_USER,
	password = WP_ADMIN_PASSWORD
) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', username );
	await page.fill( '#user_pass', password );
	await page.click( '#wp-submit' );
	// Use a generous timeout for the login redirect — WP trunk can be slow
	// to respond on CI runners under load.
	await page.waitForURL( /wp-admin/, { timeout: 60_000 } );
}

/**
 * Navigate to the Gratis AI Agent admin page (Chat route).
 *
 * The UnifiedAdminMenu consolidates all admin pages into a single React SPA
 * at admin.php?page=gratis-ai-agent with hash-based routing. The chat route
 * is the default (no hash or #/chat).
 *
 * Waits for both the sessions list and shared sessions REST responses so that
 * the sidebar is fully populated before the function returns. This prevents
 * race conditions where tests assert on sidebar elements before React has had
 * time to render the intercepted responses.
 *
 * The endpoints may be intercepted (returning instantly) or real (network
 * latency). Either way, waiting for the responses — rather than just
 * `networkidle` — guarantees the store has received its data before we proceed.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToAgentPage( page ) {
	// Set up the response waiters BEFORE navigating so we don't miss requests
	// that fire immediately after React hydrates and dispatches fetchSessions()
	// and fetchSharedSessions().
	// wp-env may use plain-permalink URLs (?rest_route=...) where slashes are
	// URL-encoded, so always decode before matching.
	const sessionsResponsePromise = page
		.waitForResponse(
			( resp ) => {
				const decoded = decodeURIComponent( resp.url() );
				return (
					decoded.includes( 'gratis-ai-agent/v1/sessions' ) &&
					! decoded.includes( 'gratis-ai-agent/v1/sessions/shared' ) &&
					resp.status() === 200
				);
			},
			{ timeout: 15_000 }
		)
		.catch( () => null ); // Non-fatal: some tests may not trigger a sessions fetch.

	// Also wait for the shared sessions response — fetchSharedSessions() fires
	// on mount alongside fetchSessions(). Tests that check sharedSessions state
	// (e.g. context menu showing Unshare) need this to be settled before they
	// assert. Non-fatal because some tests don't intercept this endpoint.
	const sharedSessionsResponsePromise = page
		.waitForResponse(
			( resp ) => {
				const decoded = decodeURIComponent( resp.url() );
				return (
					decoded.includes( 'gratis-ai-agent/v1/sessions/shared' ) &&
					resp.status() === 200
				);
			},
			{ timeout: 15_000 }
		)
		.catch( () => null );

	// UnifiedAdminMenu registers a top-level menu page at admin.php (not
	// tools.php). The chat route is the default — no hash suffix needed.
	await page.goto( '/wp-admin/admin.php?page=gratis-ai-agent' );
	await page.waitForLoadState( 'domcontentloaded' );

	// Wait for both responses so the sidebar is fully populated before returning.
	await Promise.all( [ sessionsResponsePromise, sharedSessionsResponsePromise ] );

	// Wait for the unified admin app root to be present. The SPA mounts into
	// #gratis-ai-agent-root and renders .gratis-ai-agent-unified-admin once React
	// has hydrated. This replaces the old .gratis-ai-agent-chat-panel wait which
	// could time out under CI load when the admin-page bundle is slow to mount.
	// Use 30 s — WP 6.9 CI runners can be slow to render the SPA even with
	// 2 parallel workers.
	await page
		.locator( '.gratis-ai-agent-unified-admin' )
		.waitFor( { state: 'visible', timeout: 30_000 } )
		.catch( () => {} ); // Non-fatal: some tests navigate away before app renders.

	// Wait for the chat container to be present — ChatRoute mounts the chat
	// app into #gratis-ai-agent-chat-container. Either the container or the session
	// list / empty state must be visible before we return.
	await page
		.locator( '#gratis-ai-agent-chat-container, .gratis-ai-agent-session-item, .gratis-ai-agent-session-empty' )
		.first()
		.waitFor( { state: 'visible', timeout: 10_000 } )
		.catch( () => {} ); // Non-fatal: some tests navigate away before list renders.
}

/**
 * Navigate to any admin page where the floating widget is rendered.
 *
 * Waits for the floating action button to be visible before returning so
 * that tests which immediately click the FAB don't race against React mount.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToAdminDashboard( page ) {
	await page.goto( '/wp-admin/index.php' );
	await page.waitForLoadState( 'networkidle' );
	// Wait for the floating widget React app to mount and render the FAB.
	// The FAB renders after FloatingWidget mounts and fetchSettings() resolves.
	// Without this wait, tests that immediately call fab.click() can time out
	// when the CI runner is under load.
	await page
		.locator( '.gratis-ai-agent-fab' )
		.waitFor( { state: 'visible', timeout: 15_000 } )
		.catch( () => {} ); // Non-fatal: some tests may not need the FAB.
}

/**
 * Wait for the floating action button to be visible.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The FAB locator.
 */
function getFloatingButton( page ) {
	return page.locator( '.gratis-ai-agent-fab' );
}

/**
 * Wait for the floating panel to be visible.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The floating panel locator.
 */
function getFloatingPanel( page ) {
	return page.locator( '.gratis-ai-agent-floating-panel' );
}

/**
 * Get the chat message input textarea.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden .gratis-ai-agent-input element. The floating widget
 * renders ChatPanel with compact=true (adds is-compact class), while the
 * admin page chat panel does not have is-compact.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The textarea locator.
 */
function getMessageInput( page ) {
	return page
		.locator( '.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-input' )
		.first();
}

/**
 * Get the send message button.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden send button.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The send button locator.
 */
function getSendButton( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-send-btn'
		)
		.first();
}

/**
 * Get the stop generation button.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden stop button.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The stop button locator.
 */
function getStopButton( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-stop-btn'
		)
		.first();
}

/**
 * Get the message list container.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden message list.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The message list locator.
 */
function getMessageList( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-messages'
		)
		.first();
}

/**
 * Get all message rows in the chat.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden message rows.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The message rows locator.
 */
function getMessageRows( page ) {
	return page.locator(
		'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-message-row'
	);
}

/**
 * Get the admin page chat panel (non-compact, not the floating widget).
 *
 * The floating widget renders ChatPanel with compact=true (adds is-compact
 * class). The admin page chat panel does not have is-compact. This selector
 * avoids strict-mode violations when both panels are in the DOM.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The chat panel locator.
 */
function getChatPanel( page ) {
	return page
		.locator( '.gratis-ai-agent-chat-panel:not(.is-compact)' )
		.first();
}

/**
 * Navigate to the Gratis AI Agent Changes admin page.
 *
 * The UnifiedAdminMenu uses hash-based routing. The changes route is at
 * admin.php?page=gratis-ai-agent#/changes. The old URL
 * (tools.php?page=gratis-ai-agent-changes) triggers a wp_safe_redirect()
 * which causes Playwright to hang waiting for networkidle on the redirect
 * target — use the canonical hash URL directly to avoid the redirect.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToChangesPage( page ) {
	await page.goto( '/wp-admin/admin.php?page=gratis-ai-agent#/changes' );
	await page.waitForLoadState( 'domcontentloaded' );

	// Wait for the unified admin app and the changes route container to render.
	// Use 45 s — the unified admin SPA can be slow to render on CI runners
	// under load. CI currently uses 2 parallel workers for both WP 6.9 and
	// trunk to reduce resource contention, but a generous timeout is still needed.
	await page
		.locator( '.gratis-ai-agent-route-changes' )
		.waitFor( { state: 'visible', timeout: 45_000 } );
}

/**
 * Navigate to the Gratis AI Agent settings page and optionally activate a tab.
 *
 * The UnifiedAdminMenu uses hash-based routing. The settings route is at
 * admin.php?page=gratis-ai-agent#/settings. The old URL
 * (tools.php?page=gratis-ai-agent-settings) triggers a wp_safe_redirect()
 * which causes Playwright to hang — use the canonical hash URL directly.
 *
 * The settings route renders a TabPanel with tabs: general, providers, advanced.
 * Pass `tabName` to click a specific tab after navigation.
 *
 * @param {import('@playwright/test').Page} page      - Playwright page object.
 * @param {string}                          [tabName] - Optional tab name to activate (e.g. 'general').
 */
async function goToSettingsPage( page, tabName ) {
	await page.goto( '/wp-admin/admin.php?page=gratis-ai-agent#/settings' );
	await page.waitForLoadState( 'domcontentloaded' );

	// Wait for the settings route container to render.
	// Use 45 s — the unified admin SPA can be slow to render on CI runners
	// under load. CI currently uses 2 parallel workers for both WP 6.9 and
	// trunk to reduce resource contention, but a generous timeout is still needed.
	await page
		.locator( '.gratis-ai-agent-route-settings' )
		.waitFor( { state: 'visible', timeout: 45_000 } );

	if ( tabName ) {
		// WordPress TabPanel renders tab buttons with role="tab" and a name
		// matching the tab title.
		const tabButton = page.getByRole( 'tab', {
			name: new RegExp( tabName, 'i' ),
		} );
		await tabButton.click();
		// Wait for the tab panel content to render after clicking.
		// The settings route wraps tabs in .gratis-ai-agent-route-settings.
		await page
			.locator( '.gratis-ai-agent-route-settings [role="tabpanel"]' )
			.waitFor( { state: 'visible', timeout: 10_000 } )
			.catch( () => {} );
	}
}

/**
 * Navigate to the Gratis AI Agent Abilities admin page.
 *
 * The UnifiedAdminMenu uses hash-based routing. The abilities route is at
 * admin.php?page=gratis-ai-agent#/abilities and renders AbilitiesExplorerApp
 * directly (not as a tab inside the settings page).
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToAbilitiesPage( page ) {
	await page.goto( '/wp-admin/admin.php?page=gratis-ai-agent#/abilities' );
	await page.waitForLoadState( 'domcontentloaded' );

	// Wait for AbilitiesExplorerApp to finish loading abilities.
	// .gratis-ai-agent-abilities-manager is the outer wrapper rendered by
	// AbilitiesExplorerApp once the REST fetch completes.
	// Use 45 s — the abilities REST fetch can be slow on CI runners under
	// load. CI currently uses 2 parallel workers for both WP 6.9 and trunk
	// to reduce resource contention, but a generous timeout is still needed.
	await page
		.locator( '.gratis-ai-agent-abilities-manager' )
		.waitFor( { state: 'visible', timeout: 45_000 } );
}

/**
 * Navigate to the Gratis AI Agent Model Benchmark admin page.
 *
 * The benchmark page is a standalone React SPA registered under Tools:
 * tools.php?page=gratis-ai-agent-benchmark. It renders BenchmarkPageApp
 * inside #gratis-ai-agent-benchmark-root once the bundle loads.
 *
 * Waits for the benchmark page root element to be present before returning
 * so that tests can immediately assert on page content.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToBenchmarkPage( page ) {
	await page.goto( '/wp-admin/tools.php?page=gratis-ai-agent-benchmark' );
	await page.waitForLoadState( 'domcontentloaded' );

	// Wait for the React app to mount into the benchmark root container.
	// Use 30 s to match the Playwright test timeout — the benchmark bundle
	// can be slow to mount on CI runners under load.
	await page
		.locator( '#gratis-ai-agent-benchmark-root, .gratis-ai-agent-benchmark-page' )
		.first()
		.waitFor( { state: 'visible', timeout: 30_000 } )
		.catch( () => {} ); // Non-fatal: some tests may assert on the wrap before React mounts.
}

/**
 * Wait for a user message to appear in the chat after sending.
 *
 * This is more reliable than waiting for the stop button because the user
 * message row is appended synchronously (before any async REST calls), so it
 * persists regardless of whether the backend job succeeds or fails quickly.
 *
 * On WP trunk the /v1/run endpoint may return an error response faster than
 * on WP 6.9, causing sending=false (and the stop button to disappear) before
 * the 10 s timeout. The message row does not disappear on error, making it a
 * stable signal that the message was submitted.
 *
 * @param {import('@playwright/test').Page} page    - Playwright page object.
 * @param {number}                          timeout - Max wait in ms (default 5 000).
 * @return {Promise<void>}
 */
async function waitForMessageSubmitted( page, timeout = 5_000 ) {
	// Scope to the non-compact (admin page) chat panel to avoid matching the
	// floating widget's hidden message rows.
	await page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-message-row'
		)
		.first()
		.waitFor( { state: 'visible', timeout } );
}

module.exports = {
	loginToWordPress,
	goToAgentPage,
	goToAdminDashboard,
	goToAbilitiesPage,
	goToBenchmarkPage,
	goToChangesPage,
	goToSettingsPage,
	getFloatingButton,
	getFloatingPanel,
	getMessageInput,
	getSendButton,
	getStopButton,
	getMessageList,
	getMessageRows,
	getChatPanel,
	waitForMessageSubmitted,
};
