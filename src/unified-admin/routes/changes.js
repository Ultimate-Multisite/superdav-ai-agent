/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody } from '@wordpress/components';

/**
 * Changes Route Component
 *
 * @return {JSX.Element} Changes route element.
 */
export default function ChangesRoute() {
	return (
		<div className="gratis-ai-route gratis-ai-route-changes">
			<Card>
				<CardHeader>
					<h2>{ __( 'Changes', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'View and manage changes made by the AI agent.',
							'gratis-ai-agent'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
