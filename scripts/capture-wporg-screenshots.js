#!/usr/bin/env node

/**
 * Capture real, scrubbed WordPress.org listing screenshots from a local site.
 *
 * Usage:
 * WP_BASE_URL=https://example.test node scripts/capture-wporg-screenshots.js
 *
 * The shared E2E login helper reads WP_ADMIN_USER and WP_ADMIN_PASSWORD when
 * supplied. Its local test defaults are used when those variables are absent.
 */

const path = require( 'path' );
const { chromium } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAgentPage,
	goToChangesPage,
	getMessageInput,
	getSendButton,
	getStopButton,
} = require( '../tests/e2e/utils/wp-admin' );

const baseURL = process.env.WP_BASE_URL;
const outputDirectory = path.resolve( __dirname, '../.wordpress-org/assets' );
const editorUrl = process.env.WP_ORG_EDITOR_URL;
const editorTitle = process.env.WP_ORG_EDITOR_TITLE || 'Sample Page';
const captureSessionTitle = process.env.WP_ORG_CAPTURE_SESSION;
const reviewSessionTitle = process.env.WP_ORG_REVIEW_SESSION;
const capturePrompt =
	process.env.WP_ORG_CAPTURE_PROMPT ||
	'Update the existing Sample Page with a clear welcome heading, a short introduction, and a contact call to action. Complete the edit and summarize what changed.';

if ( ! baseURL ) {
	throw new Error( 'Set WP_BASE_URL to the local WordPress test site URL.' );
}

/**
 * Log in to the configured local site and write the listing screenshots.
 *
 * @return {Promise<void>} Resolves after the browser closes.
 */
async function capture() {
	let browser;

	try {
		browser = await chromium.launch( { headless: true } );
		const page = await browser.newPage( {
			viewport: { width: 1440, height: 960 },
			baseURL,
		} );

		await loginToWordPress( page );

		await goToAgentPage( page );
		if ( captureSessionTitle ) {
			await page
				.locator( '.sdaa-cr-session-row', { hasText: captureSessionTitle } )
				.first()
				.click();
		} else {
			await getMessageInput( page ).fill( capturePrompt );
			await getSendButton( page ).click();

			const stopButton = getStopButton( page );
			await stopButton.waitFor( { state: 'visible', timeout: 30_000 } );
			await stopButton.waitFor( { state: 'hidden', timeout: 300_000 } );
		}
		await page
			.locator( '.sdaa-cr-msg-assistant' )
			.last()
			.waitFor( { state: 'visible', timeout: 15_000 } );
		if (
			( await page.getByText( 'Some steps need attention' ).isVisible() ) ||
			( await page
				.getByText( 'The background agent job could not finish.' )
				.isVisible() )
		) {
			throw new Error(
				'Capture requires a completed agent workflow without unresolved steps.'
			);
		}
		await page.locator( '#sdaa-root' ).screenshot( {
			path: path.join( outputDirectory, 'screenshot-1.png' ),
		} );

		if ( editorUrl ) {
			await page.goto( editorUrl );
		} else {
			await page.goto( '/wp-admin/edit.php?post_type=page' );
			await page
				.locator( '#the-list .row-title', { hasText: editorTitle } )
				.first()
				.click();
			await page.waitForURL( /post\.php/ );
		}
		await page
			.locator( '.editor-styles-wrapper, .edit-post-visual-editor' )
			.first()
			.waitFor( { state: 'visible', timeout: 30_000 } );
		if ( await page.getByText( 'Welcome to the editor' ).isVisible() ) {
			await page.keyboard.press( 'Escape' );
			await page
				.getByText( 'Welcome to the editor' )
				.waitFor( { state: 'hidden', timeout: 5_000 } );
		}
		await page
			.locator( '.block-editor-block-list__layout [data-block]' )
			.first()
			.waitFor( { state: 'visible', timeout: 30_000 } );
		await page.locator( '#wpwrap' ).screenshot( {
			path: path.join( outputDirectory, 'screenshot-2.png' ),
		} );

		if ( reviewSessionTitle ) {
			await goToAgentPage( page );
			await page
				.locator( '.sdaa-cr-session-row', { hasText: reviewSessionTitle } )
				.first()
				.click();
			await page
				.locator( '.sdaa-cr-msg-assistant' )
				.last()
				.waitFor( { state: 'visible', timeout: 15_000 } );
			if ( await page.getByText( 'Some steps need attention' ).isVisible() ) {
				throw new Error(
					'Capture requires a completed review workflow without unresolved steps.'
				);
			}
			await page.locator( '#sdaa-root' ).screenshot( {
				path: path.join( outputDirectory, 'screenshot-3.png' ),
			} );
		} else {
			await goToChangesPage( page );
			await page
				.getByText( 'Loading changes…' )
				.waitFor( { state: 'hidden', timeout: 30_000 } );
			await page.locator( '.sdaa-route-changes' ).screenshot( {
			path: path.join( outputDirectory, 'screenshot-3.png' ),
			} );
		}
	} finally {
		if ( browser ) {
			await browser.close();
		}
	}
}

capture().catch( ( error ) => {
	process.stderr.write( `${ error.stack || error.message }\n` );
	process.exitCode = 1;
} );
