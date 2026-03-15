/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ToolConfirmationDialog( {
	confirmation,
	onConfirm,
	onReject,
} ) {
	const [ alwaysAllow, setAlwaysAllow ] = useState( false );
	const dialogRef = useRef( null );

	useEffect( () => {
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				onReject();
			}
		};
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ onReject ] );

	if ( ! confirmation || ! confirmation.tools?.length ) {
		return null;
	}

	return (
		<div className="ai-agent-shortcuts-overlay">
			<div className="ai-agent-tool-confirm-dialog" ref={ dialogRef }>
				<div className="ai-agent-tool-confirm-header">
					<h3>{ __( 'Tool Confirmation Required', 'ai-agent' ) }</h3>
				</div>
				<div className="ai-agent-tool-confirm-body">
					<p className="ai-agent-tool-confirm-desc">
						{ __(
							'The AI wants to use the following tools:',
							'ai-agent'
						) }
					</p>
					{ confirmation.tools.map( ( tool ) => (
						<div
							key={ tool.id }
							className="ai-agent-tool-confirm-item"
						>
							<div className="ai-agent-tool-confirm-name">
								{ tool.name }
							</div>
							{ tool.args && (
								<pre className="ai-agent-tool-confirm-args">
									{ JSON.stringify( tool.args, null, 2 ) }
								</pre>
							) }
						</div>
					) ) }
					<label
						className="ai-agent-tool-confirm-always"
						htmlFor="tool-confirm-always-allow"
					>
						<input
							id="tool-confirm-always-allow"
							type="checkbox"
							checked={ alwaysAllow }
							onChange={ ( e ) =>
								setAlwaysAllow( e.target.checked )
							}
						/>
						{ __(
							'Always allow these tools (change permission to Auto)',
							'ai-agent'
						) }
					</label>
				</div>
				<div className="ai-agent-tool-confirm-footer">
					<button
						type="button"
						className="button"
						onClick={ onReject }
					>
						{ __( 'Deny', 'ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ () => onConfirm( alwaysAllow ) }
					>
						{ __( 'Allow', 'ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
