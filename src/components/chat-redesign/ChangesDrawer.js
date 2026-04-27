/**
 * Changes drawer — popover anchored to the convo header changes pill.
 *
 * Reads /gratis-ai-agent/v1/changes?session_id=X&reverted=false directly
 * so it does not depend on the deprecated session-changes-bar.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon, closeSmall, undo } from '@wordpress/icons';

/**
 *
 * @param {*} change
 */
function changeTitle( change ) {
	// Build a 1-line title from the change row shape used by the Changes REST endpoint.
	if ( change.title ) {
		return change.title;
	}
	const action =
		change.action ||
		change.change_type ||
		__( 'Change', 'gratis-ai-agent' );
	const object = change.object_type || change.entity || '';
	const label = change.object_label || change.label || '';
	return [ action, object, label ].filter( Boolean ).join( ' ' );
}

/**
 *
 * @param {*} change
 */
function changeMeta( change ) {
	const parts = [];
	if ( change.object_id ) {
		parts.push( `ID ${ change.object_id }` );
	}
	if ( change.status ) {
		parts.push( change.status );
	}
	if ( change.bulk_count ) {
		parts.push( `bulk · ${ change.bulk_count }` );
	}
	return parts.join( ' · ' );
}

/**
 *
 * @param {Object} root0
 * @param {*}      root0.sessionId
 * @param {*}      root0.onClose
 * @param {*}      root0.onChangesCountChange
 */
export default function ChangesDrawer( {
	sessionId,
	onClose,
	onChangesCountChange,
} ) {
	const [ changes, setChanges ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ reverting, setReverting ] = useState( false );

	const refresh = useCallback( async () => {
		if ( ! sessionId ) {
			setChanges( [] );
			return;
		}
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/changes?session_id=${ sessionId }&reverted=false&revertable=true&per_page=100`,
			} );
			const items = data.items || [];
			setChanges( items );
			onChangesCountChange?.( items.length );
		} catch {
			// Silent — drawer just shows an empty state.
		} finally {
			setLoading( false );
		}
	}, [ sessionId, onChangesCountChange ] );

	useEffect( () => {
		refresh();
	}, [ refresh ] );

	const handleRevert = useCallback(
		async ( id ) => {
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/changes/${ id }/revert`,
					method: 'POST',
				} );
				await refresh();
			} catch {
				// Silent
			}
		},
		[ refresh ]
	);

	const handleRevertAll = useCallback( async () => {
		if ( reverting || changes.length === 0 ) {
			return;
		}
		setReverting( true );
		for ( const c of changes ) {
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/changes/${ c.id }/revert`,
					method: 'POST',
				} );
			} catch {
				// Continue
			}
		}
		setReverting( false );
		await refresh();
	}, [ reverting, changes, refresh ] );

	const headerLabel = sprintf(
		/* translators: %d: number of un-reverted changes */
		_n(
			'%d change in this session',
			'%d changes in this session',
			changes.length,
			'gratis-ai-agent'
		),
		changes.length
	);

	return (
		<div
			className="gaa-cr-changes-drawer"
			role="dialog"
			aria-label={ __( 'Session changes', 'gratis-ai-agent' ) }
		>
			<div className="gaa-cr-changes-drawer-head">
				<span>{ headerLabel }</span>
				<button
					type="button"
					className="gaa-cr-icon-btn"
					onClick={ onClose }
					aria-label={ __(
						'Close changes drawer',
						'gratis-ai-agent'
					) }
				>
					<Icon icon={ closeSmall } size={ 18 } />
				</button>
			</div>
			<div className="gaa-cr-changes-drawer-body">
				{ loading && changes.length === 0 && (
					<div className="gaa-cr-changes-drawer-empty">
						{ __( 'Loading changes…', 'gratis-ai-agent' ) }
					</div>
				) }
				{ ! loading && changes.length === 0 && (
					<div className="gaa-cr-changes-drawer-empty">
						{ __( 'No un-reverted changes.', 'gratis-ai-agent' ) }
					</div>
				) }
				{ changes.map( ( c ) => (
					<div key={ c.id } className="gaa-cr-changes-row">
						<div className="gaa-cr-changes-row-body">
							<div className="gaa-cr-changes-row-title">
								{ changeTitle( c ) }
							</div>
							<div className="gaa-cr-changes-row-meta">
								{ changeMeta( c ) }
							</div>
						</div>
						<button
							type="button"
							className="gaa-cr-icon-btn is-small"
							onClick={ () => handleRevert( c.id ) }
							aria-label={ __(
								'Revert this change',
								'gratis-ai-agent'
							) }
						>
							<Icon icon={ undo } size={ 14 } />
						</button>
					</div>
				) ) }
			</div>
			<div className="gaa-cr-changes-drawer-foot">
				<a
					className="gaa-cr-btn-sm"
					href={
						window.gratisAiAgentData?.changesPageUrl ||
						window.location.href.split( '#' )[ 0 ] + '#/changes'
					}
				>
					{ __( 'View full history', 'gratis-ai-agent' ) }
				</a>
				<button
					type="button"
					className="gaa-cr-btn-sm is-destructive"
					onClick={ handleRevertAll }
					disabled={ reverting || changes.length === 0 }
				>
					{ reverting
						? __( 'Reverting…', 'gratis-ai-agent' )
						: sprintf(
								/* translators: %d: number of changes to revert */
								__( 'Revert all (%d)', 'gratis-ai-agent' ),
								changes.length
						  ) }
				</button>
			</div>
		</div>
	);
}
