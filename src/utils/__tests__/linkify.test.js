/**
 * Unit tests for utils/linkify.js
 *
 * Tests cover:
 * - Plain text with no URLs is returned as-is.
 * - A single URL is converted to an anchor element.
 * - Surrounding text is preserved as string segments.
 * - Multiple URLs produce the correct number of elements.
 * - Trailing sentence punctuation is stripped from the URL.
 * - The anchor element has the correct href, target, and rel attributes.
 * - Edge cases: empty string, falsy input.
 */

import { linkifyText } from '../linkify';

// @wordpress/element's createElement returns a plain React element object.
// We inspect its shape (type, props) directly to avoid needing a DOM.

/**
 * Helper: returns true when the value looks like a React/WP element for an <a>.
 *
 * @param {*} val Value to check.
 * @return {boolean} Whether val is a rendered anchor element.
 */
function isAnchorElement( val ) {
	return (
		val !== null &&
		typeof val === 'object' &&
		val.type === 'a' &&
		typeof val.props === 'object'
	);
}

// ─── No URL in text ────────────────────────────────────────────────────────────

describe( 'linkifyText — no URLs', () => {
	test( 'returns original text in array when no URL present', () => {
		const result = linkifyText( 'Hello world' );
		expect( result ).toEqual( [ 'Hello world' ] );
	} );

	test( 'returns empty string in array', () => {
		const result = linkifyText( '' );
		expect( result ).toEqual( [ '' ] );
	} );

	test( 'returns [null] for null input', () => {
		const result = linkifyText( null );
		expect( result ).toEqual( [ null ] );
	} );

	test( 'returns [undefined] for undefined input', () => {
		const result = linkifyText( undefined );
		expect( result ).toEqual( [ undefined ] );
	} );
} );

// ─── Single URL ────────────────────────────────────────────────────────────────

describe( 'linkifyText — single URL', () => {
	test( 'URL-only string produces one anchor element', () => {
		const result = linkifyText( 'https://example.com' );
		expect( result ).toHaveLength( 1 );
		expect( isAnchorElement( result[ 0 ] ) ).toBe( true );
		expect( result[ 0 ].props.href ).toBe( 'https://example.com' );
	} );

	test( 'anchor element has target="_blank"', () => {
		const [ anchor ] = linkifyText( 'https://example.com' );
		expect( anchor.props.target ).toBe( '_blank' );
	} );

	test( 'anchor element has rel="noopener noreferrer"', () => {
		const [ anchor ] = linkifyText( 'https://example.com' );
		expect( anchor.props.rel ).toBe( 'noopener noreferrer' );
	} );

	test( 'URL at the end of text: preserves prefix text', () => {
		const result = linkifyText( 'Read more: https://example.com' );
		expect( result ).toHaveLength( 2 );
		expect( result[ 0 ] ).toBe( 'Read more: ' );
		expect( isAnchorElement( result[ 1 ] ) ).toBe( true );
		expect( result[ 1 ].props.href ).toBe( 'https://example.com' );
	} );

	test( 'URL at the start of text: preserves suffix text', () => {
		const result = linkifyText( 'https://example.com is the link' );
		expect( result ).toHaveLength( 2 );
		expect( isAnchorElement( result[ 0 ] ) ).toBe( true );
		expect( result[ 0 ].props.href ).toBe( 'https://example.com' );
		expect( result[ 1 ] ).toBe( ' is the link' );
	} );

	test( 'URL in the middle of text: preserves prefix and suffix', () => {
		const result = linkifyText( 'See https://example.com for details.' );
		expect( result ).toHaveLength( 3 );
		expect( result[ 0 ] ).toBe( 'See ' );
		expect( isAnchorElement( result[ 1 ] ) ).toBe( true );
		expect( result[ 1 ].props.href ).toBe( 'https://example.com' );
		expect( result[ 2 ] ).toBe( ' for details.' );
	} );

	test( 'http:// URLs are also linkified', () => {
		const result = linkifyText( 'http://example.com' );
		expect( result ).toHaveLength( 1 );
		expect( isAnchorElement( result[ 0 ] ) ).toBe( true );
		expect( result[ 0 ].props.href ).toBe( 'http://example.com' );
	} );
} );

// ─── Trailing punctuation stripping ───────────────────────────────────────────

describe( 'linkifyText — trailing punctuation stripping', () => {
	test( 'trailing period is stripped from URL', () => {
		const result = linkifyText( 'See https://example.com.' );
		expect( result ).toHaveLength( 3 );
		expect( isAnchorElement( result[ 1 ] ) ).toBe( true );
		expect( result[ 1 ].props.href ).toBe( 'https://example.com' );
		expect( result[ 2 ] ).toBe( '.' );
	} );

	test( 'trailing comma is stripped from URL', () => {
		const result = linkifyText(
			'Visit https://example.com, then continue.'
		);
		const anchor = result.find( isAnchorElement );
		expect( anchor ).toBeDefined();
		expect( anchor.props.href ).toBe( 'https://example.com' );
	} );

	test( 'trailing question mark is stripped from URL', () => {
		const result = linkifyText( 'Did you check https://example.com?' );
		const anchor = result.find( isAnchorElement );
		expect( anchor ).toBeDefined();
		expect( anchor.props.href ).toBe( 'https://example.com' );
	} );

	test( 'real-world OpenAI 429 error URL with trailing period', () => {
		const text =
			'Too many requests. Read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors.';
		const result = linkifyText( text );
		const anchor = result.find( isAnchorElement );
		expect( anchor ).toBeDefined();
		expect( anchor.props.href ).toBe(
			'https://platform.openai.com/docs/guides/error-codes/api-errors'
		);
		// The stripped period should appear in the trailing text segment.
		const lastPart = result[ result.length - 1 ];
		expect( typeof lastPart === 'string' && lastPart.endsWith( '.' ) ).toBe(
			true
		);
	} );
} );

// ─── Multiple URLs ─────────────────────────────────────────────────────────────

describe( 'linkifyText — multiple URLs', () => {
	test( 'two URLs are each converted to anchor elements', () => {
		const result = linkifyText(
			'Go to https://example.com or https://other.org for info.'
		);
		const anchors = result.filter( isAnchorElement );
		expect( anchors ).toHaveLength( 2 );
		expect( anchors[ 0 ].props.href ).toBe( 'https://example.com' );
		expect( anchors[ 1 ].props.href ).toBe( 'https://other.org' );
	} );

	test( 'text between two URLs is preserved as string segment', () => {
		const result = linkifyText(
			'First: https://a.com, second: https://b.com.'
		);
		const strings = result.filter( ( p ) => typeof p === 'string' );
		// Expect "First: ", ", second: ", and "." (stripped punctuation parts).
		expect( strings.some( ( s ) => s.includes( 'second' ) ) ).toBe( true );
	} );

	test( 'each anchor has unique key to support React reconciliation', () => {
		const result = linkifyText( 'https://a.com and https://b.com' );
		const anchors = result.filter( isAnchorElement );
		expect( anchors[ 0 ].key ).not.toBe( anchors[ 1 ].key );
	} );
} );
