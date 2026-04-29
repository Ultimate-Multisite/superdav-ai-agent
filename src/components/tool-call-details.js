/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * @typedef {import('../types').ToolCall} ToolCall
 */

/**
 * Format an ability name for display: strip wpab__ prefix, replace
 * double-underscore namespace separator with /.
 *
 * @param {string} name - Raw ability/function name.
 * @return {string} Human-readable name.
 */
function formatToolName( name ) {
	let display = name || '';
	if ( display.startsWith( 'wpab__' ) ) {
		display = display.substring( 6 );
	}
	display = display.replace( /__/g, '/' );
	return display;
}

/**
 * Detect if a tool call is a skill load based on its name.
 *
 * @param {string} name - Tool name.
 * @return {boolean} True if this is a skill-related call.
 */
function isSkillCall( name ) {
	const n = ( name || '' ).toLowerCase();
	return n.includes( 'skill' );
}

/**
 * Inline badge for annotations on tool calls.
 *
 * @param {Object} props
 * @param {string} props.label Text to display.
 * @param {string} props.color Badge color variant: 'blue', 'purple'.
 * @return {JSX.Element} The badge element.
 */
function Badge( { label, color = 'blue' } ) {
	const colors = {
		blue: {
			background: '#e8f4fd',
			color: '#0a4b78',
			border: '#72aee6',
		},
		purple: {
			background: '#f0e6f6',
			color: '#4c0070',
			border: '#a36ec5',
		},
	};
	const scheme = colors[ color ] || colors.blue;

	return (
		<span className="sd-ai-agent-tool-badge" style={ scheme }>
			{ label }
		</span>
	);
}

/**
 * Expandable pre block for JSON or text content.
 * Shows a truncated preview with a toggle to expand.
 *
 * @param {Object} props
 * @param {string} props.content          - Text content to display.
 * @param {string} props.label            - Toggle label prefix (e.g. "Arguments", "Response").
 * @param {number} [props.maxPreview=200] - Max chars for collapsed preview.
 * @return {JSX.Element} The expandable content block.
 */
function ExpandableContent( { content, label, maxPreview = 200 } ) {
	const [ expanded, setExpanded ] = useState( false );

	if ( ! content ) {
		return null;
	}

	const isLong = content.length > maxPreview;
	const displayText =
		expanded || ! isLong
			? content
			: content.substring( 0, maxPreview ) + '...';

	return (
		<div className="sd-ai-agent-tool-expandable">
			<details>
				<summary className="sd-ai-agent-tool-expandable-toggle">
					{ label }
					{ isLong && (
						<span className="sd-ai-agent-tool-expandable-size">
							{ ' ' }
							({ formatSize( content.length ) })
						</span>
					) }
				</summary>
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions -- the pre is supplementary; the button below is the primary expand control */ }
				<pre
					className="sd-ai-agent-tool-expandable-content"
					onClick={
						isLong && ! expanded
							? () => setExpanded( true )
							: undefined
					}
				>
					{ displayText }
					{ isLong && ! expanded && (
						<button
							type="button"
							className="sd-ai-agent-tool-expand-btn"
							onClick={ ( e ) => {
								e.stopPropagation();
								setExpanded( true );
							} }
						>
							{ __( 'Show full', 'sd-ai-agent' ) }
						</button>
					) }
				</pre>
			</details>
		</div>
	);
}

/**
 * Format a character count as a human-readable size.
 *
 * @param {number} chars - Number of characters.
 * @return {string} Formatted size string.
 */
function formatSize( chars ) {
	if ( chars < 1000 ) {
		return `${ chars } chars`;
	}
	return `${ ( chars / 1000 ).toFixed( 1 ) }k chars`;
}

/**
 * Format tool arguments or response for display.
 *
 * @param {unknown} value - The value to format.
 * @return {string} Formatted string.
 */
function formatValue( value ) {
	if ( typeof value === 'string' ) {
		return value;
	}
	if ( value === null || value === undefined ) {
		return '';
	}
	return JSON.stringify( value, null, 2 );
}

/**
 * Group flat tool call entries into call/response pairs.
 *
 * @param {ToolCall[]} toolCalls - Flat array of call and response entries.
 * @return {Array<{call: ToolCall, response: ToolCall|null}>} Paired entries.
 */
function groupToolCallPairs( toolCalls ) {
	const pairs = [];
	const responseMap = {};

	// Index responses by ID for quick lookup.
	for ( const entry of toolCalls ) {
		if ( entry.type === 'response' && entry.id ) {
			responseMap[ entry.id ] = entry;
		}
	}

	// Walk through calls and pair with their responses.
	for ( const entry of toolCalls ) {
		if ( entry.type === 'call' ) {
			pairs.push( {
				call: entry,
				response: entry.id ? responseMap[ entry.id ] || null : null,
			} );
		}
	}

	// If no calls found but there are entries, fall back to showing them flat.
	if ( pairs.length === 0 && toolCalls.length > 0 ) {
		for ( const entry of toolCalls ) {
			pairs.push( {
				call: entry,
				response: null,
			} );
		}
	}

	return pairs;
}

/**
 * A single tool call pair: the call and its response, displayed together.
 *
 * @param {Object}        props
 * @param {ToolCall}      props.call     - The tool call entry.
 * @param {ToolCall|null} props.response - The matching response entry.
 * @return {JSX.Element} The rendered pair.
 */
function ToolCallPair( { call, response } ) {
	const displayName = formatToolName( call.name );
	const isSkill = isSkillCall( call.name );
	const hasResponse = response && response.response !== undefined;
	const ranInBrowser = !! call.ran_in_browser || !! response?.ran_in_browser;

	const argsStr = formatValue( call.args );
	const responseStr = hasResponse ? formatValue( response.response ) : '';

	return (
		<div
			className={ `sd-ai-agent-tool-pair${
				isSkill ? ' sd-ai-agent-tool-pair--skill' : ''
			}` }
		>
			<div className="sd-ai-agent-tool-pair-header">
				<span className="sd-ai-agent-tool-pair-icon">
					{ isSkill ? '\u{1F4DA}' : '\u{2699}\u{FE0F}' }
				</span>
				<code className="sd-ai-agent-tool-pair-name">
					{ displayName }
				</code>
				{ ranInBrowser && (
					<Badge
						label={ __( 'Browser', 'sd-ai-agent' ) }
						color="blue"
					/>
				) }
				{ isSkill && (
					<Badge
						label={ __( 'Skill', 'sd-ai-agent' ) }
						color="purple"
					/>
				) }
				{ hasResponse && response.response?.success === false ? (
					<span className="sd-ai-agent-tool-pair-status sd-ai-agent-tool-pair-status--error">
						{ '✗' }
					</span>
				) : (
					hasResponse && (
						<span className="sd-ai-agent-tool-pair-status sd-ai-agent-tool-pair-status--ok">
							{ '✓' }
						</span>
					)
				) }
			</div>

			{ argsStr && argsStr !== '{}' && argsStr !== 'null' && (
				<ExpandableContent
					content={ argsStr }
					label={ __( 'Arguments', 'sd-ai-agent' ) }
					maxPreview={ 200 }
				/>
			) }

			{ responseStr && (
				<ExpandableContent
					content={ responseStr }
					label={ __( 'Response', 'sd-ai-agent' ) }
					maxPreview={ 300 }
				/>
			) }
		</div>
	);
}

/**
 * Collapsible details panel showing tool calls and their results.
 *
 * Groups call/response pairs together for clarity. Each pair shows the tool
 * name prominently, with expandable sections for arguments and response.
 * Skill loads are visually distinguished with a different icon and badge.
 *
 * @param {Object}     props           - Component props.
 * @param {ToolCall[]} props.toolCalls - Tool call/result entries to display.
 * @return {JSX.Element|null} The tool call details element, or null when empty.
 */
export default function ToolCallDetails( { toolCalls } ) {
	if ( ! toolCalls?.length ) {
		return null;
	}

	const pairs = groupToolCallPairs( toolCalls );
	const callCount = pairs.length;
	const skillCount = pairs.filter( ( p ) =>
		isSkillCall( p.call.name )
	).length;
	const toolCount = callCount - skillCount;

	// Build summary text.
	const summaryParts = [];
	if ( toolCount > 0 ) {
		summaryParts.push(
			toolCount === 1
				? __( '1 tool used', 'sd-ai-agent' )
				: `${ toolCount } ${ __( 'tools used', 'sd-ai-agent' ) }`
		);
	}
	if ( skillCount > 0 ) {
		summaryParts.push(
			skillCount === 1
				? __( '1 skill loaded', 'sd-ai-agent' )
				: `${ skillCount } ${ __( 'skills loaded', 'sd-ai-agent' ) }`
		);
	}
	const summaryText =
		summaryParts.join( ', ' ) || __( 'tool calls executed', 'sd-ai-agent' );

	return (
		<div className="sd-ai-agent-tool-calls">
			<details>
				<summary>{ summaryText }</summary>
				<div className="sd-ai-agent-tool-list">
					{ pairs.map( ( pair, i ) => (
						<ToolCallPair
							key={ pair.call.id || i }
							call={ pair.call }
							response={ pair.response }
						/>
					) ) }
				</div>
			</details>
		</div>
	);
}
