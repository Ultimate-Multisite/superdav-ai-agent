/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody } from '@wordpress/components';

/**
 * Chat Route Component
 *
 * Mounts the floating-widget chat app into a dedicated container.
 * The mount API (`window.gratisAiAgentChat`) must expose an `unmount()`
 * method so React's cleanup lifecycle is respected — never clear the
 * container via `innerHTML = ''`, which bypasses React's unmount hooks
 * and leaks subscriptions and event handlers.
 *
 * @return {JSX.Element} Chat route element.
 */
export default function ChatRoute() {
	const containerRef = useRef( null );

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container ) {
			return;
		}

		// Mount the chat app when the API is available.
		if (
			window.gratisAiAgentChat &&
			typeof window.gratisAiAgentChat.mount === 'function'
		) {
			window.gratisAiAgentChat.mount( container );
		}

		// Cleanup: call unmount() to let React tear down its tree properly.
		// Do NOT use innerHTML = '' — that bypasses React's unmount lifecycle.
		return () => {
			if (
				window.gratisAiAgentChat &&
				typeof window.gratisAiAgentChat.unmount === 'function'
			) {
				window.gratisAiAgentChat.unmount( container );
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
