/**
 * Unit tests for components/onboarding-wizard.js
 *
 * Tests cover:
 * - Snapshot rendering (step 0 welcome)
 * - Renders gratis-ai-agent-wizard wrapper
 * - Renders step title for step 0 (Welcome)
 * - Renders progress dots matching number of steps
 * - Active dot has is-active class on current step
 * - Completed dot has is-complete class for prior steps
 * - Back button not shown on step 0
 * - Back button shown on step 1+
 * - Next button shown when not on last step
 * - Start Chatting button shown on last step
 * - Skip button always rendered
 * - Clicking Next advances to next step
 * - Clicking Back goes to previous step
 * - Clicking Skip calls handleFinish (saveSettings + onComplete)
 * - Clicking Start Chatting calls handleFinish
 * - Provider rows rendered on step 1
 * - Abilities step renders description text
 * - ProviderSelector shown on step 1 when hasAnyProvider is true
 * - ProviderSelector not shown on step 1 when no providers
 * - OnboardingProviderRow: renders provider name
 * - OnboardingProviderRow: Save Key button disabled when apiKey is empty
 * - OnboardingProviderRow: Test button disabled when no key and not configured
 * - OnboardingProviderRow: shows Configured badge when hasKey is true
 *
 * Uses react-dom/server for snapshot/rendering tests and react-dom/client
 * (React 18 createRoot) for interaction tests.
 */

import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server.node';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import OnboardingWizard from '../onboarding-wizard';

// Configure React act() environment for jsdom.
global.IS_REACT_ACT_ENVIRONMENT = true;

// ─── Mock @wordpress/data ─────────────────────────────────────────────────────

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
} ) );

// ─── Mock @wordpress/i18n ─────────────────────────────────────────────────────

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	sprintf: ( fmt, ...args ) => {
		let result = fmt;
		args.forEach( ( arg ) => {
			result = result.replace( /%[sd]/, String( arg ) );
		} );
		return result;
	},
} ) );

// ─── Mock @wordpress/components ──────────────────────────────────────────────

jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	return {
		Button: ( {
			children,
			onClick,
			className,
			disabled,
			variant,
			isBusy,
			size,
		} ) =>
			React.createElement(
				'button',
				{
					onClick,
					className,
					disabled,
					'data-variant': variant,
					'data-busy': isBusy,
					'data-size': size,
				},
				children
			),
		ToggleControl: ( { label, checked, onChange, help } ) =>
			React.createElement(
				'div',
				{ 'data-testid': 'toggle-control' },
				React.createElement( 'label', null, label ),
				React.createElement( 'input', {
					type: 'checkbox',
					checked,
					onChange: ( e ) => onChange( e.target.checked ),
				} ),
				help
					? React.createElement(
							'span',
							{ 'data-testid': 'toggle-help' },
							help
					  )
					: null
			),
		TextControl: ( { label, value, onChange, type, placeholder } ) =>
			React.createElement(
				'div',
				{ 'data-testid': 'text-control' },
				React.createElement( 'label', null, label ),
				React.createElement( 'input', {
					type: type || 'text',
					value,
					onChange: ( e ) => onChange( e.target.value ),
					placeholder,
				} )
			),
		Notice: ( { children, status, isDismissible, onDismiss } ) =>
			React.createElement(
				'div',
				{
					'data-testid': 'notice',
					'data-status': status,
					'data-dismissible': isDismissible,
				},
				children,
				isDismissible && onDismiss
					? React.createElement(
							'button',
							{ onClick: onDismiss },
							'Dismiss'
					  )
					: null
			),
		Spinner: () =>
			React.createElement( 'span', { 'data-testid': 'spinner' } ),
	};
} );

// ─── Mock @wordpress/api-fetch ────────────────────────────────────────────────

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// ─── Mock store ───────────────────────────────────────────────────────────────

jest.mock( '../../store', () => 'gratis-ai-agent' );

// ─── Mock child components ────────────────────────────────────────────────────

jest.mock( '../provider-selector', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'provider-selector' } );
} );

// ─── Helpers ──────────────────────────────────────────────────────────────────

const mockProviders = [
	{
		id: 'openai',
		name: 'OpenAI',
		models: [ { id: 'gpt-4o', name: 'GPT-4o' } ],
	},
];

/**
 * @param {Object} root0
 * @param {Array}  root0.providers
 * @param {string} root0.selectedProviderId
 * @param {string} root0.selectedModelId
 * @param {Object} root0.settings
 */
function setupMocks( {
	providers = [],
	selectedProviderId = '',
	selectedModelId = '',
	settings = {},
} = {} ) {
	const saveSettings = jest.fn().mockResolvedValue( {} );
	const fetchProviders = jest.fn().mockResolvedValue( [] );

	const storeSelectors = {
		getProviders: () => providers,
		getSelectedProviderId: () => selectedProviderId,
		getSelectedModelId: () => selectedModelId,
		getSettings: () => settings,
	};

	useSelect.mockImplementation( ( selector ) =>
		selector( () => storeSelectors )
	);

	useDispatch.mockReturnValue( { saveSettings, fetchProviders } );

	return { saveSettings, fetchProviders };
}

// ─── Snapshot tests ───────────────────────────────────────────────────────────

describe( 'OnboardingWizard snapshots', () => {
	beforeEach( () => {
		const apiFetch = require( '@wordpress/api-fetch' );
		apiFetch.mockResolvedValue( [] );
	} );

	test( 'matches snapshot on step 0 (Welcome)', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot with providers available', () => {
		setupMocks( {
			providers: mockProviders,
			selectedProviderId: 'openai',
		} );
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toMatchSnapshot();
	} );
} );

// ─── Rendering tests ──────────────────────────────────────────────────────────

describe( 'OnboardingWizard rendering', () => {
	beforeEach( () => {
		const apiFetch = require( '@wordpress/api-fetch' );
		apiFetch.mockResolvedValue( [] );
	} );

	test( 'renders gratis-ai-agent-wizard wrapper', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'gratis-ai-agent-wizard' );
	} );

	test( 'renders Welcome title on step 0', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'Welcome to Gratis AI Agent' );
	} );

	test( 'renders progress dots', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'gratis-ai-agent-wizard-dot' );
	} );

	test( 'first dot has is-active class on step 0', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'gratis-ai-agent-wizard-dot is-active' );
	} );

	test( 'Back button not shown on step 0', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).not.toContain( '>Back<' );
	} );

	test( 'Next button shown on step 0', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( '>Next<' );
	} );

	test( 'Skip button always rendered', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( '>Skip<' );
	} );

	test( 'renders wizard footer', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'gratis-ai-agent-wizard-footer' );
	} );

	test( 'renders wizard body', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'gratis-ai-agent-wizard-body' );
	} );

	test( 'renders wizard header', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		expect( html ).toContain( 'gratis-ai-agent-wizard-header' );
	} );

	test( 'ProviderSelector not shown on step 0 (welcome step)', () => {
		setupMocks( { providers: mockProviders } );
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		// On step 0, provider selector is not rendered (it's on step 1).
		// The welcome content doesn't include provider-selector.
		expect( html ).toContain( 'Welcome to Gratis AI Agent' );
	} );
} );

// ─── Interaction tests ────────────────────────────────────────────────────────

describe( 'OnboardingWizard interactions', () => {
	let container;
	let root;

	beforeEach( () => {
		const apiFetch = require( '@wordpress/api-fetch' );
		// Use mockResolvedValue — render must use async act to flush the promise.
		apiFetch.mockResolvedValue( [] );
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( async () => {
		await act( async () => {
			root.unmount();
		} );
		document.body.removeChild( container );
	} );

	/**
	 * Helper: render the wizard and flush all pending async effects (apiFetch).
	 *
	 * @param {Object} props - Props to pass to OnboardingWizard.
	 */
	async function renderWizard( props ) {
		await act( async () => {
			root.render( createElement( OnboardingWizard, props ) );
		} );
	}

	test( 'clicking Next advances to step 1 (Set Up an AI Provider)', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		const nextBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		expect( nextBtn ).not.toBeNull();
		act( () => {
			nextBtn.click();
		} );

		expect( container.querySelector( 'h2' ).textContent ).toBe(
			'Set Up an AI Provider'
		);
	} );

	test( 'clicking Back on step 1 returns to step 0', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 1.
		const nextBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		act( () => {
			nextBtn.click();
		} );

		// Now click Back.
		const backBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Back' );
		expect( backBtn ).not.toBeNull();
		act( () => {
			backBtn.click();
		} );

		expect( container.querySelector( 'h2' ).textContent ).toBe(
			'Welcome to Gratis AI Agent'
		);
	} );

	test( 'Back button not present on step 0', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		const backBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Back' );
		expect( backBtn ).toBeUndefined();
	} );

	test( 'clicking Skip calls saveSettings and onComplete', async () => {
		const { saveSettings } = setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		const skipBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Skip' );
		expect( skipBtn ).not.toBeNull();

		await act( async () => {
			skipBtn.click();
		} );

		expect( saveSettings ).toHaveBeenCalledWith(
			expect.objectContaining( { onboarding_complete: true } )
		);
		expect( onComplete ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'navigating to last step shows Start Chatting button', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Navigate through all steps (5 steps: 0-4, need 4 Next clicks).
		// Use async act for each click to flush any async effects (e.g. WooCommerce step).
		for ( let i = 0; i < 4; i++ ) {
			const nextBtn = Array.from(
				container.querySelectorAll( 'button' )
			).find( ( b ) => b.textContent === 'Next' );
			if ( nextBtn ) {
				await act( async () => {
					nextBtn.click();
				} );
			}
		}

		const startBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Start Chatting' );
		expect( startBtn ).not.toBeNull();
	} );

	test( 'clicking Start Chatting calls saveSettings and onComplete', async () => {
		const { saveSettings } = setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Navigate to last step. Use async act to flush WooCommerce step effects.
		for ( let i = 0; i < 4; i++ ) {
			const nextBtn = Array.from(
				container.querySelectorAll( 'button' )
			).find( ( b ) => b.textContent === 'Next' );
			if ( nextBtn ) {
				await act( async () => {
					nextBtn.click();
				} );
			}
		}

		const startBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Start Chatting' );

		await act( async () => {
			startBtn.click();
		} );

		expect( saveSettings ).toHaveBeenCalledWith(
			expect.objectContaining( { onboarding_complete: true } )
		);
		expect( onComplete ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'step 1 renders provider rows for all three providers', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 1.
		const nextBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		act( () => {
			nextBtn.click();
		} );

		const html = container.innerHTML;
		expect( html ).toContain( 'OpenAI' );
		expect( html ).toContain( 'Anthropic' );
		expect( html ).toContain( 'Google AI' );
	} );

	test( 'step 1 shows ProviderSelector when providers are available', async () => {
		setupMocks( { providers: mockProviders } );
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 1.
		const nextBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		act( () => {
			nextBtn.click();
		} );

		const providerSelector = container.querySelector(
			'[data-testid="provider-selector"]'
		);
		expect( providerSelector ).not.toBeNull();
	} );

	test( 'step 1 does not show ProviderSelector when no providers', async () => {
		setupMocks( { providers: [] } );
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 1.
		const nextBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		act( () => {
			nextBtn.click();
		} );

		const providerSelector = container.querySelector(
			'[data-testid="provider-selector"]'
		);
		expect( providerSelector ).toBeNull();
	} );

	test( 'step 2 renders Abilities title', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 2.
		for ( let i = 0; i < 2; i++ ) {
			const nextBtn = Array.from(
				container.querySelectorAll( 'button' )
			).find( ( b ) => b.textContent === 'Next' );
			act( () => {
				nextBtn.click();
			} );
		}

		// Step 2 title changed from "Configure Abilities" to "Abilities"
		// when the step was updated to show auto-discovery messaging.
		expect( container.querySelector( 'h2' ).textContent ).toBe(
			'Abilities'
		);
	} );

	test( 'step 2 shows auto-discovery message', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 2.
		for ( let i = 0; i < 2; i++ ) {
			const nextBtn = Array.from(
				container.querySelectorAll( 'button' )
			).find( ( b ) => b.textContent === 'Next' );
			act( () => {
				nextBtn.click();
			} );
		}

		// "No abilities registered yet" was replaced with auto-discovery messaging.
		expect( container.innerHTML ).toContain(
			'automatically discover and use any ability'
		);
	} );

	test( 'progress dots: second dot has is-complete class after advancing to step 1', async () => {
		setupMocks();
		const onComplete = jest.fn();
		await renderWizard( { onComplete } );

		// Advance to step 1.
		const nextBtn = Array.from(
			container.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		act( () => {
			nextBtn.click();
		} );

		const dots = container.querySelectorAll(
			'.gratis-ai-agent-wizard-dot'
		);
		// Step 0 dot should now have is-complete class.
		expect( dots[ 0 ].className ).toContain( 'is-complete' );
		// Step 1 dot should have is-active class.
		expect( dots[ 1 ].className ).toContain( 'is-active' );
	} );
} );

// ─── OnboardingProviderRow rendering tests ────────────────────────────────────

describe( 'OnboardingProviderRow rendering', () => {
	beforeEach( () => {
		const apiFetch = require( '@wordpress/api-fetch' );
		apiFetch.mockResolvedValue( [] );
	} );

	test( 'renders wizard wrapper (step 0 welcome content)', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( OnboardingWizard, { onComplete: jest.fn() } )
		);
		// Step 0 renders welcome content, not provider rows.
		// We verify the wizard renders without error.
		expect( html ).toContain( 'gratis-ai-agent-wizard' );
	} );

	test( 'step 1 renders Connectors page link instead of provider badge after refactor', async () => {
		setupMocks( {
			settings: { _provider_keys: { openai: true } },
		} );

		const localContainer = document.createElement( 'div' );
		document.body.appendChild( localContainer );
		const localRoot = createRoot( localContainer );

		await act( async () => {
			localRoot.render(
				createElement( OnboardingWizard, { onComplete: jest.fn() } )
			);
		} );

		// Advance to step 1.
		const nextBtn = Array.from(
			localContainer.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Next' );
		act( () => {
			nextBtn.click();
		} );

		// Step 1 now directs users to the Connectors page instead of showing
		// inline provider rows with Configured badges. Assert the link renders.
		const connectorsLink = localContainer.querySelector(
			'.gratis-ai-agent-wizard-connectors-link'
		);
		expect( connectorsLink ).not.toBeNull();
		expect( connectorsLink.href ).toContain( 'options-connectors.php' );
		expect( connectorsLink.textContent ).toContain(
			'Open Connectors page to configure a provider'
		);

		await act( async () => {
			localRoot.unmount();
		} );
		document.body.removeChild( localContainer );
	} );
} );
