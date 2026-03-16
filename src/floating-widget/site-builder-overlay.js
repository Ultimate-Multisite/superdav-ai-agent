/**
 * Full-screen site builder overlay for fresh WordPress installs (t062).
 *
 * Renders a centered full-screen overlay instead of the FAB when
 * site builder mode is active. Includes a progress bar, step indicator,
 * and a "Skip" option to dismiss the overlay and return to normal mode.
 */

/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ChatPanel from '../components/chat-panel';

/**
 * Full-screen site builder overlay component.
 *
 * Displayed instead of the FAB when `siteBuilderMode` is true in the store.
 * Shows a progress bar (when totalSteps > 0), the chat panel, and a Skip button.
 *
 * @return {JSX.Element} The site builder overlay element.
 */
export default function SiteBuilderOverlay() {
	const { setSiteBuilderMode } = useDispatch( STORE_NAME );

	const { step, totalSteps } = useSelect(
		( select ) => ( {
			step: select( STORE_NAME ).getSiteBuilderStep(),
			totalSteps: select( STORE_NAME ).getSiteBuilderTotalSteps(),
		} ),
		[]
	);

	const hasProgress = totalSteps > 0;
	const progressPercent = hasProgress
		? Math.min( 100, Math.round( ( step / totalSteps ) * 100 ) )
		: 0;

	/**
	 * Dismiss the site builder overlay and return to normal FAB mode.
	 */
	function handleSkip() {
		setSiteBuilderMode( false );
	}

	return (
		<div
			className="ai-agent-site-builder-overlay"
			role="dialog"
			aria-modal="true"
			aria-label={ __( 'Site Builder', 'gratis-ai-agent' ) }
		>
			<div className="ai-agent-site-builder-backdrop" />

			<div className="ai-agent-site-builder-panel">
				{ /* Header */ }
				<div className="ai-agent-site-builder-header">
					<div className="ai-agent-site-builder-header-text">
						<h2 className="ai-agent-site-builder-title">
							{ __( 'Build Your Site', 'gratis-ai-agent' ) }
						</h2>
						<p className="ai-agent-site-builder-subtitle">
							{ __(
								"Let's set up your WordPress site. Answer a few questions and I'll build it for you.",
								'gratis-ai-agent'
							) }
						</p>
					</div>
					<Button
						className="ai-agent-site-builder-skip"
						variant="tertiary"
						onClick={ handleSkip }
					>
						{ __( 'Skip', 'gratis-ai-agent' ) }
					</Button>
				</div>

				{ /* Progress bar */ }
				{ hasProgress && (
					<div className="ai-agent-site-builder-progress">
						<div className="ai-agent-site-builder-progress-bar">
							<div
								className="ai-agent-site-builder-progress-fill"
								style={ { width: progressPercent + '%' } }
								role="progressbar"
								aria-valuenow={ progressPercent }
								aria-valuemin={ 0 }
								aria-valuemax={ 100 }
								aria-label={ sprintf(
									/* translators: %d: progress percentage */
									__( '%d%% complete', 'gratis-ai-agent' ),
									progressPercent
								) }
							/>
						</div>
						<span className="ai-agent-site-builder-progress-label">
							{ sprintf(
								/* translators: 1: current step, 2: total steps */
								__( 'Step %1$d of %2$d', 'gratis-ai-agent' ),
								step,
								totalSteps
							) }
						</span>
					</div>
				) }

				{ /* Chat panel */ }
				<div className="ai-agent-site-builder-chat">
					<ChatPanel />
				</div>
			</div>
		</div>
	);
}
