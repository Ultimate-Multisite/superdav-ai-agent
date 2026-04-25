/**
 * Shared helpers used by both the chat-redesign message list and the
 * chat-widget compact message list. Keep the message transformation logic
 * in one place so the two UIs render the same store data identically.
 */

/**
 *
 * @param {Object} message
 */
export function extractText( message ) {
	if ( ! message.parts?.length ) {
		return '';
	}
	return message.parts
		.filter( ( p ) => p.text )
		.map( ( p ) => p.text )
		.join( '' );
}

/**
 *
 * @param {Array} toolCalls
 */
export function pairToolCalls( toolCalls ) {
	if ( ! toolCalls?.length ) {
		return [];
	}
	const responses = {};
	for ( const t of toolCalls ) {
		if ( ( t.type === 'response' || t.type === 'result' ) && t.id ) {
			responses[ t.id ] = t;
		}
	}
	const pairs = [];
	for ( const t of toolCalls ) {
		if ( t.type === 'call' ) {
			pairs.push( {
				call: t,
				response: t.id ? responses[ t.id ] || null : null,
			} );
		}
	}
	if ( pairs.length === 0 ) {
		for ( const t of toolCalls ) {
			pairs.push( { call: t, response: null } );
		}
	}
	return pairs;
}

/**
 *
 * @param {string} text
 */
export function parseSuggestions( text ) {
	const lines = text.split( '\n' );
	const suggestions = [];
	let lastContentIdx = lines.length - 1;
	for ( let i = lines.length - 1; i >= 0; i-- ) {
		const trimmed = lines[ i ].trim();
		if ( trimmed.startsWith( '[suggestion]' ) ) {
			suggestions.unshift( trimmed.replace( /^\[suggestion\]\s*/, '' ) );
			lastContentIdx = i - 1;
		} else if ( trimmed === '' && suggestions.length > 0 ) {
			lastContentIdx = i - 1;
		} else {
			break;
		}
	}
	return {
		cleanText: lines
			.slice( 0, lastContentIdx + 1 )
			.join( '\n' )
			.trimEnd(),
		suggestions,
	};
}

/**
 * Returns the short name of the tool that is currently in-flight, or
 * null when all calls have a matching response (i.e. the model is just
 * composing its reply).
 *
 * @param {Array} toolCalls
 * @return {string|null} The running tool name or null.
 */
export function getRunningToolName( toolCalls ) {
	const calls = toolCalls?.filter( ( t ) => t.type === 'call' ) || [];
	const responses =
		toolCalls?.filter(
			( t ) => t.type === 'response' || t.type === 'result'
		) || [];
	const lastCall = calls[ calls.length - 1 ];
	if ( responses.length < calls.length && lastCall ) {
		return ( lastCall.name || '' )
			.replace( /^wpab__/, '' )
			.replace( /__/g, '/' );
	}
	return null;
}
