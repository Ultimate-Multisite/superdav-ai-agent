/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody } from '@wordpress/components';

/**
 * Chat Route Component
 *
 * @return {JSX.Element} Chat route element.
 */
export default function ChatRoute() {
	return (
		<div className="gratis-ai-route gratis-ai-route-chat">
			<Card>
				<CardHeader>
					<h2>{ __( 'Chat', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Chat with your AI assistant. Use the sidebar to manage sessions.',
							'gratis-ai-agent'
						) }
					</p>
					<div
						id="gratis-ai-chat-container"
						className="gratis-ai-chat-container"
						style={ { minHeight: '500px' } }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
