/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { SHORTCUTS } from '../utils/keyboard-shortcuts';

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
		<div className="gratis-ai-agent-shortcuts-overlay">
			<div className="gratis-ai-agent-shortcuts-dialog" ref={ dialogRef }>
				<div className="gratis-ai-agent-shortcuts-header">
					<h3>{ __( 'Keyboard Shortcuts', 'gratis-ai-agent' ) }</h3>
					<button type="button" onClick={ onClose }>
						&times;
					</button>
				</div>
				<div className="gratis-ai-agent-shortcuts-list">
					{ SHORTCUTS.map( ( s ) => (
						<div
							key={ s.combo }
							className="gratis-ai-agent-shortcut-row"
						>
							<span className="gratis-ai-agent-shortcut-label">
								{ s.label }
							</span>
							<kbd className="gratis-ai-agent-shortcut-key">
								{ s.combo
									.replace( /mod/i, modKey )
									.replace( /\+/g, ' + ' ) }
							</kbd>
						</div>
					) ) }
				</div>
				<div className="gratis-ai-agent-shortcuts-footer">
					<h4>{ __( 'Slash Commands', 'gratis-ai-agent' ) }</h4>
					<div className="gratis-ai-agent-shortcut-row">
						<span>/new</span>
						<span>{ __( 'New chat', 'gratis-ai-agent' ) }</span>
					</div>
					<div className="gratis-ai-agent-shortcut-row">
						<span>/model</span>
						<span>{ __( 'Switch model', 'gratis-ai-agent' ) }</span>
					</div>
					<div className="gratis-ai-agent-shortcut-row">
						<span>/clear</span>
						<span>
							{ __( 'Clear conversation', 'gratis-ai-agent' ) }
						</span>
					</div>
					<div className="gratis-ai-agent-shortcut-row">
						<span>/export</span>
						<span>
							{ __( 'Export conversation', 'gratis-ai-agent' ) }
						</span>
					</div>
					<div className="gratis-ai-agent-shortcut-row">
						<span>/compact</span>
						<span>
							{ __( 'Compact conversation', 'gratis-ai-agent' ) }
						</span>
					</div>
					<div className="gratis-ai-agent-shortcut-row">
						<span>/help</span>
						<span>
							{ __( 'Show shortcuts', 'gratis-ai-agent' ) }
						</span>
					</div>
				</div>
			</div>
		</div>
	);
}
