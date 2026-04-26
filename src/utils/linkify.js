/**
 * WordPress dependencies
 */
import { createElement } from '@wordpress/element';

/**
 * Regex that matches http/https URLs in plain text.
 * Using the source string so each linkifyText call creates a fresh stateful
 * RegExp (required for correct `exec` iteration).
 */
const URL_REGEX_SOURCE = /https?:\/\/[^\s<>"]+/.source;

/**
 * Trailing punctuation that is commonly appended after a URL in prose
 * (e.g. "See https://example.com.") but is not part of the URL itself.
 */
const TRAILING_PUNCT = /[.,;:!?'")\]]+$/;

/**
 * Splits plain text into an array of strings and React anchor elements,
 * converting any http/https URLs into clickable `<a>` tags.
 *
 * Trailing sentence-ending punctuation (`.`, `,`, `;`, `:`, `!`, `?`) is
 * stripped from the URL so that prose like "read the docs: https://example.com."
 * links to the correct URL while the trailing period appears as regular text.
 *
 * @param {string} text Plain text that may contain URLs.
 * @return {Array<string|import('@wordpress/element').WPElement>} Mixed array of
 *   text segments and anchor elements suitable for direct rendering in JSX.
 */
export function linkifyText( text ) {
	if ( ! text ) {
		return [ text ];
	}

	const regex = new RegExp( URL_REGEX_SOURCE, 'g' );
	const parts = [];
	let lastIndex = 0;
	let match;

	while ( ( match = regex.exec( text ) ) !== null ) {
		// Strip trailing punctuation that is not part of the URL.
		const url = match[ 0 ].replace( TRAILING_PUNCT, '' );
		// urlEnd points to just after the trimmed URL in the source text,
		// so any stripped characters fall into the next text segment.
		const urlEnd = match.index + url.length;

		if ( match.index > lastIndex ) {
			parts.push( text.slice( lastIndex, match.index ) );
		}

		parts.push(
			createElement(
				'a',
				{
					key: `link-${ match.index }`,
					href: url,
					target: '_blank',
					rel: 'noopener noreferrer',
				},
				url
			)
		);

		lastIndex = urlEnd;
	}

	if ( lastIndex < text.length ) {
		parts.push( text.slice( lastIndex ) );
	}

	// When no URLs were found the parts array is empty; return the original text.
	return parts.length > 0 ? parts : [ text ];
}
