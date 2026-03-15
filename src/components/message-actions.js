/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Per-message action buttons (Copy, Edit, Regenerate).
 *
 * - Copy: available on all messages.
 * - Edit: available on user messages; opens an inline edit textarea.
 * - Regenerate: available on model messages; re-runs from the preceding user message.
 *
 * @param {Object}                     props         - Component props.
 * @param {import('../types').Message} props.message - The message to act on.
 * @param {number}                     props.index   - Index of the message in the list.
 * @return {JSX.Element} The message actions element.
 */
export default function MessageActions( { message, index } ) {
	const [ copied, setCopied ] = useState( false );
	const [ editing, setEditing ] = useState( false );
	const [ editText, setEditText ] = useState( '' );
	const editInputRef = useRef( null );

	useEffect( () => {
		if ( editing && editInputRef.current ) {
			editInputRef.current.focus();
		}
	}, [ editing ] );

	const { regenerateMessage, editAndResend } = useDispatch( STORE_NAME );
	const sending = useSelect(
		( select ) => select( STORE_NAME ).isSending(),
		[]
	);

	const text = message.parts
		?.filter( ( p ) => p.text )
		.map( ( p ) => p.text )
		.join( '' );

	const handleCopy = useCallback( () => {
		navigator.clipboard.writeText( text || '' ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} );
	}, [ text ] );

	const handleEdit = useCallback( () => {
		setEditText( text || '' );
		setEditing( true );
	}, [ text ] );

	const handleEditSubmit = useCallback( () => {
		if ( editText.trim() ) {
			editAndResend( index, editText.trim() );
		}
		setEditing( false );
	}, [ editText, index, editAndResend ] );

	if ( editing ) {
		return (
			<div className="ai-agent-message-edit">
				<textarea
					ref={ editInputRef }
					className="ai-agent-message-edit-input"
					value={ editText }
					onChange={ ( e ) => setEditText( e.target.value ) }
					rows={ 3 }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' && ! e.shiftKey ) {
							e.preventDefault();
							handleEditSubmit();
						}
						if ( e.key === 'Escape' ) {
							setEditing( false );
						}
					} }
				/>
				<div className="ai-agent-message-edit-actions">
					<button
						type="button"
						onClick={ handleEditSubmit }
						disabled={ sending }
					>
						{ __( 'Send', 'ai-agent' ) }
					</button>
					<button type="button" onClick={ () => setEditing( false ) }>
						{ __( 'Cancel', 'ai-agent' ) }
					</button>
				</div>
			</div>
		);
	}

	const isUser = message.role === 'user';
	const isModel = message.role === 'model';

	return (
		<div className="ai-agent-message-actions">
			<button
				type="button"
				className="ai-agent-action-btn"
				onClick={ handleCopy }
				title={ __( 'Copy', 'ai-agent' ) }
			>
				{ copied
					? __( 'Copied', 'ai-agent' )
					: __( 'Copy', 'ai-agent' ) }
			</button>
			{ isUser && (
				<button
					type="button"
					className="ai-agent-action-btn"
					onClick={ handleEdit }
					disabled={ sending }
					title={ __( 'Edit', 'ai-agent' ) }
				>
					{ __( 'Edit', 'ai-agent' ) }
				</button>
			) }
			{ isModel && (
				<button
					type="button"
					className="ai-agent-action-btn"
					onClick={ () => regenerateMessage( index ) }
					disabled={ sending }
					title={ __( 'Regenerate', 'ai-agent' ) }
				>
					{ __( 'Regenerate', 'ai-agent' ) }
				</button>
			) }
		</div>
	);
}
