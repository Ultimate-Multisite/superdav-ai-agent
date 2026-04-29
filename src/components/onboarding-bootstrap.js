/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ChatPanel from './ChatPanel';

/**
 * Onboarding bootstrap component — shown after a connector is configured
 * for the first time.
 *
 * Calls POST /onboarding/bootstrap-start to:
 *  1. Mark onboarding as complete on the server.
 *  2. Auto-detect WooCommerce and queue RAG indexing.
 *  3. Create a new session with a discovery-oriented system prompt.
 *
 * Once the session is ready the component auto-sends a kickoff message so
 * the agent begins exploring the site immediately — no form, no wizard.
 *
 * The user sees the normal ChatPanel throughout. The only difference from
 * a regular session is the locked onboarding system prompt (injected via
 * the system_instruction option in the first message).
 *
 * @return {JSX.Element} The onboarding bootstrap element.
 */
export default function OnboardingBootstrap() {
	const { openSession, sendMessage } = useDispatch( STORE_NAME );
	const bootstrappedRef = useRef( false );

	useEffect( () => {
		// Guard against double-invocation in React 18 strict-mode or re-renders.
		if ( bootstrappedRef.current ) {
			return;
		}
		bootstrappedRef.current = true;

		apiFetch( {
			path: '/sd-ai-agent/v1/onboarding/bootstrap-start',
			method: 'POST',
		} )
			.then( ( data ) => {
				if ( ! data?.session_id ) {
					// Fallback: if the endpoint doesn't return a session, the
					// ChatPanel will create one on the first message.
					return;
				}

				// Activate the bootstrap session in the store.
				openSession( data.session_id ).then( () => {
					// Auto-send the kickoff message with the onboarding system
					// instruction locked in so the agent explores before asking.
					sendMessage(
						data.kickoff_message ||
							__(
								"Hello! I'm just getting set up. Please explore this WordPress site and introduce yourself — tell me what you notice and what you can help with.",
								'sd-ai-agent'
							),
						[],
						data.bootstrap_system_prompt
							? {
									systemInstruction:
										data.bootstrap_system_prompt,
							  }
							: {}
					);
				} );
			} )
			.catch( () => {
				// Non-fatal: fall through to the normal ChatPanel without
				// the auto-discovery kickoff. The user can start chatting manually.
			} );
	}, [ openSession, sendMessage ] );

	return (
		<div className="sd-ai-agent-onboarding-bootstrap">
			<ChatPanel />
		</div>
	);
}
