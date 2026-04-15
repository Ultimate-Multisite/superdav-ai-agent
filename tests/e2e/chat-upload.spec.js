/**
 * E2E tests for image/file upload in the chat input (t122).
 *
 * Covers the upload feature added in PR #560 (t109):
 *   - Paperclip upload button visibility
 *   - File picker triggered by upload button
 *   - Drag-drop zone (is-drag-over class + drop overlay)
 *   - Thumbnail preview strip after attaching a file
 *   - Per-attachment remove button
 *   - Send button enabled when only attachments are present (no text)
 *
 * Tests run against the admin page at /wp-admin/admin.php?page=gratis-ai-agent.
 * No live AI provider is required — file attachment state is purely client-side.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const {
	loginToWordPress,
	goToAgentPage,
	getMessageInput,
	getSendButton,
} = require( './utils/wp-admin' );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Get the paperclip upload button locator.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden upload button.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getUploadButton( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-upload-btn'
		)
		.first();
}

/**
 * Get the hidden file input locator.
 *
 * Scoped to the non-compact (admin page) chat panel to avoid matching the
 * floating widget's hidden file input.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getFileInput( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-file-input'
		)
		.first();
}

/**
 * Get the attachment preview strip locator.
 *
 * Scoped to the non-compact (admin page) chat panel.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAttachmentPreviews( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-attachment-previews'
		)
		.first();
}

/**
 * Get all attachment thumbnail locators.
 *
 * Scoped to the non-compact (admin page) chat panel.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAttachmentThumbs( page ) {
	return page.locator(
		'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-attachment-thumb'
	);
}

/**
 * Get the input area wrapper (drag-drop target).
 *
 * Scoped to the non-compact (admin page) chat panel.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getInputArea( page ) {
	return page
		.locator(
			'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-input-area'
		)
		.first();
}

/**
 * Dispatch a drag event on a locator using a properly constructed DataTransfer
 * object created inside the browser context.
 *
 * WP 6.9 (and modern browsers) reject DragEventInit with a plain object for
 * `dataTransfer` — the property must be a real DataTransfer instance.
 * We work around this by constructing the event entirely inside page.evaluate().
 *
 * @param {import('@playwright/test').Page}    page
 * @param {import('@playwright/test').Locator} locator  Target element locator.
 * @param {'dragover'|'dragleave'|'drop'}      eventType
 */
async function dispatchDragEvent( page, locator, eventType ) {
	// Resolve the element handle so we can pass it into evaluate.
	const elementHandle = await locator.elementHandle();
	await page.evaluate(
		( { el, type } ) => {
			const dt = new DataTransfer();
			const event = new DragEvent( type, {
				bubbles: true,
				cancelable: true,
				dataTransfer: dt,
			} );
			el.dispatchEvent( event );
		},
		{ el: elementHandle, type: eventType }
	);
}

/**
 * Create a minimal 1×1 PNG as a Buffer (valid PNG header + IDAT).
 * Used to simulate a real image file without needing a fixture on disk.
 *
 * @return {Buffer} Raw PNG bytes.
 */
function createMinimalPng() {
	// Minimal valid 1×1 white PNG (137 bytes).
	return Buffer.from(
		'89504e470d0a1a0a0000000d49484452000000010000000108020000009001' +
			'2e00000000c4944415478016360f8cfc00000000200016e0213500000000049454e44ae426082',
		'hex'
	);
}

/**
 * Attach a synthetic PNG file to the hidden file input via setInputFiles.
 * This bypasses the OS file picker and directly sets the file on the input.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} [fileName='test-image.png']
 */
async function attachPngViaInput( page, fileName = 'test-image.png' ) {
	const fileInput = getFileInput( page );
	await fileInput.setInputFiles( {
		name: fileName,
		mimeType: 'image/png',
		buffer: createMinimalPng(),
	} );
}

/**
 * Attach a synthetic plain-text file to the hidden file input.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} [fileName='test-doc.txt']
 */
async function attachTextViaInput( page, fileName = 'test-doc.txt' ) {
	const fileInput = getFileInput( page );
	await fileInput.setInputFiles( {
		name: fileName,
		mimeType: 'text/plain',
		buffer: Buffer.from( 'Hello, world!' ),
	} );
}

// ---------------------------------------------------------------------------
// Test suites
// ---------------------------------------------------------------------------

test.describe( 'Chat Upload - Upload Button (t122)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'paperclip upload button is visible in the input row', async ( {
		page,
	} ) => {
		const uploadBtn = getUploadButton( page );
		await expect( uploadBtn ).toBeVisible();
	} );

	test( 'upload button has accessible label', async ( { page } ) => {
		const uploadBtn = getUploadButton( page );
		// The Button component renders aria-label from the `label` prop.
		const label = await uploadBtn.getAttribute( 'aria-label' );
		expect( label ).toBeTruthy();
		expect( label.toLowerCase() ).toContain( 'attach' );
	} );

	test( 'upload button is disabled while sending', async ( { page } ) => {
		// Intercept the stream so it stays open long enough to check the button state.
		let resolveStream;
		const streamPending = new Promise( ( res ) => {
			resolveStream = res;
		} );

		await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
			// Hold the route open until we resolve.
			await streamPending;
			await route.fulfill( {
				status: 200,
				headers: { 'Content-Type': 'text/event-stream' },
				body: 'event: done\ndata: {}\n\n',
			} );
		} );

		const input = getMessageInput( page );
		await input.fill( 'Test' );
		await input.press( 'Enter' );

		// While the stream is pending, the upload button should be disabled.
		const uploadBtn = getUploadButton( page );
		await expect( uploadBtn ).toBeDisabled( { timeout: 5_000 } );

		// Unblock the stream so the test can clean up.
		resolveStream();
	} );

	test( 'clicking upload button triggers the hidden file input', async ( {
		page,
	} ) => {
		// Listen for the file chooser that the hidden input opens.
		const fileChooserPromise = page.waitForEvent( 'filechooser', {
			timeout: 5_000,
		} );

		const uploadBtn = getUploadButton( page );
		await uploadBtn.click();

		// If the file chooser opens, the button correctly triggered the input.
		const fileChooser = await fileChooserPromise;
		expect( fileChooser ).toBeTruthy();
	} );
} );

test.describe( 'Chat Upload - Drag-Drop Zone (t122)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'input area gains is-drag-over class on dragover', async ( {
		page,
	} ) => {
		const inputArea = getInputArea( page );

		// Dispatch a dragover event with a properly constructed DataTransfer.
		// Plain objects are rejected by WP 6.9+ — must use a real DataTransfer.
		await dispatchDragEvent( page, inputArea, 'dragover' );

		await expect( inputArea ).toHaveClass( /is-drag-over/ );
	} );

	test( 'is-drag-over class is removed on dragleave', async ( { page } ) => {
		const inputArea = getInputArea( page );

		// Simulate dragover then dragleave using real DataTransfer objects.
		await dispatchDragEvent( page, inputArea, 'dragover' );
		await expect( inputArea ).toHaveClass( /is-drag-over/ );

		await dispatchDragEvent( page, inputArea, 'dragleave' );
		await expect( inputArea ).not.toHaveClass( /is-drag-over/ );
	} );

	test( 'drop overlay text is shown while dragging over', async ( {
		page,
	} ) => {
		const inputArea = getInputArea( page );

		await dispatchDragEvent( page, inputArea, 'dragover' );

		// The drop overlay renders "Drop files here" text.
		const dropOverlay = page.locator( '.gratis-ai-agent-drop-overlay' );
		await expect( dropOverlay ).toBeVisible();
		await expect( dropOverlay ).toContainText( 'Drop files here' );
	} );

	test( 'drop overlay is hidden when not dragging', async ( { page } ) => {
		// On initial load, no drag is active — overlay should not be present.
		const dropOverlay = page.locator( '.gratis-ai-agent-drop-overlay' );
		await expect( dropOverlay ).not.toBeVisible();
	} );
} );

test.describe( 'Chat Upload - Thumbnail Preview (t122)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'attachment preview strip is hidden before any file is attached', async ( {
		page,
	} ) => {
		// AttachmentPreviews returns null when attachments is empty.
		const previews = getAttachmentPreviews( page );
		await expect( previews ).not.toBeVisible();
	} );

	test( 'thumbnail preview appears after attaching an image', async ( {
		page,
	} ) => {
		await attachPngViaInput( page );

		// The preview strip should now be visible.
		const previews = getAttachmentPreviews( page );
		await expect( previews ).toBeVisible( { timeout: 5_000 } );

		// At least one thumbnail should be present.
		const thumbs = getAttachmentThumbs( page );
		await expect( thumbs ).toHaveCount( 1 );
	} );

	test( 'thumbnail shows an <img> element for image attachments', async ( {
		page,
	} ) => {
		await attachPngViaInput( page );

		const thumbImg = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__img'
		);
		await expect( thumbImg ).toBeVisible( { timeout: 5_000 } );

		// The src should be a data URL (base64-encoded image).
		const src = await thumbImg.getAttribute( 'src' );
		expect( src ).toMatch( /^data:image\// );
	} );

	test( 'thumbnail shows file extension badge for non-image attachments', async ( {
		page,
	} ) => {
		await attachTextViaInput( page );

		const extBadge = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__ext'
		);
		await expect( extBadge ).toBeVisible( { timeout: 5_000 } );
		await expect( extBadge ).toContainText( 'TXT' );
	} );

	test( 'thumbnail shows the file name', async ( { page } ) => {
		await attachPngViaInput( page, 'my-screenshot.png' );

		const thumbName = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__name'
		);
		await expect( thumbName ).toBeVisible( { timeout: 5_000 } );
		await expect( thumbName ).toContainText( 'my-screenshot.png' );
	} );

	test( 'multiple files produce multiple thumbnails', async ( { page } ) => {
		const fileInput = getFileInput( page );
		await fileInput.setInputFiles( [
			{
				name: 'image1.png',
				mimeType: 'image/png',
				buffer: createMinimalPng(),
			},
			{
				name: 'image2.png',
				mimeType: 'image/png',
				buffer: createMinimalPng(),
			},
		] );

		const thumbs = getAttachmentThumbs( page );
		await expect( thumbs ).toHaveCount( 2, { timeout: 5_000 } );
	} );
} );

test.describe( 'Chat Upload - Remove Button (t122)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'each thumbnail has a remove button', async ( { page } ) => {
		await attachPngViaInput( page );

		const removeBtn = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__remove'
		);
		await expect( removeBtn ).toBeVisible( { timeout: 5_000 } );
	} );

	test( 'remove button has accessible aria-label', async ( { page } ) => {
		await attachPngViaInput( page );

		const removeBtn = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__remove'
		);
		await expect( removeBtn ).toBeVisible( { timeout: 5_000 } );

		const label = await removeBtn.getAttribute( 'aria-label' );
		expect( label ).toBeTruthy();
		expect( label.toLowerCase() ).toContain( 'remove' );
	} );

	test( 'clicking remove button removes the thumbnail', async ( { page } ) => {
		await attachPngViaInput( page );

		// Confirm thumbnail is present.
		const thumbs = getAttachmentThumbs( page );
		await expect( thumbs ).toHaveCount( 1, { timeout: 5_000 } );

		// Click the remove button.
		const removeBtn = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__remove'
		);
		await removeBtn.click();

		// Thumbnail should be gone.
		await expect( thumbs ).toHaveCount( 0, { timeout: 5_000 } );

		// Preview strip should also disappear (returns null when empty).
		const previews = getAttachmentPreviews( page );
		await expect( previews ).not.toBeVisible();
	} );

	test( 'removing one of multiple thumbnails leaves the rest', async ( {
		page,
	} ) => {
		const fileInput = getFileInput( page );
		await fileInput.setInputFiles( [
			{
				name: 'first.png',
				mimeType: 'image/png',
				buffer: createMinimalPng(),
			},
			{
				name: 'second.png',
				mimeType: 'image/png',
				buffer: createMinimalPng(),
			},
		] );

		const thumbs = getAttachmentThumbs( page );
		await expect( thumbs ).toHaveCount( 2, { timeout: 5_000 } );

		// Remove the first thumbnail.
		const firstRemoveBtn = page
			.locator(
				'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__remove'
			)
			.first();
		await firstRemoveBtn.click();

		// Only one thumbnail should remain.
		await expect( thumbs ).toHaveCount( 1, { timeout: 5_000 } );
	} );
} );

test.describe( 'Chat Upload - Send Button State (t122)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'send button is enabled when only an attachment is present (no text)', async ( {
		page,
	} ) => {
		// Confirm send button starts disabled (no text, no attachments).
		const sendButton = getSendButton( page );
		await expect( sendButton ).toBeDisabled();

		// Attach a file without typing any text.
		await attachPngViaInput( page );

		// Send button should now be enabled — attachments alone allow sending.
		await expect( sendButton ).toBeEnabled( { timeout: 5_000 } );
	} );

	test( 'send button is disabled after removing the only attachment', async ( {
		page,
	} ) => {
		await attachPngViaInput( page );

		const sendButton = getSendButton( page );
		await expect( sendButton ).toBeEnabled( { timeout: 5_000 } );

		// Remove the attachment.
		const removeBtn = page.locator(
			'.gratis-ai-agent-attachment-thumb .gratis-ai-agent-attachment-thumb__remove'
		);
		await removeBtn.click();

		// No text, no attachments — send button should be disabled again.
		await expect( sendButton ).toBeDisabled( { timeout: 5_000 } );
	} );

	test( 'attachments are cleared after sending a message', async ( {
		page,
	} ) => {
		// Intercept the stream so it completes quickly.
		await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
			const sseBody = [
				'event: token',
				`data: ${ JSON.stringify( { token: 'OK' } ) }`,
				'',
				'event: done',
				`data: ${ JSON.stringify( { session_id: 1 } ) }`,
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

		// Attach a file and type a message.
		await attachPngViaInput( page );
		const input = getMessageInput( page );
		await input.fill( 'Here is an image' );

		// Confirm thumbnail is present before sending.
		const thumbs = getAttachmentThumbs( page );
		await expect( thumbs ).toHaveCount( 1, { timeout: 5_000 } );

		// Send the message.
		await input.press( 'Enter' );

		// After sending, the attachment strip should be cleared.
		const previews = getAttachmentPreviews( page );
		await expect( previews ).not.toBeVisible( { timeout: 5_000 } );
	} );
} );
