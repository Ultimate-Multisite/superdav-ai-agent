/**
 * Store-driven host for the add-credit popup.
 *
 * The popup itself is loaded on demand so the floating-widget launch bundle
 * only pays for this tiny host until managed SD AI credit actually runs out.
 */

import { useDispatch, useSelect } from '@wordpress/data';
import { lazy, Suspense } from '@wordpress/element';

import STORE_NAME from '../../store';

// Unnamed chunk: a named chunk adds its name to the launch bundle's runtime map.
const CreditUpgradeModal = lazy( () => import( './index' ) );

/**
 * Render the add-credit popup while a credit prompt is open.
 *
 * @return {JSX.Element|null} Popup, or nothing when no prompt is open.
 */
export default function CreditUpgradePrompt() {
	const { prompt, currentSessionId } = useSelect( ( sel ) => {
		const store = sel( STORE_NAME );
		return {
			prompt: store.getCreditPrompt?.() || null,
			currentSessionId: store.getCurrentSessionId(),
		};
	}, [] );
	const { closeCreditPrompt, retryLastMessage, fetchProviders } =
		useDispatch( STORE_NAME );

	if ( ! prompt ) {
		return null;
	}

	const canContinue =
		!! prompt.sessionId &&
		String( prompt.sessionId ) === String( currentSessionId );

	return (
		<Suspense fallback={ null }>
			<CreditUpgradeModal
				onClose={ closeCreditPrompt }
				onCreditAdded={ () => fetchProviders?.() }
				onContinue={
					canContinue
						? () => {
								closeCreditPrompt();
								retryLastMessage();
						  }
						: null
				}
			/>
		</Suspense>
	);
}
