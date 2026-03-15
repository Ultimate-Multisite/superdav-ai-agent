/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

export default function MessageActions( { message, index } ) {
	const [ copied, setCopied ] = useState( false );
	const [ editing, setEditing ] = useState( false );
	const [ editText, setEditText ] = useState( '' );

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
			<div className="gratis-ai-agent-message-edit">
				<textarea
					className="gratis-ai-agent-message-edit-input"
					value={ editText }
					onChange={ ( e ) => setEditText( e.target.value ) }
					rows={ 3 }
					// eslint-disable-next-line jsx-a11y/no-autofocus
					autoFocus
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
				<div className="gratis-ai-agent-message-edit-actions">
					<button
						type="button"
						onClick={ handleEditSubmit }
						disabled={ sending }
					>
						{ __( 'Send', 'gratis-ai-agent' ) }
					</button>
					<button type="button" onClick={ () => setEditing( false ) }>
						{ __( 'Cancel', 'gratis-ai-agent' ) }
					</button>
				</div>
			</div>
		);
	}

	const isUser = message.role === 'user';
	const isModel = message.role === 'model';

	return (
		<div className="gratis-ai-agent-message-actions">
			<button
				type="button"
				className="gratis-ai-agent-action-btn"
				onClick={ handleCopy }
				title={ __( 'Copy', 'gratis-ai-agent' ) }
			>
				{ copied
					? __( 'Copied', 'gratis-ai-agent' )
					: __( 'Copy', 'gratis-ai-agent' ) }
			</button>
			{ isUser && (
				<button
					type="button"
					className="gratis-ai-agent-action-btn"
					onClick={ handleEdit }
					disabled={ sending }
					title={ __( 'Edit', 'gratis-ai-agent' ) }
				>
					{ __( 'Edit', 'gratis-ai-agent' ) }
				</button>
			) }
			{ isModel && (
				<button
					type="button"
					className="gratis-ai-agent-action-btn"
					onClick={ () => regenerateMessage( index ) }
					disabled={ sending }
					title={ __( 'Regenerate', 'gratis-ai-agent' ) }
				>
					{ __( 'Regenerate', 'gratis-ai-agent' ) }
				</button>
			) }
		</div>
	);
}
