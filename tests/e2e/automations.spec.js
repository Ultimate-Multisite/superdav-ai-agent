/**
 * E2E tests for the Gratis AI Agent automations system (t080/t081).
 *
 * Covers:
 *   - Scheduled automations: create, list, enable/disable toggle
 *   - Event-driven automations: create, list, enable/disable toggle
 *   - Proactive alert badge on the FAB
 *
 * All REST calls to /gratis-ai-agent/v1/* are intercepted and mocked so
 * these tests run without a real AI provider or a live WordPress back-end.
 *
 * Run: npm run test:e2e:playwright -- --grep automations
 */

const { test, expect } = require( '@playwright/test' );
const { loginToWordPress, goToAdminDashboard } = require( './utils/wp-admin' );

// ---------------------------------------------------------------------------
// Shared mock data
// ---------------------------------------------------------------------------

/** A minimal scheduled automation returned by the REST API. */
const MOCK_AUTOMATION = {
	id: 1,
	name: 'Daily Site Health Report',
	description: 'Run a comprehensive site health check.',
	prompt: 'Check site health and report issues.',
	schedule: 'daily',
	tool_profile: '',
	max_iterations: 10,
	enabled: true,
	notification_channels: [],
	run_count: 3,
	last_run_at: '2026-03-18 08:00:00',
	next_run_at: null,
	created_at: '2026-01-01 00:00:00',
	updated_at: '2026-03-18 08:00:00',
};

/** A minimal event automation returned by the REST API. */
const MOCK_EVENT = {
	id: 1,
	name: 'Auto-tag new posts',
	description: 'Tag posts automatically when published.',
	hook_name: 'transition_post_status',
	prompt_template: 'Tag the post {{post_title}} with relevant categories.',
	conditions: { post_type: 'post', new_status: 'publish' },
	tool_profile: '',
	max_iterations: 10,
	enabled: true,
	run_count: 5,
	last_run_at: '2026-03-17 12:00:00',
	created_at: '2026-01-01 00:00:00',
	updated_at: '2026-03-17 12:00:00',
};

/** A minimal event trigger definition. */
const MOCK_TRIGGER = {
	hook_name: 'transition_post_status',
	label: 'Post status changed',
	// Description must match the real EventTriggerRegistry value so the test
	// passes whether the mock intercepts or the real server responds.
	description: 'Fires when a post status transitions (e.g. draft to publish).',
	category: 'content',
	placeholders: [
		{ key: 'post_title', description: 'The post title' },
		{ key: 'post_id', description: 'The post ID' },
	],
	conditions: [
		{ key: 'post_type', description: 'Post type' },
		{ key: 'new_status', description: 'New status' },
	],
};

/** Automation templates returned by the REST API. */
const MOCK_TEMPLATES = [
	{
		name: 'Daily Site Health Report',
		description: 'Run a comprehensive automated site health check.',
		prompt: 'Check site health.',
		schedule: 'daily',
		tool_profile: 'site-health',
	},
];

// ---------------------------------------------------------------------------
// Route-mocking helpers
// ---------------------------------------------------------------------------

/**
 * Minimal settings object returned by the /settings endpoint.
 *
 * The SettingsApp component blocks rendering until settingsLoaded is true,
 * which requires a successful response from /gratis-ai-agent/v1/settings.
 * Without this mock the page stays in a loading spinner and all tab content
 * (including the automations and events managers) is never rendered.
 */
const MOCK_SETTINGS = {
	default_provider: '',
	default_model: '',
	max_iterations: 10,
	greeting_message: '',
	keyboard_shortcut: 'alt+a',
	yolo_mode: false,
	show_on_frontend: false,
	show_token_costs: true,
	auto_memory: false,
	knowledge_enabled: false,
	knowledge_auto_index: false,
	system_prompt: '',
	temperature: 0.7,
	max_output_tokens: 4096,
	context_window_default: 128000,
	tool_discovery_mode: 'auto',
	tool_discovery_threshold: 20,
	budget_daily_cap: 0,
	budget_monthly_cap: 0,
	budget_warning_threshold: 80,
	budget_exceeded_action: 'pause',
	image_generation_size: '1024x1024',
	image_generation_quality: 'standard',
	image_generation_style: 'vivid',
	tool_permissions: {},
	_defaults: {},
	_provider_keys: {},
};

/**
 * Install REST API mocks for the automations endpoints.
 *
 * Intercepts all /wp-json/gratis-ai-agent/v1/* requests and returns
 * controlled JSON responses so tests run without a live WordPress back-end.
 *
 * Critically, this also mocks the /settings, /providers, /abilities, and
 * /settings/google-analytics endpoints that SettingsApp fetches on mount.
 * Without these mocks the settings page stays in a loading spinner and the
 * tab content (AutomationsManager, EventsManager) is never rendered.
 *
 * @param {import('@playwright/test').Page} page         - Playwright page.
 * @param {object}                          [overrides]  - Per-endpoint overrides.
 * @param {Array}                           [overrides.automations]          - List response.
 * @param {Array}                           [overrides.eventAutomations]     - List response.
 * @param {Array}                           [overrides.triggers]             - Triggers list.
 * @param {Array}                           [overrides.templates]            - Templates list.
 * @param {object}                          [overrides.createdAutomation]    - POST response.
 * @param {object}                          [overrides.createdEvent]         - POST response.
 * @param {object}                          [overrides.alerts]               - Alerts response.
 */
async function mockAutomationRoutes( page, overrides = {} ) {
	const {
		automations = [ MOCK_AUTOMATION ],
		eventAutomations = [ MOCK_EVENT ],
		triggers = [ MOCK_TRIGGER ],
		templates = MOCK_TEMPLATES,
		createdAutomation = { ...MOCK_AUTOMATION, id: 99 },
		createdEvent = { ...MOCK_EVENT, id: 99 },
		alerts = { count: 0, alerts: [] },
	} = overrides;

	// Clear any previously registered route handlers so that re-registering
	// in beforeEach (or within a test body) does not stack handlers from prior
	// tests. Playwright evaluates handlers LIFO, so stale handlers from earlier
	// tests would otherwise shadow the current mock data.
	await page.unrouteAll( { behavior: 'ignoreErrors' } );

	// Match both pretty-permalink (/wp-json/...) and plain-permalink
	// (?rest_route=...) REST URL forms so mocks work regardless of how
	// wp-env configures WordPress permalinks.
	await page.route( /gratis-ai-agent\/v1\//, async ( route ) => {
		const url = route.request().url();
		const method = route.request().method();

		// ----------------------------------------------------------------
		// Settings-page bootstrap endpoints.
		// SettingsApp calls these on mount; without responses it stays in
		// a loading spinner and never renders the tab content.
		// ----------------------------------------------------------------

		// Google Analytics credential status (fetched in useEffect).
		if ( url.includes( '/settings/google-analytics' ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					has_credentials: false,
					has_property_id: false,
					property_id: '',
					has_service_key: false,
				} ),
			} );
		}

		// Settings — must respond before SettingsApp renders tab content.
		if ( url.match( /\/settings$/ ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( MOCK_SETTINGS ),
			} );
		}

		// Providers list.
		if ( url.match( /\/providers$/ ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( [] ),
			} );
		}

		// Abilities list.
		if ( url.match( /\/abilities$/ ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( [] ),
			} );
		}

		// ----------------------------------------------------------------
		// Automations-specific endpoints.
		// ----------------------------------------------------------------

		// Alerts endpoint — used by the FAB badge.
		if ( url.includes( '/alerts' ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( alerts ),
			} );
		}

		// Tool profiles — used by both managers.
		if ( url.includes( '/tool-profiles' ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( [] ),
			} );
		}

		// Automation templates.
		if ( url.includes( '/automation-templates' ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( templates ),
			} );
		}

		// Event triggers registry.
		if ( url.includes( '/event-triggers' ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( triggers ),
			} );
		}

		// Event automations CRUD.
		if ( url.includes( '/event-automations' ) ) {
			if ( method === 'POST' ) {
				return route.fulfill( {
					status: 201,
					contentType: 'application/json',
					body: JSON.stringify( createdEvent ),
				} );
			}
			if ( method === 'PATCH' || method === 'DELETE' ) {
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( { success: true } ),
				} );
			}
			// GET /event-automations or /event-automations/:id
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify(
					url.match( /\/event-automations\/\d+/ )
						? eventAutomations[ 0 ] || MOCK_EVENT
						: eventAutomations
				),
			} );
		}

		// Automation logs.
		if ( url.includes( '/automation-logs' ) ) {
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( [] ),
			} );
		}

		// Scheduled automations CRUD and run.
		if ( url.includes( '/automations' ) ) {
			if ( method === 'POST' ) {
				// /automations/:id/run
				if ( url.match( /\/automations\/\d+\/run/ ) ) {
					return route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( {
							success: true,
							message: 'Automation ran successfully.',
						} ),
					} );
				}
				return route.fulfill( {
					status: 201,
					contentType: 'application/json',
					body: JSON.stringify( createdAutomation ),
				} );
			}
			if ( method === 'PATCH' || method === 'DELETE' ) {
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( { success: true } ),
				} );
			}
			// GET /automations or /automations/:id
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify(
					url.match( /\/automations\/\d+/ )
						? automations[ 0 ] || MOCK_AUTOMATION
						: automations
				),
			} );
		}

		// Fall through — let other endpoints (sessions, nonces, etc.) pass.
		return route.continue();
	} );
}

/**
 * Navigate to the Settings page and activate the Automations tab.
 *
 * Waits for the AutomationsManager container to be visible rather than
 * relying solely on networkidle, which can resolve before React finishes
 * rendering the tab content.
 *
 * @param {import('@playwright/test').Page} page - Playwright page.
 */
async function goToAutomationsTab( page ) {
	await page.goto( '/wp-admin/tools.php?page=gratis-ai-agent-settings' );
	await page.waitForLoadState( 'networkidle' );
	const tab = page.getByRole( 'tab', { name: /automations/i } );
	await tab.click();
	// Wait for the manager container to confirm the tab content has rendered.
	await page
		.locator( '.ai-agent-automations-manager' )
		.waitFor( { state: 'visible', timeout: 15_000 } );
}

/**
 * Navigate to the Settings page and activate the Events tab.
 *
 * Waits for the EventsManager container to be visible rather than
 * relying solely on networkidle, which can resolve before React finishes
 * rendering the tab content.
 *
 * @param {import('@playwright/test').Page} page - Playwright page.
 */
async function goToEventsTab( page ) {
	await page.goto( '/wp-admin/tools.php?page=gratis-ai-agent-settings' );
	await page.waitForLoadState( 'networkidle' );
	const tab = page.getByRole( 'tab', { name: /events/i } );
	await tab.click();
	// Wait for the manager container to confirm the tab content has rendered.
	await page
		.locator( '.ai-agent-events-manager' )
		.waitFor( { state: 'visible', timeout: 15_000 } );
}

// ---------------------------------------------------------------------------
// Scheduled Automations
// ---------------------------------------------------------------------------

test.describe( 'Scheduled Automations (t080)', () => {
	test.beforeEach( async ( { page } ) => {
		// Install route mocks BEFORE login so the floating widget's fetchAlerts()
		// and SettingsApp bootstrap requests are intercepted from the first page load.
		await mockAutomationRoutes( page );
		await loginToWordPress( page );
	} );

	test( 'automations tab renders the manager heading', async ( { page } ) => {
		await goToAutomationsTab( page );

		const manager = page.locator( '.ai-agent-automations-manager' );
		await expect( manager ).toBeVisible();

		// Heading text.
		await expect(
			page.getByRole( 'heading', { name: /scheduled automations/i } )
		).toBeVisible();
	} );

	test( 'automation list displays existing automations', async ( { page } ) => {
		await goToAutomationsTab( page );

		// The mock returns one automation — its name should appear in a card.
		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_AUTOMATION.name } );
		await expect( card ).toBeVisible();
	} );

	test( 'automation card shows schedule badge', async ( { page } ) => {
		await goToAutomationsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_AUTOMATION.name } );

		// Schedule badge (e.g. "daily").
		const badge = card.locator( '.ai-agent-skill-badge' ).first();
		await expect( badge ).toContainText( MOCK_AUTOMATION.schedule );
	} );

	test( 'automation card shows run count', async ( { page } ) => {
		await goToAutomationsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_AUTOMATION.name } );

		await expect( card ).toContainText(
			`${ MOCK_AUTOMATION.run_count } runs`
		);
	} );

	test( 'Add Automation button opens the creation form', async ( { page } ) => {
		await goToAutomationsTab( page );

		const addButton = page.getByRole( 'button', {
			name: /add automation/i,
		} );
		await expect( addButton ).toBeVisible();
		await addButton.click();

		// Form fields should appear.
		const form = page.locator( '.ai-agent-skill-form' );
		await expect( form ).toBeVisible();

		// Name and Prompt fields are required.
		await expect( form.getByLabel( /^name/i ) ).toBeVisible();
		await expect( form.getByLabel( /prompt/i ) ).toBeVisible();
	} );

	test( 'Create button is disabled when Name or Prompt is empty', async ( {
		page,
	} ) => {
		await goToAutomationsTab( page );

		await page
			.getByRole( 'button', { name: /add automation/i } )
			.click();

		const form = page.locator( '.ai-agent-skill-form' );
		const createButton = form.getByRole( 'button', { name: /^create$/i } );

		// Both fields empty → disabled.
		await expect( createButton ).toBeDisabled();

		// Fill only Name → still disabled (Prompt missing).
		await form.getByLabel( /^name/i ).fill( 'My Automation' );
		await expect( createButton ).toBeDisabled();

		// Fill Prompt → enabled.
		await form.getByLabel( /prompt/i ).fill( 'Do something useful.' );
		await expect( createButton ).toBeEnabled();
	} );

	test( 'creating a scheduled automation submits POST and shows success notice', async ( {
		page,
	} ) => {
		// After creation the list re-fetches; return the new item.
		const newAutomation = {
			...MOCK_AUTOMATION,
			id: 99,
			name: 'My New Automation',
			prompt: 'Do something useful.',
		};

		// Track whether the POST was made.
		let postMade = false;
		// Use a function matcher to precisely target the /automations list
		// endpoint (not /automations/ID or /automation-templates).
		// A function matcher is more reliable than a regex with lookahead
		// because it can inspect the parsed URL directly.
		await page.route(
			( url ) => {
				const path = url.pathname || url.toString();
				// Match /automations at the end of the path (with optional
				// trailing slash) but not /automations/ID or /automation-templates.
				return (
					/\/automations\/?$/.test( path ) ||
					/[?&]rest_route=%2Fgratis-ai-agent%2Fv1%2Fautomations\/?$/.test(
						url.toString()
					)
				);
			},
			async ( route ) => {
				if ( route.request().method() === 'POST' ) {
					postMade = true;
					return route.fulfill( {
						status: 201,
						contentType: 'application/json',
						body: JSON.stringify( newAutomation ),
					} );
				}
				// GET after creation returns the new item.
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( [ newAutomation ] ),
				} );
			}
		);

		await goToAutomationsTab( page );

		await page
			.getByRole( 'button', { name: /add automation/i } )
			.click();

		const form = page.locator( '.ai-agent-skill-form' );
		await form.getByLabel( /^name/i ).fill( 'My New Automation' );
		await form.getByLabel( /prompt/i ).fill( 'Do something useful.' );

		await form.getByRole( 'button', { name: /^create$/i } ).click();

		// Success notice should appear.
		await expect(
			page.locator( '.components-notice' ).filter( { hasText: /saved/i } )
		).toBeVisible( { timeout: 10_000 } );

		expect( postMade ).toBe( true );
	} );

	test( 'enable/disable toggle calls PATCH and updates card state', async ( {
		page,
	} ) => {
		// Start with an enabled automation.
		let patchCalled = false;
		let patchBody = null;

		await page.route(
			( url ) => {
				const path = url.pathname || url.toString();
				return /\/automations\/1\/?$/.test( path );
			},
			async ( route ) => {
				if ( route.request().method() === 'PATCH' ) {
					patchCalled = true;
					patchBody = JSON.parse( route.request().postData() || '{}' );
					return route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( { success: true } ),
					} );
				}
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( MOCK_AUTOMATION ),
				} );
			}
		);

		await goToAutomationsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_AUTOMATION.name } );

		// The ToggleControl inside the card header.
		const toggle = card.locator( 'input[type="checkbox"]' ).first();
		await expect( toggle ).toBeChecked(); // enabled by default.

		await toggle.click();

		// PATCH should have been called with enabled: false.
		await expect
			.poll( () => patchCalled, { timeout: 5_000 } )
			.toBe( true );
		expect( patchBody ).toMatchObject( { enabled: false } );
	} );

	test( 'disabled automation card has disabled CSS class', async ( {
		page,
	} ) => {
		const disabledAutomation = { ...MOCK_AUTOMATION, enabled: false };
		await mockAutomationRoutes( page, {
			automations: [ disabledAutomation ],
		} );

		await goToAutomationsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: disabledAutomation.name } );

		await expect( card ).toHaveClass( /ai-agent-skill-card--disabled/ );
	} );

	test( 'Cancel button hides the creation form', async ( { page } ) => {
		await goToAutomationsTab( page );

		await page
			.getByRole( 'button', { name: /add automation/i } )
			.click();

		const form = page.locator( '.ai-agent-skill-form' );
		await expect( form ).toBeVisible();

		await form.getByRole( 'button', { name: /cancel/i } ).click();

		await expect( form ).not.toBeVisible();
	} );

	test( 'Schedule select has expected options', async ( { page } ) => {
		await goToAutomationsTab( page );

		await page
			.getByRole( 'button', { name: /add automation/i } )
			.click();

		const form = page.locator( '.ai-agent-skill-form' );
		const scheduleSelect = form.getByLabel( /schedule/i );

		// Verify the four standard WP cron schedules are present.
		// Use value-attribute selectors rather than :has-text() to avoid
		// substring matches (e.g. "Daily" would match "Twice Daily").
		for ( const [ label, value ] of [
			[ 'Hourly', 'hourly' ],
			[ 'Twice Daily', 'twicedaily' ],
			[ 'Daily', 'daily' ],
			[ 'Weekly', 'weekly' ],
		] ) {
			await expect(
				scheduleSelect.locator( `option[value="${ value }"]` )
			).toHaveCount( 1, { message: `Expected option "${ label }" to exist` } );
		}
	} );

	test( 'empty state shows when no automations exist', async ( { page } ) => {
		await mockAutomationRoutes( page, { automations: [], templates: [] } );

		await goToAutomationsTab( page );

		// No automation cards should be rendered (templates also use
		// .ai-agent-skill-card, so we check the automations list container
		// directly — it is only rendered when automations.length > 0).
		await expect(
			page.locator( '.ai-agent-automations-manager .ai-agent-skill-cards' )
		).toHaveCount( 0 );
	} );

	test( 'Use Template button pre-fills the form', async ( { page } ) => {
		await mockAutomationRoutes( page, { automations: [] } );

		await goToAutomationsTab( page );

		const useTemplateButton = page
			.getByRole( 'button', { name: /use template/i } )
			.first();
		await expect( useTemplateButton ).toBeVisible();
		await useTemplateButton.click();

		// Form should open pre-filled with the template name.
		const form = page.locator( '.ai-agent-skill-form' );
		await expect( form ).toBeVisible();

		const nameInput = form.getByLabel( /^name/i );
		await expect( nameInput ).toHaveValue( MOCK_TEMPLATES[ 0 ].name );
	} );
} );

// ---------------------------------------------------------------------------
// Event-Driven Automations
// ---------------------------------------------------------------------------

test.describe( 'Event-Driven Automations (t081)', () => {
	test.beforeEach( async ( { page } ) => {
		// Install route mocks BEFORE login so the floating widget's fetchAlerts()
		// and SettingsApp bootstrap requests are intercepted from the first page load.
		await mockAutomationRoutes( page );
		await loginToWordPress( page );
	} );

	test( 'events tab renders the manager heading', async ( { page } ) => {
		await goToEventsTab( page );

		const manager = page.locator( '.ai-agent-events-manager' );
		await expect( manager ).toBeVisible();

		await expect(
			page.getByRole( 'heading', { name: /event-driven automations/i } )
		).toBeVisible();
	} );

	test( 'event list displays existing event automations', async ( {
		page,
	} ) => {
		await goToEventsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_EVENT.name } );
		await expect( card ).toBeVisible();
	} );

	test( 'event card shows hook name badge', async ( { page } ) => {
		await goToEventsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_EVENT.name } );

		const badge = card.locator( '.ai-agent-skill-badge' ).first();
		await expect( badge ).toContainText( MOCK_EVENT.hook_name );
	} );

	test( 'event card shows run count', async ( { page } ) => {
		await goToEventsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_EVENT.name } );

		await expect( card ).toContainText( `${ MOCK_EVENT.run_count } runs` );
	} );

	test( 'Add Event button opens the creation form', async ( { page } ) => {
		await goToEventsTab( page );

		const addButton = page.getByRole( 'button', { name: /add event/i } );
		await expect( addButton ).toBeVisible();
		await addButton.click();

		const form = page.locator( '.ai-agent-skill-form' );
		await expect( form ).toBeVisible();

		// Required fields.
		await expect( form.getByLabel( /^name/i ) ).toBeVisible();
		await expect( form.getByLabel( /trigger hook/i ) ).toBeVisible();
		await expect( form.getByLabel( /prompt template/i ) ).toBeVisible();
	} );

	test( 'Create button is disabled when required fields are empty', async ( {
		page,
	} ) => {
		await goToEventsTab( page );

		await page.getByRole( 'button', { name: /add event/i } ).click();

		const form = page.locator( '.ai-agent-skill-form' );
		const createButton = form.getByRole( 'button', { name: /^create$/i } );

		// All empty → disabled.
		await expect( createButton ).toBeDisabled();

		// Name only → still disabled.
		await form.getByLabel( /^name/i ).fill( 'My Event' );
		await expect( createButton ).toBeDisabled();

		// Name + hook → still disabled (prompt missing).
		const hookSelect = form.getByLabel( /trigger hook/i );
		await hookSelect.selectOption( MOCK_TRIGGER.hook_name );
		await expect( createButton ).toBeDisabled();

		// All three filled → enabled.
		await form
			.getByLabel( /prompt template/i )
			.fill( 'Handle {{post_title}}.' );
		await expect( createButton ).toBeEnabled();
	} );

	test( 'creating an event automation submits POST and shows success notice', async ( {
		page,
	} ) => {
		const newEvent = {
			...MOCK_EVENT,
			id: 99,
			name: 'My New Event',
			prompt_template: 'Handle {{post_title}}.',
		};

		let postMade = false;
		await page.route(
			( url ) => {
				const path = url.pathname || url.toString();
				return /\/event-automations\/?$/.test( path );
			},
			async ( route ) => {
				if ( route.request().method() === 'POST' ) {
					postMade = true;
					return route.fulfill( {
						status: 201,
						contentType: 'application/json',
						body: JSON.stringify( newEvent ),
					} );
				}
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( [ newEvent ] ),
				} );
			}
		);

		await goToEventsTab( page );

		await page.getByRole( 'button', { name: /add event/i } ).click();

		const form = page.locator( '.ai-agent-skill-form' );
		await form.getByLabel( /^name/i ).fill( 'My New Event' );
		await form
			.getByLabel( /trigger hook/i )
			.selectOption( MOCK_TRIGGER.hook_name );
		await form
			.getByLabel( /prompt template/i )
			.fill( 'Handle {{post_title}}.' );

		await form.getByRole( 'button', { name: /^create$/i } ).click();

		await expect(
			page
				.locator( '.components-notice' )
				.filter( { hasText: /saved/i } )
		).toBeVisible( { timeout: 10_000 } );

		expect( postMade ).toBe( true );
	} );

	test( 'trigger hook select is populated from the event-triggers endpoint', async ( {
		page,
	} ) => {
		await goToEventsTab( page );

		await page.getByRole( 'button', { name: /add event/i } ).click();

		const form = page.locator( '.ai-agent-skill-form' );
		const hookSelect = form.getByLabel( /trigger hook/i );

		// The mock trigger should appear as an option.
		const option = hookSelect.locator(
			`option[value="${ MOCK_TRIGGER.hook_name }"]`
		);
		await expect( option ).toHaveCount( 1 );
	} );

	test( 'selecting a trigger shows its description and placeholders', async ( {
		page,
	} ) => {
		await goToEventsTab( page );

		await page.getByRole( 'button', { name: /add event/i } ).click();

		const form = page.locator( '.ai-agent-skill-form' );
		await form
			.getByLabel( /trigger hook/i )
			.selectOption( MOCK_TRIGGER.hook_name );

		// Trigger info block should appear.
		const triggerInfo = form.locator( '.ai-agent-trigger-info' );
		await expect( triggerInfo ).toBeVisible();
		await expect( triggerInfo ).toContainText(
			MOCK_TRIGGER.description
		);

		// Placeholder keys should be listed.
		for ( const placeholder of MOCK_TRIGGER.placeholders ) {
			await expect( triggerInfo ).toContainText(
				`{{${ placeholder.key }}}`
			);
		}
	} );

	test( 'enable/disable toggle calls PATCH for event automation', async ( {
		page,
	} ) => {
		let patchCalled = false;
		let patchBody = null;

		await page.route(
			( url ) => {
				const path = url.pathname || url.toString();
				return /\/event-automations\/1\/?$/.test( path );
			},
			async ( route ) => {
				if ( route.request().method() === 'PATCH' ) {
					patchCalled = true;
					patchBody = JSON.parse( route.request().postData() || '{}' );
					return route.fulfill( {
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify( { success: true } ),
					} );
				}
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( MOCK_EVENT ),
				} );
			}
		);

		await goToEventsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: MOCK_EVENT.name } );

		const toggle = card.locator( 'input[type="checkbox"]' ).first();
		await expect( toggle ).toBeChecked();

		await toggle.click();

		await expect
			.poll( () => patchCalled, { timeout: 5_000 } )
			.toBe( true );
		expect( patchBody ).toMatchObject( { enabled: false } );
	} );

	test( 'disabled event card has disabled CSS class', async ( { page } ) => {
		const disabledEvent = { ...MOCK_EVENT, enabled: false };
		await mockAutomationRoutes( page, {
			eventAutomations: [ disabledEvent ],
		} );

		await goToEventsTab( page );

		const card = page
			.locator( '.ai-agent-skill-card' )
			.filter( { hasText: disabledEvent.name } );

		await expect( card ).toHaveClass( /ai-agent-skill-card--disabled/ );
	} );

	test( 'empty state shows when no event automations exist', async ( {
		page,
	} ) => {
		await mockAutomationRoutes( page, { eventAutomations: [] } );

		await goToEventsTab( page );

		await expect(
			page.getByText( 'No event automations configured yet.' )
		).toBeVisible();
	} );

	test( 'Cancel button hides the event creation form', async ( { page } ) => {
		await goToEventsTab( page );

		await page.getByRole( 'button', { name: /add event/i } ).click();

		const form = page.locator( '.ai-agent-skill-form' );
		await expect( form ).toBeVisible();

		await form.getByRole( 'button', { name: /cancel/i } ).click();

		await expect( form ).not.toBeVisible();
	} );
} );

// ---------------------------------------------------------------------------
// Proactive Alert Badge on FAB
// ---------------------------------------------------------------------------

test.describe( 'Proactive Alert Badge on FAB', () => {
	test.beforeEach( async ( { page } ) => {
		// Install a default mock BEFORE login so the floating widget's initial
		// fetchAlerts() call (triggered when /wp-admin/ loads after login) is
		// intercepted rather than hitting the real backend.
		await mockAutomationRoutes( page );
		await loginToWordPress( page );
	} );

	test( 'FAB badge is hidden when alert count is zero', async ( { page } ) => {
		// Override with test-specific alerts mock (registered after beforeEach mock;
		// Playwright evaluates route handlers LIFO so this takes precedence).
		await mockAutomationRoutes( page, {
			alerts: { count: 0, alerts: [] },
		} );

		await goToAdminDashboard( page );

		const fab = page.locator( '.gratis-ai-agent-fab' );
		await expect( fab ).toBeVisible();

		// Badge should not be present when count is 0.
		const badge = fab.locator( '.gratis-ai-agent-fab-badge' );
		await expect( badge ).toHaveCount( 0 );
	} );

	test( 'FAB badge shows alert count when alerts exist', async ( { page } ) => {
		await mockAutomationRoutes( page, {
			alerts: {
				count: 3,
				alerts: [
					{ type: 'warning', message: 'Plugin updates available.' },
					{ type: 'warning', message: 'Debug mode is enabled.' },
					{ type: 'info', message: 'Disk usage above 80%.' },
				],
			},
		} );

		await goToAdminDashboard( page );

		const fab = page.locator( '.gratis-ai-agent-fab' );
		await expect( fab ).toBeVisible();

		// Badge should appear with the count.
		const badge = fab.locator( '.gratis-ai-agent-fab-badge' );
		await expect( badge ).toBeVisible( { timeout: 10_000 } );
		await expect( badge ).toContainText( '3' );
	} );

	test( 'FAB badge shows "9+" when alert count exceeds 9', async ( {
		page,
	} ) => {
		await mockAutomationRoutes( page, {
			alerts: {
				count: 12,
				alerts: Array.from( { length: 12 }, ( _, i ) => ( {
					type: 'warning',
					message: `Alert ${ i + 1 }`,
				} ) ),
			},
		} );

		await goToAdminDashboard( page );

		const badge = page.locator( '.gratis-ai-agent-fab-badge' );
		await expect( badge ).toBeVisible( { timeout: 10_000 } );
		await expect( badge ).toContainText( '9+' );
	} );

	test( 'FAB badge has accessible aria-label with alert count', async ( {
		page,
	} ) => {
		await mockAutomationRoutes( page, {
			alerts: { count: 2, alerts: [] },
		} );

		await goToAdminDashboard( page );

		const badge = page.locator( '.gratis-ai-agent-fab-badge' );
		await expect( badge ).toBeVisible( { timeout: 10_000 } );

		// aria-label should include the count for screen readers.
		const ariaLabel = await badge.getAttribute( 'aria-label' );
		expect( ariaLabel ).toMatch( /2\s*alert/i );
	} );
} );
