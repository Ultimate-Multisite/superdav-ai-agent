/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @typedef {import('../types').SlashCommand} SlashCommand
 */

/**
 * All registered slash commands.
 *
 * @type {SlashCommand[]}
 */
const COMMANDS = [
	{
		name: '/new',
		description: __( 'Start a new chat', 'ai-agent' ),
		action: 'new',
	},
	{
		name: '/model',
		description: __( 'Switch model (type model name after)', 'ai-agent' ),
		action: 'model',
	},
	{
		name: '/remember',
		description: __(
			'Save a fact to memory (type fact after)',
			'ai-agent'
		),
		action: 'remember',
	},
	{
		name: '/forget',
		description: __( 'Forget memories matching a topic', 'ai-agent' ),
		action: 'forget',
	},
	{
		name: '/clear',
		description: __( 'Clear conversation', 'ai-agent' ),
		action: 'clear',
	},
	{
		name: '/export',
		description: __( 'Export current conversation', 'ai-agent' ),
		action: 'export',
	},
	{
		name: '/compact',
		description: __( 'Compact conversation to save context', 'ai-agent' ),
		action: 'compact',
	},
	{
		name: '/help',
		description: __( 'Show keyboard shortcuts', 'ai-agent' ),
		action: 'help',
	},
	{
		name: '/debug',
		description: __(
			'Toggle debug mode (per-response metrics)',
			'ai-agent'
		),
		action: 'debug',
	},
];

/**
 * Autocomplete dropdown for slash commands.
 *
 * Filters the command list as the user types, supports keyboard navigation
 * (ArrowUp/Down, Enter/Tab to select, Escape to close), and closes when
 * no commands match.
 *
 * @param {Object}           props            - Component props.
 * @param {string}           props.filter     - Current input text used to filter commands.
 * @param {Function}         props.onSelect   - Called with the selected SlashCommand object.
 * @param {Function}         props.onClose    - Called when the menu should close.
 * @param {{bottom: number}} [props.position] - Optional CSS bottom offset for positioning.
 * @return {JSX.Element|null} The command menu, or null when no commands match.
 */
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
			className="ai-agent-slash-menu"
			ref={ menuRef }
			style={ position ? { bottom: position.bottom } : {} }
		>
			{ filtered.map( ( cmd, i ) => (
				<div
					key={ cmd.name }
					role="option"
					aria-selected={ i === selectedIndex }
					tabIndex={ 0 }
					className={ `ai-agent-slash-item ${
						i === selectedIndex ? 'is-selected' : ''
					}` }
					onClick={ () => onSelect( cmd ) }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' || e.key === ' ' ) {
							e.preventDefault();
							onSelect( cmd );
						}
					} }
					onMouseEnter={ () => setSelectedIndex( i ) }
				>
					<span className="ai-agent-slash-name">{ cmd.name }</span>
					<span className="ai-agent-slash-desc">
						{ cmd.description }
					</span>
				</div>
			) ) }
		</div>
	);
}

export { COMMANDS };
