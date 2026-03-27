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
	await page.waitForURL( /wp-admin/ );
}

/**
 * Navigate to the Gratis AI Agent admin page (Chat route).
 *
 * The UnifiedAdminMenu consolidates all admin pages into a single React SPA
 * at admin.php?page=gratis-ai-agent with hash-based routing. The chat route
 * is the default (no hash or #/chat).
 *
 * The chat UI (AdminPageApp) is mounted by the unified admin's ChatRoute via
 * window.gratisAiAgentChat.mount(). AdminPageApp renders null until
 * settingsLoaded=true, then renders .gratis-ai-agent-layout inside
 * #gratis-ai-chat-container. We wait for the non-compact chat panel to confirm
 * the app has fully hydrated before returning.
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
	// #gratis-ai-agent-root and renders .gratis-ai-unified-admin once React
	// has hydrated.
	await page
		.locator( '.gratis-ai-unified-admin' )
		.waitFor( { state: 'visible', timeout: 15_000 } )
		.catch( () => {} ); // Non-fatal: some tests navigate away before app renders.

	// Wait for the AdminPageApp to mount inside #gratis-ai-chat-container.
	// ChatRoute calls window.gratisAiAgentChat.mount(container) which renders
	// AdminPageApp. AdminPageApp returns null until settingsLoaded=true, then
	// renders .gratis-ai-agent-layout. The non-compact chat panel
	// (.gratis-ai-agent-chat-panel:not(.is-compact)) confirms the app has
	// fully hydrated. The floating widget renders a compact panel (is-compact),
	// so this selector uniquely identifies the admin page chat.
	await page
		.locator( '.gratis-ai-agent-chat-panel:not(.is-compact)' )
		.waitFor( { state: 'visible', timeout: 20_000 } )
		.catch( () => {} ); // Non-fatal: some tests navigate away before app renders.
}

/**
 * Navigate to any admin page where the floating widget is rendered.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToAdminDashboard( page ) {
	await page.goto( '/wp-admin/index.php' );
	await page.waitForLoadState( 'networkidle' );
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
	return page.locator( '.ai-agent-floating-panel' );
}

/**
 * Get the chat message input textarea.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden .ai-agent-input element. The floating widget
 * renders ChatPanel with compact=true (adds is-compact class), while the
 * admin page chat panel does not have is-compact.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The textarea locator.
 */
function getMessageInput( page ) {
	return page
		.locator( '.gratis-ai-agent-chat-panel:not(.is-compact) .ai-agent-input' )
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
			'.gratis-ai-agent-chat-panel:not(.is-compact) .ai-agent-send-btn'
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
			'.gratis-ai-agent-chat-panel:not(.is-compact) .ai-agent-stop-btn'
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
			'.gratis-ai-agent-chat-panel:not(.is-compact) .ai-agent-messages'
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
		'.gratis-ai-agent-chat-panel:not(.is-compact) .ai-agent-message-row'
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
	await page
		.locator( '.gratis-ai-route-changes' )
		.waitFor( { state: 'visible', timeout: 15_000 } )
		.catch( () => {} );
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
	await page
		.locator( '.gratis-ai-route-settings' )
		.waitFor( { state: 'visible', timeout: 15_000 } )
		.catch( () => {} );

	if ( tabName ) {
		// WordPress TabPanel renders tab buttons with role="tab" and a name
		// matching the tab title.
		const tabButton = page.getByRole( 'tab', {
			name: new RegExp( tabName, 'i' ),
		} );
		await tabButton.click();
		// Wait for the tab panel content to render after clicking.
		await page
			.locator( '.gratis-ai-settings-tabs [role="tabpanel"]' )
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

	// Wait for the abilities route container and the abilities manager to render.
	await page
		.locator( '.gratis-ai-route-abilities' )
		.waitFor( { state: 'visible', timeout: 15_000 } )
		.catch( () => {} );

	// Wait for AbilitiesExplorerApp to finish loading abilities.
	await page
		.locator( '.ai-agent-abilities-manager' )
		.waitFor( { state: 'visible', timeout: 15_000 } )
		.catch( () => {} );
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
			'.gratis-ai-agent-chat-panel:not(.is-compact) .ai-agent-message-row'
		)
		.first()
		.waitFor( { state: 'visible', timeout } );
}

module.exports = {
	loginToWordPress,
	goToAgentPage,
	goToAdminDashboard,
	goToAbilitiesPage,
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
