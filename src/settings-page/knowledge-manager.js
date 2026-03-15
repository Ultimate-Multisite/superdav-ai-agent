/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	TextControl,
	TextareaControl,
	ToggleControl,
	Spinner,
	Notice,
	Modal,
	FormFileUpload,
	__experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const API_BASE = '/gratis-ai-agent/v1/knowledge';

export default function KnowledgeManager() {
	const [ collections, setCollections ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );
	const [ showCreate, setShowCreate ] = useState( false );
	const [ editingId, setEditingId ] = useState( null );
	const [ expandedId, setExpandedId ] = useState( null );
	const [ sources, setSources ] = useState( {} );
	const [ indexing, setIndexing ] = useState( {} );
	const [ uploading, setUploading ] = useState( {} );

	// Search preview state.
	const [ searchQuery, setSearchQuery ] = useState( '' );
	const [ searchResults, setSearchResults ] = useState( [] );
	const [ searching, setSearching ] = useState( false );

	// Form state.
	const [ form, setForm ] = useState( {
		name: '',
		slug: '',
		description: '',
		auto_index: false,
		source_config: { post_types: [ 'post', 'page' ] },
	} );

	const fetchCollections = useCallback( () => {
		setLoading( true );
		apiFetch( { path: `${ API_BASE }/collections` } )
			.then( setCollections )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'Failed to load collections.',
						'gratis-ai-agent'
					),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		fetchCollections();
	}, [ fetchCollections ] );

	const fetchSources = useCallback( ( collectionId ) => {
		apiFetch( {
			path: `${ API_BASE }/collections/${ collectionId }/sources`,
		} )
			.then( ( data ) => {
				setSources( ( prev ) => ( {
					...prev,
					[ collectionId ]: data,
				} ) );
			} )
			.catch( () => {} );
	}, [] );

	const handleCreateOrEdit = useCallback( async () => {
		try {
			if ( editingId ) {
				await apiFetch( {
					path: `${ API_BASE }/collections/${ editingId }`,
					method: 'PATCH',
					data: form,
				} );
				setNotice( {
					status: 'success',
					message: __( 'Collection updated.', 'gratis-ai-agent' ),
				} );
			} else {
				await apiFetch( {
					path: `${ API_BASE }/collections`,
					method: 'POST',
					data: form,
				} );
				setNotice( {
					status: 'success',
					message: __( 'Collection created.', 'gratis-ai-agent' ),
				} );
			}
			setShowCreate( false );
			setEditingId( null );
			setForm( {
				name: '',
				slug: '',
				description: '',
				auto_index: false,
				source_config: { post_types: [ 'post', 'page' ] },
			} );
			fetchCollections();
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err.message || __( 'Operation failed.', 'gratis-ai-agent' ),
			} );
		}
	}, [ form, editingId, fetchCollections ] );

	const handleDelete = useCallback(
		async ( id ) => {
			// eslint-disable-next-line no-alert
			const confirmed = window.confirm(
				__(
					'Delete this collection and all its indexed data?',
					'gratis-ai-agent'
				)
			);
			if ( ! confirmed ) {
				return;
			}
			try {
				await apiFetch( {
					path: `${ API_BASE }/collections/${ id }`,
					method: 'DELETE',
				} );
				setNotice( {
					status: 'success',
					message: __( 'Collection deleted.', 'gratis-ai-agent' ),
				} );
				fetchCollections();
			} catch {
				setNotice( {
					status: 'error',
					message: __(
						'Failed to delete collection.',
						'gratis-ai-agent'
					),
				} );
			}
		},
		[ fetchCollections ]
	);

	const handleIndex = useCallback(
		async ( id ) => {
			setIndexing( ( prev ) => ( { ...prev, [ id ]: true } ) );
			try {
				const result = await apiFetch( {
					path: `${ API_BASE }/collections/${ id }/index`,
					method: 'POST',
				} );
				setNotice( {
					status: 'success',
					message: `${ __( 'Indexed:', 'gratis-ai-agent' ) } ${
						result.indexed
					} | ${ __( 'Skipped:', 'gratis-ai-agent' ) } ${
						result.skipped
					} | ${ __( 'Errors:', 'gratis-ai-agent' ) } ${
						result.errors
					}`,
				} );
				fetchCollections();
				if ( expandedId === id ) {
					fetchSources( id );
				}
			} catch {
				setNotice( {
					status: 'error',
					message: __( 'Indexing failed.', 'gratis-ai-agent' ),
				} );
			}
			setIndexing( ( prev ) => ( { ...prev, [ id ]: false } ) );
		},
		[ fetchCollections, fetchSources, expandedId ]
	);

	const handleUpload = useCallback(
		async ( id, event ) => {
			const file = event.target?.files?.[ 0 ];
			if ( ! file ) {
				return;
			}

			setUploading( ( prev ) => ( { ...prev, [ id ]: true } ) );

			const formData = new FormData();
			formData.append( 'file', file );
			formData.append( 'collection_id', id );

			try {
				await apiFetch( {
					path: `${ API_BASE }/upload`,
					method: 'POST',
					body: formData,
					// Don't set Content-Type — let browser set it with boundary.
					headers: {},
				} );
				setNotice( {
					status: 'success',
					message: __(
						'Document uploaded and indexed.',
						'gratis-ai-agent'
					),
				} );
				fetchCollections();
				if ( expandedId === id ) {
					fetchSources( id );
				}
			} catch {
				setNotice( {
					status: 'error',
					message: __( 'Upload failed.', 'gratis-ai-agent' ),
				} );
			}
			setUploading( ( prev ) => ( { ...prev, [ id ]: false } ) );
		},
		[ fetchCollections, fetchSources, expandedId ]
	);

	const handleDeleteSource = useCallback(
		async ( sourceId, collectionId ) => {
			try {
				await apiFetch( {
					path: `${ API_BASE }/sources/${ sourceId }`,
					method: 'DELETE',
				} );
				fetchSources( collectionId );
				fetchCollections();
			} catch {
				setNotice( {
					status: 'error',
					message: __(
						'Failed to delete source.',
						'gratis-ai-agent'
					),
				} );
			}
		},
		[ fetchSources, fetchCollections ]
	);

	const handleSearch = useCallback( async () => {
		if ( ! searchQuery.trim() ) {
			return;
		}
		setSearching( true );
		try {
			const results = await apiFetch( {
				path: `${ API_BASE }/search?q=${ encodeURIComponent(
					searchQuery
				) }`,
			} );
			setSearchResults( results );
		} catch {
			setSearchResults( [] );
		}
		setSearching( false );
	}, [ searchQuery ] );

	const openEdit = useCallback( ( collection ) => {
		setForm( {
			name: collection.name,
			slug: collection.slug,
			description: collection.description || '',
			auto_index: collection.auto_index,
			source_config: collection.source_config || {
				post_types: [ 'post', 'page' ],
			},
		} );
		setEditingId( collection.id );
		setShowCreate( true );
	}, [] );

	const toggleExpanded = useCallback(
		( id ) => {
			if ( expandedId === id ) {
				setExpandedId( null );
			} else {
				setExpandedId( id );
				if ( ! sources[ id ] ) {
					fetchSources( id );
				}
			}
		},
		[ expandedId, sources, fetchSources ]
	);

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div className="gratis-ai-agent-knowledge-manager">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: '16px',
				} }
			>
				<h3 style={ { margin: 0 } }>
					{ __( 'Collections', 'gratis-ai-agent' ) }
				</h3>
				<Button
					variant="primary"
					onClick={ () => {
						setForm( {
							name: '',
							slug: '',
							description: '',
							auto_index: false,
							source_config: { post_types: [ 'post', 'page' ] },
						} );
						setEditingId( null );
						setShowCreate( true );
					} }
				>
					{ __( 'Create Collection', 'gratis-ai-agent' ) }
				</Button>
			</div>

			{ collections.length === 0 && (
				<p className="description">
					{ __(
						'No collections yet. Create one to start indexing content.',
						'gratis-ai-agent'
					) }
				</p>
			) }

			{ collections.map( ( col ) => (
				<Card key={ col.id } style={ { marginBottom: '12px' } }>
					<CardHeader>
						<div
							style={ {
								display: 'flex',
								justifyContent: 'space-between',
								alignItems: 'center',
								width: '100%',
							} }
						>
							<div>
								<strong>{ col.name }</strong>
								<Text
									variant="muted"
									style={ { marginLeft: '8px' } }
								>
									{ col.slug }
								</Text>
							</div>
							<div
								style={ {
									display: 'flex',
									gap: '8px',
									alignItems: 'center',
								} }
							>
								<Text variant="muted">
									{ col.chunk_count }{ ' ' }
									{ __( 'chunks', 'gratis-ai-agent' ) }
								</Text>
								{ col.auto_index && (
									<span className="gratis-ai-agent-badge">
										{ __( 'Auto', 'gratis-ai-agent' ) }
									</span>
								) }
							</div>
						</div>
					</CardHeader>
					<CardBody>
						{ col.description && (
							<p className="description">{ col.description }</p>
						) }
						{ col.last_indexed_at && (
							<p className="description">
								{ __( 'Last indexed:', 'gratis-ai-agent' ) }{ ' ' }
								{ col.last_indexed_at }
							</p>
						) }
						<div
							style={ {
								display: 'flex',
								gap: '8px',
								marginTop: '8px',
								flexWrap: 'wrap',
							} }
						>
							<Button
								variant="secondary"
								onClick={ () => handleIndex( col.id ) }
								isBusy={ indexing[ col.id ] }
								disabled={ indexing[ col.id ] }
							>
								{ indexing[ col.id ]
									? __( 'Indexing…', 'gratis-ai-agent' )
									: __( 'Index Now', 'gratis-ai-agent' ) }
							</Button>
							<FormFileUpload
								accept=".pdf,.docx,.txt,.md,.html"
								onChange={ ( e ) => handleUpload( col.id, e ) }
								render={ ( { openFileDialog } ) => (
									<Button
										variant="secondary"
										onClick={ openFileDialog }
										isBusy={ uploading[ col.id ] }
										disabled={ uploading[ col.id ] }
									>
										{ __(
											'Upload Document',
											'gratis-ai-agent'
										) }
									</Button>
								) }
							/>
							<Button
								variant="tertiary"
								onClick={ () => toggleExpanded( col.id ) }
							>
								{ expandedId === col.id
									? __( 'Hide Sources', 'gratis-ai-agent' )
									: __( 'Show Sources', 'gratis-ai-agent' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () => openEdit( col ) }
							>
								{ __( 'Edit', 'gratis-ai-agent' ) }
							</Button>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () => handleDelete( col.id ) }
							>
								{ __( 'Delete', 'gratis-ai-agent' ) }
							</Button>
						</div>

						{ expandedId === col.id && (
							<div
								style={ {
									marginTop: '12px',
									borderTop: '1px solid #ddd',
									paddingTop: '12px',
								} }
							>
								<h4 style={ { margin: '0 0 8px 0' } }>
									{ __( 'Sources', 'gratis-ai-agent' ) }
								</h4>
								{ ( () => {
									if ( ! sources[ col.id ] ) {
										return <Spinner />;
									}
									if ( sources[ col.id ].length === 0 ) {
										return (
											<p className="description">
												{ __(
													'No sources indexed yet.',
													'gratis-ai-agent'
												) }
											</p>
										);
									}
									return (
										<table
											className="widefat striped"
											style={ { marginTop: '4px' } }
										>
											<thead>
												<tr>
													<th>
														{ __(
															'Title',
															'gratis-ai-agent'
														) }
													</th>
													<th>
														{ __(
															'Type',
															'gratis-ai-agent'
														) }
													</th>
													<th>
														{ __(
															'Status',
															'gratis-ai-agent'
														) }
													</th>
													<th>
														{ __(
															'Chunks',
															'gratis-ai-agent'
														) }
													</th>
													<th></th>
												</tr>
											</thead>
											<tbody>
												{ sources[ col.id ].map(
													( src ) => (
														<tr key={ src.id }>
															<td>
																{ src.title ||
																	__(
																		'(untitled)',
																		'gratis-ai-agent'
																	) }
															</td>
															<td>
																{
																	src.source_type
																}
															</td>
															<td>
																<span
																	className={ `gratis-ai-agent-status-badge is-${ src.status }` }
																>
																	{
																		src.status
																	}
																</span>
																{ src.error_message && (
																	<span
																		title={
																			src.error_message
																		}
																		style={ {
																			cursor: 'help',
																			marginLeft:
																				'4px',
																		} }
																	>
																		&#9888;
																	</span>
																) }
															</td>
															<td>
																{
																	src.chunk_count
																}
															</td>
															<td>
																<Button
																	variant="tertiary"
																	isDestructive
																	isSmall
																	onClick={ () =>
																		handleDeleteSource(
																			src.id,
																			col.id
																		)
																	}
																>
																	{ __(
																		'Remove',
																		'gratis-ai-agent'
																	) }
																</Button>
															</td>
														</tr>
													)
												) }
											</tbody>
										</table>
									);
								} )() }
							</div>
						) }
					</CardBody>
				</Card>
			) ) }

			{ /* Search Preview */ }
			<div style={ { marginTop: '24px' } }>
				<h3>{ __( 'Search Preview', 'gratis-ai-agent' ) }</h3>
				<div
					style={ {
						display: 'flex',
						gap: '8px',
						marginBottom: '12px',
					} }
				>
					<TextControl
						value={ searchQuery }
						onChange={ setSearchQuery }
						placeholder={ __(
							'Search the knowledge base…',
							'gratis-ai-agent'
						) }
						style={ { flex: 1 } }
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' ) {
								handleSearch();
							}
						} }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={ handleSearch }
						isBusy={ searching }
						disabled={ searching || ! searchQuery.trim() }
					>
						{ __( 'Search', 'gratis-ai-agent' ) }
					</Button>
				</div>
				{ searchResults.length > 0 && (
					<div className="gratis-ai-agent-search-results">
						{ searchResults.map( ( result, i ) => (
							<Card key={ i } style={ { marginBottom: '8px' } }>
								<CardBody>
									<div
										style={ {
											display: 'flex',
											justifyContent: 'space-between',
											marginBottom: '4px',
										} }
									>
										<Text variant="muted">
											<strong>
												{ result.source_title }
											</strong>
											{ result.collection_name &&
												` — ${ result.collection_name }` }
										</Text>
										{ result.score && (
											<Text variant="muted">
												{ __(
													'Score:',
													'gratis-ai-agent'
												) }{ ' ' }
												{ result.score.toFixed( 2 ) }
											</Text>
										) }
									</div>
									<p
										style={ {
											margin: 0,
											fontSize: '13px',
											whiteSpace: 'pre-wrap',
										} }
									>
										{ result.chunk_text.length > 300
											? result.chunk_text.substring(
													0,
													300
											  ) + '...'
											: result.chunk_text }
									</p>
								</CardBody>
							</Card>
						) ) }
					</div>
				) }
			</div>

			{ /* Create/Edit Modal */ }
			{ showCreate && (
				<Modal
					title={
						editingId
							? __( 'Edit Collection', 'gratis-ai-agent' )
							: __( 'Create Collection', 'gratis-ai-agent' )
					}
					onRequestClose={ () => {
						setShowCreate( false );
						setEditingId( null );
					} }
				>
					<TextControl
						label={ __( 'Name', 'gratis-ai-agent' ) }
						value={ form.name }
						onChange={ ( v ) => {
							setForm( ( prev ) => ( {
								...prev,
								name: v,
								// Auto-generate slug from name if creating.
								...( ! editingId
									? {
											slug: v
												.toLowerCase()
												.replace( /[^a-z0-9]+/g, '-' )
												.replace( /^-|-$/g, '' ),
									  }
									: {} ),
							} ) );
						} }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Slug', 'gratis-ai-agent' ) }
						value={ form.slug }
						onChange={ ( v ) =>
							setForm( ( prev ) => ( { ...prev, slug: v } ) )
						}
						help={ __(
							'Unique identifier for this collection.',
							'gratis-ai-agent'
						) }
						disabled={ !! editingId }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Description', 'gratis-ai-agent' ) }
						value={ form.description }
						onChange={ ( v ) =>
							setForm( ( prev ) => ( {
								...prev,
								description: v,
							} ) )
						}
						rows={ 2 }
					/>
					<TextControl
						label={ __(
							'Post Types (comma-separated)',
							'gratis-ai-agent'
						) }
						value={ ( form.source_config?.post_types || [] ).join(
							', '
						) }
						onChange={ ( v ) =>
							setForm( ( prev ) => ( {
								...prev,
								source_config: {
									...prev.source_config,
									post_types: v
										.split( ',' )
										.map( ( s ) => s.trim() )
										.filter( Boolean ),
								},
							} ) )
						}
						help={ __(
							'Post types to include when indexing (e.g., post, page, product).',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Auto-index', 'gratis-ai-agent' ) }
						checked={ form.auto_index }
						onChange={ ( v ) =>
							setForm( ( prev ) => ( {
								...prev,
								auto_index: v,
							} ) )
						}
						help={ __(
							'Automatically index new and updated posts matching this collection.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<div
						style={ {
							marginTop: '16px',
							display: 'flex',
							gap: '8px',
							justifyContent: 'flex-end',
						} }
					>
						<Button
							variant="tertiary"
							onClick={ () => {
								setShowCreate( false );
								setEditingId( null );
							} }
						>
							{ __( 'Cancel', 'gratis-ai-agent' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleCreateOrEdit }
							disabled={ ! form.name || ! form.slug }
						>
							{ editingId
								? __( 'Save', 'gratis-ai-agent' )
								: __( 'Create', 'gratis-ai-agent' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
