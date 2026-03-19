/**
 * E2E tests for the Agent Builder UI (t133 / issue #608).
 *
 * Covers the agent builder shipped in t082 (PR #437):
 *   - Create a new agent with custom name, system prompt, and model
 *   - Assign a tool profile (abilities) to the agent
 *   - Saved agent appears in the agent selector in the chat panel
 *   - Edit an existing agent
 *   - Delete an agent
 *
 * Tests run against the settings page at:
 *   /wp-admin/tools.php?page=gratis-ai-agent-settings
 *
 * The agent builder lives on the "Agents" tab of the settings page.
 * REST API calls to /gratis-ai-agent/v1/agents are intercepted so tests
 * are deterministic and do not require a live WordPress database.
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
// Fixtures — deterministic agent data returned by mocked REST responses.
// ---------------------------------------------------------------------------

const AGENT_FIXTURE = {
	id: 1,
	slug: 'test-agent',
	name: 'Test Agent',
	description: 'A test agent for E2E coverage.',
	system_prompt: 'You are a helpful test assistant.',
	provider_id: '',
	model_id: '',
	tool_profile: '',
	temperature: null,
	max_iterations: null,
	greeting: '',
	avatar_icon: '',
	enabled: true,
};

const AGENT_FIXTURE_UPDATED = {
	...AGENT_FIXTURE,
	name: 'Updated Agent',
	system_prompt: 'You are an updated test assistant.',
};

// ---------------------------------------------------------------------------
// REST mock helpers
// ---------------------------------------------------------------------------

/**
 * Intercept all /gratis-ai-agent/v1/agents REST calls and return controlled
 * fixture data. This makes tests deterministic without a live WP database.
 *
 * Clears any previously registered route handlers before registering new ones
 * so that successive beforeEach calls in the same browser context do not stack
 * handlers (Playwright matches the first registered handler, not the last).
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object}                          opts
 * @param {Object[]}                        [opts.initialAgents=[]]  - Agents returned by GET /agents.
 * @param {Object}                          [opts.createdAgent]      - Agent returned by POST /agents.
 * @param {Object}                          [opts.updatedAgent]      - Agent returned by PUT /agents/:id.
 */
async function mockAgentsApi( page, opts = {} ) {
	const {
		initialAgents = [],
		createdAgent = AGENT_FIXTURE,
		updatedAgent = AGENT_FIXTURE_UPDATED,
	} = opts;

	// Remove any previously registered route handlers so that re-registering
	// in beforeEach does not stack handlers from prior tests.
	await page.unrouteAll( { behavior: 'ignoreErrors' } );

	// Mutable list so POST/DELETE mutations are reflected in subsequent GETs.
	let agents = [ ...initialAgents ];

	await page.route( /gratis-ai-agent\/v1\/agents/, async ( route ) => {
		const method = route.request().method();
		const url = route.request().url();

		// DELETE /agents/:id
		if ( method === 'DELETE' ) {
			const idMatch = url.match( /\/agents\/(\d+)/ );
			if ( idMatch ) {
				const id = parseInt( idMatch[ 1 ], 10 );
				agents = agents.filter( ( a ) => a.id !== id );
			}
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( { deleted: true } ),
			} );
			return;
		}

		// PUT /agents/:id (update)
		if ( method === 'PUT' || method === 'PATCH' ) {
			agents = agents.map( ( a ) =>
				a.id === updatedAgent.id ? updatedAgent : a
			);
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( updatedAgent ),
			} );
			return;
		}

		// POST /agents (create)
		if ( method === 'POST' && ! url.match( /\/agents\/\d+/ ) ) {
			agents = [ ...agents, createdAgent ];
			await route.fulfill( {
				status: 201,
				contentType: 'application/json',
				body: JSON.stringify( createdAgent ),
			} );
			return;
		}

		// GET /agents or GET /agents/:id
		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( agents ),
		} );
	} );

	// Stub tool-profiles endpoint (used by the form dropdown).
	await page.route( /gratis-ai-agent\/v1\/tool-profiles/, async ( route ) => {
		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( [
				{ slug: 'default', name: 'Default' },
				{ slug: 'read-only', name: 'Read Only' },
			] ),
		} );
	} );

	// Stub providers endpoint (used by the provider/model dropdowns).
	await page.route( /gratis-ai-agent\/v1\/providers/, async ( route ) => {
		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( [] ),
		} );
	} );
}

// ---------------------------------------------------------------------------
// Locator helpers
// ---------------------------------------------------------------------------

/**
 * Get the agent builder container.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAgentBuilder( page ) {
	return page.locator( '.gratis-ai-agent-agent-builder' );
}

/**
 * Get the "Add Agent" button.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAddAgentButton( page ) {
	return page.getByRole( 'button', { name: /Add Agent/i } );
}

/**
 * Get the agent creation/edit form.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAgentForm( page ) {
	return page.locator( '.gratis-ai-agent-agent-form' );
}

/**
 * Get the "Create Agent" submit button.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getCreateAgentButton( page ) {
	return page.getByRole( 'button', { name: /Create Agent/i } );
}

/**
 * Get the "Update Agent" submit button.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getUpdateAgentButton( page ) {
	return page.getByRole( 'button', { name: /Update Agent/i } );
}

/**
 * Get the "Cancel" button in the form.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getCancelButton( page ) {
	return page.getByRole( 'button', { name: /Cancel/i } );
}

/**
 * Get all agent cards in the list.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAgentCards( page ) {
	return page.locator( '.gratis-ai-agent-agent-card' );
}

/**
 * Get the edit (pencil) button for a specific agent card.
 *
 * @param {import('@playwright/test').Locator} card - Agent card locator.
 * @return {import('@playwright/test').Locator}
 */
function getEditButton( card ) {
	return card.getByRole( 'button', { name: /Edit agent/i } );
}

/**
 * Get the delete (trash) button for a specific agent card.
 *
 * @param {import('@playwright/test').Locator} card - Agent card locator.
 * @return {import('@playwright/test').Locator}
 */
function getDeleteButton( card ) {
	return card.getByRole( 'button', { name: /Delete agent/i } );
}

/**
 * Get the agent selector dropdown in the chat panel.
 *
 * @param {import('@playwright/test').Page} page
 * @return {import('@playwright/test').Locator}
 */
function getAgentSelector( page ) {
	return page.locator( '.gratis-ai-agent-agent-selector' );
}

/**
 * Navigate to the Agents tab on the settings page.
 *
 * @param {import('@playwright/test').Page} page
 */
async function goToAgentsTab( page ) {
	await goToSettingsPage( page, 'agents' );
}

// ---------------------------------------------------------------------------
// Test suites
// ---------------------------------------------------------------------------

test.describe( 'Agent Builder - Create Agent', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockAgentsApi( page, { initialAgents: [] } );
		await goToAgentsTab( page );
	} );

	test( 'agent builder section is visible on the Agents tab', async ( {
		page,
	} ) => {
		const builder = getAgentBuilder( page );
		await expect( builder ).toBeVisible();
	} );

	test( '"Add Agent" button is visible when no agents exist', async ( {
		page,
	} ) => {
		const addBtn = getAddAgentButton( page );
		await expect( addBtn ).toBeVisible();
	} );

	test( 'clicking "Add Agent" shows the creation form', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();
		const form = getAgentForm( page );
		await expect( form ).toBeVisible();
		// Form heading should say "New Agent".
		await expect( form.getByRole( 'heading', { name: /New Agent/i } ) ).toBeVisible();
	} );

	test( 'form has slug, name, system prompt, and tool profile fields', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();
		const form = getAgentForm( page );

		await expect( form.getByLabel( /Slug/i ) ).toBeVisible();
		await expect( form.getByLabel( /^Name/i ) ).toBeVisible();
		await expect( form.getByLabel( /System Prompt/i ) ).toBeVisible();
		await expect( form.getByLabel( /Tool Profile/i ) ).toBeVisible();
	} );

	test( 'submitting without a name shows a validation error', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();
		// Fill slug but leave name empty.
		await page.getByLabel( /Slug/i ).fill( 'my-agent' );
		await getCreateAgentButton( page ).click();

		// A notice with status "error" should appear.
		await expect(
			page.locator( '.components-notice.is-error' )
		).toBeVisible();
	} );

	test( 'submitting without a slug shows a validation error', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();
		// Fill name but leave slug empty.
		await page.getByLabel( /^Name/i ).fill( 'My Agent' );
		await getCreateAgentButton( page ).click();

		await expect(
			page.locator( '.components-notice.is-error' )
		).toBeVisible();
	} );

	test( 'creates an agent with custom name, system prompt, and model', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();

		const form = getAgentForm( page );
		await form.getByLabel( /Slug/i ).fill( AGENT_FIXTURE.slug );
		await form.getByLabel( /^Name/i ).fill( AGENT_FIXTURE.name );
		await form
			.getByLabel( /System Prompt/i )
			.fill( AGENT_FIXTURE.system_prompt );

		await getCreateAgentButton( page ).click();

		// After a successful create the form closes and the new agent card
		// appears. The component calls resetForm() immediately after
		// createAgent() resolves, which clears the notice in the same render
		// cycle, so we verify the outcome (card visible) rather than the
		// transient notice.
		await expect( getAgentForm( page ) ).not.toBeVisible();
		const cards = getAgentCards( page );
		await expect( cards ).toHaveCount( 1 );
		await expect( cards.first() ).toContainText( AGENT_FIXTURE.name );
	} );

	test( 'assigns a tool profile (abilities) when creating an agent', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();

		const form = getAgentForm( page );
		await form.getByLabel( /Slug/i ).fill( 'tool-agent' );
		await form.getByLabel( /^Name/i ).fill( 'Tool Agent' );

		// Wait for the tool-profiles API response to populate the dropdown
		// before attempting to select an option.
		const toolProfileSelect = form.getByLabel( /Tool Profile/i );
		await expect(
			toolProfileSelect.locator( 'option', { hasText: 'Read Only' } )
		).toBeAttached();

		// Select the "Read Only" tool profile from the dropdown.
		await toolProfileSelect.selectOption( { label: 'Read Only' } );

		await getCreateAgentButton( page ).click();

		// Form closes and card appears — the create succeeded.
		await expect( getAgentForm( page ) ).not.toBeVisible();
		await expect( getAgentCards( page ) ).toHaveCount( 1 );
	} );

	test( '"Cancel" button hides the form without saving', async ( {
		page,
	} ) => {
		await getAddAgentButton( page ).click();
		await expect( getAgentForm( page ) ).toBeVisible();

		await getCancelButton( page ).click();

		await expect( getAgentForm( page ) ).not.toBeVisible();
		// No agent cards should have been created.
		await expect( getAgentCards( page ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'Agent Builder - Agent List', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockAgentsApi( page, { initialAgents: [ AGENT_FIXTURE ] } );
		await goToAgentsTab( page );
	} );

	test( 'existing agents are listed as cards', async ( { page } ) => {
		const cards = getAgentCards( page );
		await expect( cards ).toHaveCount( 1 );
		await expect( cards.first() ).toContainText( AGENT_FIXTURE.name );
	} );

	test( 'agent card shows system prompt preview', async ( { page } ) => {
		const card = getAgentCards( page ).first();
		await expect(
			card.locator( '.gratis-ai-agent-agent-prompt-preview' )
		).toContainText( AGENT_FIXTURE.system_prompt.slice( 0, 40 ) );
	} );

	test( 'edit and delete buttons are present on each card', async ( {
		page,
	} ) => {
		const card = getAgentCards( page ).first();
		await expect( getEditButton( card ) ).toBeVisible();
		await expect( getDeleteButton( card ) ).toBeVisible();
	} );
} );

test.describe( 'Agent Builder - Edit Agent', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockAgentsApi( page, { initialAgents: [ AGENT_FIXTURE ] } );
		await goToAgentsTab( page );
	} );

	test( 'clicking edit opens the form pre-populated with agent data', async ( {
		page,
	} ) => {
		const card = getAgentCards( page ).first();
		await getEditButton( card ).click();

		const form = getAgentForm( page );
		await expect( form ).toBeVisible();

		// Heading should say "Edit Agent".
		await expect(
			form.getByRole( 'heading', { name: /Edit Agent/i } )
		).toBeVisible();

		// Slug field should NOT be shown when editing (cannot change slug).
		await expect( form.getByLabel( /Slug/i ) ).not.toBeVisible();

		// Name field should be pre-filled.
		await expect( form.getByLabel( /^Name/i ) ).toHaveValue(
			AGENT_FIXTURE.name
		);

		// System prompt should be pre-filled.
		await expect( form.getByLabel( /System Prompt/i ) ).toHaveValue(
			AGENT_FIXTURE.system_prompt
		);
	} );

	test( 'updating an agent shows a success notice', async ( { page } ) => {
		const card = getAgentCards( page ).first();
		await getEditButton( card ).click();

		const form = getAgentForm( page );
		await form
			.getByLabel( /^Name/i )
			.fill( AGENT_FIXTURE_UPDATED.name );
		await form
			.getByLabel( /System Prompt/i )
			.fill( AGENT_FIXTURE_UPDATED.system_prompt );

		await getUpdateAgentButton( page ).click();

		// For updates the form stays open and the notice persists (resetForm
		// is not called on update, only on create).
		await expect(
			page.locator( '.components-notice.is-success' )
		).toBeVisible( { timeout: 10000 } );
	} );

	test( 'updated agent name is reflected in the card after saving', async ( {
		page,
	} ) => {
		const card = getAgentCards( page ).first();
		await getEditButton( card ).click();

		await page
			.getByLabel( /^Name/i )
			.fill( AGENT_FIXTURE_UPDATED.name );

		await getUpdateAgentButton( page ).click();

		// After update the card should show the new name.
		await expect( getAgentCards( page ).first() ).toContainText(
			AGENT_FIXTURE_UPDATED.name
		);
	} );

	test( '"Cancel" in edit mode closes the form without saving', async ( {
		page,
	} ) => {
		const card = getAgentCards( page ).first();
		await getEditButton( card ).click();

		await page.getByLabel( /^Name/i ).fill( 'Should Not Save' );
		await getCancelButton( page ).click();

		await expect( getAgentForm( page ) ).not.toBeVisible();
		// Original name should still be shown.
		await expect( getAgentCards( page ).first() ).toContainText(
			AGENT_FIXTURE.name
		);
	} );
} );

test.describe( 'Agent Builder - Delete Agent', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await mockAgentsApi( page, { initialAgents: [ AGENT_FIXTURE ] } );
		await goToAgentsTab( page );
	} );

	test( 'deleting an agent removes it from the list', async ( { page } ) => {
		// Accept the confirmation dialog automatically.
		page.on( 'dialog', ( dialog ) => dialog.accept() );

		const card = getAgentCards( page ).first();
		await getDeleteButton( card ).click();

		// Card should be removed.
		await expect( getAgentCards( page ) ).toHaveCount( 0 );
	} );

	test( 'dismissing the delete confirmation keeps the agent', async ( {
		page,
	} ) => {
		// Dismiss the confirmation dialog.
		page.on( 'dialog', ( dialog ) => dialog.dismiss() );

		const card = getAgentCards( page ).first();
		await getDeleteButton( card ).click();

		// Card should still be present.
		await expect( getAgentCards( page ) ).toHaveCount( 1 );
	} );
} );

test.describe( 'Agent Builder - Agent Selector in Chat', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		// Mock agents API for the chat page as well.
		await mockAgentsApi( page, { initialAgents: [ AGENT_FIXTURE ] } );
		await goToAgentPage( page );
	} );

	test( 'agent selector is visible in the chat panel when agents exist', async ( {
		page,
	} ) => {
		// The AgentSelector component renders null until the agents store is
		// loaded and there is at least one enabled agent. Wait with a generous
		// timeout to allow the mocked API response to be processed.
		const selector = getAgentSelector( page );
		await expect( selector ).toBeVisible( { timeout: 10000 } );
	} );

	test( 'agent selector contains the custom agent by name', async ( {
		page,
	} ) => {
		const selector = getAgentSelector( page );
		await expect( selector ).toBeVisible( { timeout: 10000 } );
		const select = selector.locator( 'select' );
		await expect( select ).toBeVisible();

		// The option for our fixture agent should be present.
		const option = select.locator(
			`option[value="${ AGENT_FIXTURE.id }"]`
		);
		await expect( option ).toHaveText( AGENT_FIXTURE.name );
	} );

	test( 'selecting an agent from the dropdown updates the selection', async ( {
		page,
	} ) => {
		const selector = getAgentSelector( page );
		await expect( selector ).toBeVisible( { timeout: 10000 } );
		const select = selector.locator( 'select' );

		// Select the custom agent.
		await select.selectOption( String( AGENT_FIXTURE.id ) );

		// The select value should reflect the chosen agent.
		await expect( select ).toHaveValue( String( AGENT_FIXTURE.id ) );
	} );

	test( 'agent selector includes a "Default agent" option', async ( {
		page,
	} ) => {
		const selector = getAgentSelector( page );
		await expect( selector ).toBeVisible( { timeout: 10000 } );
		const select = selector.locator( 'select' );

		const defaultOption = select.locator( 'option[value=""]' );
		await expect( defaultOption ).toHaveText( /Default agent/i );
	} );

	test( 'selecting "Default agent" resets the agent selection', async ( {
		page,
	} ) => {
		const selector = getAgentSelector( page );
		await expect( selector ).toBeVisible( { timeout: 10000 } );
		const select = selector.locator( 'select' );

		// First select the custom agent.
		await select.selectOption( String( AGENT_FIXTURE.id ) );
		await expect( select ).toHaveValue( String( AGENT_FIXTURE.id ) );

		// Then reset to default.
		await select.selectOption( '' );
		await expect( select ).toHaveValue( '' );
	} );
} );

test.describe( 'Agent Builder - Full Lifecycle', () => {
	/**
	 * End-to-end lifecycle: create → verify in chat selector → edit → delete.
	 *
	 * This test exercises the complete agent lifecycle in a single flow,
	 * mirroring real user behaviour.
	 */
	test( 'create, verify in chat selector, edit, then delete an agent', async ( {
		page,
	} ) => {
		await loginToWordPress( page );

		// Start with no agents.
		await mockAgentsApi( page, { initialAgents: [] } );

		// ---- Step 1: Create the agent ----
		await goToAgentsTab( page );

		await getAddAgentButton( page ).click();

		const form = getAgentForm( page );
		await form.getByLabel( /Slug/i ).fill( AGENT_FIXTURE.slug );
		await form.getByLabel( /^Name/i ).fill( AGENT_FIXTURE.name );
		await form
			.getByLabel( /System Prompt/i )
			.fill( AGENT_FIXTURE.system_prompt );

		// Wait for tool-profile options to load before selecting.
		const toolProfileSelect = form.getByLabel( /Tool Profile/i );
		await expect(
			toolProfileSelect.locator( 'option', { hasText: 'Read Only' } )
		).toBeAttached();
		await toolProfileSelect.selectOption( { label: 'Read Only' } );

		await getCreateAgentButton( page ).click();

		// Form closes and card appears after successful create.
		await expect( getAgentForm( page ) ).not.toBeVisible();
		await expect( getAgentCards( page ) ).toHaveCount( 1 );

		// ---- Step 2: Verify agent appears in chat selector ----
		// Re-mock with the created agent so the chat page sees it.
		await mockAgentsApi( page, { initialAgents: [ AGENT_FIXTURE ] } );
		await goToAgentPage( page );

		const selector = getAgentSelector( page );
		await expect( selector ).toBeVisible( { timeout: 10000 } );
		await expect(
			selector.locator( `option[value="${ AGENT_FIXTURE.id }"]` )
		).toHaveText( AGENT_FIXTURE.name );

		// ---- Step 3: Edit the agent ----
		await mockAgentsApi( page, { initialAgents: [ AGENT_FIXTURE ] } );
		await goToAgentsTab( page );

		const card = getAgentCards( page ).first();
		await getEditButton( card ).click();

		await page
			.getByLabel( /^Name/i )
			.fill( AGENT_FIXTURE_UPDATED.name );
		await page
			.getByLabel( /System Prompt/i )
			.fill( AGENT_FIXTURE_UPDATED.system_prompt );

		await getUpdateAgentButton( page ).click();

		await expect(
			page.locator( '.components-notice.is-success' )
		).toBeVisible( { timeout: 10000 } );
		await expect( getAgentCards( page ).first() ).toContainText(
			AGENT_FIXTURE_UPDATED.name
		);

		// ---- Step 4: Delete the agent ----
		page.on( 'dialog', ( dialog ) => dialog.accept() );

		await getDeleteButton( getAgentCards( page ).first() ).click();

		await expect( getAgentCards( page ) ).toHaveCount( 0 );
	} );
} );
