const puppeteer = require( 'puppeteer-core' );

const BASE_URL = 'http://localhost:8888';
const SCREENSHOT_DIR = '/screenshots';
const CHROME_PATH =
	process.env.CHROME_PATH || '/usr/bin/chromium-browser';

( async () => {
	const browser = await puppeteer.launch( {
		headless: 'new',
		args: [ '--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu' ],
		executablePath: CHROME_PATH,
	} );

	const page = await browser.newPage();
	await page.setViewport( { width: 1400, height: 900 } );

	try {
		// Step 1: Login
		console.log( 'Step 1: Logging in...' );
		await page.goto( `${ BASE_URL }/wp-login.php`, {
			waitUntil: 'networkidle2',
			timeout: 15000,
		} );
		await page.screenshot( {
			path: `${ SCREENSHOT_DIR }/01-login-page.png`,
			fullPage: true,
		} );

		await page.type( '#user_login', 'admin' );
		await page.type( '#user_pass', 'password' );
		await page.click( '#wp-submit' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle2',
			timeout: 15000,
		} );
		console.log( 'Login successful' );
		await page.screenshot( {
			path: `${ SCREENSHOT_DIR }/02-dashboard.png`,
			fullPage: true,
		} );

		// Step 2: Plugins page
		console.log( 'Step 2: Plugins page...' );
		await page.goto( `${ BASE_URL }/wp-admin/plugins.php`, {
			waitUntil: 'networkidle2',
			timeout: 15000,
		} );
		await page.screenshot( {
			path: `${ SCREENSHOT_DIR }/03-plugins.png`,
			fullPage: true,
		} );

		// Step 3: Connector settings
		console.log( 'Step 3: Connector settings...' );
		await page.goto(
			`${ BASE_URL }/wp-admin/options-general.php?page=openai-compatible-connector`,
			{ waitUntil: 'networkidle2', timeout: 15000 }
		);
		await page.screenshot( {
			path: `${ SCREENSHOT_DIR }/04-connector-settings.png`,
			fullPage: true,
		} );

		// Step 4: AI Agent Settings
		console.log( 'Step 4: AI Agent Settings...' );
		await page.goto(
			`${ BASE_URL }/wp-admin/tools.php?page=ai-agent-settings`,
			{ waitUntil: 'networkidle2', timeout: 15000 }
		);
		await new Promise( ( r ) => setTimeout( r, 4000 ) );
		await page.screenshot( {
			path: `${ SCREENSHOT_DIR }/05-ai-agent-settings.png`,
			fullPage: true,
		} );

		// Step 5: AI Agent Chat page
		console.log( 'Step 5: AI Agent Chat page...' );
		await page.goto( `${ BASE_URL }/wp-admin/tools.php?page=ai-agent`, {
			waitUntil: 'networkidle2',
			timeout: 15000,
		} );
		await new Promise( ( r ) => setTimeout( r, 4000 ) );
		await page.screenshot( {
			path: `${ SCREENSHOT_DIR }/06-chat-page.png`,
			fullPage: true,
		} );

		// Check chat UI
		const chatInfo = await page.evaluate( () => {
			const root = document.getElementById( 'ai-agent-root' );
			const textareas = document.querySelectorAll( 'textarea' );
			const inputs = document.querySelectorAll( 'input[type="text"]' );
			return {
				rootExists: !! root,
				rootChildCount: root ? root.children.length : 0,
				rootInnerHTML: root
					? root.innerHTML.substring( 0, 300 )
					: 'NOT FOUND',
				textareaCount: textareas.length,
				inputCount: inputs.length,
				allTextareas: Array.from( textareas ).map( ( t ) => ( {
					placeholder: t.placeholder,
					visible: t.offsetParent !== null,
				} ) ),
			};
		} );
		console.log( 'Chat UI:', JSON.stringify( chatInfo, null, 2 ) );

		// Step 6: Try sending a message
		console.log( 'Step 6: Sending test message...' );
		const textarea = await page.$( 'textarea' );
		if ( textarea ) {
			await textarea.type( 'Hello! What model are you?' );
			await page.screenshot( {
				path: `${ SCREENSHOT_DIR }/07-message-typed.png`,
				fullPage: true,
			} );

			// Find and click send button
			const sendResult = await page.evaluate( () => {
				const buttons = document.querySelectorAll( 'button' );
				for ( const btn of buttons ) {
					const text = ( btn.textContent || '' ).toLowerCase().trim();
					const label = (
						btn.getAttribute( 'aria-label' ) || ''
					).toLowerCase();
					if (
						text.includes( 'send' ) ||
						label.includes( 'send' ) ||
						btn.type === 'submit'
					) {
						btn.click();
						return `Clicked: ${ text || label || 'submit button' }`;
					}
				}
				// Try form submit
				const form = document.querySelector( 'form' );
				if ( form ) {
					form.dispatchEvent(
						new Event( 'submit', { bubbles: true } )
					);
					return 'Form submitted';
				}
				return 'No send button found';
			} );
			console.log( 'Send result:', sendResult );

			// Wait for response
			console.log( 'Waiting 25s for AI response...' );
			await new Promise( ( r ) => setTimeout( r, 25000 ) );
			await page.screenshot( {
				path: `${ SCREENSHOT_DIR }/08-chat-response.png`,
				fullPage: true,
			} );

			// Check for response content
			const responseInfo = await page.evaluate( () => {
				const root = document.getElementById( 'ai-agent-root' );
				return {
					innerHTML: root
						? root.innerHTML.substring( 0, 1000 )
						: 'NOT FOUND',
					textContent: root
						? root.textContent.substring( 0, 500 )
						: 'NOT FOUND',
				};
			} );
			console.log(
				'Response info:',
				JSON.stringify( responseInfo ).substring( 0, 500 )
			);
		} else {
			console.log( 'No textarea found on chat page' );
		}

		// Step 7: Test REST API
		console.log( 'Step 7: Testing REST API...' );
		const apiResult = await page.evaluate( async () => {
			const results = {};
			try {
				const resp = await fetch(
					'/?rest_route=/ai-agent/v1/providers',
					{
						headers: {
							'X-WP-Nonce': window.wpApiSettings?.nonce || '',
						},
					}
				);
				results.providers = await resp.json();
			} catch ( e ) {
				results.providersError = e.message;
			}

			try {
				const resp = await fetch(
					'/?rest_route=/ai-agent/v1/settings',
					{
						headers: {
							'X-WP-Nonce': window.wpApiSettings?.nonce || '',
						},
					}
				);
				results.settings = await resp.json();
			} catch ( e ) {
				results.settingsError = e.message;
			}

			return results;
		} );
		console.log(
			'Providers:',
			JSON.stringify(
				apiResult.providers || apiResult.providersError
			).substring( 0, 500 )
		);
		console.log(
			'Settings provider:',
			apiResult.settings?.default_provider
		);
		console.log( 'Settings model:', apiResult.settings?.default_model );

		// Step 8: Console errors check
		console.log( 'Step 8: Error check...' );
		const phpErrors = await page.evaluate( () => {
			const body = document.body.innerHTML;
			const errors = [];
			for ( const pattern of [
				'Fatal error',
				'Warning:',
				'Notice:',
				'Parse error',
			] ) {
				if ( body.includes( pattern ) ) {
					const idx = body.indexOf( pattern );
					errors.push( body.substring( idx, idx + 200 ) );
				}
			}
			return errors;
		} );
		console.log(
			`PHP errors: ${
				phpErrors.length > 0
					? JSON.stringify( phpErrors )
					: 'None found'
			}`
		);

		console.log( '\n=== ALL SCREENSHOTS TAKEN ===' );
	} catch ( error ) {
		console.error( 'Error:', error.message );
		await page
			.screenshot( {
				path: `${ SCREENSHOT_DIR }/99-error.png`,
				fullPage: true,
			} )
			.catch( () => {} );
	} finally {
		await browser.close();
	}
} )();
