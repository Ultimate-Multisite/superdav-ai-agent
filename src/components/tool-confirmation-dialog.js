/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @typedef {import('../types').PendingConfirmation} PendingConfirmation
 */

/**
 * Modal dialog asking the user to allow or deny pending tool calls.
 *
 * Includes an "Always allow" checkbox that grants permanent auto-allow
 * permission for the listed tools. Pressing Escape triggers onReject.
 *
 * @param {Object}              props              - Component props.
 * @param {PendingConfirmation} props.confirmation - Pending confirmation payload.
 * @param {Function}            props.onConfirm    - Called with (alwaysAllow: boolean)
 *                                                 when the user clicks Allow.
 * @param {Function}            props.onReject     - Called when the user clicks Deny
 *                                                 or presses Escape.
 * @return {JSX.Element|null} The dialog element, or null when there are no tools.
 */
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
		<div className="sd-ai-agent-shortcuts-overlay">
			<div className="sd-ai-agent-tool-confirm-dialog" ref={ dialogRef }>
				<div className="sd-ai-agent-tool-confirm-header">
					<h3>
						{ __( 'Tool Confirmation Required', 'sd-ai-agent' ) }
					</h3>
				</div>
				<div className="sd-ai-agent-tool-confirm-body">
					<p className="sd-ai-agent-tool-confirm-desc">
						{ __(
							'The AI wants to use the following tools:',
							'sd-ai-agent'
						) }
					</p>
					{ confirmation.tools.map( ( tool ) => (
						<div
							key={ tool.id }
							className="sd-ai-agent-tool-confirm-item"
						>
							<div className="sd-ai-agent-tool-confirm-name">
								{ tool.label || tool.name }
							</div>
							{ tool.description && (
								<p className="sd-ai-agent-tool-confirm-description">
									{ tool.description }
								</p>
							) }
							{ tool.args && (
								<details className="sd-ai-agent-tool-confirm-details">
									<summary>
										{ __(
											'Technical details',
											'sd-ai-agent'
										) }
									</summary>
									<div className="sd-ai-agent-tool-confirm-tool-name">
										{ tool.name }
									</div>
									<pre className="sd-ai-agent-tool-confirm-args">
										{ JSON.stringify( tool.args, null, 2 ) }
									</pre>
								</details>
							) }
						</div>
					) ) }
					<label
						className="sd-ai-agent-tool-confirm-always"
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
							'Always allow these tools without asking',
							'sd-ai-agent'
						) }
					</label>
				</div>
				<div className="sd-ai-agent-tool-confirm-footer">
					<button
						type="button"
						className="button"
						onClick={ onReject }
					>
						{ __( 'Deny', 'sd-ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ () => onConfirm( alwaysAllow ) }
					>
						{ __( 'Allow', 'sd-ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
