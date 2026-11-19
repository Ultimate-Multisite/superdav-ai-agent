/**
 * Shared account-action message rendered by every React chat surface.
 */

import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	CREDIT_EXHAUSTED_REASON,
	PURCHASE_CREDITS_ACTION,
} from '../../utils/superdav-credit-notice';

/**
 * Resolve translated presentation copy for a semantic account action.
 *
 * The credit offer must never be tied to a WordPress.org review: the plugin
 * team treats any reward offered for a review as an invalid (bribed) review.
 *
 * @param {Object} notice Structured account-action notice.
 * @return {{template: string, primaryActionText: string, hasCreditOffer: boolean}}
 *   Translated notice presentation.
 */
export function getAccountActionPresentation( notice ) {
	if (
		notice?.reason === CREDIT_EXHAUSTED_REASON &&
		notice?.action === PURCHASE_CREDITS_ACTION
	) {
		return {
			template: __(
				'Good news—as an early adopter, use coupon code <strong>EARLY</strong> in your <settingsLink>account settings</settingsLink> to claim $200 in AI usage credits.',
				'superdav-ai-agent'
			),
			primaryActionText: __( 'Redeem EARLY coupon', 'superdav-ai-agent' ),
			hasCreditOffer: true,
		};
	}

	return {
		template:
			typeof notice?.message === 'string'
				? notice.message
				: __(
						'Review your account settings to continue.',
						'superdav-ai-agent'
				  ),
		primaryActionText: __( 'Account settings', 'superdav-ai-agent' ),
		hasCreditOffer: false,
	};
}

/**
 * Render a semantic account-action notice with an optional CTA.
 *
 * @param {Object} root0
 * @param {Object} root0.notice Structured account-action notice.
 * @return {JSX.Element} Notice row.
 */
export default function AccountActionMessage( { notice } ) {
	const actionUrl = notice?.actionUrl || '';
	const presentation = getAccountActionPresentation( notice );
	const settingsElement = actionUrl ? (
		// The translated <settingsLink> content is supplied by createInterpolateElement.
		// eslint-disable-next-line jsx-a11y/anchor-has-content
		<a
			className="sd-ai-agent-cr-msg-system-inline-action"
			href={ actionUrl }
			target="_blank"
			rel="noopener noreferrer"
		/>
	) : (
		<span />
	);
	const message = presentation.hasCreditOffer
		? createInterpolateElement( presentation.template, {
				settingsLink: settingsElement,
				strong: <strong />,
		  } )
		: presentation.template;

	return (
		<div className="sdaa-cr-msg-row">
			<div
				className="sd-ai-agent-cr-msg-system sd-ai-agent-cr-msg-system--account-action"
				role="status"
			>
				{ message }
				{ actionUrl && (
					<div className="sd-ai-agent-cr-msg-system-actions">
						<a
							className="sd-ai-agent-cr-msg-system-action"
							href={ actionUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ presentation.primaryActionText }
						</a>
					</div>
				) }
			</div>
		</div>
	);
}
