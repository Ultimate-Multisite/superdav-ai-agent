/**
 * WordPress dependencies
 */
import { useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Get the URL for the Connectors admin page.
 *
 * @return {string} Connectors page URL.
 */
function getConnectorsUrl() {
	return window.gratisAiAgentData?.connectorsUrl || 'options-connectors.php';
}

/**
 * Single-screen connector gate shown on first activation.
 *
 * Polls for available AI providers every 4 seconds. When at least one
 * provider becomes available it automatically calls `onComplete` — no
 * user interaction required. There is no skip, no next step, and no
 * progress dots; the gate is intentionally friction-free once the user
 * has connected a provider.
 *
 * @param {Object}   props            - Component props.
 * @param {Function} props.onComplete - Called when a provider is detected.
 * @return {JSX.Element} The connector gate element.
 */
export default function OnboardingGate( { onComplete } ) {
	const { fetchProviders } = useDispatch( STORE_NAME );
	const providers = useSelect(
		( select ) => select( STORE_NAME ).getProviders(),
		[]
	);

	/**
	 * Poll for providers at a 4-second interval.
	 * Stops automatically once a provider is detected.
	 */
	const startPolling = useCallback( () => {
		const intervalId = setInterval( async () => {
			await fetchProviders();
		}, 4000 );

		return () => clearInterval( intervalId );
	}, [ fetchProviders ] );

	// Start provider polling on mount.
	useEffect( () => {
		const stop = startPolling();
		return stop;
	}, [ startPolling ] );

	// Auto-transition when a provider becomes available.
	useEffect( () => {
		if ( providers.length > 0 ) {
			onComplete();
		}
	}, [ providers, onComplete ] );

	// If a provider is already available, transition immediately.
	if ( providers.length > 0 ) {
		return null;
	}

	return (
		<div className="gratis-ai-agent-onboarding-gate">
			<div className="gratis-ai-agent-onboarding-gate__content">
				<h2 className="gratis-ai-agent-onboarding-gate__title">
					{ __( 'Connect an AI Provider', 'gratis-ai-agent' ) }
				</h2>

				<p className="gratis-ai-agent-onboarding-gate__description">
					{ __(
						'Gratis AI Agent needs at least one AI provider to work. Configure a provider API key on the Connectors page, then come back here.',
						'gratis-ai-agent'
					) }
				</p>

				<Button
					variant="primary"
					href={ getConnectorsUrl() }
					className="gratis-ai-agent-onboarding-gate__cta"
				>
					{ __( 'Open Connectors page →', 'gratis-ai-agent' ) }
				</Button>

				<div className="gratis-ai-agent-onboarding-gate__waiting">
					<Spinner />
					<p className="gratis-ai-agent-onboarding-gate__waiting-text">
						{ __(
							'Waiting for a provider to be connected…',
							'gratis-ai-agent'
						) }
					</p>
				</div>
			</div>
		</div>
	);
}
