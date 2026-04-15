/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Feedback consent modal.
 *
 * Shown when the user invokes /report-issue, clicks a thumbs-down button, or
 * when the auto-prompt banner fires on spin_detected / timeout / max_iterations.
 *
 * Features (t182):
 *   1. Summary stats: message count, tool call count, environment keys.
 *   2. Collapsible "View full payload" section with the sanitized JSON preview.
 *   3. Privacy notice.
 *   4. Optional user description textarea (pre-filled from the triggering context).
 *   5. "Strip tool results" checkbox for aggressive privacy.
 *   6. "Send Report" and "Dismiss" buttons.
 *
 * @param {Object}   props                      - Component props.
 * @param {string}   props.reportType           - Type of report sent in the
 *                                              payload: 'user_reported',
 *                                              'thumbs_down', 'self_reported',
 *                                              'spin_detected', 'timeout', etc.
 * @param {string}   [props.userDescription=''] - Pre-filled description text.
 *                                              Editable by the user before sending.
 * @param {number}   [props.sessionId]          - Current session ID used to build
 *                                              the payload preview and the report.
 * @param {Function} props.onClose              - Called when the modal should close.
 * @return {JSX.Element} The feedback consent modal element.
 */
export default function FeedbackConsentModal( {
	reportType,
	userDescription = '',
	sessionId,
	onClose,
} ) {
	const [ description, setDescription ] = useState( userDescription );
	const [ stripToolResults, setStripToolResults ] = useState( false );
	const [ isSending, setIsSending ] = useState( false );
	const [ isSent, setIsSent ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ payloadExpanded, setPayloadExpanded ] = useState( false );

	// Preview data fetched from the server.
	const [ preview, setPreview ] = useState( null );
	const [ previewLoading, setPreviewLoading ] = useState( false );

	const dialogRef = useRef( null );

	// Close on Escape key.
	useEffect( () => {
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ onClose ] );

	// Close on click outside the dialog box.
	useEffect( () => {
		const handler = ( e ) => {
			if (
				dialogRef.current &&
				! dialogRef.current.contains( e.target )
			) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ onClose ] );

	// Fetch payload preview from the server when sessionId is present.
	// Re-fetch whenever stripToolResults changes so the preview stays accurate.
	useEffect( () => {
		if ( ! sessionId ) {
			return;
		}

		let cancelled = false;
		setPreviewLoading( true );
		setPreview( null );

		apiFetch( {
			path: `/gratis-ai-agent/v1/feedback/preview?session_id=${ sessionId }&strip_tool_results=${
				stripToolResults ? '1' : '0'
			}`,
		} )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setPreview( data );
				}
			} )
			.catch( () => {
				// Preview fetch failure is non-fatal — the modal remains functional.
				if ( ! cancelled ) {
					setPreview( null );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setPreviewLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ sessionId, stripToolResults ] );

	const handleSend = useCallback( async () => {
		setIsSending( true );
		setError( null );
		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/feedback/send',
				method: 'POST',
				data: {
					report_type: reportType,
					user_description: description,
					session_id: sessionId ?? 0,
					strip_tool_results: stripToolResults,
				},
			} );
			setIsSent( true );
			// Auto-close after a short confirmation delay.
			setTimeout( onClose, 1500 );
		} catch {
			setError(
				__(
					'Failed to send report. Please try again.',
					'gratis-ai-agent'
				)
			);
			setIsSending( false );
		}
	}, [ reportType, description, sessionId, stripToolResults, onClose ] );

	const summary = preview?.summary;

	return (
		<div className="gratis-ai-agent-shortcuts-overlay">
			<div
				className="gratis-ai-agent-feedback-modal"
				ref={ dialogRef }
				role="dialog"
				aria-modal="true"
				aria-labelledby="gratis-ai-agent-feedback-title"
			>
				<div className="gratis-ai-agent-feedback-modal__header">
					<h3 id="gratis-ai-agent-feedback-title">
						{ __( 'Send Feedback Report', 'gratis-ai-agent' ) }
					</h3>
					<button
						type="button"
						className="gratis-ai-agent-feedback-modal__close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'gratis-ai-agent' ) }
					>
						&times;
					</button>
				</div>
				<div className="gratis-ai-agent-feedback-modal__body">
					{ isSent ? (
						<p className="gratis-ai-agent-feedback-modal__success">
							{ __(
								'Report sent. Thank you!',
								'gratis-ai-agent'
							) }
						</p>
					) : (
						<>
							<p className="gratis-ai-agent-feedback-modal__notice">
								{ __(
									'No passwords, API keys, or credentials are included. Server paths are anonymized. Review the full payload below.',
									'gratis-ai-agent'
								) }
							</p>

							{ /* Summary stats — shown when a session is loaded */ }
							{ sessionId && (
								<div className="gratis-ai-agent-feedback-modal__stats">
									{ previewLoading && (
										<p className="gratis-ai-agent-feedback-modal__stats-loading">
											{ __(
												'Loading report preview…',
												'gratis-ai-agent'
											) }
										</p>
									) }
									{ ! previewLoading && summary && (
										<ul className="gratis-ai-agent-feedback-modal__stats-list">
											<li>
												{ __(
													'Messages:',
													'gratis-ai-agent'
												) }{ ' ' }
												<strong>
													{ summary.message_count }
												</strong>
											</li>
											<li>
												{ __(
													'Tool calls:',
													'gratis-ai-agent'
												) }{ ' ' }
												<strong>
													{ summary.tool_call_count }
												</strong>
											</li>
											<li>
												{ __(
													'Environment keys:',
													'gratis-ai-agent'
												) }{ ' ' }
												<strong>
													{ summary.environment_keys
														?.length ?? 0 }
												</strong>
											</li>
											{ summary.model_id && (
												<li>
													{ __(
														'Model:',
														'gratis-ai-agent'
													) }{ ' ' }
													<strong>
														{ summary.model_id }
													</strong>
												</li>
											) }
										</ul>
									) }
								</div>
							) }

							{ /* Strip tool results checkbox */ }
							{ sessionId && (
								<label
									htmlFor="gratis-ai-agent-strip-tool-results"
									className="gratis-ai-agent-feedback-modal__strip-label"
								>
									<input
										id="gratis-ai-agent-strip-tool-results"
										type="checkbox"
										className="gratis-ai-agent-feedback-modal__strip-checkbox"
										checked={ stripToolResults }
										onChange={ ( e ) =>
											setStripToolResults(
												e.target.checked
											)
										}
									/>
									{ __(
										'Strip tool results (aggressive privacy — keeps tool names and arguments but redacts all outputs)',
										'gratis-ai-agent'
									) }
								</label>
							) }

							{ /* Collapsible payload preview */ }
							{ sessionId && preview?.payload && (
								<div className="gratis-ai-agent-feedback-modal__payload-section">
									<button
										type="button"
										className="gratis-ai-agent-feedback-modal__payload-toggle"
										onClick={ () =>
											setPayloadExpanded( ( v ) => ! v )
										}
										aria-expanded={ payloadExpanded }
									>
										{ payloadExpanded
											? __(
													'Hide full payload ▲',
													'gratis-ai-agent'
											  )
											: __(
													'View full payload ▼',
													'gratis-ai-agent'
											  ) }
									</button>
									{ payloadExpanded && (
										<pre className="gratis-ai-agent-feedback-modal__payload-json">
											{ JSON.stringify(
												preview.payload,
												null,
												2
											) }
										</pre>
									) }
								</div>
							) }

							<label
								htmlFor="gratis-ai-agent-feedback-description"
								className="gratis-ai-agent-feedback-modal__label"
							>
								{ __(
									'Describe the issue (optional):',
									'gratis-ai-agent'
								) }
							</label>
							<textarea
								id="gratis-ai-agent-feedback-description"
								className="gratis-ai-agent-feedback-modal__textarea"
								value={ description }
								onChange={ ( e ) =>
									setDescription( e.target.value )
								}
								rows={ 4 }
								placeholder={ __(
									'What went wrong?',
									'gratis-ai-agent'
								) }
							/>
							{ error && (
								<p className="gratis-ai-agent-feedback-modal__error">
									{ error }
								</p>
							) }
						</>
					) }
				</div>
				{ ! isSent && (
					<div className="gratis-ai-agent-feedback-modal__footer">
						<button
							type="button"
							className="button"
							onClick={ onClose }
							disabled={ isSending }
						>
							{ __( 'Dismiss', 'gratis-ai-agent' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ handleSend }
							disabled={ isSending }
						>
							{ isSending
								? __( 'Sending\u2026', 'gratis-ai-agent' )
								: __( 'Send Report', 'gratis-ai-agent' ) }
						</button>
					</div>
				) }
			</div>
		</div>
	);
}
