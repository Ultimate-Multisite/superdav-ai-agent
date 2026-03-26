/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody } from '@wordpress/components';

/**
 * Abilities Route Component
 *
 * @return {JSX.Element} Abilities route element.
 */
export default function AbilitiesRoute() {
	return (
		<div className="gratis-ai-route gratis-ai-route-abilities">
			<Card>
				<CardHeader>
					<h2>{ __( 'Abilities', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Explore available abilities and tools that the AI agent can use.',
							'gratis-ai-agent'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
