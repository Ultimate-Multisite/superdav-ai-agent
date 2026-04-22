/**
 * Unit tests for components/connector-gate.js
 *
 * Tests cover:
 * - Renders the gate wrapper
 * - Renders the title
 * - Renders the description
 * - Renders the CTA link pointing to Connectors page
 * - Uses custom connectorsUrl from window.gratisAiAgentData when available
 * - Falls back to polyfill Connectors page when connectorsUrl is not set
 */

import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server.node';
import ConnectorGate from '../connector-gate';

// ─── Mock @wordpress/i18n ─────────────────────────────────────────────────────

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

// ─── Mock @wordpress/components ──────────────────────────────────────────────

jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	return {
		Button: ( { children, href, className, variant } ) =>
			React.createElement(
				'a',
				{ href, className, 'data-variant': variant },
				children
			),
		Notice: ( { children, status, isDismissible } ) =>
			React.createElement(
				'div',
				{
					'data-testid': 'notice',
					'data-status': status,
					'data-dismissible': isDismissible,
				},
				children
			),
	};
} );

// ─── Tests ────────────────────────────────────────────────────────────────────

describe( 'ConnectorGate', () => {
	afterEach( () => {
		// Restore window.gratisAiAgentData between tests.
		delete window.gratisAiAgentData;
	} );

	test( 'renders the gate wrapper', () => {
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		expect( html ).toContain( 'gratis-ai-agent-connector-gate' );
	} );

	test( 'renders the title', () => {
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		expect( html ).toContain( 'Set Up an AI Provider' );
	} );

	test( 'renders descriptive text about connectors', () => {
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		expect( html ).toContain( 'Connectors page' );
	} );

	test( 'renders CTA button', () => {
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		expect( html ).toContain( 'Configure a Connector' );
	} );

	test( 'CTA button links to polyfill Connectors page by default', () => {
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		expect( html ).toContain( 'admin.php?page=gratis-ai-agent#/connectors' );
	} );

	test( 'uses connectorsUrl from window.gratisAiAgentData when available', () => {
		window.gratisAiAgentData = {
			connectorsUrl: 'admin.php?page=custom-connectors',
		};
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		expect( html ).toContain( 'admin.php?page=custom-connectors' );
	} );

	test( 'renders info notice', () => {
		const html = renderToStaticMarkup( createElement( ConnectorGate, {} ) );
		const notice = html.match( /data-status="info"/ );
		expect( notice ).not.toBeNull();
	} );
} );
