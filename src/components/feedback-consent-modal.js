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
 * Features (t186):
 *   7. Optional messageIndex prop: when set, the payload is scoped to the targeted
 *      message ± 2 surrounding messages rather than the full conversation.
 *   8. "Include full conversation" checkbox, visible only when messageIndex is set.
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
 * @param {number}   [props.messageIndex]       - Zero-based index of the specific
 *                                              message that triggered the report.
 *                                              When provided, the payload includes
 *                                              only that message ± 2 context messages
 *                                              unless the user opts into the full
 *                                              conversation via the checkbox.
 * @param {Function} props.onClose              - Called when the modal should close.
 * @return {JSX.Element} The feedback consent modal element.
 */
export default function FeedbackConsentModal( {
	reportType,
	userDescription = '',
	sessionId,
	messageIndex,
	onClose,
} ) {
	const [ description, setDescription ] = useState( userDescription );
	const [ stripToolResults, setStripToolResults ] = useState( false );
	// When messageIndex is provided, the payload is scoped to that message ± 2.
	// The user can opt out of the scope restriction by checking this box (t186).
	const [ includeFullConversation, setIncludeFullConversation ] =
		useState( false );
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
	// Re-fetch whenever stripToolResults, includeFullConversation, or messageIndex
	// changes so the preview stays accurate (t186: scoped vs full context).
	useEffect( () => {
		if ( ! sessionId ) {
			return;
		}

		let cancelled = false;
		setPreviewLoading( true );
		setPreview( null );

		// Build the preview query: include message_index only when the user has
		// not opted into the full conversation and a specific message was targeted.
		const useMessageScope =
			messageIndex !== undefined &&
			messageIndex !== null &&
			! includeFullConversation;

		let path = `/sd-ai-agent/v1/feedback/preview?session_id=${ sessionId }&strip_tool_results=${
			stripToolResults ? '1' : '0'
		}`;
		if ( useMessageScope ) {
			path += `&message_index=${ messageIndex }`;
		}

		apiFetch( { path } )
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
	}, [ sessionId, stripToolResults, messageIndex, includeFullConversation ] );

	const handleSend = useCallback( async () => {
		setIsSending( true );
		setError( null );
		try {
			// Include message_index when the payload should be scoped to the
			// specific message and its surrounding context (t186).
			const useMessageScope =
				messageIndex !== undefined &&
				messageIndex !== null &&
				! includeFullConversation;

			const postData = {
				report_type: reportType,
				user_description: description,
				session_id: sessionId ?? 0,
				strip_tool_results: stripToolResults,
			};
			if ( useMessageScope ) {
				postData.message_index = messageIndex;
			}

			await apiFetch( {
				path: '/sd-ai-agent/v1/feedback/send',
				method: 'POST',
				data: postData,
			} );
			setIsSent( true );
			// Auto-close after a short confirmation delay.
			setTimeout( onClose, 1500 );
		} catch {
			setError(
				__( 'Failed to send report. Please try again.', 'sd-ai-agent' )
			);
			setIsSending( false );
		}
	}, [
		reportType,
		description,
		sessionId,
		stripToolResults,
		messageIndex,
		includeFullConversation,
		onClose,
	] );

	const summary = preview?.summary;

	return (
		<div className="sd-ai-agent-shortcuts-overlay">
			<div
				className="sd-ai-agent-feedback-modal"
				ref={ dialogRef }
				role="dialog"
				aria-modal="true"
				aria-labelledby="sd-ai-agent-feedback-title"
			>
				<div className="sd-ai-agent-feedback-modal__header">
					<h3 id="sd-ai-agent-feedback-title">
						{ __( 'Send Feedback Report', 'sd-ai-agent' ) }
					</h3>
					<button
						type="button"
						className="sd-ai-agent-feedback-modal__close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'sd-ai-agent' ) }
					>
						&times;
					</button>
				</div>
				<div className="sd-ai-agent-feedback-modal__body">
					{ isSent ? (
						<p className="sd-ai-agent-feedback-modal__success">
							{ __( 'Report sent. Thank you!', 'sd-ai-agent' ) }
						</p>
					) : (
						<>
							<p className="sd-ai-agent-feedback-modal__notice">
								{ __(
									'No passwords, API keys, or credentials are included. Server paths are anonymized. Review the full payload below.',
									'sd-ai-agent'
								) }
							</p>

							{ /* Summary stats — shown when a session is loaded */ }
							{ sessionId && (
								<div className="sd-ai-agent-feedback-modal__stats">
									{ previewLoading && (
										<p className="sd-ai-agent-feedback-modal__stats-loading">
											{ __(
												'Loading report preview…',
												'sd-ai-agent'
											) }
										</p>
									) }
									{ ! previewLoading && summary && (
										<ul className="sd-ai-agent-feedback-modal__stats-list">
											<li>
												{ __(
													'Messages:',
													'sd-ai-agent'
												) }{ ' ' }
												<strong>
													{ summary.message_count }
												</strong>
											</li>
											<li>
												{ __(
													'Tool calls:',
													'sd-ai-agent'
												) }{ ' ' }
												<strong>
													{ summary.tool_call_count }
												</strong>
											</li>
											<li>
												{ __(
													'Environment keys:',
													'sd-ai-agent'
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
														'sd-ai-agent'
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

							{ /* Full conversation opt-in — only shown for thumbs-down (messageIndex is set) */ }
							{ sessionId &&
								messageIndex !== undefined &&
								messageIndex !== null && (
									<label
										htmlFor="sd-ai-agent-full-conversation"
										className="sd-ai-agent-feedback-modal__strip-label"
									>
										<input
											id="sd-ai-agent-full-conversation"
											type="checkbox"
											className="sd-ai-agent-feedback-modal__strip-checkbox"
											checked={ includeFullConversation }
											onChange={ ( e ) =>
												setIncludeFullConversation(
													e.target.checked
												)
											}
										/>
										{ __(
											'Include full conversation (by default only the selected response and 2 surrounding messages are sent)',
											'sd-ai-agent'
										) }
									</label>
								) }

							{ /* Strip tool results checkbox */ }
							{ sessionId && (
								<label
									htmlFor="sd-ai-agent-strip-tool-results"
									className="sd-ai-agent-feedback-modal__strip-label"
								>
									<input
										id="sd-ai-agent-strip-tool-results"
										type="checkbox"
										className="sd-ai-agent-feedback-modal__strip-checkbox"
										checked={ stripToolResults }
										onChange={ ( e ) =>
											setStripToolResults(
												e.target.checked
											)
										}
									/>
									{ __(
										'Strip tool results (aggressive privacy — keeps tool names and arguments but redacts all outputs)',
										'sd-ai-agent'
									) }
								</label>
							) }

							{ /* Collapsible payload preview */ }
							{ sessionId && preview?.payload && (
								<div className="sd-ai-agent-feedback-modal__payload-section">
									<button
										type="button"
										className="sd-ai-agent-feedback-modal__payload-toggle"
										onClick={ () =>
											setPayloadExpanded( ( v ) => ! v )
										}
										aria-expanded={ payloadExpanded }
									>
										{ payloadExpanded
											? __(
													'Hide full payload ▲',
													'sd-ai-agent'
											  )
											: __(
													'View full payload ▼',
													'sd-ai-agent'
											  ) }
									</button>
									{ payloadExpanded && (
										<pre className="sd-ai-agent-feedback-modal__payload-json">
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
								htmlFor="sd-ai-agent-feedback-description"
								className="sd-ai-agent-feedback-modal__label"
							>
								{ __(
									'Describe the issue (optional):',
									'sd-ai-agent'
								) }
							</label>
							<textarea
								id="sd-ai-agent-feedback-description"
								className="sd-ai-agent-feedback-modal__textarea"
								value={ description }
								onChange={ ( e ) =>
									setDescription( e.target.value )
								}
								rows={ 4 }
								placeholder={ __(
									'What went wrong?',
									'sd-ai-agent'
								) }
							/>
							{ error && (
								<p className="sd-ai-agent-feedback-modal__error">
									{ error }
								</p>
							) }
						</>
					) }
				</div>
				{ ! isSent && (
					<div className="sd-ai-agent-feedback-modal__footer">
						<button
							type="button"
							className="button"
							onClick={ onClose }
							disabled={ isSending }
						>
							{ __( 'Dismiss', 'sd-ai-agent' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ handleSend }
							disabled={ isSending }
						>
							{ isSending
								? __( 'Sending\u2026', 'sd-ai-agent' )
								: __( 'Send Report', 'sd-ai-agent' ) }
						</button>
					</div>
				) }
			</div>
		</div>
	);
}
