/**
 * E2E tests for the text-to-speech (TTS) feature.
 *
 * Tests the TTS toggle button in the chat header, the TTS settings tab,
 * and the auto-speak behaviour on AI responses.
 *
 * The Web Speech API (SpeechSynthesis) is not available in headless Chromium,
 * so each test injects a minimal mock via page.addInitScript() before
 * navigating. The mock records calls to speak() and cancel() so tests can
 * assert on TTS behaviour without requiring real audio output.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAgentPage,
	goToSettingsPage,
} = require( './utils/wp-admin' );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Inject a minimal SpeechSynthesis mock into the page before navigation.
 *
 * The mock exposes:
 *   - window.speechSynthesis.speak(utterance)  — records the utterance text
 *   - window.speechSynthesis.cancel()          — records a cancel call
 *   - window.__ttsMockCalls                    — array of recorded calls
 *   - window.__ttsMockVoices                   — voices returned by getVoices()
 *
 * Calling speak() fires utterance.onstart synchronously so the React hook
 * transitions isSpeaking → true immediately, then fires utterance.onend
 * after a short delay so the hook transitions back to false.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function injectTtsMock( page ) {
	await page.addInitScript( () => {
		const calls = [];
		window.__ttsMockCalls = calls;

		const mockVoice = {
			name: 'Mock Voice',
			lang: 'en-US',
			voiceURI: 'mock-voice-uri',
			localService: true,
			default: true,
		};
		window.__ttsMockVoices = [ mockVoice ];

		const synthesis = {
			speaking: false,
			pending: false,
			paused: false,
			_voicesChangedListeners: [],
			getVoices() {
				return window.__ttsMockVoices;
			},
			speak( utterance ) {
				calls.push( { type: 'speak', text: utterance.text } );
				synthesis.speaking = true;
				if ( typeof utterance.onstart === 'function' ) {
					utterance.onstart();
				}
				// Simulate speech ending after a short delay.
				setTimeout( () => {
					synthesis.speaking = false;
					if ( typeof utterance.onend === 'function' ) {
						utterance.onend();
					}
				}, 50 );
			},
			cancel() {
				calls.push( { type: 'cancel' } );
				synthesis.speaking = false;
			},
			pause() {},
			resume() {},
			addEventListener( event, listener ) {
				if ( event === 'voiceschanged' ) {
					synthesis._voicesChangedListeners.push( listener );
				}
			},
			removeEventListener( event, listener ) {
				if ( event === 'voiceschanged' ) {
					synthesis._voicesChangedListeners =
						synthesis._voicesChangedListeners.filter(
							( l ) => l !== listener
						);
				}
			},
		};

		Object.defineProperty( window, 'speechSynthesis', {
			value: synthesis,
			writable: false,
			configurable: true,
		} );

		// SpeechSynthesisUtterance mock.
		window.SpeechSynthesisUtterance = class {
			constructor( text ) {
				this.text = text;
				this.rate = 1;
				this.pitch = 1;
				this.voice = null;
				this.onstart = null;
				this.onend = null;
				this.onerror = null;
			}
		};
	} );
}

/**
 * Intercept the stream endpoint and return a minimal SSE response with a
 * single AI token so the store completes the message and TTS can fire.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 */
async function interceptStream( page ) {
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

		const sseBody = [
			'event: token',
			`data: ${ JSON.stringify( { token: 'Hello from the AI!' } ) }`,
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

// ---------------------------------------------------------------------------
// Tests: TTS toggle button in the chat header
// ---------------------------------------------------------------------------

test.describe( 'TTS Toggle Button', () => {
	test.beforeEach( async ( { page } ) => {
		await injectTtsMock( page );
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'TTS toggle button is visible in the chat header', async ( {
		page,
	} ) => {
		// The button is only rendered when isTTSSupported is true.
		// Our mock defines window.speechSynthesis, so the button should appear.
		// Scope to the non-compact (admin page) chat panel to avoid matching
		// the floating widget's hidden TTS button.
		const ttsBtn = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-tts-btn'
			)
			.first();
		await expect( ttsBtn ).toBeVisible();
	} );

	test( 'clicking TTS toggle button enables TTS and adds is-active class', async ( {
		page,
	} ) => {
		// Scope to the non-compact (admin page) chat panel.
		const ttsBtn = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-tts-btn'
			)
			.first();
		await expect( ttsBtn ).toBeVisible();

		// Ensure TTS starts disabled (default state).
		// If it is already active, click once to disable first.
		const isActive = await ttsBtn.evaluate( ( el ) =>
			el.classList.contains( 'is-active' )
		);
		if ( isActive ) {
			await ttsBtn.click();
		}

		// Now enable TTS.
		await ttsBtn.click();
		await expect( ttsBtn ).toHaveClass( /is-active/ );
	} );

	test( 'clicking TTS toggle button a second time disables TTS', async ( {
		page,
	} ) => {
		// Scope to the non-compact (admin page) chat panel.
		const ttsBtn = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-tts-btn'
			)
			.first();
		await expect( ttsBtn ).toBeVisible();

		// Enable TTS.
		const isActive = await ttsBtn.evaluate( ( el ) =>
			el.classList.contains( 'is-active' )
		);
		if ( ! isActive ) {
			await ttsBtn.click();
		}
		await expect( ttsBtn ).toHaveClass( /is-active/ );

		// Disable TTS.
		await ttsBtn.click();
		await expect( ttsBtn ).not.toHaveClass( /is-active/ );
	} );
} );

// ---------------------------------------------------------------------------
// Tests: TTS settings tab
// ---------------------------------------------------------------------------

test.describe( 'TTS Settings Tab', () => {
	test.beforeEach( async ( { page } ) => {
		await injectTtsMock( page );
		await loginToWordPress( page );
		// TTS settings live inside the General tab of the Settings page.
		// There is no separate "Text-to-Speech" tab — the section is rendered
		// as part of the General tab content under a "Text-to-Speech" heading.
		await goToSettingsPage( page, 'general' );
		// Wait for settings to finish loading so the TTS section is rendered.
		await page
			.locator( '.gratis-ai-agent-settings-loading' )
			.waitFor( { state: 'hidden', timeout: 15_000 } )
			.catch( () => {} );
	} );

	test( 'Text-to-Speech settings are present in the General tab', async ( {
		page,
	} ) => {
		// TTS settings are rendered under a "Text-to-Speech" section heading
		// inside the General tab — there is no dedicated TTS tab.
		const ttsHeading = page.getByRole( 'heading', {
			name: /text-to-speech/i,
		} );
		await expect( ttsHeading ).toBeVisible();
	} );

	test( 'TTS enable toggle is visible in the Text-to-Speech settings section', async ( {
		page,
	} ) => {
		// The ToggleControl for TTS auto-speak should be visible.
		// WordPress ToggleControl renders a <label> containing the label text.
		// The actual label is "Read AI responses aloud automatically".
		const ttsToggleLabel = page.getByText(
			'Read AI responses aloud automatically',
			{ exact: false }
		);
		await expect( ttsToggleLabel ).toBeVisible();
	} );

	test( 'enabling TTS in settings persists the toggle state', async ( {
		page,
	} ) => {
		// Find the toggle input for the TTS auto-speak setting.
		// WordPress ToggleControl renders a checkbox input inside a label.
		// The actual label is "Read AI responses aloud automatically".
		const ttsToggle = page
			.locator( '.components-toggle-control' )
			.filter( { hasText: 'Read AI responses aloud automatically' } )
			.locator( 'input[type="checkbox"]' );

		const wasChecked = await ttsToggle.isChecked();

		// Toggle to the opposite state.
		await ttsToggle.click();
		const nowChecked = await ttsToggle.isChecked();
		expect( nowChecked ).toBe( ! wasChecked );

		// Toggle back to original state to avoid polluting other tests.
		await ttsToggle.click();
		const restoredChecked = await ttsToggle.isChecked();
		expect( restoredChecked ).toBe( wasChecked );
	} );
} );

// ---------------------------------------------------------------------------
// Tests: TTS auto-speak on AI responses
// ---------------------------------------------------------------------------

test.describe( 'TTS Auto-Speak on AI Responses', () => {
	test.beforeEach( async ( { page } ) => {
		await injectTtsMock( page );
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'speechSynthesis.speak is called when TTS is enabled and AI responds', async ( {
		page,
	} ) => {
		// Enable TTS via the header toggle. Scope to the non-compact (admin page)
		// chat panel to avoid matching the floating widget's hidden TTS button.
		const ttsBtn = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-tts-btn'
			)
			.first();
		await expect( ttsBtn ).toBeVisible();

		const isActive = await ttsBtn.evaluate( ( el ) =>
			el.classList.contains( 'is-active' )
		);
		if ( ! isActive ) {
			await ttsBtn.click();
		}
		await expect( ttsBtn ).toHaveClass( /is-active/ );

		// Intercept the stream so the AI response completes quickly.
		await interceptStream( page );

		// Send a message. Scope to the non-compact chat panel.
		const input = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-input'
			)
			.first();
		await input.fill( 'Hello' );
		await input.press( 'Enter' );

		// Wait for the AI message to appear in the chat.
		await page
			.locator( '.gratis-ai-agent-bubble.gratis-ai-agent-assistant' )
			.first()
			.waitFor( { state: 'visible', timeout: 15_000 } );

		// Verify that speak() was called on the mock.
		// TTS fires asynchronously after the stream completes, so poll until
		// the speak call appears rather than checking synchronously.
		await expect
			.poll(
				async () => {
					const calls = await page.evaluate(
						() => window.__ttsMockCalls
					);
					return calls.filter( ( c ) => c.type === 'speak' ).length;
				},
				{ timeout: 10_000 }
			)
			.toBeGreaterThanOrEqual( 1 );
	} );

	test( 'speechSynthesis.speak is NOT called when TTS is disabled', async ( {
		page,
	} ) => {
		// Ensure TTS is disabled. Scope to the non-compact (admin page) chat panel.
		const ttsBtn = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-tts-btn'
			)
			.first();
		await expect( ttsBtn ).toBeVisible();

		const isActive = await ttsBtn.evaluate( ( el ) =>
			el.classList.contains( 'is-active' )
		);
		if ( isActive ) {
			await ttsBtn.click();
		}
		await expect( ttsBtn ).not.toHaveClass( /is-active/ );

		// Intercept the stream.
		await interceptStream( page );

		// Send a message. Scope to the non-compact chat panel.
		const input = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-input'
			)
			.first();
		await input.fill( 'Hello' );
		await input.press( 'Enter' );

		// Wait for the AI message to appear.
		await page
			.locator( '.gratis-ai-agent-bubble.gratis-ai-agent-assistant' )
			.first()
			.waitFor( { state: 'visible', timeout: 15_000 } );

		// Verify that speak() was NOT called.
		const calls = await page.evaluate( () => window.__ttsMockCalls );
		const speakCalls = calls.filter( ( c ) => c.type === 'speak' );
		expect( speakCalls.length ).toBe( 0 );
	} );

	test( 'disabling TTS mid-conversation calls speechSynthesis.cancel', async ( {
		page,
	} ) => {
		// Enable TTS. Scope to the non-compact (admin page) chat panel.
		const ttsBtn = page
			.locator(
				'.gratis-ai-agent-chat-panel:not(.is-compact) .gratis-ai-agent-tts-btn'
			)
			.first();
		await expect( ttsBtn ).toBeVisible();

		const isActive = await ttsBtn.evaluate( ( el ) =>
			el.classList.contains( 'is-active' )
		);
		if ( ! isActive ) {
			await ttsBtn.click();
		}
		await expect( ttsBtn ).toHaveClass( /is-active/ );

		// Disable TTS — the store effect calls cancel() when ttsEnabled → false.
		await ttsBtn.click();
		await expect( ttsBtn ).not.toHaveClass( /is-active/ );

		// Verify cancel() was called.
		const calls = await page.evaluate( () => window.__ttsMockCalls );
		const cancelCalls = calls.filter( ( c ) => c.type === 'cancel' );
		expect( cancelCalls.length ).toBeGreaterThanOrEqual( 1 );
	} );
} );
