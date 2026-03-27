/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody } from '@wordpress/components';

/**
 * Chat Route Component
 *
 * Mounts the AdminPageApp chat UI into a dedicated container.
 * The mount API (`window.gratisAiAgentChat`) is exposed by the admin-page
 * bundle (build/admin-page.js), which is enqueued after unified-admin.js.
 * Because the two bundles load asynchronously, the API may not be defined
 * when this component first mounts. We poll with a short interval until the
 * API becomes available, then call mount() exactly once.
 *
 * The mount API must expose an `unmount()` method so React's cleanup
 * lifecycle is respected — never clear the container via `innerHTML = ''`,
 * which bypasses React's unmount hooks and leaks subscriptions and event
 * handlers.
 *
 * @return {JSX.Element} Chat route element.
 */
export default function ChatRoute() {
	const containerRef = useRef( null );
	// Track whether mount() has been called so we don't call it twice.
	const mountedRef = useRef( false );

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container ) {
			return;
		}

		/**
		 * Attempt to mount the chat app. Returns true if mount succeeded.
		 *
		 * @return {boolean} Whether the mount API was available and called.
		 */
		function tryMount() {
			if (
				window.gratisAiAgentChat &&
				typeof window.gratisAiAgentChat.mount === 'function' &&
				! mountedRef.current
			) {
				mountedRef.current = true;
				window.gratisAiAgentChat.mount( container );
				return true;
			}
			return false;
		}

		// Try immediately — the API may already be defined if admin-page.js
		// loaded synchronously before this effect ran.
		if ( tryMount() ) {
			return () => {
				if (
					window.gratisAiAgentChat &&
					typeof window.gratisAiAgentChat.unmount === 'function'
				) {
					window.gratisAiAgentChat.unmount( container );
				}
			};
		}

		// Poll every 50 ms for up to 30 s waiting for admin-page.js to load
		// and expose window.gratisAiAgentChat. This handles the race condition
		// where unified-admin.js renders ChatRoute before admin-page.js has
		// finished executing (both are loaded as async scripts).
		// 30 s matches the goToAgentPage() wait timeout in tests/e2e/utils/wp-admin.js
		// so the chat panel always has a chance to mount before the test times out.
		let intervalId = setInterval( () => {
			if ( tryMount() ) {
				clearInterval( intervalId );
				intervalId = null;
			}
		}, 50 );

		// Safety timeout: stop polling after 30 s to avoid an infinite loop.
		const timeoutId = setTimeout( () => {
			if ( intervalId ) {
				clearInterval( intervalId );
				intervalId = null;
			}
		}, 30_000 );

		// Cleanup: stop polling and unmount the chat app.
		return () => {
			if ( intervalId ) {
				clearInterval( intervalId );
			}
			clearTimeout( timeoutId );
			if (
				mountedRef.current &&
				window.gratisAiAgentChat &&
				typeof window.gratisAiAgentChat.unmount === 'function'
			) {
				window.gratisAiAgentChat.unmount( container );
				mountedRef.current = false;
			}
		};
	}, [] );

	return (
		<div className="gratis-ai-route gratis-ai-route-chat">
			<Card>
				<CardHeader>
					<h2>{ __( 'Chat', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<div
						ref={ containerRef }
						id="gratis-ai-chat-container"
						className="gratis-ai-chat-container"
						style={ { minHeight: '500px' } }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
