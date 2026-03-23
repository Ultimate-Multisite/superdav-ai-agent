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
 * Navigate to the Gratis AI Agent admin page.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToAgentPage( page ) {
	await page.goto( '/wp-admin/tools.php?page=gratis-ai-agent' );
	await page.waitForLoadState( 'networkidle' );
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
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The textarea locator.
 */
function getMessageInput( page ) {
	return page.locator( '.ai-agent-input' );
}

/**
 * Get the send message button.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The send button locator.
 */
function getSendButton( page ) {
	return page.locator( '.ai-agent-send-btn' );
}

/**
 * Get the stop generation button.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The stop button locator.
 */
function getStopButton( page ) {
	return page.locator( '.ai-agent-stop-btn' );
}

/**
 * Get the message list container.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The message list locator.
 */
function getMessageList( page ) {
	return page.locator( '.ai-agent-messages' );
}

/**
 * Get all message rows in the chat.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The message rows locator.
 */
function getMessageRows( page ) {
	return page.locator( '.ai-agent-message-row' );
}

/**
 * Get the chat panel (works in both admin page and floating widget).
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {import('@playwright/test').Locator} The chat panel locator.
 */
function getChatPanel( page ) {
	return page.locator( '.gratis-ai-agent-chat-panel' );
}

/**
 * Navigate to the Gratis AI Agent Changes admin page.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function goToChangesPage( page ) {
	await page.goto( '/wp-admin/tools.php?page=gratis-ai-agent-changes' );
	await page.waitForLoadState( 'networkidle' );
}

/**
 * Navigate to the Gratis AI Agent settings page and optionally activate a tab.
 *
 * The settings page is at /wp-admin/tools.php?page=gratis-ai-agent-settings.
 * Tabs are rendered by the WordPress TabPanel component; clicking a tab button
 * activates it. Pass `tabName` to click a specific tab after navigation.
 *
 * @param {import('@playwright/test').Page} page      - Playwright page object.
 * @param {string}                          [tabName] - Optional tab name to activate (e.g. 'abilities').
 */
async function goToSettingsPage( page, tabName ) {
	await page.goto( '/wp-admin/tools.php?page=gratis-ai-agent-settings' );
	await page.waitForLoadState( 'networkidle' );

	if ( tabName ) {
		// WordPress TabPanel renders tab buttons with role="tab" and a name
		// matching the tab title. The 'abilities' tab has title 'Abilities'.
		const tabButton = page.getByRole( 'tab', {
			name: new RegExp( tabName, 'i' ),
		} );
		await tabButton.click();
		// Wait for the tab panel content to render.
		await page.waitForLoadState( 'networkidle' );
	}
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
	await page
		.locator( '.ai-agent-message-row' )
		.first()
		.waitFor( { state: 'visible', timeout } );
}

module.exports = {
	loginToWordPress,
	goToAgentPage,
	goToAdminDashboard,
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
