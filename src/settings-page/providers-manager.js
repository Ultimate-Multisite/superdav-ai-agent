/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import {
	Button,
	TextControl,
	Notice,
	Spinner,
	Card,
	CardHeader,
	CardBody,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Metadata for the three official AI providers.
 */
const PROVIDERS = [
	{
		id: 'openai',
		name: 'OpenAI',
		description: __(
			'Access GPT-4o, o1, and other OpenAI models. Get your API key at platform.openai.com.',
			'gratis-ai-agent'
		),
		keyPlaceholder: 'sk-...',
		docsUrl: 'https://platform.openai.com/api-keys',
	},
	{
		id: 'anthropic',
		name: 'Anthropic',
		description: __(
			'Access Claude Sonnet, Opus, and Haiku models. Get your API key at console.anthropic.com.',
			'gratis-ai-agent'
		),
		keyPlaceholder: 'sk-ant-...',
		docsUrl: 'https://console.anthropic.com/settings/keys',
	},
	{
		id: 'google',
		name: 'Google AI',
		description: __(
			'Access Gemini 2.0 Flash, 2.5 Pro, and other Google models. Get your API key at aistudio.google.com.',
			'gratis-ai-agent'
		),
		keyPlaceholder: 'AIza...',
		docsUrl: 'https://aistudio.google.com/app/apikey',
	},
];

/**
 * Single provider configuration card.
 *
 * @param {Object}  root0          - Component props.
 * @param {Object}  root0.provider - Provider configuration object.
 * @param {boolean} root0.hasKey   - Whether an API key is already configured.
 */
function ProviderCard( { provider, hasKey } ) {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ keyConfigured, setKeyConfigured ] = useState( hasKey );
	const { fetchProviders } = useDispatch( STORE_NAME );

	const handleSave = useCallback( async () => {
		if ( ! apiKey.trim() ) {
			setNotice( {
				status: 'error',
				message: __( 'Please enter an API key.', 'gratis-ai-agent' ),
			} );
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

			setKeyConfigured( true );
			setApiKey( '' );
			setNotice( {
				status: 'success',
				message: __( 'API key saved.', 'gratis-ai-agent' ),
			} );

			// Refresh the providers list in the store so the chat UI updates.
			fetchProviders();
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to save API key.', 'gratis-ai-agent' ),
			} );
		}

		setSaving( false );
	}, [ apiKey, provider.id, fetchProviders ] );

	const handleTest = useCallback( async () => {
		const keyToTest = apiKey.trim() || undefined;

		if ( ! keyToTest && ! keyConfigured ) {
			setNotice( {
				status: 'error',
				message: __(
					'Enter an API key or save one first.',
					'gratis-ai-agent'
				),
			} );
			return;
		}

		setTesting( true );
		setNotice( null );

		try {
			const result = await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/provider-key/test',
				method: 'POST',
				data: {
					provider: provider.id,
					...( keyToTest ? { api_key: keyToTest } : {} ),
				},
			} );

			if ( result.success ) {
				setNotice( {
					status: 'success',
					message: sprintf(
						/* translators: %s: model name */
						__(
							'Connection successful. Model: %s',
							'gratis-ai-agent'
						),
						result.model || provider.id
					),
				} );
			} else {
				setNotice( {
					status: 'error',
					message:
						result.error ||
						__( 'Connection test failed.', 'gratis-ai-agent' ),
				} );
			}
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Connection test failed.', 'gratis-ai-agent' ),
			} );
		}

		setTesting( false );
	}, [ apiKey, keyConfigured, provider.id ] );

	const handleRemove = useCallback( async () => {
		setSaving( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/provider-key',
				method: 'POST',
				data: { provider: provider.id, api_key: '' },
			} );

			setKeyConfigured( false );
			setApiKey( '' );
			setNotice( {
				status: 'success',
				message: __( 'API key removed.', 'gratis-ai-agent' ),
			} );

			fetchProviders();
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to remove API key.', 'gratis-ai-agent' ),
			} );
		}

		setSaving( false );
	}, [ provider.id, fetchProviders ] );

	return (
		<Card className="gratis-ai-agent-provider-card">
			<CardHeader>
				<div className="gratis-ai-agent-provider-card__header">
					<h3 className="gratis-ai-agent-provider-card__title">
						{ provider.name }
						{ keyConfigured && (
							<span className="gratis-ai-agent-provider-card__badge gratis-ai-agent-provider-card__badge--configured">
								{ __( 'Configured', 'gratis-ai-agent' ) }
							</span>
						) }
					</h3>
					<a
						href={ provider.docsUrl }
						target="_blank"
						rel="noopener noreferrer"
						className="gratis-ai-agent-provider-card__docs-link"
					>
						{ __( 'Get API key', 'gratis-ai-agent' ) } ↗
					</a>
				</div>
			</CardHeader>
			<CardBody>
				<p className="gratis-ai-agent-provider-card__description">
					{ provider.description }
				</p>

				{ notice && (
					<Notice
						status={ notice.status }
						isDismissible
						onDismiss={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }

				<div className="gratis-ai-agent-provider-card__key-row">
					<TextControl
						label={
							keyConfigured
								? __( 'Replace API key', 'gratis-ai-agent' )
								: __( 'API key', 'gratis-ai-agent' )
						}
						type="password"
						value={ apiKey }
						onChange={ setApiKey }
						placeholder={
							keyConfigured
								? __(
										'(key saved — enter new key to replace)',
										'gratis-ai-agent'
								  )
								: provider.keyPlaceholder
						}
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="gratis-ai-agent-provider-card__actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving || testing || ! apiKey.trim() }
					>
						{ saving ? (
							<Spinner />
						) : (
							__( 'Save Key', 'gratis-ai-agent' )
						) }
					</Button>

					<Button
						variant="secondary"
						onClick={ handleTest }
						isBusy={ testing }
						disabled={
							saving ||
							testing ||
							( ! apiKey.trim() && ! keyConfigured )
						}
					>
						{ testing ? (
							<Spinner />
						) : (
							__( 'Test Connection', 'gratis-ai-agent' )
						) }
					</Button>

					{ keyConfigured && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ handleRemove }
							disabled={ saving || testing }
						>
							{ __( 'Remove Key', 'gratis-ai-agent' ) }
						</Button>
					) }
				</div>
			</CardBody>
		</Card>
	);
}

/**
 * Providers manager — configure API keys for OpenAI, Anthropic, and Google.
 *
 * @param {Object} props
 * @param {Object} props.providerKeys Map of provider ID → boolean (has key).
 */
export default function ProvidersManager( { providerKeys = {} } ) {
	return (
		<div className="gratis-ai-agent-providers-manager">
			<p className="gratis-ai-agent-providers-manager__intro">
				{ __(
					'Configure API keys for the official AI providers. Keys are stored securely in the WordPress database and never exposed through the settings API.',
					'gratis-ai-agent'
				) }
			</p>

			<div className="gratis-ai-agent-providers-manager__grid">
				{ PROVIDERS.map( ( provider ) => (
					<ProviderCard
						key={ provider.id }
						provider={ provider }
						hasKey={ !! providerKeys[ provider.id ] }
					/>
				) ) }
			</div>
		</div>
	);
}
