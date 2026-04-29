/**
 * Unit tests for components/context-indicator.js
 *
 * Tests cover:
 * - Snapshot rendering (normal, warning, high usage)
 * - Returns null when no tokens tracked
 * - Displays formatted token counts
 * - Shows correct percentage
 * - Bar color changes at thresholds (green/yellow/red)
 * - Warning section shown only above 80%
 * - Compact and Clear buttons call correct dispatch actions
 *
 * Uses react-dom/server for snapshot/rendering tests and react-dom/client
 * (React 18 createRoot) for interaction tests.
 */

import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server.node';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import ContextIndicator from '../context-indicator';

// Configure React act() environment for jsdom.
global.IS_REACT_ACT_ENVIRONMENT = true;

// Mock @wordpress/data.
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
} ) );

// Mock @wordpress/i18n.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

// Mock @wordpress/components — minimal Button.
// Use require() inside factory to avoid out-of-scope variable error.
jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	return {
		Button: ( { children, onClick, variant, size } ) =>
			React.createElement(
				'button',
				{ onClick, 'data-variant': variant, 'data-size': size },
				children
			),
	};
} );

// Mock store.
jest.mock( '../../store', () => 'sd-ai-agent' );

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 *
 * @param {Object}  root0
 * @param {number}  root0.percentage
 * @param {boolean} root0.isWarning
 * @param {Object}  root0.tokenUsage
 */
function setupMocks( {
	percentage = 10,
	isWarning = false,
	tokenUsage = { prompt: 1000, completion: 500 },
} = {} ) {
	const clearCurrentSession = jest.fn();
	const compactConversation = jest.fn();

	const storeSelectors = {
		getContextPercentage: () => percentage,
		isContextWarning: () => isWarning,
		getTokenUsage: () => tokenUsage,
	};
	useSelect.mockImplementation( ( selector ) =>
		selector( () => storeSelectors )
	);

	useDispatch.mockReturnValue( { clearCurrentSession, compactConversation } );

	return { clearCurrentSession, compactConversation };
}

// ─── Snapshot tests ───────────────────────────────────────────────────────────

describe( 'ContextIndicator snapshots', () => {
	test( 'matches snapshot with normal usage', () => {
		setupMocks( {
			percentage: 10,
			isWarning: false,
			tokenUsage: { prompt: 1000, completion: 500 },
		} );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot with warning state (>80%)', () => {
		setupMocks( {
			percentage: 85,
			isWarning: true,
			tokenUsage: { prompt: 108800, completion: 5000 },
		} );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot with zero tokens (renders empty string)', () => {
		setupMocks( {
			percentage: 0,
			isWarning: false,
			tokenUsage: { prompt: 0, completion: 0 },
		} );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toMatchSnapshot();
	} );
} );

// ─── Rendering tests ──────────────────────────────────────────────────────────

describe( 'ContextIndicator rendering', () => {
	test( 'renders empty string when both prompt and completion tokens are 0', () => {
		setupMocks( { tokenUsage: { prompt: 0, completion: 0 } } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toBe( '' );
	} );

	test( 'renders content when prompt tokens > 0', () => {
		setupMocks( { tokenUsage: { prompt: 100, completion: 0 } } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html.length ).toBeGreaterThan( 0 );
	} );

	test( 'renders content when completion tokens > 0', () => {
		setupMocks( { tokenUsage: { prompt: 0, completion: 50 } } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html.length ).toBeGreaterThan( 0 );
	} );

	test( 'displays total token count formatted as K (1500 → 1.5K)', () => {
		setupMocks( { tokenUsage: { prompt: 1000, completion: 500 } } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '1.5K' );
	} );

	test( 'formats tokens in millions when >= 1,000,000', () => {
		setupMocks( {
			percentage: 50,
			tokenUsage: { prompt: 1000000, completion: 500000 },
		} );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '1.5M' );
	} );

	test( 'formats tokens as plain number when < 1000', () => {
		setupMocks( {
			percentage: 1,
			tokenUsage: { prompt: 400, completion: 100 },
		} );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '500' );
	} );

	test( 'displays percentage rounded to nearest integer', () => {
		setupMocks( { percentage: 42.7 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '43%' );
	} );

	test( 'clamps percentage display at 100%', () => {
		setupMocks( { percentage: 150 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '100%' );
	} );

	test( 'does not show warning section when isWarning is false', () => {
		setupMocks( { isWarning: false } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).not.toContain( 'Context window is getting full.' );
	} );

	test( 'shows warning section when isWarning is true', () => {
		setupMocks( { isWarning: true } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( 'Context window is getting full.' );
	} );

	test( 'shows Compact and New Chat buttons in warning state', () => {
		setupMocks( { isWarning: true } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( 'Compact' );
		expect( html ).toContain( 'New Chat' );
	} );

	test( 'bar fill has green color at low usage (<= 70%)', () => {
		setupMocks( { percentage: 50 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '#00a32a' );
	} );

	test( 'bar fill has yellow color at medium usage (70-80%)', () => {
		setupMocks( { percentage: 75 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '#dba617' );
	} );

	test( 'bar fill has red color at high usage (> 80%)', () => {
		setupMocks( { percentage: 85 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( '#d63638' );
	} );

	test( 'bar fill width matches clamped percentage', () => {
		setupMocks( { percentage: 60 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( 'width:60%' );
	} );

	test( 'bar fill width is clamped to 100% when percentage exceeds 100', () => {
		setupMocks( { percentage: 150 } );
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( 'width:100%' );
	} );

	test( 'renders sd-ai-agent-context-indicator wrapper', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( 'sd-ai-agent-context-indicator' );
	} );

	test( 'renders sd-ai-agent-context-bar-track element', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( ContextIndicator, {} )
		);
		expect( html ).toContain( 'sd-ai-agent-context-bar-track' );
	} );
} );

// ─── Interaction tests ────────────────────────────────────────────────────────

describe( 'ContextIndicator interactions', () => {
	let container;
	let root;

	beforeEach( () => {
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => {
			root.unmount();
		} );
		document.body.removeChild( container );
	} );

	test( 'Compact button calls compactConversation', () => {
		const { compactConversation } = setupMocks( { isWarning: true } );

		act( () => {
			root.render( createElement( ContextIndicator, {} ) );
		} );

		const buttons = container.querySelectorAll( 'button' );
		const compactBtn = Array.from( buttons ).find(
			( b ) => b.textContent === 'Compact'
		);
		expect( compactBtn ).toBeDefined();
		act( () => {
			compactBtn.click();
		} );
		expect( compactConversation ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'New Chat button calls clearCurrentSession', () => {
		const { clearCurrentSession } = setupMocks( { isWarning: true } );

		act( () => {
			root.render( createElement( ContextIndicator, {} ) );
		} );

		const buttons = container.querySelectorAll( 'button' );
		const newChatBtn = Array.from( buttons ).find(
			( b ) => b.textContent === 'New Chat'
		);
		expect( newChatBtn ).toBeDefined();
		act( () => {
			newChatBtn.click();
		} );
		expect( clearCurrentSession ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'no buttons rendered when isWarning is false', () => {
		setupMocks( { isWarning: false } );

		act( () => {
			root.render( createElement( ContextIndicator, {} ) );
		} );

		const buttons = container.querySelectorAll( 'button' );
		expect( buttons.length ).toBe( 0 );
	} );
} );
