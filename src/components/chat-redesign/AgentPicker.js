/**
 * Agent chip — compact agent selector for the input toolbar.
 *
 * Clicking the chip opens an upward popover listing enabled agents, each
 * showing its configured icon (dashicon slug or emoji) and name. Selecting
 * an agent updates the store's selectedAgentId.
 *
 * The popover is portaled to document.body and positioned with fixed
 * coordinates so it is not clipped by any ancestor overflow.
 *
 * Only renders when two or more agents are enabled.
 */

import {
	useState,
	useRef,
	useEffect,
	useLayoutEffect,
	useCallback,
} from '@wordpress/element';
import { createPortal } from 'react-dom';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, chevronDown, check } from '@wordpress/icons';

import STORE_NAME from '../../store';

/**
 * Renders the agent's configured icon: a WordPress dashicon or plain text
 * (emoji). Returns null when no icon is configured.
 *
 * @param {Object} props
 * @param {string} [props.icon] avatar_icon value from the agent record.
 */
function AgentIcon( { icon } ) {
	if ( ! icon ) {
		return null;
	}
	if ( icon.startsWith( 'dashicons-' ) ) {
		return (
			<span
				className={ `dashicons ${ icon } gaa-cr-agent-icon` }
				aria-hidden="true"
			/>
		);
	}
	return (
		<span
			className="gaa-cr-agent-icon gaa-cr-agent-icon--emoji"
			aria-hidden="true"
		>
			{ icon }
		</span>
	);
}

/**
 *
 */
export default function AgentPicker() {
	const { fetchAgents, setSelectedAgentId } = useDispatch( STORE_NAME );

	const { agents, agentsLoaded, selectedAgentId } = useSelect( ( sel ) => {
		const s = sel( STORE_NAME );
		return {
			agents: s.getAgents(),
			agentsLoaded: s.getAgentsLoaded(),
			selectedAgentId: s.getSelectedAgentId(),
		};
	}, [] );

	useEffect( () => {
		if ( ! agentsLoaded ) {
			fetchAgents();
		}
	}, [ agentsLoaded, fetchAgents ] );

	const [ open, setOpen ] = useState( false );
	const [ pos, setPos ] = useState( {
		left: 0,
		bottom: 0,
		minWidth: 160,
		maxHeight: 400,
	} );
	const chipRef = useRef( null );
	const popoverRef = useRef( null );

	const updatePosition = useCallback( () => {
		if ( ! chipRef.current ) {
			return;
		}
		const rect = chipRef.current.getBoundingClientRect();
		const gap = 6;
		const topMargin = 16;
		setPos( {
			left: rect.left,
			bottom: window.innerHeight - rect.top + gap,
			minWidth: Math.max( rect.width, 180 ),
			maxHeight: Math.max( 120, rect.top - gap - topMargin ),
		} );
	}, [] );

	useLayoutEffect( () => {
		if ( open ) {
			updatePosition();
		}
	}, [ open, updatePosition ] );

	// When the popover opens, move focus to the active menu item so keyboard
	// users land on a sensible starting point without having to tab through.
	useEffect( () => {
		if ( ! open || ! popoverRef.current ) {
			return;
		}
		const active = popoverRef.current.querySelector(
			'[role="menuitem"].is-active'
		);
		const first = popoverRef.current.querySelector( '[role="menuitem"]' );
		( active ?? first )?.focus();
	}, [ open ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const handler = ( e ) => {
			if (
				chipRef.current &&
				! chipRef.current.contains( e.target ) &&
				popoverRef.current &&
				! popoverRef.current.contains( e.target )
			) {
				setOpen( false );
			}
		};
		const onScrollOrResize = () => updatePosition();
		const onKeyDown = ( e ) => {
			if ( ! popoverRef.current ) {
				return;
			}
			const items = Array.from(
				popoverRef.current.querySelectorAll( '[role="menuitem"]' )
			);
			const focused = popoverRef.current.ownerDocument.activeElement;
			const currentIndex = items.indexOf( focused );

			if ( e.key === 'Escape' ) {
				e.preventDefault();
				setOpen( false );
				chipRef.current?.focus();
			} else if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				const next = items[ currentIndex + 1 ] ?? items[ 0 ];
				next?.focus();
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				const prev =
					items[ currentIndex - 1 ] ?? items[ items.length - 1 ];
				prev?.focus();
			} else if ( e.key === 'Home' ) {
				e.preventDefault();
				items[ 0 ]?.focus();
			} else if ( e.key === 'End' ) {
				e.preventDefault();
				items[ items.length - 1 ]?.focus();
			}
		};
		document.addEventListener( 'mousedown', handler );
		document.addEventListener( 'keydown', onKeyDown );
		window.addEventListener( 'resize', onScrollOrResize );
		window.addEventListener( 'scroll', onScrollOrResize, true );
		return () => {
			document.removeEventListener( 'mousedown', handler );
			document.removeEventListener( 'keydown', onKeyDown );
			window.removeEventListener( 'resize', onScrollOrResize );
			window.removeEventListener( 'scroll', onScrollOrResize, true );
		};
	}, [ open, updatePosition ] );

	// Only show when there are multiple agents to switch between.
	const enabledAgents = agents.filter( ( a ) => a.enabled );
	if ( ! agentsLoaded || enabledAgents.length < 2 ) {
		return null;
	}

	const activeAgent =
		enabledAgents.find(
			( a ) => String( a.id ) === String( selectedAgentId )
		) || enabledAgents[ 0 ];

	const pick = ( id ) => {
		setSelectedAgentId( id );
		setOpen( false );
	};

	const popover = open
		? createPortal(
				<div
					ref={ popoverRef }
					className="gaa-cr-popover gaa-cr-popover-fixed"
					role="menu"
					aria-label={ __( 'Select agent', 'gratis-ai-agent' ) }
					style={ {
						left: pos.left,
						bottom: pos.bottom,
						minWidth: pos.minWidth,
						maxHeight: pos.maxHeight,
					} }
				>
					<div className="gaa-cr-popover-section-label">
						{ __( 'Agent', 'gratis-ai-agent' ) }
					</div>
					{ enabledAgents.map( ( agent ) => {
						const active =
							String( agent.id ) === String( activeAgent?.id );
						return (
							<button
								type="button"
								key={ agent.id }
								role="menuitem"
								className={ `gaa-cr-popover-item gaa-cr-agent-popover-item${
									active ? ' is-active' : ''
								}` }
								onClick={ () => pick( agent.id ) }
							>
								<AgentIcon icon={ agent.avatar_icon } />
								<span>{ agent.name }</span>
								{ active && (
									<span className="gaa-cr-popover-item-check">
										<Icon icon={ check } size={ 14 } />
									</span>
								) }
							</button>
						);
					} ) }
				</div>,
				document.body
		  )
		: null;

	return (
		<div className="gaa-cr-model-chip-wrap">
			<button
				ref={ chipRef }
				type="button"
				className="gaa-cr-model-chip gaa-cr-agent-chip"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-haspopup="menu"
				aria-expanded={ open }
				title={ __( 'Change agent', 'gratis-ai-agent' ) }
			>
				<AgentIcon icon={ activeAgent?.avatar_icon } />
				<span className="gaa-cr-model-chip-model">
					{ activeAgent?.name ||
						__( '(default)', 'gratis-ai-agent' ) }
				</span>
				<Icon icon={ chevronDown } size={ 14 } />
			</button>
			{ popover }
		</div>
	);
}
