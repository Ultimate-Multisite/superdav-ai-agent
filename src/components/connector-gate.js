/**
 * WordPress dependencies
 */
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Get the URL for the Connectors admin page.
 *
 * @return {string} Connectors page URL.
 */
function getConnectorsUrl() {
	return (
		window.gratisAiAgentData?.connectorsUrl ||
		'admin.php?page=gratis-ai-agent#/connectors'
	);
}

/**
 * Connector gate shown before onboarding when no AI provider is configured.
 *
 * This is a hard gate: the chat and onboarding are inaccessible until at
 * least one AI connector (OpenAI, Anthropic, Google AI) is configured via
 * the WordPress Connectors page. The user sees only this screen until a
 * provider becomes available.
 *
 * Polling is handled by the parent (AdminPageApp) which calls fetchProviders
 * every 5 s and re-renders this component away once providers become available.
 *
 * @return {JSX.Element} The connector gate element.
 */
export default function ConnectorGate() {
	return (
		<div className="gratis-ai-agent-connector-gate">
			<div className="gratis-ai-agent-connector-gate__inner">
				<h2 className="gratis-ai-agent-connector-gate__title">
					{ __( 'Set Up an AI Provider', 'gratis-ai-agent' ) }
				</h2>

				<p className="gratis-ai-agent-connector-gate__description">
					{ __(
						'Gratis AI Agent needs an AI provider to work. Configure an API key for OpenAI, Anthropic, or Google AI on the Connectors page to get started.',
						'gratis-ai-agent'
					) }
				</p>

				<Notice status="info" isDismissible={ false }>
					{ __(
						'You will be brought back here automatically once a connector is set up.',
						'gratis-ai-agent'
					) }
				</Notice>

				<div className="gratis-ai-agent-connector-gate__actions">
					<Button
						variant="primary"
						href={ getConnectorsUrl() }
						className="gratis-ai-agent-connector-gate__cta"
					>
						{ __( 'Configure a Connector →', 'gratis-ai-agent' ) }
					</Button>
				</div>
			</div>
		</div>
	);
}
