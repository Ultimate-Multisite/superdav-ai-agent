/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

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

function truncate( str, max ) {
	if ( ! str ) {
		return '';
	}
	return str.length > max ? str.substring( 0, max ) + '...' : str;
}
