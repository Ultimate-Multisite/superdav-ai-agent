/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Render a human-readable label for a tool call.
 *
 * Converts the internal wpab__ function name to a readable string and
 * formats the most relevant argument as a short description.
 *
 * @param {string} name The raw function name (e.g. wpab__ai-agent__post-delete).
 * @param {Object} args The tool arguments.
 * @return {{ title: string, description: string }} Human-readable title and description.
 */
function describeToolCall( name, args ) {
	// Strip the wpab__ prefix and convert dashes/underscores to spaces.
	const readable = name
		.replace( /^wpab__[^_]+__/, '' )
		.replace( /[-_]/g, ' ' );

	const title = readable
		.split( ' ' )
		.map( ( w ) => w.charAt( 0 ).toUpperCase() + w.slice( 1 ) )
		.join( ' ' );

	// Build a short description from the most relevant arg.
	const argEntries = args ? Object.entries( args ) : [];
	let description = '';
	if ( argEntries.length > 0 ) {
		// Prefer id, title, name, slug, path, url in that order.
		const preferred = [ 'id', 'title', 'name', 'slug', 'path', 'url' ];
		const found = preferred.find( ( k ) =>
			argEntries.some( ( [ key ] ) => key === k )
		);
		if ( found ) {
			const val = args[ found ];
			description = `${ found }: ${ val }`;
		} else {
			// Fall back to first arg.
			const [ key, val ] = argEntries[ 0 ];
			description = `${ key }: ${ String( val ).slice( 0, 80 ) }`;
		}
	}

	return { title, description };
}

/**
 * ActionCard — inline confirmation card rendered in the message list.
 *
 * Shown when the AI proposes a destructive or significant operation and
 * the tool permission is set to "confirm". The user can approve or cancel
 * without leaving the chat flow.
 *
 * @param {Object}   props
 * @param {Object}   props.card      The pending action card data { jobId, tools }.
 * @param {Function} props.onConfirm Called with (alwaysAllow: boolean) on confirm.
 * @param {Function} props.onCancel  Called on cancel/reject.
 */
export default function ActionCard( { card, onConfirm, onCancel } ) {
	const confirmRef = useRef( null );

	// Focus the confirm button when the card appears.
	useEffect( () => {
		if ( confirmRef.current ) {
			confirmRef.current.focus();
		}
	}, [] );

	if ( ! card || ! card.tools?.length ) {
		return null;
	}

	return (
		<div
			className="ai-agent-action-card"
			role="region"
			aria-label={ __( 'Action confirmation', 'ai-agent' ) }
		>
			<div className="ai-agent-action-card-header">
				<span className="ai-agent-action-card-icon" aria-hidden="true">
					&#9888;
				</span>
				<span className="ai-agent-action-card-heading">
					{ __( 'Confirm Action', 'ai-agent' ) }
				</span>
			</div>

			<div className="ai-agent-action-card-body">
				{ card.tools.map( ( tool ) => {
					const { title, description } = describeToolCall(
						tool.name,
						tool.args
					);
					return (
						<div
							key={ tool.id || tool.name }
							className="ai-agent-action-card-tool"
						>
							<div className="ai-agent-action-card-tool-title">
								{ title }
							</div>
							{ description && (
								<div className="ai-agent-action-card-tool-desc">
									{ description }
								</div>
							) }
							{ tool.args && (
								<details className="ai-agent-action-card-tool-args-details">
									<summary>
										{ __( 'View details', 'ai-agent' ) }
									</summary>
									<pre className="ai-agent-action-card-tool-args">
										{ JSON.stringify( tool.args, null, 2 ) }
									</pre>
								</details>
							) }
						</div>
					);
				} ) }
			</div>

			<div className="ai-agent-action-card-footer">
				<button
					type="button"
					className="button ai-agent-action-card-btn-cancel"
					onClick={ onCancel }
				>
					{ __( 'Cancel', 'ai-agent' ) }
				</button>
				<button
					type="button"
					ref={ confirmRef }
					className="button button-primary ai-agent-action-card-btn-confirm"
					onClick={ () => onConfirm( false ) }
				>
					{ __( 'Confirm', 'ai-agent' ) }
				</button>
			</div>
		</div>
	);
}
