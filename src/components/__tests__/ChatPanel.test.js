/**
 * Unit tests for components/ChatPanel.js
 *
 * Tests cover:
 * - Snapshot rendering (default and compact modes)
 * - Renders gratis-ai-agent-chat-panel wrapper
 * - Applies is-compact class in compact mode
 * - Renders DEBUG badge when debugMode is true
 * - Does not render DEBUG badge when debugMode is false
 * - Renders YOLO badge when yoloMode is true
 * - Does not render YOLO badge when yoloMode is false
 * - Auto-confirms pending tool call when YOLO mode is active
 * - TTS button rendered when isTTSSupported is true
 * - TTS button not rendered when isTTSSupported is false
 * - TTS button has is-active class when ttsEnabled is true
 * - TTS button does not have is-active class when ttsEnabled is false
 * - Clicking TTS button calls setTtsEnabled with toggled value
 * - ToolConfirmationDialog rendered when pendingConfirmation and not yoloMode
 * - ToolConfirmationDialog not rendered when yoloMode is true
 *
 * Uses react-dom/server for snapshot/rendering tests and react-dom/client
 * (React 18 createRoot) for interaction tests.
 */

import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server.node';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import ChatPanel from '../ChatPanel';

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
} ) );

// ─── Mock @wordpress/components ──────────────────────────────────────────────

jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	return {
		Button: ( { children, onClick, className, label, icon } ) =>
			React.createElement(
				'button',
				{ onClick, className, 'aria-label': label },
				icon || null,
				children
			),
		Tooltip: ( { children } ) => children,
	};
} );

// ─── Mock store ───────────────────────────────────────────────────────────────

jest.mock( '../../store', () => 'gratis-ai-agent' );

// ─── Mock child components ────────────────────────────────────────────────────

jest.mock( '../error-boundary', () => {
	const React = require( 'react' );
	return ( { children } ) =>
		React.createElement(
			'div',
			{ 'data-testid': 'error-boundary' },
			children
		);
} );

jest.mock( '../provider-selector', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'provider-selector' } );
} );

jest.mock( '../agent-selector', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'agent-selector' } );
} );

jest.mock( '../message-list', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'message-list' } );
} );

jest.mock( '../message-input', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'message-input' } );
} );

jest.mock( '../context-indicator', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'context-indicator' } );
} );

jest.mock( '../tool-confirmation-dialog', () => {
	const React = require( 'react' );
	return ( { confirmation, onConfirm, onReject } ) =>
		React.createElement(
			'div',
			{
				'data-testid': 'tool-confirmation-dialog',
				'data-job-id': confirmation?.jobId,
			},
			React.createElement(
				'button',
				{ onClick: () => onConfirm( false ) },
				'Confirm'
			),
			React.createElement( 'button', { onClick: onReject }, 'Reject' )
		);
} );

jest.mock( '../budget-indicator', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'budget-indicator' } );
} );

jest.mock( '../token-counter', () => {
	const React = require( 'react' );
	return () =>
		React.createElement( 'div', { 'data-testid': 'token-counter' } );
} );

// ─── Mock use-text-to-speech ─────────────────────────────────────────────────

// Default: TTS not supported. Individual tests override as needed.
let mockIsTTSSupported = false;

jest.mock( '../use-text-to-speech', () => ( {
	get isTTSSupported() {
		return mockIsTTSSupported;
	},
} ) );

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @param {Object}  root0
 * @param {boolean} root0.debugMode
 * @param {boolean} root0.yoloMode
 * @param {boolean} root0.ttsEnabled
 * @param {Object}  root0.pendingConfirmation
 */
function setupMocks( {
	debugMode = false,
	yoloMode = false,
	ttsEnabled = false,
	pendingConfirmation = null,
} = {} ) {
	const confirmToolCall = jest.fn();
	const rejectToolCall = jest.fn();
	const setTtsEnabled = jest.fn();

	const storeSelectors = {
		getPendingConfirmation: () => pendingConfirmation,
		isDebugMode: () => debugMode,
		isYoloMode: () => yoloMode,
		isTtsEnabled: () => ttsEnabled,
	};

	useSelect.mockImplementation( ( selector ) =>
		selector( () => storeSelectors )
	);

	useDispatch.mockReturnValue( {
		confirmToolCall,
		rejectToolCall,
		setTtsEnabled,
	} );

	return { confirmToolCall, rejectToolCall, setTtsEnabled };
}

// ─── Snapshot tests ───────────────────────────────────────────────────────────

describe( 'ChatPanel snapshots', () => {
	beforeEach( () => {
		mockIsTTSSupported = false;
	} );

	test( 'matches snapshot in default mode', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot in compact mode', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( ChatPanel, { compact: true } )
		);
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot with debug mode enabled', () => {
		setupMocks( { debugMode: true } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot with YOLO mode enabled', () => {
		setupMocks( { yoloMode: true } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toMatchSnapshot();
	} );
} );

// ─── Rendering tests ──────────────────────────────────────────────────────────

describe( 'ChatPanel rendering', () => {
	beforeEach( () => {
		mockIsTTSSupported = false;
	} );

	test( 'renders gratis-ai-agent-chat-panel wrapper', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'gratis-ai-agent-chat-panel' );
	} );

	test( 'applies is-compact class in compact mode', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( ChatPanel, { compact: true } )
		);
		expect( html ).toContain( 'is-compact' );
	} );

	test( 'does not apply is-compact class in default mode', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).not.toContain( 'is-compact' );
	} );

	test( 'renders DEBUG badge when debugMode is true', () => {
		setupMocks( { debugMode: true } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'DEBUG' );
		expect( html ).toContain( 'gratis-ai-agent-debug-badge' );
	} );

	test( 'does not render DEBUG badge when debugMode is false', () => {
		setupMocks( { debugMode: false } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).not.toContain( 'gratis-ai-agent-debug-badge' );
	} );

	test( 'renders YOLO badge when yoloMode is true', () => {
		setupMocks( { yoloMode: true } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'YOLO' );
		expect( html ).toContain( 'gratis-ai-agent-yolo-badge' );
	} );

	test( 'does not render YOLO badge when yoloMode is false', () => {
		setupMocks( { yoloMode: false } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).not.toContain( 'gratis-ai-agent-yolo-badge' );
	} );

	test( 'renders ToolConfirmationDialog when pendingConfirmation and not yoloMode', () => {
		setupMocks( {
			pendingConfirmation: { jobId: 'job-123' },
			yoloMode: false,
		} );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'tool-confirmation-dialog' );
	} );

	test( 'does not render ToolConfirmationDialog when yoloMode is true', () => {
		setupMocks( {
			pendingConfirmation: { jobId: 'job-123' },
			yoloMode: true,
		} );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).not.toContain( 'tool-confirmation-dialog' );
	} );

	test( 'does not render ToolConfirmationDialog when no pendingConfirmation', () => {
		setupMocks( { pendingConfirmation: null, yoloMode: false } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).not.toContain( 'tool-confirmation-dialog' );
	} );

	test( 'renders TTS button when isTTSSupported is true', () => {
		mockIsTTSSupported = true;
		setupMocks();
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'gratis-ai-agent-tts-btn' );
	} );

	test( 'does not render TTS button when isTTSSupported is false', () => {
		mockIsTTSSupported = false;
		setupMocks();
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).not.toContain( 'gratis-ai-agent-tts-btn' );
	} );

	test( 'TTS button has is-active class when ttsEnabled is true', () => {
		mockIsTTSSupported = true;
		setupMocks( { ttsEnabled: true } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'gratis-ai-agent-tts-btn is-active' );
	} );

	test( 'TTS button does not have is-active class when ttsEnabled is false', () => {
		mockIsTTSSupported = true;
		setupMocks( { ttsEnabled: false } );
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'gratis-ai-agent-tts-btn' );
		expect( html ).not.toContain( 'gratis-ai-agent-tts-btn is-active' );
	} );

	test( 'renders gratis-ai-agent-header wrapper', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( ChatPanel, {} ) );
		expect( html ).toContain( 'gratis-ai-agent-header' );
	} );
} );

// ─── Interaction tests ────────────────────────────────────────────────────────

describe( 'ChatPanel interactions', () => {
	let container;
	let root;

	beforeEach( () => {
		mockIsTTSSupported = false;
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

	test( 'clicking TTS button calls setTtsEnabled with toggled value (false → true)', () => {
		mockIsTTSSupported = true;
		const { setTtsEnabled } = setupMocks( { ttsEnabled: false } );

		act( () => {
			root.render( createElement( ChatPanel, {} ) );
		} );

		const ttsBtn = container.querySelector( '.gratis-ai-agent-tts-btn' );
		expect( ttsBtn ).not.toBeNull();
		act( () => {
			ttsBtn.click();
		} );
		expect( setTtsEnabled ).toHaveBeenCalledWith( true );
	} );

	test( 'clicking TTS button calls setTtsEnabled with toggled value (true → false)', () => {
		mockIsTTSSupported = true;
		const { setTtsEnabled } = setupMocks( { ttsEnabled: true } );

		act( () => {
			root.render( createElement( ChatPanel, {} ) );
		} );

		const ttsBtn = container.querySelector( '.gratis-ai-agent-tts-btn' );
		expect( ttsBtn ).not.toBeNull();
		act( () => {
			ttsBtn.click();
		} );
		expect( setTtsEnabled ).toHaveBeenCalledWith( false );
	} );

	test( 'auto-confirms pending tool call when YOLO mode is active', () => {
		const pendingConfirmation = { jobId: 'job-yolo-42' };
		const { confirmToolCall } = setupMocks( {
			yoloMode: true,
			pendingConfirmation,
		} );

		act( () => {
			root.render( createElement( ChatPanel, {} ) );
		} );

		// useEffect fires after render — confirmToolCall should be called.
		expect( confirmToolCall ).toHaveBeenCalledWith( 'job-yolo-42', false );
	} );

	test( 'does not auto-confirm when YOLO mode is inactive', () => {
		const pendingConfirmation = { jobId: 'job-123' };
		const { confirmToolCall } = setupMocks( {
			yoloMode: false,
			pendingConfirmation,
		} );

		act( () => {
			root.render( createElement( ChatPanel, {} ) );
		} );

		expect( confirmToolCall ).not.toHaveBeenCalled();
	} );

	test( 'Confirm button in ToolConfirmationDialog calls confirmToolCall', () => {
		const pendingConfirmation = { jobId: 'job-confirm-test' };
		const { confirmToolCall } = setupMocks( {
			pendingConfirmation,
			yoloMode: false,
		} );

		act( () => {
			root.render( createElement( ChatPanel, {} ) );
		} );

		const dialog = container.querySelector(
			'[data-testid="tool-confirmation-dialog"]'
		);
		expect( dialog ).not.toBeNull();
		const confirmBtn = Array.from(
			dialog.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Confirm' );
		act( () => {
			confirmBtn.click();
		} );
		expect( confirmToolCall ).toHaveBeenCalledWith(
			'job-confirm-test',
			false
		);
	} );

	test( 'Reject button in ToolConfirmationDialog calls rejectToolCall', () => {
		const pendingConfirmation = { jobId: 'job-reject-test' };
		const { rejectToolCall } = setupMocks( {
			pendingConfirmation,
			yoloMode: false,
		} );

		act( () => {
			root.render( createElement( ChatPanel, {} ) );
		} );

		const dialog = container.querySelector(
			'[data-testid="tool-confirmation-dialog"]'
		);
		const rejectBtn = Array.from(
			dialog.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'Reject' );
		act( () => {
			rejectBtn.click();
		} );
		expect( rejectToolCall ).toHaveBeenCalledWith( 'job-reject-test' );
	} );
} );
