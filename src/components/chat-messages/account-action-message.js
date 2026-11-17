/**
 * Shared account-action message rendered by every React chat surface.
 */

import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import STORE_NAME from '../../store';
import {
	CREDIT_EXHAUSTED_REASON,
	PURCHASE_CREDITS_ACTION,
} from '../../utils/superdav-credit-notice';

/**
 * Resolve translated presentation copy for a semantic account action.
 *
 * @param {Object} notice Structured account-action notice.
 * @return {{message: string, primaryActionText: string, opensCreditPrompt: boolean}}
 *   Translated notice presentation.
 */
export function getAccountActionPresentation( notice ) {
	if (
		notice?.reason === CREDIT_EXHAUSTED_REASON &&
		notice?.action === PURCHASE_CREDITS_ACTION
	) {
		return {
			message: __(
				'You’ve used your SD AI credit. Add credit to keep working. Your conversation is saved.',
				'superdav-ai-agent'
			),
			primaryActionText: __( 'Add credit', 'superdav-ai-agent' ),
			opensCreditPrompt: true,
		};
	}

	return {
		message:
			typeof notice?.message === 'string'
				? notice.message
				: __(
						'Review your account settings to continue.',
						'superdav-ai-agent'
				  ),
		primaryActionText: __( 'Account settings', 'superdav-ai-agent' ),
		opensCreditPrompt: false,
	};
}

/**
 * Render a semantic account-action notice with an optional CTA.
 *
 * Credit-exhaustion notices reopen the shared add-credit popup so the inline
 * transcript entry and the popup always lead to the same one-click checkout.
 *
 * @param {Object} root0
 * @param {Object} root0.notice Structured account-action notice.
 * @return {JSX.Element} Notice row.
 */
export default function AccountActionMessage( { notice } ) {
	const { openCreditPrompt } = useDispatch( STORE_NAME );
	const currentSessionId = useSelect(
		( sel ) => sel( STORE_NAME ).getCurrentSessionId?.() ?? null,
		[]
	);
	const actionUrl = notice?.actionUrl || '';
	const presentation = getAccountActionPresentation( notice );
	const canOpenPrompt =
		presentation.opensCreditPrompt &&
		typeof openCreditPrompt === 'function';

	let action = null;
	if ( canOpenPrompt ) {
		action = (
			<button
				type="button"
				className="sd-ai-agent-cr-msg-system-action"
				onClick={ () => openCreditPrompt( currentSessionId ) }
			>
				{ presentation.primaryActionText }
			</button>
		);
	} else if ( actionUrl ) {
		action = (
			<a
				className="sd-ai-agent-cr-msg-system-action"
				href={ actionUrl }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ presentation.primaryActionText }
			</a>
		);
	}

	return (
		<div className="sdaa-cr-msg-row">
			<div
				className="sd-ai-agent-cr-msg-system sd-ai-agent-cr-msg-system--account-action"
				role="status"
			>
				{ presentation.message }
				{ action && (
					<div className="sd-ai-agent-cr-msg-system-actions">
						{ action }
					</div>
				) }
			</div>
		</div>
	);
}
