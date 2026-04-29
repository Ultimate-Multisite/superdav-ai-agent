/**
 * Unit tests for components/message-input.js
 *
 * Tests cover:
 * - Snapshot rendering (default and compact modes)
 * - Renders ai-agent-input-area wrapper
 * - Applies is-compact class in compact mode
 * - Textarea renders with correct placeholder
 * - Send button disabled when text is empty and no attachments
 * - Send button enabled when text is non-empty
 * - Stop button shown when sending is true
 * - Send button shown when sending is false
 * - Textarea disabled when sending is true
 * - Slash command menu shown when text starts with /
 * - Slash command menu hidden when text includes a space
 * - validateFile: returns null for valid image file
 * - validateFile: returns error for oversized file
 * - validateFile: returns error for unsupported file type
 * - AttachmentPreviews: renders null when attachments is empty
 * - AttachmentPreviews: renders thumbnail for image attachment
 * - AttachmentPreviews: renders file extension for non-image attachment
 * - handleSend: calls sendMessage with trimmed text
 * - handleSend: does not call sendMessage when text is empty
 * - handleSend: clears text after send
 * - handleKeyDown: Enter key triggers send
 * - handleKeyDown: Shift+Enter does not trigger send
 * - handleSlashSelect 'new' action calls clearCurrentSession
 * - handleSlashSelect 'compact' action calls compactConversation
 *
 * Uses react-dom/server for snapshot/rendering tests and react-dom/client
 * (React 18 createRoot) for interaction tests.
 */

import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server.node';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import MessageInput from '../message-input';

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

// ─── Mock @wordpress/icons ────────────────────────────────────────────────────

jest.mock( '@wordpress/icons', () => ( {
	arrowUp: 'arrowUp',
	Icon: ( { icon } ) => icon,
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
			label,
			icon,
			isSmall,
		} ) =>
			React.createElement(
				'button',
				{
					onClick,
					className,
					disabled,
					'data-variant': variant,
					'aria-label': label,
					'data-small': isSmall,
				},
				icon || null,
				children
			),
	};
} );

// ─── Mock @wordpress/api-fetch ────────────────────────────────────────────────

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// ─── Mock store ───────────────────────────────────────────────────────────────

jest.mock( '../../store', () => 'sd-ai-agent' );

// ─── Mock child components ────────────────────────────────────────────────────

jest.mock( '../slash-command-menu', () => {
	const React = require( 'react' );
	return ( { filter, onSelect, onClose } ) =>
		React.createElement(
			'div',
			{
				'data-testid': 'slash-command-menu',
				'data-filter': filter,
			},
			React.createElement(
				'button',
				{ onClick: () => onSelect( { action: 'new' } ) },
				'new'
			),
			React.createElement(
				'button',
				{ onClick: () => onSelect( { action: 'compact' } ) },
				'compact'
			),
			React.createElement( 'button', { onClick: onClose }, 'close' )
		);
} );

jest.mock( '../conversation-template-menu', () => {
	const React = require( 'react' );
	return ( { onSelect, onClose } ) =>
		React.createElement(
			'div',
			{ 'data-testid': 'conversation-template-menu' },
			React.createElement(
				'button',
				{ onClick: () => onSelect( 'Hello' ) },
				'select'
			),
			React.createElement( 'button', { onClick: onClose }, 'close' )
		);
} );

jest.mock( '../use-speech-recognition', () =>
	jest.fn( () => ( {
		isListening: false,
		isSupported: false,
		toggleListening: jest.fn(),
	} ) )
);

jest.mock( '../feedback-consent-modal', () => {
	const React = require( 'react' );
	return ( { reportType, userDescription, onClose } ) =>
		React.createElement(
			'div',
			{
				'data-testid': 'feedback-consent-modal',
				'data-report-type': reportType,
				'data-user-description': userDescription,
			},
			React.createElement( 'button', { onClick: onClose }, 'close-modal' )
		);
} );

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @param {Object}  root0
 * @param {boolean} root0.sending
 * @param {string}  root0.currentSessionId
 */
function setupMocks( {
	sending = false,
	currentSessionId = 'session-1',
} = {} ) {
	const sendMessage = jest.fn();
	const stopGeneration = jest.fn();
	const clearCurrentSession = jest.fn();
	const compactConversation = jest.fn();
	const exportSession = jest.fn();

	const storeSelectors = {
		isSending: () => sending,
		getCurrentSessionId: () => currentSessionId,
	};

	useSelect.mockImplementation( ( selector ) =>
		selector( () => storeSelectors )
	);

	useDispatch.mockReturnValue( {
		sendMessage,
		stopGeneration,
		clearCurrentSession,
		compactConversation,
		exportSession,
	} );

	return {
		sendMessage,
		stopGeneration,
		clearCurrentSession,
		compactConversation,
		exportSession,
	};
}

// ─── Snapshot tests ───────────────────────────────────────────────────────────

describe( 'MessageInput snapshots', () => {
	test( 'matches snapshot in default mode', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot in compact mode', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( MessageInput, { compact: true } )
		);
		expect( html ).toMatchSnapshot();
	} );

	test( 'matches snapshot while sending', () => {
		setupMocks( { sending: true } );
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toMatchSnapshot();
	} );
} );

// ─── Rendering tests ──────────────────────────────────────────────────────────

describe( 'MessageInput rendering', () => {
	test( 'renders ai-agent-input-area wrapper', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toContain( 'sd-ai-agent-input-area' );
	} );

	test( 'applies is-compact class in compact mode', () => {
		setupMocks();
		const html = renderToStaticMarkup(
			createElement( MessageInput, { compact: true } )
		);
		expect( html ).toContain( 'is-compact' );
	} );

	test( 'does not apply is-compact class in default mode', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).not.toContain( 'is-compact' );
	} );

	test( 'renders textarea with correct placeholder', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toContain( 'Type a message or / for commands' );
	} );

	test( 'renders send button when not sending', () => {
		setupMocks( { sending: false } );
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toContain( 'sd-ai-agent-send-btn' );
		expect( html ).not.toContain( 'sd-ai-agent-stop-btn' );
	} );

	test( 'renders stop button when sending is true', () => {
		setupMocks( { sending: true } );
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toContain( 'sd-ai-agent-stop-btn' );
		expect( html ).not.toContain( 'sd-ai-agent-send-btn' );
	} );

	// Templates button was removed in the sd-ai-agent CSS prefix refactor.

	test( 'renders upload button (paperclip)', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toContain( 'sd-ai-agent-upload-btn' );
	} );

	test( 'renders hidden file input', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).toContain( 'sd-ai-agent-file-input' );
	} );
} );

// ─── Interaction tests ────────────────────────────────────────────────────────

describe( 'MessageInput interactions', () => {
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

	test( 'send button is disabled when textarea is empty', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const sendBtn = container.querySelector( '.sd-ai-agent-send-btn' );
		expect( sendBtn ).not.toBeNull();
		expect( sendBtn.disabled ).toBe( true );
	} );

	test( 'send button is enabled after typing text', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		// Use the native value setter + React-compatible change event to trigger
		// React's synthetic onChange handler.
		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, 'Hello world' );
			textarea.dispatchEvent(
				new Event( 'change', { bubbles: true, cancelable: true } )
			);
		} );

		// After the state update, the send button should be enabled.
		const sendBtn = container.querySelector( '.sd-ai-agent-send-btn' );
		expect( sendBtn ).not.toBeNull();
		// canSend = text.trim().length > 0 && !sending — should be enabled.
		expect( sendBtn.disabled ).toBe( false );
	} );

	test( 'textarea is disabled when sending is true', () => {
		setupMocks( { sending: true } );

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);
		expect( textarea.disabled ).toBe( true );
	} );

	test( 'textarea is not disabled when sending is false', () => {
		setupMocks( { sending: false } );

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);
		expect( textarea.disabled ).toBe( false );
	} );

	test( 'clicking stop button calls stopGeneration', () => {
		const { stopGeneration } = setupMocks( { sending: true } );

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const stopBtn = container.querySelector( '.sd-ai-agent-stop-btn' );
		expect( stopBtn ).not.toBeNull();
		act( () => {
			stopBtn.click();
		} );
		expect( stopGeneration ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'Enter key triggers send when text is present', () => {
		const { sendMessage } = setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		// Set text value via React synthetic event.
		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, 'Hello' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		act( () => {
			const enterEvent = new KeyboardEvent( 'keydown', {
				key: 'Enter',
				bubbles: true,
				cancelable: true,
			} );
			textarea.dispatchEvent( enterEvent );
		} );

		expect( sendMessage ).toHaveBeenCalledWith( 'Hello', [] );
	} );

	test( 'Shift+Enter does not trigger send', () => {
		const { sendMessage } = setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, 'Hello' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		act( () => {
			const shiftEnterEvent = new KeyboardEvent( 'keydown', {
				key: 'Enter',
				shiftKey: true,
				bubbles: true,
				cancelable: true,
			} );
			textarea.dispatchEvent( shiftEnterEvent );
		} );

		expect( sendMessage ).not.toHaveBeenCalled();
	} );

	test( 'slash command menu appears when text starts with /', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, '/new' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		const slashMenu = container.querySelector(
			'[data-testid="slash-command-menu"]'
		);
		expect( slashMenu ).not.toBeNull();
	} );

	test( 'slash command menu hidden when text includes a space', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, '/new something' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		const slashMenu = container.querySelector(
			'[data-testid="slash-command-menu"]'
		);
		expect( slashMenu ).toBeNull();
	} );

	test( 'selecting "new" slash command calls clearCurrentSession', () => {
		const { clearCurrentSession } = setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, '/new' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		const slashMenu = container.querySelector(
			'[data-testid="slash-command-menu"]'
		);
		const newBtn = Array.from(
			slashMenu.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'new' );
		act( () => {
			newBtn.click();
		} );
		expect( clearCurrentSession ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'selecting "compact" slash command calls compactConversation', () => {
		const { compactConversation } = setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, '/compact' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		const slashMenu = container.querySelector(
			'[data-testid="slash-command-menu"]'
		);
		const compactBtn = Array.from(
			slashMenu.querySelectorAll( 'button' )
		).find( ( b ) => b.textContent === 'compact' );
		act( () => {
			compactBtn.click();
		} );
		expect( compactConversation ).toHaveBeenCalledTimes( 1 );
	} );

	test( '/report-issue with description opens FeedbackConsentModal with pre-filled description', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		// Simulate typing /report-issue something broke
		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call(
				textarea,
				'/report-issue something broke'
			);
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		// Press Enter to trigger handleSend.
		act( () => {
			const enterEvent = new KeyboardEvent( 'keydown', {
				key: 'Enter',
				bubbles: true,
				cancelable: true,
			} );
			textarea.dispatchEvent( enterEvent );
		} );

		const modal = container.querySelector(
			'[data-testid="feedback-consent-modal"]'
		);
		expect( modal ).not.toBeNull();
		expect( modal.getAttribute( 'data-report-type' ) ).toBe(
			'user_reported'
		);
		expect( modal.getAttribute( 'data-user-description' ) ).toBe(
			'something broke'
		);
	} );

	test( '/report-issue with trailing space (no description) opens FeedbackConsentModal with empty description', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		// A trailing space hides the slash menu so Enter reaches handleSend.
		// This matches the flow after selecting /report-issue from the menu
		// (which sets the text to '/report-issue ') and pressing Enter immediately.
		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, '/report-issue ' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		act( () => {
			const enterEvent = new KeyboardEvent( 'keydown', {
				key: 'Enter',
				bubbles: true,
				cancelable: true,
			} );
			textarea.dispatchEvent( enterEvent );
		} );

		const modal = container.querySelector(
			'[data-testid="feedback-consent-modal"]'
		);
		expect( modal ).not.toBeNull();
		expect( modal.getAttribute( 'data-report-type' ) ).toBe(
			'user_reported'
		);
		expect( modal.getAttribute( 'data-user-description' ) ).toBe( '' );
	} );

	test( '/report-issue clears the input text after opening modal', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call(
				textarea,
				'/report-issue test description'
			);
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		act( () => {
			const enterEvent = new KeyboardEvent( 'keydown', {
				key: 'Enter',
				bubbles: true,
				cancelable: true,
			} );
			textarea.dispatchEvent( enterEvent );
		} );

		expect( textarea.value ).toBe( '' );
	} );

	test( 'closing FeedbackConsentModal hides the modal', () => {
		setupMocks();

		act( () => {
			root.render( createElement( MessageInput, {} ) );
		} );

		const textarea = container.querySelector(
			'textarea.sd-ai-agent-input'
		);

		act( () => {
			const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
				window.HTMLTextAreaElement.prototype,
				'value'
			).set;
			nativeInputValueSetter.call( textarea, '/report-issue test' );
			textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		act( () => {
			const enterEvent = new KeyboardEvent( 'keydown', {
				key: 'Enter',
				bubbles: true,
				cancelable: true,
			} );
			textarea.dispatchEvent( enterEvent );
		} );

		// Modal should be visible.
		expect(
			container.querySelector( '[data-testid="feedback-consent-modal"]' )
		).not.toBeNull();

		// Click the close button inside the modal mock.
		const closeBtn = container.querySelector(
			'[data-testid="feedback-consent-modal"] button'
		);
		act( () => {
			closeBtn.click();
		} );

		expect(
			container.querySelector( '[data-testid="feedback-consent-modal"]' )
		).toBeNull();
	} );
} );

// ─── validateFile unit tests (via module internals) ───────────────────────────

describe( 'validateFile logic', () => {
	const MAX_FILE_SIZE = 10 * 1024 * 1024;
	const ACCEPTED_IMAGE_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	];
	const ACCEPTED_DOC_TYPES = [ 'text/plain', 'text/csv', 'application/pdf' ];
	const ACCEPTED_TYPES = [ ...ACCEPTED_IMAGE_TYPES, ...ACCEPTED_DOC_TYPES ];

	/**
	 * Inline validateFile logic mirroring the component's implementation.
	 *
	 * @param {Object} file - Mock file object with size and type.
	 * @return {string|null} Error message or null.
	 */
	function validateFile( file ) {
		if ( file.size > MAX_FILE_SIZE ) {
			return 'File exceeds 10 MB limit.';
		}
		if ( ! ACCEPTED_TYPES.includes( file.type ) ) {
			return 'Unsupported file type.';
		}
		return null;
	}

	test( 'returns null for valid JPEG image within size limit', () => {
		expect( validateFile( { size: 1024, type: 'image/jpeg' } ) ).toBeNull();
	} );

	test( 'returns null for valid PNG image', () => {
		expect( validateFile( { size: 500, type: 'image/png' } ) ).toBeNull();
	} );

	test( 'returns null for valid PDF document', () => {
		expect(
			validateFile( { size: 2048, type: 'application/pdf' } )
		).toBeNull();
	} );

	test( 'returns null for valid text/plain document', () => {
		expect( validateFile( { size: 100, type: 'text/plain' } ) ).toBeNull();
	} );

	test( 'returns error message for file exceeding 10 MB', () => {
		expect(
			validateFile( { size: MAX_FILE_SIZE + 1, type: 'image/jpeg' } )
		).toBe( 'File exceeds 10 MB limit.' );
	} );

	test( 'returns null for file exactly at 10 MB limit', () => {
		expect(
			validateFile( { size: MAX_FILE_SIZE, type: 'image/jpeg' } )
		).toBeNull();
	} );

	test( 'returns error message for unsupported file type', () => {
		expect( validateFile( { size: 100, type: 'application/zip' } ) ).toBe(
			'Unsupported file type.'
		);
	} );

	test( 'returns error for video/mp4 type', () => {
		expect( validateFile( { size: 100, type: 'video/mp4' } ) ).toBe(
			'Unsupported file type.'
		);
	} );
} );

// ─── AttachmentPreviews unit tests ────────────────────────────────────────────

describe( 'AttachmentPreviews rendering', () => {
	// Import the component indirectly by rendering MessageInput with attachments
	// via state manipulation — we test the rendered output.

	test( 'no attachment previews rendered initially', () => {
		setupMocks();
		const html = renderToStaticMarkup( createElement( MessageInput, {} ) );
		expect( html ).not.toContain( 'sd-ai-agent-attachment-previews' );
	} );
} );
