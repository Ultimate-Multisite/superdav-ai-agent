/**
 * Unit tests for components/chart-block.js
 *
 * Tests cover:
 * - Renders canvas element for valid Chart.js JSON config
 * - Shows error message for invalid JSON
 * - Shows error message when "type" field is missing
 * - Shows error message when "data" field is missing
 * - Falls back to CodeBlock on parse error
 * - Renders sd-ai-agent-chart-block wrapper for valid config
 * - Renders sd-ai-agent-chart-error wrapper for invalid config
 *
 * Uses react-dom/server for static rendering tests.
 * Chart.js is mocked to avoid canvas rendering in jsdom.
 */

import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server.node';
import ChartBlock from '../chart-block';

// Mock @wordpress/i18n.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

// Mock @wordpress/element — pass through real hooks but allow override.
jest.mock( '@wordpress/element', () => {
	const actual = jest.requireActual( '@wordpress/element' );
	return {
		...actual,
	};
} );

// Mock Chart.js to avoid canvas errors in jsdom.
jest.mock( 'chart.js', () => {
	const MockChart = jest.fn().mockImplementation( () => ( {
		destroy: jest.fn(),
	} ) );
	MockChart.register = jest.fn();
	return {
		Chart: MockChart,
		CategoryScale: {},
		LinearScale: {},
		BarElement: {},
		LineElement: {},
		PointElement: {},
		ArcElement: {},
		RadialLinearScale: {},
		Title: {},
		Tooltip: {},
		Legend: {},
		Filler: {},
		BarController: {},
		LineController: {},
		PieController: {},
		DoughnutController: {},
		RadarController: {},
		PolarAreaController: {},
		BubbleController: {},
		ScatterController: {},
	};
} );

// Mock CodeBlock to simplify fallback assertions.
jest.mock( '../code-block', () => {
	const React = require( 'react' );
	return function MockCodeBlock( { children } ) {
		return React.createElement(
			'pre',
			{ 'data-testid': 'code-block' },
			children
		);
	};
} );

// ─── Helpers ──────────────────────────────────────────────────────────────────

const VALID_CONFIG = JSON.stringify( {
	type: 'bar',
	data: {
		labels: [ 'A', 'B', 'C' ],
		datasets: [ { label: 'Test', data: [ 1, 2, 3 ] } ],
	},
} );

const INVALID_JSON = '{ not valid json }';

const MISSING_TYPE = JSON.stringify( {
	data: { labels: [], datasets: [] },
} );

const MISSING_DATA = JSON.stringify( {
	type: 'bar',
} );

// ─── Rendering tests ──────────────────────────────────────────────────────────

describe( 'ChartBlock rendering', () => {
	test( 'renders sd-ai-agent-chart-block wrapper for valid config', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, VALID_CONFIG )
		);
		expect( html ).toContain( 'sd-ai-agent-chart-block' );
	} );

	test( 'renders a canvas element for valid config', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, VALID_CONFIG )
		);
		expect( html ).toContain( '<canvas' );
	} );

	test( 'does not render error wrapper for valid config', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, VALID_CONFIG )
		);
		expect( html ).not.toContain( 'sd-ai-agent-chart-error' );
	} );

	test( 'renders sd-ai-agent-chart-error wrapper for invalid JSON', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, INVALID_JSON )
		);
		expect( html ).toContain( 'sd-ai-agent-chart-error' );
	} );

	test( 'renders error message for invalid JSON', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, INVALID_JSON )
		);
		expect( html ).toContain( 'Chart render error' );
	} );

	test( 'falls back to CodeBlock for invalid JSON', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, INVALID_JSON )
		);
		expect( html ).toContain( 'data-testid="code-block"' );
	} );

	test( 'renders error when "type" field is missing', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, MISSING_TYPE )
		);
		expect( html ).toContain( 'sd-ai-agent-chart-error' );
		// HTML-encoded double quotes in the error message.
		expect( html ).toContain( '&quot;type&quot;' );
	} );

	test( 'renders error when "data" field is missing', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, MISSING_DATA )
		);
		expect( html ).toContain( 'sd-ai-agent-chart-error' );
		// HTML-encoded double quotes in the error message.
		expect( html ).toContain( '&quot;data&quot;' );
	} );

	test( 'does not render canvas for invalid JSON', () => {
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, INVALID_JSON )
		);
		expect( html ).not.toContain( '<canvas' );
	} );

	test( 'trims whitespace from children before parsing', () => {
		const padded = `\n  ${ VALID_CONFIG }  \n`;
		const html = renderToStaticMarkup(
			createElement( ChartBlock, {}, padded )
		);
		expect( html ).toContain( 'sd-ai-agent-chart-block' );
	} );
} );
