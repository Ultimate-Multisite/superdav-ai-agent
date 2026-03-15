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
		<div className="gratis-ai-agent-shortcuts-overlay">
			<div
				className="gratis-ai-agent-tool-confirm-dialog"
				ref={ dialogRef }
			>
				<div className="gratis-ai-agent-tool-confirm-header">
					<h3>
						{ __(
							'Tool Confirmation Required',
							'gratis-ai-agent'
						) }
					</h3>
				</div>
				<div className="gratis-ai-agent-tool-confirm-body">
					<p className="gratis-ai-agent-tool-confirm-desc">
						{ __(
							'The AI wants to use the following tools:',
							'gratis-ai-agent'
						) }
					</p>
					{ confirmation.tools.map( ( tool ) => (
						<div
							key={ tool.id }
							className="gratis-ai-agent-tool-confirm-item"
						>
							<div className="gratis-ai-agent-tool-confirm-name">
								{ tool.name }
							</div>
							{ tool.args && (
								<pre className="gratis-ai-agent-tool-confirm-args">
									{ JSON.stringify( tool.args, null, 2 ) }
								</pre>
							) }
						</div>
					) ) }
					{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
					<label className="gratis-ai-agent-tool-confirm-always">
						<input
							type="checkbox"
							checked={ alwaysAllow }
							onChange={ ( e ) =>
								setAlwaysAllow( e.target.checked )
							}
						/>
						{ __(
							'Always allow these tools (change permission to Auto)',
							'gratis-ai-agent'
						) }
					</label>
				</div>
				<div className="gratis-ai-agent-tool-confirm-footer">
					<button
						type="button"
						className="button"
						onClick={ onReject }
					>
						{ __( 'Deny', 'gratis-ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ () => onConfirm( alwaysAllow ) }
					>
						{ __( 'Allow', 'gratis-ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
