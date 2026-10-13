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
		<div className="sdaa-shortcuts-overlay">
			<div className="sdaa-tool-confirm-dialog" ref={ dialogRef }>
				<div className="sdaa-tool-confirm-header">
					<h3>
						{ __(
							'Tool Confirmation Required',
							'superdav-ai-agent'
						) }
					</h3>
				</div>
				<div className="sdaa-tool-confirm-body">
					<p className="sdaa-tool-confirm-desc">
						{ __(
							'The AI wants to use the following tools:',
							'superdav-ai-agent'
						) }
					</p>
					{ confirmation.tools.map( ( tool ) => (
						<div key={ tool.id } className="sdaa-tool-confirm-item">
							<div className="sdaa-tool-confirm-name">
								{ tool.label || tool.name }
							</div>
							{ tool.description && (
								<p className="sdaa-tool-confirm-description">
									{ tool.description }
								</p>
							) }
							{ tool.args && (
								<details className="sdaa-tool-confirm-details">
									<summary>
										{ __(
											'Technical details',
											'superdav-ai-agent'
										) }
									</summary>
									<div className="sdaa-tool-confirm-tool-name">
										{ tool.name }
									</div>
									<pre className="sdaa-tool-confirm-args">
										{ JSON.stringify( tool.args, null, 2 ) }
									</pre>
								</details>
							) }
						</div>
					) ) }
					<label
						className="sdaa-tool-confirm-always"
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
							'superdav-ai-agent'
						) }
					</label>
				</div>
				<div className="sdaa-tool-confirm-footer">
					<button
						type="button"
						className="button"
						onClick={ onReject }
					>
						{ __( 'Deny', 'superdav-ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ () => onConfirm( alwaysAllow ) }
					>
						{ __( 'Allow', 'superdav-ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
