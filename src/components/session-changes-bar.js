/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Persistent bar shown at the bottom of the chat panel whenever the current
 * session has un-reverted AI-made changes.
 *
 * Fetches from GET /gratis-ai-agent/v1/changes?session_id=X&reverted=false
 * after every agent turn (when `sending` transitions true→false).
 * Provides one-click "Revert all" and a link to the full Changes page.
 *
 * @return {JSX.Element|null} The changes bar, or null when there is nothing to show.
 */
export default function SessionChangesBar() {
	const { currentSessionId, sending } = useSelect(
		( select ) => ( {
			currentSessionId: select( STORE_NAME ).getCurrentSessionId(),
			sending: select( STORE_NAME ).isSending(),
		} ),
		[]
	);

	const [ changes, setChanges ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ reverting, setReverting ] = useState( false );
	const [ revertedCount, setRevertedCount ] = useState( 0 );
	const [ dismissed, setDismissed ] = useState( false );

	// Track the previous `sending` value to detect turn completion.
	const prevSendingRef = useRef( false );

	const fetchChanges = useCallback( async () => {
		if ( ! currentSessionId ) {
			setChanges( [] );
			return;
		}
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/changes?session_id=${ currentSessionId }&reverted=false&per_page=100`,
			} );
			const items = data.items || [];
			setChanges( items );
			// Reset dismissed/reverted count whenever new changes arrive.
			if ( items.length > 0 ) {
				setDismissed( false );
				setRevertedCount( 0 );
			}
		} catch {
			// Silent — don't disrupt the chat on a background fetch failure.
		} finally {
			setLoading( false );
		}
	}, [ currentSessionId ] );

	// Fetch when the session changes.
	useEffect( () => {
		setChanges( [] );
		setRevertedCount( 0 );
		setDismissed( false );
		fetchChanges();
	}, [ fetchChanges ] );

	// Refetch when a turn completes (sending: true → false).
	useEffect( () => {
		const wasSending = prevSendingRef.current;
		prevSendingRef.current = sending;
		if ( wasSending && ! sending && currentSessionId ) {
			fetchChanges();
		}
	}, [ sending, currentSessionId, fetchChanges ] );

	const handleRevertAll = useCallback( async () => {
		if ( reverting || changes.length === 0 ) {
			return;
		}
		setReverting( true );
		let reverted = 0;
		for ( const change of changes ) {
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/changes/${ change.id }/revert`,
					method: 'POST',
				} );
				reverted++;
			} catch {
				// Continue with remaining changes even if one fails.
			}
		}
		setRevertedCount( ( prev ) => prev + reverted );
		setReverting( false );
		// Refresh so the count reflects what's actually left.
		await fetchChanges();
	}, [ changes, reverting, fetchChanges ] );

	// Nothing to show: no session, still loading, no changes, not just reverted.
	if (
		! currentSessionId ||
		loading ||
		( changes.length === 0 && revertedCount === 0 )
	) {
		return null;
	}

	if ( dismissed ) {
		return null;
	}

	// Success state: all changes reverted.
	if ( revertedCount > 0 && changes.length === 0 ) {
		const revertedLabel =
			revertedCount === 1
				? sprintf(
						/* translators: %d: number of changes reverted */
						__( '%d change reverted.', 'gratis-ai-agent' ),
						revertedCount
				  )
				: sprintf(
						/* translators: %d: number of changes reverted */
						__( '%d changes reverted.', 'gratis-ai-agent' ),
						revertedCount
				  );

		return (
			<div className="gratis-ai-agent-changes-bar gratis-ai-agent-changes-bar--reverted">
				<span className="gratis-ai-agent-changes-bar__text">
					{ revertedLabel }
				</span>
				<Button
					variant="link"
					className="gratis-ai-agent-changes-bar__dismiss"
					onClick={ () => setDismissed( true ) }
					size="small"
				>
					{ __( 'Dismiss', 'gratis-ai-agent' ) }
				</Button>
			</div>
		);
	}

	// Active state: show count + revert/view actions.
	const changesUrl = window.location.href.split( '#' )[ 0 ] + '#/changes';

	const changesLabel =
		changes.length === 1
			? sprintf(
					/* translators: %d: number of AI-made changes in this session */
					__( '%d change made', 'gratis-ai-agent' ),
					changes.length
			  )
			: sprintf(
					/* translators: %d: number of AI-made changes in this session */
					__( '%d changes made', 'gratis-ai-agent' ),
					changes.length
			  );

	let revertButtonLabel;
	if ( reverting ) {
		revertButtonLabel = __( 'Reverting\u2026', 'gratis-ai-agent' );
	} else if ( changes.length === 1 ) {
		revertButtonLabel = __( 'Revert', 'gratis-ai-agent' );
	} else {
		revertButtonLabel = sprintf(
			/* translators: %d: number of changes to revert */
			__( 'Revert all (%d)', 'gratis-ai-agent' ),
			changes.length
		);
	}

	return (
		<div className="gratis-ai-agent-changes-bar">
			<span className="gratis-ai-agent-changes-bar__text">
				{ changesLabel }
			</span>
			<div className="gratis-ai-agent-changes-bar__actions">
				<Button
					variant="secondary"
					isDestructive
					onClick={ handleRevertAll }
					disabled={ reverting }
					isBusy={ reverting }
					size="small"
				>
					{ revertButtonLabel }
				</Button>
				<Button
					variant="link"
					href={ changesUrl }
					className="gratis-ai-agent-changes-bar__view-link"
					size="small"
				>
					{ __( 'View changes \u2197', 'gratis-ai-agent' ) }
				</Button>
			</div>
		</div>
	);
}
