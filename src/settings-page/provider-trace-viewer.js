/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	Notice,
	Spinner,
	ToggleControl,
	TextControl,
	SelectControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Provider Trace Viewer — admin UI for inspecting captured LLM provider HTTP traffic.
 *
 * @return {JSX.Element} Provider trace viewer component.
 */
export default function ProviderTraceViewer() {
	const [ traceSettings, setTraceSettings ] = useState( null );
	const [ traces, setTraces ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ selectedTrace, setSelectedTrace ] = useState( null );
	const [ detailLoading, setDetailLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ filters, setFilters ] = useState( {
		provider: '',
		errors_only: false,
		limit: 50,
		offset: 0,
	} );

	// Fetch trace settings.
	const fetchSettings = useCallback( async () => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/trace/settings',
			} );
			setTraceSettings( data );
		} catch {
			// Silently fail — settings will show as loading.
		}
	}, [] );

	// Fetch trace list.
	const fetchTraces = useCallback( async () => {
		setLoading( true );
		try {
			const params = new URLSearchParams();
			params.set( 'limit', String( filters.limit ) );
			params.set( 'offset', String( filters.offset ) );
			if ( filters.provider ) {
				params.set( 'provider', filters.provider );
			}
			if ( filters.errors_only ) {
				params.set( 'errors_only', '1' );
			}

			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/trace?${ params.toString() }`,
			} );
			setTraces( data.traces || [] );
			setTotal( data.total || 0 );
		} catch {
			setTraces( [] );
		}
		setLoading( false );
	}, [ filters ] );

	useEffect( () => {
		fetchSettings();
		fetchTraces();
	}, [ fetchSettings, fetchTraces ] );

	// Toggle tracing.
	const handleToggle = useCallback( async ( enabled ) => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/trace/settings',
				method: 'POST',
				data: { enabled },
			} );
			setTraceSettings( data );
			setNotice( {
				status: 'success',
				message: enabled
					? __( 'Provider tracing enabled.', 'gratis-ai-agent' )
					: __( 'Provider tracing disabled.', 'gratis-ai-agent' ),
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to update trace settings.',
					'gratis-ai-agent'
				),
			} );
		}
	}, [] );

	// Update max rows.
	const handleMaxRowsChange = useCallback( async ( maxRows ) => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/trace/settings',
				method: 'POST',
				data: { max_rows: parseInt( maxRows, 10 ) || 200 },
			} );
			setTraceSettings( data );
		} catch {
			// Silently fail.
		}
	}, [] );

	// Clear all traces.
	const handleClear = useCallback( async () => {
		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/trace',
				method: 'DELETE',
			} );
			setTraces( [] );
			setTotal( 0 );
			setNotice( {
				status: 'success',
				message: __( 'All trace records cleared.', 'gratis-ai-agent' ),
			} );
			fetchSettings();
		} catch {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to clear trace records.',
					'gratis-ai-agent'
				),
			} );
		}
	}, [ fetchSettings ] );

	// View trace detail.
	const handleViewTrace = useCallback( async ( id ) => {
		setDetailLoading( true );
		try {
			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/trace/${ id }`,
			} );
			setSelectedTrace( data );
		} catch {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to load trace details.',
					'gratis-ai-agent'
				),
			} );
		}
		setDetailLoading( false );
	}, [] );

	// Copy as curl.
	const handleCopyCurl = useCallback( async ( id ) => {
		try {
			const data = await apiFetch( {
				path: `/gratis-ai-agent/v1/trace/${ id }/curl`,
			} );
			await navigator.clipboard.writeText( data.curl );
			setNotice( {
				status: 'success',
				message: __(
					'Curl command copied to clipboard.',
					'gratis-ai-agent'
				),
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: __(
					'Failed to copy curl command.',
					'gratis-ai-agent'
				),
			} );
		}
	}, [] );

	// Format JSON for display.
	const formatJson = ( str ) => {
		if ( ! str ) {
			return '';
		}
		try {
			const parsed = JSON.parse( str );
			return JSON.stringify( parsed, null, 2 );
		} catch {
			return str;
		}
	};

	// Status code badge.
	const StatusBadge = ( { code } ) => {
		const isError = code < 200 || code >= 300;
		const className = isError
			? 'gratis-ai-agent-trace-status-error'
			: 'gratis-ai-agent-trace-status-ok';
		return <span className={ className }>{ code }</span>;
	};

	if ( ! traceSettings ) {
		return (
			<div className="gratis-ai-agent-settings-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="gratis-ai-agent-provider-trace">
			<h3 className="gratis-ai-agent-settings-section-title">
				{ __( 'Provider Trace', 'gratis-ai-agent' ) }
			</h3>
			<p className="description">
				{ __(
					'Capture and inspect HTTP traffic between this plugin and AI providers. Useful for debugging provider errors, malformed requests, and response issues.',
					'gratis-ai-agent'
				) }
			</p>

			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ /* Settings */ }
			<div className="gratis-ai-agent-trace-settings">
				<ToggleControl
					label={ __(
						'Enable provider trace logging',
						'gratis-ai-agent'
					) }
					checked={ traceSettings.enabled }
					onChange={ handleToggle }
					help={
						traceSettings.enabled
							? traceSettings.warning
							: __(
									'When enabled, outbound HTTP requests to AI providers will be logged for debugging.',
									'gratis-ai-agent'
							  )
					}
					__nextHasNoMarginBottom
				/>

				{ traceSettings.enabled && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Provider tracing is active. Logs may contain prompt content and should not be left enabled on shared or production environments.',
							'gratis-ai-agent'
						) }
					</Notice>
				) }

				<div className="gratis-ai-agent-trace-settings-row">
					<TextControl
						label={ __( 'Max stored rows', 'gratis-ai-agent' ) }
						type="number"
						min={ 10 }
						max={ 10000 }
						value={ traceSettings.max_rows }
						onChange={ handleMaxRowsChange }
						help={ __(
							'Oldest rows are automatically deleted when this limit is reached.',
							'gratis-ai-agent'
						) }
						__nextHasNoMarginBottom
					/>
					<p className="description">
						{ __( 'Currently stored:', 'gratis-ai-agent' ) }{ ' ' }
						<strong>{ traceSettings.count }</strong>
					</p>
				</div>
			</div>

			{ /* Filters */ }
			<div className="gratis-ai-agent-trace-filters">
				<SelectControl
					label={ __( 'Provider', 'gratis-ai-agent' ) }
					value={ filters.provider }
					options={ [
						{
							label: __( 'All providers', 'gratis-ai-agent' ),
							value: '',
						},
						{ label: 'Anthropic', value: 'anthropic' },
						{ label: 'OpenAI', value: 'openai' },
						{ label: 'Google', value: 'google' },
						{ label: 'Ollama', value: 'ollama' },
					] }
					onChange={ ( v ) =>
						setFilters( ( prev ) => ( {
							...prev,
							provider: v,
							offset: 0,
						} ) )
					}
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Errors only', 'gratis-ai-agent' ) }
					checked={ filters.errors_only }
					onChange={ ( v ) =>
						setFilters( ( prev ) => ( {
							...prev,
							errors_only: v,
							offset: 0,
						} ) )
					}
					__nextHasNoMarginBottom
				/>
				<Button variant="secondary" onClick={ fetchTraces }>
					{ __( 'Refresh', 'gratis-ai-agent' ) }
				</Button>
				{ total > 0 && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ handleClear }
					>
						{ __( 'Clear All', 'gratis-ai-agent' ) }
					</Button>
				) }
			</div>

			{ /* Trace List */ }
			{ loading && <Spinner /> }
			{ ! loading && traces.length === 0 && (
				<p className="gratis-ai-agent-trace-empty">
					{ traceSettings.enabled
						? __(
								'No trace records yet. Make an AI request to start capturing traffic.',
								'gratis-ai-agent'
						  )
						: __(
								'No trace records. Enable tracing above to start capturing provider traffic.',
								'gratis-ai-agent'
						  ) }
				</p>
			) }
			{ ! loading && traces.length > 0 && (
				<>
					<table className="widefat gratis-ai-agent-trace-table">
						<thead>
							<tr>
								<th>{ __( 'ID', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Time', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Provider', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Model', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Status', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Duration', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Error', 'gratis-ai-agent' ) }</th>
								<th>{ __( 'Actions', 'gratis-ai-agent' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ traces.map( ( trace ) => (
								<tr
									key={ trace.id }
									className={
										trace.error
											? 'gratis-ai-agent-trace-row-error'
											: ''
									}
								>
									<td>{ trace.id }</td>
									<td>{ trace.created_at }</td>
									<td>{ trace.provider_id }</td>
									<td>
										{ trace.model_id
											? trace.model_id
											: '—' }
									</td>
									<td>
										<StatusBadge
											code={ trace.status_code }
										/>
									</td>
									<td>{ trace.duration_ms }ms</td>
									<td
										className="gratis-ai-agent-trace-error-cell"
										title={ trace.error || '' }
									>
										{ trace.error
											? trace.error.substring( 0, 50 ) +
											  ( trace.error.length > 50
													? '...'
													: '' )
											: '—' }
									</td>
									<td>
										<Button
											variant="link"
											onClick={ () =>
												handleViewTrace( trace.id )
											}
										>
											{ __( 'View', 'gratis-ai-agent' ) }
										</Button>
										<Button
											variant="link"
											onClick={ () =>
												handleCopyCurl( trace.id )
											}
										>
											{ __(
												'Copy curl',
												'gratis-ai-agent'
											) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					{ /* Pagination */ }
					<div className="gratis-ai-agent-trace-pagination">
						<span>
							{ __( 'Showing', 'gratis-ai-agent' ) }{ ' ' }
							{ filters.offset + 1 }–
							{ Math.min(
								filters.offset + filters.limit,
								total
							) }{ ' ' }
							{ __( 'of', 'gratis-ai-agent' ) } { total }
						</span>
						{ filters.offset > 0 && (
							<Button
								variant="secondary"
								onClick={ () =>
									setFilters( ( prev ) => ( {
										...prev,
										offset: Math.max(
											0,
											prev.offset - prev.limit
										),
									} ) )
								}
							>
								{ __( 'Previous', 'gratis-ai-agent' ) }
							</Button>
						) }
						{ filters.offset + filters.limit < total && (
							<Button
								variant="secondary"
								onClick={ () =>
									setFilters( ( prev ) => ( {
										...prev,
										offset: prev.offset + prev.limit,
									} ) )
								}
							>
								{ __( 'Next', 'gratis-ai-agent' ) }
							</Button>
						) }
					</div>
				</>
			) }

			{ /* Detail Modal */ }
			{ selectedTrace && (
				<Modal
					title={ `${ __( 'Trace', 'gratis-ai-agent' ) } #${
						selectedTrace.id
					}` }
					onRequestClose={ () => setSelectedTrace( null ) }
					className="gratis-ai-agent-trace-modal"
					isFullScreen
				>
					{ detailLoading ? (
						<Spinner />
					) : (
						<div className="gratis-ai-agent-trace-detail">
							<div className="gratis-ai-agent-trace-detail-meta">
								<table className="widefat">
									<tbody>
										<tr>
											<th>
												{ __(
													'Provider',
													'gratis-ai-agent'
												) }
											</th>
											<td>
												{ selectedTrace.provider_id }
											</td>
											<th>
												{ __(
													'Model',
													'gratis-ai-agent'
												) }
											</th>
											<td>{ selectedTrace.model_id }</td>
										</tr>
										<tr>
											<th>
												{ __(
													'URL',
													'gratis-ai-agent'
												) }
											</th>
											<td colSpan="3">
												<code>
													{ selectedTrace.method }{ ' ' }
													{ selectedTrace.url }
												</code>
											</td>
										</tr>
										<tr>
											<th>
												{ __(
													'Status',
													'gratis-ai-agent'
												) }
											</th>
											<td>
												<StatusBadge
													code={
														selectedTrace.status_code
													}
												/>
											</td>
											<th>
												{ __(
													'Duration',
													'gratis-ai-agent'
												) }
											</th>
											<td>
												{ selectedTrace.duration_ms }
												ms
											</td>
										</tr>
										<tr>
											<th>
												{ __(
													'Time',
													'gratis-ai-agent'
												) }
											</th>
											<td>
												{ selectedTrace.created_at }
											</td>
											<th>
												{ __(
													'Error',
													'gratis-ai-agent'
												) }
											</th>
											<td>
												{ selectedTrace.error || '—' }
											</td>
										</tr>
									</tbody>
								</table>
							</div>

							<div className="gratis-ai-agent-trace-detail-panels">
								<div className="gratis-ai-agent-trace-detail-panel">
									<h4>
										{ __(
											'Request Headers',
											'gratis-ai-agent'
										) }
									</h4>
									<pre className="gratis-ai-agent-trace-json">
										{ formatJson(
											selectedTrace.request_headers
										) }
									</pre>
								</div>
								<div className="gratis-ai-agent-trace-detail-panel">
									<h4>
										{ __(
											'Response Headers',
											'gratis-ai-agent'
										) }
									</h4>
									<pre className="gratis-ai-agent-trace-json">
										{ formatJson(
											selectedTrace.response_headers
										) }
									</pre>
								</div>
							</div>

							<div className="gratis-ai-agent-trace-detail-panels">
								<div className="gratis-ai-agent-trace-detail-panel">
									<h4>
										{ __(
											'Request Body',
											'gratis-ai-agent'
										) }
									</h4>
									<pre className="gratis-ai-agent-trace-json">
										{ formatJson(
											selectedTrace.request_body
										) }
									</pre>
								</div>
								<div className="gratis-ai-agent-trace-detail-panel">
									<h4>
										{ __(
											'Response Body',
											'gratis-ai-agent'
										) }
									</h4>
									<pre className="gratis-ai-agent-trace-json">
										{ formatJson(
											selectedTrace.response_body
										) }
									</pre>
								</div>
							</div>

							<div className="gratis-ai-agent-trace-detail-actions">
								<Button
									variant="secondary"
									onClick={ () =>
										handleCopyCurl( selectedTrace.id )
									}
								>
									{ __( 'Copy as curl', 'gratis-ai-agent' ) }
								</Button>
							</div>
						</div>
					) }
				</Modal>
			) }
		</div>
	);
}
