/**
 * WordPress dependencies
 */
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Get the URL for the Connectors admin page.
 *
 * @return {string} Connectors page URL.
 */
function getConnectorsUrl() {
	return (
		window.gratisAiAgentData?.connectorsUrl ||
		'options-general.php?page=options-connectors-wp-admin'
	);
}

/**
 * Whether the Connectors page is available (WP 7.0+ or Gutenberg 22.8.0+).
 *
 * wp_localize_script() converts PHP booleans to strings ('1' or ''),
 * so we check for truthiness rather than strict boolean comparison.
 *
 * @return {boolean} True when the Connectors page exists.
 */
function isConnectorsAvailable() {
	return !! window.gratisAiAgentData?.connectorsAvailable;
}

/**
 * Connector gate shown before onboarding when no AI provider is configured.
 *
 * This is a hard gate: the chat and onboarding are inaccessible until at
 * least one AI connector (OpenAI, Anthropic, Google AI) is configured via
 * the WordPress Connectors page. The user sees only this screen until a
 * provider becomes available.
 *
 * On WP 6.9 without Gutenberg 22.8.0+, the Connectors page does not exist.
 * In that case, the user can install and activate Gutenberg with one click.
 *
 * Polling is handled by the parent (AdminPageApp) which calls fetchProviders
 * every 5 s and re-renders this component away once providers become available.
 *
 * @return {JSX.Element} The connector gate element.
 */
export default function ConnectorGate() {
	const connectorsAvailable = isConnectorsAvailable();
	const [ installing, setInstalling ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleInstallGutenberg = useCallback( async () => {
		setInstalling( true );
		setError( null );
		try {
			await apiFetch( {
				path: '/wp/v2/plugins',
				method: 'POST',
				data: { slug: 'gutenberg', status: 'active' },
			} );
			// Reload so PHP detects GUTENBERG_VERSION and enables Connectors.
			window.location.reload();
		} catch ( err ) {
			setError(
				err?.message ||
					__(
						'Failed to install Gutenberg. Please install it manually from the Plugins page.',
						'gratis-ai-agent'
					)
			);
			setInstalling( false );
		}
	}, [] );

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

				{ connectorsAvailable ? (
					<>
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
								{ __(
									'Configure a Connector →',
									'gratis-ai-agent'
								) }
							</Button>
						</div>
					</>
				) : (
					<>
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Your WordPress version does not include the Connectors page. Install the Gutenberg plugin (version 22.8.0 or newer) to configure AI providers.',
								'gratis-ai-agent'
							) }
						</Notice>

						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }

						<div className="gratis-ai-agent-connector-gate__actions">
							<Button
								variant="primary"
								onClick={ handleInstallGutenberg }
								isBusy={ installing }
								disabled={ installing }
								className="gratis-ai-agent-connector-gate__cta"
							>
								{ installing ? (
									<>
										<Spinner />
										{ __(
											'Installing Gutenberg…',
											'gratis-ai-agent'
										) }
									</>
								) : (
									__(
										'Install & Activate Gutenberg',
										'gratis-ai-agent'
									)
								) }
							</Button>
						</div>
					</>
				) }
			</div>
		</div>
	);
}
