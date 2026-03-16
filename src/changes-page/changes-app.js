/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import {
	Button,
	Notice,
	Spinner,
	SelectControl,
	CheckboxControl,
	Modal,
	Badge,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Format a datetime string for display.
 *
 * @param {string} dateStr ISO datetime string.
 * @return {string} Formatted date.
 */
function formatDate( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}
	try {
		return new Date( dateStr + 'Z' ).toLocaleString();
	} catch {
		return dateStr;
	}
}

/**
 * Truncate a string to a max length.
 *
 * @param {string} str    Input string.
 * @param {number} maxLen Max length.
 * @return {string} Truncated string.
 */
function truncate( str, maxLen = 120 ) {
	if ( ! str ) {
		return '';
	}
	return str.length > maxLen ? str.slice( 0, maxLen ) + '…' : str;
}

/**
 * DiffViewer renders a simple before/after diff.
 *
 * @param {Object} props
 * @param {string} props.before Before value.
 * @param {string} props.after  After value.
 */
function DiffViewer( { before, after } ) {
	const beforeLines = ( before || '' ).split( '\n' );
	const afterLines = ( after || '' ).split( '\n' );

	return (
		<div className="gratis-changes-diff">
			<div className="gratis-changes-diff__pane gratis-changes-diff__pane--before">
				<div className="gratis-changes-diff__label">
					{ __( 'Before', 'gratis-ai-agent' ) }
				</div>
				<pre className="gratis-changes-diff__code">
					{ beforeLines.map( ( line, i ) => {
						const afterLine = afterLines[ i ];
						const changed = line !== afterLine;
						return (
							<div
								key={ i }
								className={
									changed
										? 'gratis-changes-diff__line gratis-changes-diff__line--removed'
										: 'gratis-changes-diff__line'
								}
							>
								{ line }
							</div>
						);
					} ) }
				</pre>
			</div>
			<div className="gratis-changes-diff__pane gratis-changes-diff__pane--after">
				<div className="gratis-changes-diff__label">
					{ __( 'After', 'gratis-ai-agent' ) }
				</div>
				<pre className="gratis-changes-diff__code">
					{ afterLines.map( ( line, i ) => {
						const beforeLine = beforeLines[ i ];
						const changed = line !== beforeLine;
						return (
							<div
								key={ i }
								className={
									changed
										? 'gratis-changes-diff__line gratis-changes-diff__line--added'
										: 'gratis-changes-diff__line'
								}
							>
								{ line }
							</div>
						);
					} ) }
				</pre>
			</div>
		</div>
	);
}

/**
 * Main Changes App component.
 */
export default function ChangesApp() {
	const [ changes, setChanges ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ perPage ] = useState( 25 );
	const [ loading, setLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ filterType, setFilterType ] = useState( '' );
	const [ filterReverted, setFilterReverted ] = useState( '' );
	const [ selectedIds, setSelectedIds ] = useState( [] );
	const [ diffModal, setDiffModal ] = useState( null );
	const [ diffLoading, setDiffLoading ] = useState( false );
	const [ exporting, setExporting ] = useState( false );
	const [ reverting, setReverting ] = useState( null );

	const fetchChanges = useCallback( async () => {
		setLoading( true );
		setNotice( null );
		try {
			const params = new URLSearchParams( {
				per_page: perPage,
				page,
			} );
			if ( filterType ) {
				params.set( 'object_type', filterType );
			}
			if ( filterReverted !== '' ) {
				params.set( 'reverted', filterReverted );
			}
			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/changes?${ params.toString() }`,
			} );
			setChanges( data.items || [] );
			setTotal( data.total || 0 );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to load changes.', 'gratis-ai-agent' ),
			} );
		} finally {
			setLoading( false );
		}
	}, [ page, perPage, filterType, filterReverted ] );

	useEffect( () => {
		fetchChanges();
	}, [ fetchChanges ] );

	const handleSelectAll = useCallback(
		( checked ) => {
			const currentPageIds = changes.map( ( change ) => change.id );
			setSelectedIds( ( prev ) =>
				checked
					? [ ...new Set( [ ...prev, ...currentPageIds ] ) ]
					: prev.filter( ( id ) => ! currentPageIds.includes( id ) )
			);
		},
		[ changes ]
	);

	const handleSelectOne = useCallback( ( id, checked ) => {
		setSelectedIds( ( prev ) =>
			checked ? [ ...prev, id ] : prev.filter( ( i ) => i !== id )
		);
	}, [] );

	const handleViewDiff = useCallback( async ( change ) => {
		setDiffLoading( true );
		setDiffModal( { change, diff: null } );
		try {
			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/changes/${ change.id }/diff`,
			} );
			setDiffModal( { change, diff: data } );
		} catch ( err ) {
			setDiffModal( {
				change,
				diff: null,
				error:
					err?.message ||
					__( 'Failed to load diff.', 'gratis-ai-agent' ),
			} );
		} finally {
			setDiffLoading( false );
		}
	}, [] );

	const handleRevert = useCallback(
		async ( id ) => {
			// eslint-disable-next-line no-alert -- Intentional confirmation dialog for destructive revert action.
			const confirmed = window.confirm(
				__(
					'Revert this change? The original value will be restored.',
					'gratis-ai-agent'
				)
			); // eslint-disable-line no-alert
			if ( ! confirmed ) {
				return;
			}
			setReverting( id );
			setNotice( null );
			try {
				await apiFetch( {
					path: `/gratis-ai-agent/v1/changes/${ id }/revert`,
					method: 'POST',
				} );
				setNotice( {
					status: 'success',
					message: __( 'Change reverted.', 'gratis-ai-agent' ),
				} );
				fetchChanges();
			} catch ( err ) {
				setNotice( {
					status: 'error',
					message:
						err?.message ||
						__( 'Failed to revert change.', 'gratis-ai-agent' ),
				} );
			} finally {
				setReverting( null );
			}
		},
		[ fetchChanges ]
	);

	const handleExport = useCallback( async () => {
		if ( selectedIds.length === 0 ) {
			setNotice( {
				status: 'warning',
				message: __(
					'Select at least one change to export.',
					'gratis-ai-agent'
				),
			} );
			return;
		}
		setExporting( true );
		setNotice( null );
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/changes/export',
				method: 'POST',
				data: { ids: selectedIds },
			} );
			// Trigger download.
			const blob = new Blob( [ data.patch ], { type: 'text/plain' } );
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = data.filename || 'ai-changes.patch';
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			URL.revokeObjectURL( url );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to export changes.', 'gratis-ai-agent' ),
			} );
		} finally {
			setExporting( false );
		}
	}, [ selectedIds ] );

	const totalPages = Math.ceil( total / perPage );
	const currentPageIds = changes.map( ( change ) => change.id );
	const allSelected =
		currentPageIds.length > 0 &&
		currentPageIds.every( ( id ) => selectedIds.includes( id ) );

	return (
		<div className="gratis-changes-app">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					isDismissible
				>
					{ notice.message }
				</Notice>
			) }

			{ /* Filters */ }
			<div className="gratis-changes-filters">
				<SelectControl
					label={ __( 'Object Type', 'gratis-ai-agent' ) }
					value={ filterType }
					options={ [
						{
							label: __( 'All Types', 'gratis-ai-agent' ),
							value: '',
						},
						{
							label: __( 'Post', 'gratis-ai-agent' ),
							value: 'post',
						},
						{
							label: __( 'Option', 'gratis-ai-agent' ),
							value: 'option',
						},
						{
							label: __( 'Term', 'gratis-ai-agent' ),
							value: 'term',
						},
						{
							label: __( 'User', 'gratis-ai-agent' ),
							value: 'user',
						},
					] }
					onChange={ ( val ) => {
						setFilterType( val );
						setPage( 1 );
					} }
				/>
				<SelectControl
					label={ __( 'Status', 'gratis-ai-agent' ) }
					value={ filterReverted }
					options={ [
						{ label: __( 'All', 'gratis-ai-agent' ), value: '' },
						{
							label: __( 'Active', 'gratis-ai-agent' ),
							value: 'false',
						},
						{
							label: __( 'Reverted', 'gratis-ai-agent' ),
							value: 'true',
						},
					] }
					onChange={ ( val ) => {
						setFilterReverted( val );
						setPage( 1 );
					} }
				/>
				<div className="gratis-changes-filters__actions">
					<Button
						variant="secondary"
						onClick={ handleExport }
						disabled={ exporting || selectedIds.length === 0 }
						isBusy={ exporting }
					>
						{ __( 'Export Patch', 'gratis-ai-agent' ) }
						{ selectedIds.length > 0 &&
							` (${ selectedIds.length })` }
					</Button>
				</div>
			</div>

			{ /* Table */ }
			{ loading ? (
				<div className="gratis-changes-loading">
					<Spinner />
					<span>{ __( 'Loading changes…', 'gratis-ai-agent' ) }</span>
				</div>
			) : (
				<>
					<table className="wp-list-table widefat fixed striped gratis-changes-table">
						<thead>
							<tr>
								<th className="check-column">
									<CheckboxControl
										checked={ allSelected }
										onChange={ handleSelectAll }
										label=""
										aria-label={ __(
											'Select all',
											'gratis-ai-agent'
										) }
									/>
								</th>
								<th>{ __( 'Object', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Field', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Ability', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Before', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'After', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Date', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Status', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Actions', 'gratis-ai-agent' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ changes.length === 0 ? (
								<tr>
									<td
										colSpan={ 9 }
										className="gratis-changes-empty"
									>
										{ __(
											'No changes recorded yet. Changes made by the AI agent will appear here.',
											'gratis-ai-agent'
										) }
									</td>
								</tr>
							) : (
								changes.map( ( change ) => (
									<tr key={ change.id }>
										<td className="check-column">
											<CheckboxControl
												checked={ selectedIds.includes(
													change.id
												) }
												onChange={ ( checked ) =>
													handleSelectOne(
														change.id,
														checked
													)
												}
												label=""
												aria-label={ __(
													'Select',
													'gratis-ai-agent'
												) }
											/>
										</td>
										<td>
											<strong>
												{ change.object_title ||
													`${ change.object_type } #${ change.object_id }` }
											</strong>
											<br />
											<span className="gratis-changes-meta">
												{ change.object_type }
												{ change.object_id > 0 &&
													` #${ change.object_id }` }
											</span>
										</td>
										<td>
											<code>{ change.field_name }</code>
										</td>
										<td>
											<span className="gratis-changes-ability">
												{ change.ability_name || '—' }
											</span>
										</td>
										<td className="gratis-changes-value">
											{ truncate(
												change.before_value
											) || (
												<em>
													{ __(
														'(empty)',
														'gratis-ai-agent'
													) }
												</em>
											) }
										</td>
										<td className="gratis-changes-value">
											{ truncate(
												change.after_value
											) || (
												<em>
													{ __(
														'(empty)',
														'gratis-ai-agent'
													) }
												</em>
											) }
										</td>
										<td>
											{ formatDate( change.created_at ) }
										</td>
										<td>
											{ change.reverted ? (
												<Badge>
													{ __(
														'Reverted',
														'gratis-ai-agent'
													) }
												</Badge>
											) : (
												<Badge>
													{ __(
														'Active',
														'gratis-ai-agent'
													) }
												</Badge>
											) }
										</td>
										<td>
											<div className="gratis-changes-actions">
												<Button
													variant="link"
													onClick={ () =>
														handleViewDiff( change )
													}
												>
													{ __(
														'Diff',
														'gratis-ai-agent'
													) }
												</Button>
												{ ! change.reverted && (
													<Button
														variant="link"
														isDestructive
														onClick={ () =>
															handleRevert(
																change.id
															)
														}
														disabled={
															reverting ===
															change.id
														}
														isBusy={
															reverting ===
															change.id
														}
													>
														{ __(
															'Revert',
															'gratis-ai-agent'
														) }
													</Button>
												) }
											</div>
										</td>
									</tr>
								) )
							) }
						</tbody>
					</table>

					{ /* Pagination */ }
					{ totalPages > 1 && (
						<div className="gratis-changes-pagination">
							<Button
								variant="secondary"
								disabled={ page <= 1 }
								onClick={ () => setPage( ( p ) => p - 1 ) }
							>
								{ __( '← Previous', 'gratis-ai-agent' ) }
							</Button>
							<span>
								{ page } / { totalPages }
							</span>
							<Button
								variant="secondary"
								disabled={ page >= totalPages }
								onClick={ () => setPage( ( p ) => p + 1 ) }
							>
								{ __( 'Next →', 'gratis-ai-agent' ) }
							</Button>
						</div>
					) }
				</>
			) }

			{ /* Diff Modal */ }
			{ diffModal && (
				<Modal
					title={ sprintf(
						/* translators: %s: object title */
						__( 'Diff — %s', 'gratis-ai-agent' ),
						diffModal.change.object_title ||
							`${ diffModal.change.object_type } #${ diffModal.change.object_id }`
					) }
					onRequestClose={ () => setDiffModal( null ) }
					className="gratis-changes-diff-modal"
					size="large"
				>
					{ /* eslint-disable no-nested-ternary */ }
					{ diffLoading ? (
						<div className="gratis-changes-loading">
							<Spinner />
						</div>
					) : diffModal.error ? (
						<Notice status="error" isDismissible={ false }>
							{ diffModal.error }
						</Notice>
					) : (
						<>
							<p>
								<strong>
									{ __( 'Field:', 'gratis-ai-agent' ) }
								</strong>{ ' ' }
								<code>{ diffModal.change.field_name }</code>
								{ diffModal.change.ability_name && (
									<>
										{ ' ' }
										<strong>
											{ __(
												'Ability:',
												'gratis-ai-agent'
											) }
										</strong>{ ' ' }
										{ diffModal.change.ability_name }
									</>
								) }
							</p>
							<DiffViewer
								before={
									diffModal.diff?.before_value ??
									diffModal.change.before_value
								}
								after={
									diffModal.diff?.after_value ??
									diffModal.change.after_value
								}
							/>
							{ ! diffModal.change.reverted && (
								<div className="gratis-changes-diff-modal__actions">
									<Button
										variant="primary"
										isDestructive
										onClick={ () => {
											setDiffModal( null );
											handleRevert( diffModal.change.id );
										} }
									>
										{ __(
											'Revert This Change',
											'gratis-ai-agent'
										) }
									</Button>
								</div>
							) }
						</>
					) }
					{ /* eslint-enable no-nested-ternary */ }
				</Modal>
			) }
		</div>
	);
}
