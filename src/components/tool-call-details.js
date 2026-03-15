/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * @typedef {import('../types').ToolCall} ToolCall
 */

/**
 * Collapsible details panel showing tool calls and their results.
 *
 * Renders a `<details>` element with a summary of how many tools were called,
 * and individual entries for each call and result.
 *
 * @param {Object}     props           - Component props.
 * @param {ToolCall[]} props.toolCalls - Tool call/result entries to display.
 * @return {JSX.Element|null} The tool call details element, or null when empty.
 */
export default function ToolCallDetails( { toolCalls } ) {
	if ( ! toolCalls?.length ) {
		return null;
	}

	return (
		<div className="gratis-ai-agent-tool-calls">
			<details>
				<summary>
					{ toolCalls.length }{ ' ' }
					{ toolCalls.length === 1
						? __( 'tool call executed', 'gratis-ai-agent' )
						: __( 'tool calls executed', 'gratis-ai-agent' ) }
				</summary>
				<div className="gratis-ai-agent-tool-list">
					{ toolCalls.map( ( entry, i ) => (
						<div
							key={ i }
							className={ `gratis-ai-agent-tool-entry gratis-ai-agent-tool-${ entry.type }` }
						>
							{ entry.type === 'call' ? (
								<>
									<span className="gratis-ai-agent-tool-label">
										{ __( 'Call:', 'gratis-ai-agent' ) }
									</span>{ ' ' }
									<code>{ entry.name }</code>
									<pre>
										{ JSON.stringify(
											entry.args,
											null,
											2
										) }
									</pre>
								</>
							) : (
								<>
									<span className="gratis-ai-agent-tool-label">
										{ __( 'Result:', 'gratis-ai-agent' ) }
									</span>{ ' ' }
									<code>{ entry.name }</code>
									<pre>
										{ truncate(
											typeof entry.response === 'string'
												? entry.response
												: JSON.stringify(
														entry.response,
														null,
														2
												  ),
											500
										) }
									</pre>
								</>
							) }
						</div>
					) ) }
				</div>
			</details>
		</div>
	);
}

/**
 * Truncate a string to a maximum length, appending '...' when truncated.
 *
 * @param {string} str - Input string.
 * @param {number} max - Maximum character length.
 * @return {string} Truncated string.
 */
function truncate( str, max ) {
	if ( ! str ) {
		return '';
	}
	return str.length > max ? str.substring( 0, max ) + '...' : str;
}
