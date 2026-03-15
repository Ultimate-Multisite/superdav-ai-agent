/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { SHORTCUTS } from '../utils/keyboard-shortcuts';

/**
 * Keyboard shortcuts and slash commands help dialog.
 *
 * Closes on Escape or click outside. Adapts modifier key label for macOS (⌘)
 * vs other platforms (Ctrl).
 *
 * @param {Object}   props         - Component props.
 * @param {Function} props.onClose - Called when the dialog should close.
 * @return {JSX.Element} The shortcuts help dialog element.
 */
export default function ShortcutsHelp( { onClose } ) {
	const dialogRef = useRef( null );

	useEffect( () => {
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ onClose ] );

	// Close on click outside.
	useEffect( () => {
		const handler = ( e ) => {
			if (
				dialogRef.current &&
				! dialogRef.current.contains( e.target )
			) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ onClose ] );

	const isMac =
		typeof navigator !== 'undefined' &&
		navigator.platform.indexOf( 'Mac' ) > -1;
	const modKey = isMac ? '\u2318' : 'Ctrl';

	return (
		<div className="ai-agent-shortcuts-overlay">
			<div className="ai-agent-shortcuts-dialog" ref={ dialogRef }>
				<div className="ai-agent-shortcuts-header">
					<h3>{ __( 'Keyboard Shortcuts', 'ai-agent' ) }</h3>
					<button type="button" onClick={ onClose }>
						&times;
					</button>
				</div>
				<div className="ai-agent-shortcuts-list">
					{ SHORTCUTS.map( ( s ) => (
						<div key={ s.combo } className="ai-agent-shortcut-row">
							<span className="ai-agent-shortcut-label">
								{ s.label }
							</span>
							<kbd className="ai-agent-shortcut-key">
								{ s.combo
									.replace( /mod/i, modKey )
									.replace( /\+/g, ' + ' ) }
							</kbd>
						</div>
					) ) }
				</div>
				<div className="ai-agent-shortcuts-footer">
					<h4>{ __( 'Slash Commands', 'ai-agent' ) }</h4>
					<div className="ai-agent-shortcut-row">
						<span>/new</span>
						<span>{ __( 'New chat', 'ai-agent' ) }</span>
					</div>
					<div className="ai-agent-shortcut-row">
						<span>/model</span>
						<span>{ __( 'Switch model', 'ai-agent' ) }</span>
					</div>
					<div className="ai-agent-shortcut-row">
						<span>/clear</span>
						<span>{ __( 'Clear conversation', 'ai-agent' ) }</span>
					</div>
					<div className="ai-agent-shortcut-row">
						<span>/export</span>
						<span>{ __( 'Export conversation', 'ai-agent' ) }</span>
					</div>
					<div className="ai-agent-shortcut-row">
						<span>/compact</span>
						<span>
							{ __( 'Compact conversation', 'ai-agent' ) }
						</span>
					</div>
					<div className="ai-agent-shortcut-row">
						<span>/help</span>
						<span>{ __( 'Show shortcuts', 'ai-agent' ) }</span>
					</div>
				</div>
			</div>
		</div>
	);
}
