/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Modal, SelectControl, Notice } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Dialog for sharing a session with other admin users.
 *
 * Shows the current share list, allows adding new users, and revoking access.
 *
 * @param {Object}   props           - Component props.
 * @param {number}   props.sessionId - Session ID to share.
 * @param {Function} props.onClose   - Called when the dialog should close.
 * @return {JSX.Element} The share dialog element.
 */
export default function ShareSessionDialog( { sessionId, onClose } ) {
	const [ adminUsers, setAdminUsers ] = useState( [] );
	const [ shares, setShares ] = useState( [] );
	const [ selectedUserId, setSelectedUserId ] = useState( '' );
	const [ selectedPermission, setSelectedPermission ] =
		useState( 'contribute' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	const {
		fetchAdminUsers,
		fetchSessionShares,
		shareSession,
		unshareSession,
	} = useDispatch( STORE_NAME );

	// Load admin users and current shares on mount.
	useEffect( () => {
		let cancelled = false;

		const load = async () => {
			setLoading( true );
			setError( '' );
			try {
				const [ users, currentShares ] = await Promise.all( [
					fetchAdminUsers(),
					fetchSessionShares( sessionId ),
				] );
				if ( ! cancelled ) {
					setAdminUsers( users || [] );
					setShares( currentShares || [] );
				}
			} catch {
				if ( ! cancelled ) {
					setError(
						__( 'Failed to load sharing data.', 'gratis-ai-agent' )
					);
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		};

		load();

		return () => {
			cancelled = true;
		};
	}, [ sessionId, fetchAdminUsers, fetchSessionShares ] );

	// Filter out already-shared users from the picker.
	const sharedUserIds = shares.map( ( s ) =>
		parseInt( s.shared_with_user_id, 10 )
	);
	const availableUsers = adminUsers.filter(
		( u ) => ! sharedUserIds.includes( parseInt( u.id, 10 ) )
	);

	const handleShare = useCallback( async () => {
		if ( ! selectedUserId ) {
			return;
		}
		setSaving( true );
		setError( '' );
		try {
			const updated = await shareSession(
				sessionId,
				parseInt( selectedUserId, 10 ),
				selectedPermission
			);
			setShares( updated || [] );
			setSelectedUserId( '' );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to share session.', 'gratis-ai-agent' )
			);
		} finally {
			setSaving( false );
		}
	}, [ sessionId, selectedUserId, selectedPermission, shareSession ] );

	const handleRevoke = useCallback(
		async ( userId ) => {
			setSaving( true );
			setError( '' );
			try {
				const updated = await unshareSession( sessionId, userId );
				setShares( updated || [] );
			} catch ( err ) {
				setError(
					err?.message ||
						__( 'Failed to revoke access.', 'gratis-ai-agent' )
				);
			} finally {
				setSaving( false );
			}
		},
		[ sessionId, unshareSession ]
	);

	return (
		<Modal
			title={ __( 'Share Conversation', 'gratis-ai-agent' ) }
			onRequestClose={ onClose }
			className="ai-agent-share-dialog"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading ? (
				<p>{ __( 'Loading…', 'gratis-ai-agent' ) }</p>
			) : (
				<>
					{ /* Current shares */ }
					{ shares.length > 0 && (
						<div className="ai-agent-share-list">
							<h3>{ __( 'Shared with', 'gratis-ai-agent' ) }</h3>
							<ul>
								{ shares.map( ( share ) => (
									<li
										key={ share.shared_with_user_id }
										className="ai-agent-share-item"
									>
										<span className="ai-agent-share-user">
											{ share.display_name ||
												share.user_email }
										</span>
										<span className="ai-agent-share-permission">
											{ share.permission === 'contribute'
												? __(
														'Can contribute',
														'gratis-ai-agent'
												  )
												: __(
														'View only',
														'gratis-ai-agent'
												  ) }
										</span>
										<Button
											variant="tertiary"
											isDestructive
											onClick={ () =>
												handleRevoke(
													parseInt(
														share.shared_with_user_id,
														10
													)
												)
											}
											disabled={ saving }
										>
											{ __(
												'Revoke',
												'gratis-ai-agent'
											) }
										</Button>
									</li>
								) ) }
							</ul>
						</div>
					) }

					{ /* Add new share */ }
					{ availableUsers.length > 0 ? (
						<div className="ai-agent-share-add">
							<h3>
								{ __( 'Add collaborator', 'gratis-ai-agent' ) }
							</h3>
							<div className="ai-agent-share-add-row">
								<SelectControl
									label={ __(
										'Admin user',
										'gratis-ai-agent'
									) }
									value={ selectedUserId }
									options={ [
										{
											value: '',
											label: __(
												'Select a user…',
												'gratis-ai-agent'
											),
										},
										...availableUsers.map( ( u ) => ( {
											value: String( u.id ),
											label:
												u.display_name || u.user_email,
										} ) ),
									] }
									onChange={ setSelectedUserId }
								/>
								<SelectControl
									label={ __(
										'Permission',
										'gratis-ai-agent'
									) }
									value={ selectedPermission }
									options={ [
										{
											value: 'contribute',
											label: __(
												'Can contribute',
												'gratis-ai-agent'
											),
										},
										{
											value: 'view',
											label: __(
												'View only',
												'gratis-ai-agent'
											),
										},
									] }
									onChange={ setSelectedPermission }
								/>
								<Button
									variant="primary"
									onClick={ handleShare }
									disabled={ ! selectedUserId || saving }
								>
									{ __( 'Share', 'gratis-ai-agent' ) }
								</Button>
							</div>
						</div>
					) : (
						shares.length === 0 && (
							<p className="ai-agent-share-no-users">
								{ __(
									'No other admin users available to share with.',
									'gratis-ai-agent'
								) }
							</p>
						)
					) }
				</>
			) }

			<div className="ai-agent-share-dialog-footer">
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Close', 'gratis-ai-agent' ) }
				</Button>
			</div>
		</Modal>
	);
}
