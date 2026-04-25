/**
 * Model chip — combined provider + model selector.
 *
 * Clicking the chip opens an upward popover with provider-grouped model
 * rows. Selecting a model updates the store's selectedProviderId and
 * selectedModelId. The popover is portaled to document.body and positioned
 * with fixed coordinates so it is not clipped by any ancestor overflow.
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
 *
 */
export default function ModelPicker() {
	const { providers, selectedProviderId, selectedModelId } = useSelect(
		( sel ) => {
			const s = sel( STORE_NAME );
			return {
				providers: s.getProviders(),
				selectedProviderId: s.getSelectedProviderId(),
				selectedModelId: s.getSelectedModelId(),
			};
		},
		[]
	);
	const { setSelectedProvider, setSelectedModel } = useDispatch( STORE_NAME );
	const [ open, setOpen ] = useState( false );
	const [ pos, setPos ] = useState( {
		left: 0,
		bottom: 0,
		width: 0,
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
		// Anchor the bottom of the popover just above the chip, and limit its
		// height so it never exceeds the space between the viewport top and
		// the chip (minus margins).
		setPos( {
			left: rect.left,
			bottom: window.innerHeight - rect.top + gap,
			width: rect.width,
			maxHeight: Math.max( 120, rect.top - gap - topMargin ),
		} );
	}, [] );

	useLayoutEffect( () => {
		if ( open ) {
			updatePosition();
		}
	}, [ open, updatePosition ] );

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
		document.addEventListener( 'mousedown', handler );
		window.addEventListener( 'resize', onScrollOrResize );
		window.addEventListener( 'scroll', onScrollOrResize, true );
		return () => {
			document.removeEventListener( 'mousedown', handler );
			window.removeEventListener( 'resize', onScrollOrResize );
			window.removeEventListener( 'scroll', onScrollOrResize, true );
		};
	}, [ open, updatePosition ] );

	const activeProvider =
		providers.find( ( p ) => p.id === selectedProviderId ) ||
		providers[ 0 ];
	const activeModel =
		activeProvider?.models?.find( ( m ) => m.id === selectedModelId ) ||
		activeProvider?.models?.[ 0 ];

	if ( ! activeProvider ) {
		return null;
	}

	const pick = ( providerId, modelId ) => {
		if ( providerId !== selectedProviderId ) {
			setSelectedProvider( providerId );
		}
		setSelectedModel( modelId );
		setOpen( false );
	};

	const popover = open
		? createPortal(
				<div
					ref={ popoverRef }
					className="gaa-cr-popover gaa-cr-popover-fixed"
					role="menu"
					style={ {
						left: pos.left,
						bottom: pos.bottom,
						minWidth: Math.max( pos.width, 240 ),
						maxHeight: pos.maxHeight,
					} }
				>
					{ providers.map( ( p ) => (
						<div key={ p.id }>
							<div className="gaa-cr-popover-section-label">
								{ p.name }
							</div>
							{ ( p.models || [] ).map( ( m ) => {
								const active =
									p.id === selectedProviderId &&
									m.id === selectedModelId;
								return (
									<button
										type="button"
										key={ m.id }
										role="menuitem"
										className={ `gaa-cr-popover-item${
											active ? ' is-active' : ''
										}` }
										onClick={ () => pick( p.id, m.id ) }
									>
										<span>{ m.name || m.id }</span>
										{ m.sub && (
											<span className="gaa-cr-popover-item-sub">
												{ m.sub }
											</span>
										) }
										{ active && (
											<span className="gaa-cr-popover-item-check">
												<Icon
													icon={ check }
													size={ 14 }
												/>
											</span>
										) }
									</button>
								);
							} ) }
						</div>
					) ) }
				</div>,
				document.body
		  )
		: null;

	return (
		<div className="gaa-cr-model-chip-wrap">
			<button
				ref={ chipRef }
				type="button"
				className="gaa-cr-model-chip"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-haspopup="menu"
				aria-expanded={ open }
				title={ __( 'Change model', 'gratis-ai-agent' ) }
			>
				<span className="gaa-cr-model-chip-provider">
					{ activeProvider.name }
				</span>
				<span className="gaa-cr-model-chip-model">
					{ activeModel?.name ||
						activeModel?.id ||
						__( '(default)', 'gratis-ai-agent' ) }
				</span>
				<Icon icon={ chevronDown } size={ 14 } />
			</button>
			{ popover }
		</div>
	);
}
