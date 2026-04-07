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
								{ tool.label || tool.name }
							</div>
							{ tool.description && (
								<p className="gratis-ai-agent-tool-confirm-description">
									{ tool.description }
								</p>
							) }
							{ tool.args && (
								<details className="gratis-ai-agent-tool-confirm-details">
									<summary>
										{ __(
											'Technical details',
											'gratis-ai-agent'
										) }
									</summary>
									<div className="gratis-ai-agent-tool-confirm-tool-name">
										{ tool.name }
									</div>
									<pre className="gratis-ai-agent-tool-confirm-args">
										{ JSON.stringify( tool.args, null, 2 ) }
									</pre>
								</details>
							) }
						</div>
					) ) }
					<label
						className="gratis-ai-agent-tool-confirm-always"
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
