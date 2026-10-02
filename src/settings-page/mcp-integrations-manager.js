/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const EMPTY_FORM = {
	name: '',
	endpoint: '',
	auth_type: 'none',
	secret: '',
	header_name: '',
	reuse_secret: false,
};

const BASIC_AUTH_OPTIONS = [
	{ label: __( 'No authentication', 'superdav-ai-agent' ), value: 'none' },
	{ label: __( 'Access token', 'superdav-ai-agent' ), value: 'bearer' },
];

const ADVANCED_AUTH_OPTIONS = [
	...BASIC_AUTH_OPTIONS,
	{ label: __( 'API key', 'superdav-ai-agent' ), value: 'api_key' },
	{
		label: __( 'Custom header', 'superdav-ai-agent' ),
		value: 'custom_header',
	},
];

/**
 * Return a friendly name for an endpoint without rejecting partial form input.
 *
 * @param {string} endpoint MCP server endpoint.
 * @return {string} Endpoint hostname, if available.
 */
function hostnameFromEndpoint( endpoint ) {
	try {
		return new URL( endpoint ).hostname;
	} catch {
		return '';
	}
}

/**
 * Translate persisted connection metadata into plain admin-facing status text.
 *
 * @param {Object} connection Safe connection record.
 * @return {string} Plain-language status.
 */
function connectionStatus( connection ) {
	if ( connection.status === 'ready' && connection.enabled ) {
		return __( 'Connected', 'superdav-ai-agent' );
	}

	if ( connection.status === 'stale' ) {
		return String( connection.last_error_code || '' ).includes( 'auth' )
			? __( 'Authentication required', 'superdav-ai-agent' )
			: __( 'Could not connect', 'superdav-ai-agent' );
	}

	return __( 'Disabled', 'superdav-ai-agent' );
}

/**
 * Keep remote failure details out of the admin UI while giving an actionable next step.
 *
 * @param {string} action Lifecycle action that failed.
 * @return {string} Safe user-facing failure message.
 */
function connectionErrorMessage( action ) {
	if ( action === 'load' ) {
		return __(
			'Could not load MCP servers. Reload this page and try again.',
			'superdav-ai-agent'
		);
	}

	if ( action === 'import' ) {
		return __(
			'Could not import that file. Check its MCP server format and try again.',
			'superdav-ai-agent'
		);
	}

	return __(
		'Could not connect. Check the server URL and try again.',
		'superdav-ai-agent'
	);
}

/** Manage outbound MCP connections without exposing protocol configuration. */
export default function McpIntegrationsManager() {
	const [ connections, setConnections ] = useState( [] );
	const [ form, setForm ] = useState( EMPTY_FORM );
	const [ editId, setEditId ] = useState( '' );
	const [ showForm, setShowForm ] = useState( false );
	const [ showAdvanced, setShowAdvanced ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const fileInputRef = useRef( null );

	const clearSecret = useCallback( () => {
		setForm( ( current ) => ( { ...current, secret: '' } ) );
	}, [] );

	const loadConnections = useCallback( async () => {
		try {
			const result = await apiFetch( {
				path: '/sd-ai-agent/v1/mcp-connections',
			} );
			const rows = Array.isArray( result?.connections )
				? result.connections
				: [];
			setConnections( rows );
			return rows;
		} catch {
			setNotice( {
				status: 'error',
				message: connectionErrorMessage( 'load' ),
			} );
			return [];
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadConnections();
	}, [ loadConnections ] );

	useEffect( () => clearSecret, [ clearSecret ] );

	const resetForm = useCallback( () => {
		setForm( EMPTY_FORM );
		setEditId( '' );
		setShowAdvanced( false );
		setShowForm( false );
	}, [] );

	const updateForm = useCallback( ( field, value ) => {
		setForm( ( current ) => ( { ...current, [ field ]: value } ) );
	}, [] );

	const connect = useCallback( async () => {
		const endpoint = form.endpoint.trim();
		const name = form.name.trim() || hostnameFromEndpoint( endpoint );
		if ( ! endpoint || ! name ) {
			setNotice( {
				status: 'error',
				message: __(
					'Enter a valid server URL and optional name.',
					'superdav-ai-agent'
				),
			} );
			return;
		}

		setBusy( 'connect' );
		setNotice( null );
		try {
			const data = {
				name,
				endpoint,
				auth_type: form.auth_type,
				secret: form.secret,
				header_name: form.header_name,
				reuse_secret: form.reuse_secret,
			};
			if ( ! editId ) {
				data.enabled = false;
			} else {
				data.id = editId;
			}

			const saved = await apiFetch( {
				path: '/sd-ai-agent/v1/mcp-connections',
				method: 'POST',
				data,
			} );
			let connection = saved?.connection;
			if ( ! connection?.id ) {
				const reloaded = await loadConnections();
				connection = reloaded.find(
					( candidate ) =>
						candidate.endpoint === endpoint &&
						candidate.name === name
				);
			}
			if ( ! connection?.id ) {
				throw new Error( 'connection_not_reconciled' );
			}

			await apiFetch( {
				path: `/sd-ai-agent/v1/mcp-connections/${ connection.id }/test`,
				method: 'POST',
			} );
			await apiFetch( {
				path: `/sd-ai-agent/v1/mcp-connections/${ connection.id }/enable`,
				method: 'POST',
			} );
			await loadConnections();
			resetForm();
			setNotice( {
				status: 'success',
				message: __( 'MCP server connected.', 'superdav-ai-agent' ),
			} );
		} catch {
			await loadConnections();
			setNotice( {
				status: 'error',
				message: connectionErrorMessage( 'connect' ),
			} );
		} finally {
			clearSecret();
			setBusy( '' );
		}
	}, [ clearSecret, editId, form, loadConnections, resetForm ] );

	const runAction = useCallback(
		async ( connection, action ) => {
			setBusy( `${ action }-${ connection.id }` );
			setNotice( null );
			try {
				await apiFetch( {
					path: `/sd-ai-agent/v1/mcp-connections/${ connection.id }/${ action }`,
					method: 'POST',
				} );
				await loadConnections();
				setNotice( {
					status: 'success',
					message:
						action === 'refresh'
							? __( 'Tools refreshed.', 'superdav-ai-agent' )
							: __( 'Connection updated.', 'superdav-ai-agent' ),
				} );
			} catch {
				await loadConnections();
				setNotice( {
					status: 'error',
					message: connectionErrorMessage( 'connect' ),
				} );
			} finally {
				setBusy( '' );
			}
		},
		[ loadConnections ]
	);

	const editConnection = useCallback( ( connection ) => {
		setForm( {
			name: connection.name || '',
			endpoint: connection.endpoint || '',
			auth_type: connection.auth_type || 'none',
			secret: '',
			header_name: '',
			reuse_secret: false,
		} );
		setEditId( connection.id );
		setShowAdvanced(
			[ 'api_key', 'custom_header' ].includes( connection.auth_type )
		);
		setShowForm( true );
		setNotice( null );
	}, [] );

	const deleteConnection = useCallback(
		async ( connection ) => {
			if (
				// eslint-disable-next-line no-alert
				! window.confirm(
					__(
						'Remove this MCP server? Its saved credential will also be removed.',
						'superdav-ai-agent'
					)
				)
			) {
				return;
			}

			setBusy( `delete-${ connection.id }` );
			try {
				await apiFetch( {
					path: `/sd-ai-agent/v1/mcp-connections/${ connection.id }`,
					method: 'DELETE',
				} );
				await loadConnections();
			} catch {
				setNotice( {
					status: 'error',
					message: __(
						'Could not remove this MCP server. Try again.',
						'superdav-ai-agent'
					),
				} );
			} finally {
				setBusy( '' );
			}
		},
		[ loadConnections ]
	);

	const importConnections = useCallback(
		async ( event ) => {
			const file = event.target.files?.[ 0 ];
			if ( ! file ) {
				return;
			}

			setBusy( 'import' );
			setNotice( null );
			try {
				const data = JSON.parse( await file.text() );
				await apiFetch( {
					path: '/sd-ai-agent/v1/mcp-connections/import',
					method: 'POST',
					data,
				} );
				await loadConnections();
				setNotice( {
					status: 'success',
					message: __(
						'Imported MCP servers are disabled until you review and connect them.',
						'superdav-ai-agent'
					),
				} );
			} catch {
				setNotice( {
					status: 'error',
					message: connectionErrorMessage( 'import' ),
				} );
			} finally {
				if ( fileInputRef.current ) {
					fileInputRef.current.value = '';
				}
				setBusy( '' );
			}
		},
		[ loadConnections ]
	);

	const exportConnections = useCallback( async () => {
		setBusy( 'export' );
		try {
			const data = await apiFetch( {
				path: '/sd-ai-agent/v1/mcp-connections/export',
			} );
			const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], {
				type: 'application/json',
			} );
			const href = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = href;
			link.download = 'mcp-servers.json';
			link.click();
			URL.revokeObjectURL( href );
		} catch {
			setNotice( {
				status: 'error',
				message: __(
					'Could not export MCP servers. Try again.',
					'superdav-ai-agent'
				),
			} );
		} finally {
			setBusy( '' );
		}
	}, [] );

	if ( loading ) {
		return <Spinner />;
	}

	const isCredentialed = form.auth_type !== 'none';
	const hasCredentialReuseChoice =
		!! editId &&
		connections.some(
			( connection ) => connection.id === editId && connection.configured
		);

	return (
		<div className="sdaa-mcp-integrations">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<div className="sdaa-mcp-integrations__header">
				<div>
					<h3>{ __( 'MCP servers', 'superdav-ai-agent' ) }</h3>
					<p className="description">
						{ __(
							'Connect remote tools using a server URL. Connecting discovers tools but does not change their confirmation policy.',
							'superdav-ai-agent'
						) }
					</p>
				</div>
				{ ! showForm && (
					<Button
						variant="primary"
						onClick={ () => {
							setForm( EMPTY_FORM );
							setEditId( '' );
							setShowForm( true );
						} }
					>
						{ __( 'Add server', 'superdav-ai-agent' ) }
					</Button>
				) }
			</div>

			{ showForm && (
				<div className="sdaa-mcp-integrations__form">
					<h4>
						{ editId
							? __( 'Edit MCP server', 'superdav-ai-agent' )
							: __( 'Add MCP server', 'superdav-ai-agent' ) }
					</h4>
					<TextControl
						label={ __( 'Server URL', 'superdav-ai-agent' ) }
						type="url"
						value={ form.endpoint }
						onChange={ ( endpoint ) =>
							updateForm( 'endpoint', endpoint )
						}
						placeholder="https://example.com/mcp"
						help={ __(
							'Supported modern HTTP transport is detected automatically.',
							'superdav-ai-agent'
						) }
					/>
					<TextControl
						label={ __( 'Name (optional)', 'superdav-ai-agent' ) }
						value={ form.name }
						onChange={ ( name ) => updateForm( 'name', name ) }
						placeholder={ hostnameFromEndpoint( form.endpoint ) }
					/>
					<SelectControl
						label={ __( 'Authentication', 'superdav-ai-agent' ) }
						value={ form.auth_type }
						options={
							showAdvanced
								? ADVANCED_AUTH_OPTIONS
								: BASIC_AUTH_OPTIONS
						}
						onChange={ ( authType ) =>
							updateForm( 'auth_type', authType )
						}
					/>
					{ isCredentialed && (
						<TextControl
							label={ __( 'Access token', 'superdav-ai-agent' ) }
							type="password"
							value={ form.secret }
							onChange={ ( secret ) =>
								updateForm( 'secret', secret )
							}
							help={ __(
								'Leave this blank only when you are keeping the existing credential.',
								'superdav-ai-agent'
							) }
						/>
					) }
					{ form.auth_type === 'custom_header' && (
						<TextControl
							label={ __( 'Header name', 'superdav-ai-agent' ) }
							value={ form.header_name }
							onChange={ ( headerName ) =>
								updateForm( 'header_name', headerName )
							}
							placeholder="X-API-Key"
						/>
					) }
					{ hasCredentialReuseChoice && isCredentialed && (
						<ToggleControl
							label={ __(
								'Reuse the saved credential after changing this server',
								'superdav-ai-agent'
							) }
							checked={ form.reuse_secret }
							onChange={ ( reuseSecret ) =>
								updateForm( 'reuse_secret', reuseSecret )
							}
						/>
					) }
					<Button
						variant="tertiary"
						onClick={ () =>
							setShowAdvanced( ( value ) => ! value )
						}
					>
						{ showAdvanced
							? __( 'Hide advanced options', 'superdav-ai-agent' )
							: __( 'Advanced options', 'superdav-ai-agent' ) }
					</Button>
					{ showAdvanced && (
						<p className="description">
							{ __(
								'Use API key or custom header only when your server documentation requires it. Credentials are not exported.',
								'superdav-ai-agent'
							) }
						</p>
					) }
					<div className="sdaa-mcp-integrations__actions">
						<Button
							variant="primary"
							onClick={ connect }
							disabled={ busy !== '' }
						>
							{ busy === 'connect' && <Spinner /> }
							{ busy !== 'connect' &&
								( editId
									? __(
											'Save and connect',
											'superdav-ai-agent'
									  )
									: __( 'Connect', 'superdav-ai-agent' ) ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ resetForm }
							disabled={ busy !== '' }
						>
							{ __( 'Cancel', 'superdav-ai-agent' ) }
						</Button>
					</div>
				</div>
			) }

			{ connections.length === 0 ? (
				<p className="description sdaa-mcp-integrations__empty">
					{ __(
						'No MCP servers connected yet.',
						'superdav-ai-agent'
					) }
				</p>
			) : (
				<div className="sdaa-mcp-integrations__list">
					{ connections.map( ( connection ) => {
						const toolCount = Array.isArray( connection.tools )
							? connection.tools.length
							: 0;
						const connectionBusy = busy.endsWith(
							`-${ connection.id }`
						);
						return (
							<div
								className="sdaa-mcp-integrations__connection"
								key={ connection.id }
							>
								<div className="sdaa-mcp-integrations__connection-heading">
									<div>
										<strong>{ connection.name }</strong>
										<p>{ connection.endpoint }</p>
									</div>
									<span className="sdaa-mcp-integrations__status">
										{ connectionStatus( connection ) }
									</span>
								</div>
								<p className="description">
									{ `${ toolCount } ${
										toolCount === 1
											? __(
													'tool available',
													'superdav-ai-agent'
											  )
											: __(
													'tools available',
													'superdav-ai-agent'
											  )
									}` }
								</p>
								<ToggleControl
									label={ __(
										'Enabled',
										'superdav-ai-agent'
									) }
									checked={ !! connection.enabled }
									onChange={ () =>
										runAction(
											connection,
											connection.enabled
												? 'disable'
												: 'enable'
										)
									}
									disabled={ busy !== '' }
								/>
								<div className="sdaa-mcp-integrations__actions">
									<Button
										variant="secondary"
										onClick={ () =>
											runAction( connection, 'refresh' )
										}
										disabled={ busy !== '' }
									>
										{ connectionBusy ? (
											<Spinner />
										) : (
											__(
												'Reconnect',
												'superdav-ai-agent'
											)
										) }
									</Button>
									<Button
										variant="tertiary"
										onClick={ () =>
											editConnection( connection )
										}
										disabled={ busy !== '' }
									>
										{ __( 'Edit', 'superdav-ai-agent' ) }
									</Button>
									<Button
										variant="tertiary"
										onClick={ () =>
											runAction( connection, 'refresh' )
										}
										disabled={ busy !== '' }
									>
										{ __(
											'Refresh tools',
											'superdav-ai-agent'
										) }
									</Button>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () =>
											deleteConnection( connection )
										}
										disabled={ busy !== '' }
									>
										{ __( 'Remove', 'superdav-ai-agent' ) }
									</Button>
								</div>
								{ toolCount > 0 && (
									<details>
										<summary>
											{ __(
												'View discovered tools',
												'superdav-ai-agent'
											) }
										</summary>
										<ul className="sdaa-mcp-integrations__tools">
											{ connection.tools.map(
												( tool ) => (
													<li key={ tool.name }>
														<strong>
															{ tool.name }
														</strong>
														{ tool.description &&
															` — ${ tool.description }` }
													</li>
												)
											) }
										</ul>
									</details>
								) }
								<details>
									<summary>
										{ __( 'Details', 'superdav-ai-agent' ) }
									</summary>
									<dl className="sdaa-mcp-integrations__details">
										<dt>
											{ __(
												'Protocol',
												'superdav-ai-agent'
											) }
										</dt>
										<dd>
											{ connection.protocol_version ||
												'—' }
										</dd>
										<dt>
											{ __(
												'Last discovered',
												'superdav-ai-agent'
											) }
										</dt>
										<dd>
											{ connection.last_discovered ||
												'—' }
										</dd>
										<dt>
											{ __(
												'Status code',
												'superdav-ai-agent'
											) }
										</dt>
										<dd>
											{ connection.last_error_code ||
												'—' }
										</dd>
									</dl>
								</details>
							</div>
						);
					} ) }
				</div>
			) }

			<div className="sdaa-mcp-integrations__secondary-actions">
				<input
					accept="application/json"
					type="file"
					ref={ fileInputRef }
					onChange={ importConnections }
					className="sdaa-mcp-integrations__file-input"
				/>
				<Button
					variant="tertiary"
					onClick={ () => fileInputRef.current?.click() }
					disabled={ busy !== '' }
				>
					{ __( 'Import servers', 'superdav-ai-agent' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ exportConnections }
					disabled={ busy !== '' }
				>
					{ __( 'Export servers', 'superdav-ai-agent' ) }
				</Button>
			</div>
		</div>
	);
}
