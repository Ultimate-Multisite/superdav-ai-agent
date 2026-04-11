/**
 * Full-screen site builder overlay for fresh WordPress installs (t062).
 *
 * Renders a centered full-screen overlay instead of the FAB when
 * site builder mode is active. Includes a progress bar, step indicator,
 * and a "Skip" option to dismiss the overlay and return to normal mode.
 *
 * When no AI providers are configured, shows an inline provider setup
 * panel so users can enter an API key without leaving the overlay.
 */

/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';
import { Button, TextControl, Notice, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ChatPanel from '../components/ChatPanel';

/**
 * Metadata for the three official AI providers shown in the setup panel.
 */
const PROVIDERS = [
	{
		id: 'openai',
		name: 'OpenAI',
		description: __(
			'GPT-4o, o1, and other OpenAI models.',
			'gratis-ai-agent'
		),
		keyPlaceholder: 'sk-...',
		docsUrl: 'https://platform.openai.com/api-keys',
		docsLabel: __( 'Get key', 'gratis-ai-agent' ),
	},
	{
		id: 'anthropic',
		name: 'Anthropic',
		description: __(
			'Claude Sonnet, Opus, and Haiku models.',
			'gratis-ai-agent'
		),
		keyPlaceholder: 'sk-ant-...',
		docsUrl: 'https://console.anthropic.com/settings/keys',
		docsLabel: __( 'Get key', 'gratis-ai-agent' ),
	},
	{
		id: 'google',
		name: 'Google AI',
		description: __(
			'Gemini 2.0 Flash, 2.5 Pro, and other Google models.',
			'gratis-ai-agent'
		),
		keyPlaceholder: 'AIza...',
		docsUrl: 'https://aistudio.google.com/app/apikey',
		docsLabel: __( 'Get key', 'gratis-ai-agent' ),
	},
];

/**
 * Compact provider setup row for the site builder overlay.
 *
 * @param {Object}   props            - Component props.
 * @param {Object}   props.provider   - Provider metadata object.
 * @param {Function} props.onKeySaved - Called after a key is successfully saved.
 * @return {JSX.Element} The provider row element.
 */
function ProviderSetupRow( { provider, onKeySaved } ) {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const { fetchProviders } = useDispatch( STORE_NAME );

	const handleSave = useCallback( async () => {
		if ( ! apiKey.trim() ) {
			return;
		}

		setSaving( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/provider-key',
				method: 'POST',
				data: { provider: provider.id, api_key: apiKey.trim() },
			} );

			setApiKey( '' );
			setNotice( {
				status: 'success',
				message: sprintf(
					/* translators: %s: provider name */
					__( '%s connected!', 'gratis-ai-agent' ),
					provider.name
				),
			} );

			await fetchProviders();
			onKeySaved( provider.id );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to save API key.', 'gratis-ai-agent' ),
			} );
		}

		setSaving( false );
	}, [ apiKey, provider.id, provider.name, fetchProviders, onKeySaved ] );

	return (
		<div className="gratis-ai-agent-site-builder-provider-row">
			<div className="gratis-ai-agent-site-builder-provider-info">
				<strong>{ provider.name }</strong>
				<a
					href={ provider.docsUrl }
					target="_blank"
					rel="noopener noreferrer"
					className="gratis-ai-agent-site-builder-provider-docs"
				>
					{ provider.docsLabel } ↗
				</a>
			</div>
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<div className="gratis-ai-agent-site-builder-provider-key-row">
				<TextControl
					type="password"
					value={ apiKey }
					onChange={ setApiKey }
					placeholder={ provider.keyPlaceholder }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving || ! apiKey.trim() }
					size="compact"
				>
					{ saving ? <Spinner /> : __( 'Save', 'gratis-ai-agent' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * Full-screen site builder overlay component.
 *
 * Displayed instead of the FAB when `siteBuilderMode` is true in the store.
 * Shows a progress bar (when totalSteps > 0), the chat panel, and a Skip button.
 *
 * When no providers are configured, replaces the chat panel with an inline
 * provider setup panel so users can connect at least one AI provider.
 *
 * @return {JSX.Element} The site builder overlay element.
 */
export default function SiteBuilderOverlay() {
	const { setSiteBuilderMode } = useDispatch( STORE_NAME );

	const { step, totalSteps, providers } = useSelect(
		( select ) => ( {
			step: select( STORE_NAME ).getSiteBuilderStep(),
			totalSteps: select( STORE_NAME ).getSiteBuilderTotalSteps(),
			providers: select( STORE_NAME ).getProviders(),
		} ),
		[]
	);

	const hasProviders = providers.length > 0;

	const hasProgress = totalSteps > 0;
	const progressPercent = hasProgress
		? Math.min( 100, Math.round( ( step / totalSteps ) * 100 ) )
		: 0;

	/**
	 * Dismiss the site builder overlay and return to normal FAB mode.
	 */
	function handleSkip() {
		setSiteBuilderMode( false );
	}

	/**
	 * Called when a provider key is saved — no-op since the store
	 * re-fetches providers automatically and `hasProviders` updates.
	 */
	const handleKeySaved = useCallback( () => {}, [] );

	return (
		<div
			className="gratis-ai-agent-site-builder-overlay"
			role="dialog"
			aria-modal="true"
			aria-label={ __( 'Site Builder', 'gratis-ai-agent' ) }
		>
			<div className="gratis-ai-agent-site-builder-backdrop" />

			<div className="gratis-ai-agent-site-builder-panel">
				{ /* Header */ }
				<div className="gratis-ai-agent-site-builder-header">
					<div className="gratis-ai-agent-site-builder-header-text">
						<h2 className="gratis-ai-agent-site-builder-title">
							{ __( 'Build Your Site', 'gratis-ai-agent' ) }
						</h2>
						<p className="gratis-ai-agent-site-builder-subtitle">
							{ hasProviders
								? __(
										"Let's set up your WordPress site. Answer a few questions and I'll build it for you.",
										'gratis-ai-agent'
								  )
								: __(
										'Connect an AI provider to get started.',
										'gratis-ai-agent'
								  ) }
						</p>
					</div>
					<Button
						className="gratis-ai-agent-site-builder-skip"
						variant="tertiary"
						onClick={ handleSkip }
					>
						{ __( 'Skip', 'gratis-ai-agent' ) }
					</Button>
				</div>

				{ /* Progress bar */ }
				{ hasProgress && (
					<div className="gratis-ai-agent-site-builder-progress">
						<div className="gratis-ai-agent-site-builder-progress-bar">
							<div
								className="gratis-ai-agent-site-builder-progress-fill"
								style={ { width: progressPercent + '%' } }
								role="progressbar"
								aria-valuenow={ progressPercent }
								aria-valuemin={ 0 }
								aria-valuemax={ 100 }
								aria-label={ sprintf(
									/* translators: %d: progress percentage */
									__( '%d%% complete', 'gratis-ai-agent' ),
									progressPercent
								) }
							/>
						</div>
						<span className="gratis-ai-agent-site-builder-progress-label">
							{ sprintf(
								/* translators: 1: current step, 2: total steps */
								__( 'Step %1$d of %2$d', 'gratis-ai-agent' ),
								step,
								totalSteps
							) }
						</span>
					</div>
				) }

				{ /* Provider setup or chat panel */ }
				{ hasProviders ? (
					<div className="gratis-ai-agent-site-builder-chat">
						<ChatPanel />
					</div>
				) : (
					<div className="gratis-ai-agent-site-builder-setup">
						<p className="gratis-ai-agent-site-builder-setup-intro">
							{ __(
								'Enter an API key for at least one provider. You can add more later in Settings.',
								'gratis-ai-agent'
							) }
						</p>
						<div className="gratis-ai-agent-site-builder-provider-list">
							{ PROVIDERS.map( ( provider ) => (
								<ProviderSetupRow
									key={ provider.id }
									provider={ provider }
									onKeySaved={ handleKeySaved }
								/>
							) ) }
						</div>
					</div>
				) }
			</div>
		</div>
	);
}
