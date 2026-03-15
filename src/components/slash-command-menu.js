/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const COMMANDS = [
	{
		name: '/new',
		description: __( 'Start a new chat', 'gratis-ai-agent' ),
		action: 'new',
	},
	{
		name: '/model',
		description: __(
			'Switch model (type model name after)',
			'gratis-ai-agent'
		),
		action: 'model',
	},
	{
		name: '/remember',
		description: __(
			'Save a fact to memory (type fact after)',
			'gratis-ai-agent'
		),
		action: 'remember',
	},
	{
		name: '/forget',
		description: __(
			'Forget memories matching a topic',
			'gratis-ai-agent'
		),
		action: 'forget',
	},
	{
		name: '/clear',
		description: __( 'Clear conversation', 'gratis-ai-agent' ),
		action: 'clear',
	},
	{
		name: '/export',
		description: __( 'Export current conversation', 'gratis-ai-agent' ),
		action: 'export',
	},
	{
		name: '/compact',
		description: __(
			'Compact conversation to save context',
			'gratis-ai-agent'
		),
		action: 'compact',
	},
	{
		name: '/help',
		description: __( 'Show keyboard shortcuts', 'gratis-ai-agent' ),
		action: 'help',
	},
	{
		name: '/debug',
		description: __(
			'Toggle debug mode (per-response metrics)',
			'gratis-ai-agent'
		),
		action: 'debug',
	},
];

export default function SlashCommandMenu( {
	filter,
	onSelect,
	onClose,
	position,
} ) {
	const [ selectedIndex, setSelectedIndex ] = useState( 0 );
	const menuRef = useRef( null );

	const filtered = COMMANDS.filter( ( cmd ) =>
		cmd.name.toLowerCase().startsWith( filter.toLowerCase() )
	);

	useEffect( () => {
		setSelectedIndex( 0 );
	}, [ filter ] );

	const handleKeyDown = useCallback(
		( e ) => {
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				setSelectedIndex( ( prev ) =>
					Math.min( prev + 1, filtered.length - 1 )
				);
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				setSelectedIndex( ( prev ) => Math.max( prev - 1, 0 ) );
			} else if ( e.key === 'Enter' || e.key === 'Tab' ) {
				e.preventDefault();
				if ( filtered[ selectedIndex ] ) {
					onSelect( filtered[ selectedIndex ] );
				}
			} else if ( e.key === 'Escape' ) {
				e.preventDefault();
				onClose();
			}
		},
		[ filtered, selectedIndex, onSelect, onClose ]
	);

	useEffect( () => {
		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ handleKeyDown ] );

	if ( filtered.length === 0 ) {
		return null;
	}

	return (
		<div
			className="gratis-ai-agent-slash-menu"
			ref={ menuRef }
			style={ position ? { bottom: position.bottom } : {} }
		>
			{ filtered.map( ( cmd, i ) => (
				<div
					key={ cmd.name }
					className={ `gratis-ai-agent-slash-item ${
						i === selectedIndex ? 'is-selected' : ''
					}` }
					role="option"
					aria-selected={ i === selectedIndex }
					tabIndex={ 0 }
					onClick={ () => onSelect( cmd ) }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' || e.key === ' ' ) {
							onSelect( cmd );
						}
					} }
					onMouseEnter={ () => setSelectedIndex( i ) }
				>
					<span className="gratis-ai-agent-slash-name">
						{ cmd.name }
					</span>
					<span className="gratis-ai-agent-slash-desc">
						{ cmd.description }
					</span>
				</div>
			) ) }
		</div>
	);
}

export { COMMANDS };
