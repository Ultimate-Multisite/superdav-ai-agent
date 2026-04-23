/**
 * Connectors Route — polyfill Connectors admin page for WP 6.9.
 *
 * On WordPress 7.0+ (or WP 6.9 with Gutenberg 22.8.0+), provider API keys
 * are managed on the official Connectors page at
 * options-general.php?page=options-connectors-wp-admin. This route provides
 * an equivalent UI for WordPress 6.9 installations without Gutenberg.
 *
 * Features:
 * - Lists AI provider connector plugins (Anthropic, OpenAI, Google AI)
 * - Shows plugin install/activation status with action buttons
 * - Allows API key entry and storage (saved as connectors_ai_{id}_api_key)
 * - Detects WP 7.0+ and shows a redirect link to the native page
 *
 * Plugin install/activate calls the native /wp/v2/plugins REST API.
 * API key management calls /gratis-ai-agent/v1/connectors/{id}/key.
 */

/**
 * WordPress dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
	TextControl,
	Tooltip,
} from '@wordpress/components';
import {
	useCallback,
	useEffect,
	useReducer,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import './connectors-style.css';

// ---------------------------------------------------------------------------
// State helpers
// ---------------------------------------------------------------------------

const INITIAL_STATE = {
	loading: true,
	error: null,
	providers: [],
	hasNative: false,
};

/**
 * State reducer for connectors page.
 *
 * @param {Object} state  Current state.
 * @param {Object} action Dispatched action.
 * @return {Object} Next state.
 */
function reducer( state, action ) {
	switch ( action.type ) {
		case 'FETCH_SUCCESS':
			return {
				...state,
				loading: false,
				providers: action.providers,
				hasNative: action.hasNative,
			};
		case 'FETCH_ERROR':
			return { ...state, loading: false, error: action.error };
		case 'PROVIDER_UPDATED':
			return {
				...state,
				providers: state.providers.map( ( p ) =>
					p.id === action.provider.id
						? { ...p, ...action.provider }
						: p
				),
			};
		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// ProviderCard
// ---------------------------------------------------------------------------

/**
 * Individual AI provider card with install/activate and API key controls.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.provider  Provider data from REST API.
 * @param {Function} props.onRefresh Callback to refresh the provider list.
 * @return {JSX.Element} Provider card element.
 */
function ProviderCard( { provider, onRefresh } ) {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ clearing, setClearing ] = useState( false );
	const [ installing, setInstalling ] = useState( false );
	const [ activating, setActivating ] = useState( false );
	const [ cardNotice, setCardNotice ] = useState( null );

	const clearCardNotice = useCallback( () => setCardNotice( null ), [] );

	// Derive the plugin file identifier for the WP REST Plugins API.
	// The API expects the plugin encoded as "folder%2Ffile" (folder/file without .php).
	const pluginFileEncoded = encodeURIComponent(
		provider.plugin_file.replace( /\.php$/, '' )
	);

	const handleInstall = useCallback( async () => {
		setInstalling( true );
		setCardNotice( null );
		try {
			await apiFetch( {
				path: '/wp/v2/plugins',
				method: 'POST',
				data: {
					slug: provider.plugin_slug,
					status: 'inactive',
				},
			} );
			setCardNotice( {
				status: 'success',
				message: __(
					'Plugin installed. Click Activate to enable it.',
					'gratis-ai-agent'
				),
			} );
			onRefresh();
		} catch ( err ) {
			setCardNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Installation failed.', 'gratis-ai-agent' ),
			} );
		} finally {
			setInstalling( false );
		}
	}, [ provider.plugin_slug, onRefresh ] );

	const handleActivate = useCallback( async () => {
		setActivating( true );
		setCardNotice( null );
		try {
			await apiFetch( {
				path: `/wp/v2/plugins/${ pluginFileEncoded }`,
				method: 'PUT',
				data: { status: 'active' },
			} );
			setCardNotice( {
				status: 'success',
				message: __( 'Plugin activated.', 'gratis-ai-agent' ),
			} );
			onRefresh();
		} catch ( err ) {
			setCardNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Activation failed.', 'gratis-ai-agent' ),
			} );
		} finally {
			setActivating( false );
		}
	}, [ pluginFileEncoded, onRefresh ] );

	const handleSaveKey = useCallback( async () => {
		if ( ! apiKey.trim() ) {
			return;
		}
		setSaving( true );
		setCardNotice( null );
		try {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/connectors/${ provider.id }/key`,
				method: 'POST',
				data: { api_key: apiKey.trim() },
			} );
			setApiKey( '' );
			setCardNotice( {
				status: 'success',
				message: __( 'API key saved.', 'gratis-ai-agent' ),
			} );
			onRefresh();
		} catch ( err ) {
			setCardNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to save API key.', 'gratis-ai-agent' ),
			} );
		} finally {
			setSaving( false );
		}
	}, [ apiKey, provider.id, onRefresh ] );

	const handleClearKey = useCallback( async () => {
		/* eslint-disable no-alert */
		const confirmed = window.confirm(
			__(
				'Clear the saved API key for this provider?',
				'gratis-ai-agent'
			)
		);
		/* eslint-enable no-alert */
		if ( ! confirmed ) {
			return;
		}
		setClearing( true );
		setCardNotice( null );
		try {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/connectors/${ provider.id }/key`,
				method: 'DELETE',
			} );
			setCardNotice( {
				status: 'success',
				message: __( 'API key cleared.', 'gratis-ai-agent' ),
			} );
			onRefresh();
		} catch ( err ) {
			setCardNotice( {
				status: 'error',
				message:
					err?.message ||
					__( 'Failed to clear API key.', 'gratis-ai-agent' ),
			} );
		} finally {
			setClearing( false );
		}
	}, [ provider.id, onRefresh ] );

	const statusBadge = () => {
		if ( provider.active ) {
			return (
				<span className="gratis-ai-connectors__status gratis-ai-connectors__status--active">
					{ __( 'Active', 'gratis-ai-agent' ) }
				</span>
			);
		}
		if ( provider.installed ) {
			return (
				<span className="gratis-ai-connectors__status gratis-ai-connectors__status--inactive">
					{ __( 'Inactive', 'gratis-ai-agent' ) }
				</span>
			);
		}
		return (
			<span className="gratis-ai-connectors__status gratis-ai-connectors__status--not-installed">
				{ __( 'Not Installed', 'gratis-ai-agent' ) }
			</span>
		);
	};

	return (
		<Card className="gratis-ai-connectors__provider-card">
			<CardHeader>
				<div className="gratis-ai-connectors__provider-header">
					<div className="gratis-ai-connectors__provider-title">
						<h3>{ provider.name }</h3>
						{ statusBadge() }
					</div>
					<div className="gratis-ai-connectors__provider-actions">
						{ ! provider.installed && (
							<Button
								variant="secondary"
								onClick={ handleInstall }
								isBusy={ installing }
								disabled={ installing }
							>
								{ installing
									? __( 'Installing…', 'gratis-ai-agent' )
									: __( 'Install', 'gratis-ai-agent' ) }
							</Button>
						) }
						{ provider.installed && ! provider.active && (
							<Button
								variant="primary"
								onClick={ handleActivate }
								isBusy={ activating }
								disabled={ activating }
							>
								{ activating
									? __( 'Activating…', 'gratis-ai-agent' )
									: __( 'Activate', 'gratis-ai-agent' ) }
							</Button>
						) }
					</div>
				</div>
			</CardHeader>
			<CardBody>
				{ cardNotice && (
					<Notice
						status={ cardNotice.status }
						isDismissible
						onRemove={ clearCardNotice }
						className="gratis-ai-connectors__card-notice"
					>
						{ cardNotice.message }
					</Notice>
				) }

				<p className="gratis-ai-connectors__description">
					{ provider.description }
				</p>

				<div className="gratis-ai-connectors__api-key-section">
					<div className="gratis-ai-connectors__api-key-label">
						<strong>{ __( 'API Key', 'gratis-ai-agent' ) }</strong>
						{ provider.configured && provider.masked_key && (
							<span className="gratis-ai-connectors__api-key-configured">
								{ __( 'Configured:', 'gratis-ai-agent' ) }{ ' ' }
								<code>{ provider.masked_key }</code>
								<Tooltip
									text={ __(
										'Clear this API key',
										'gratis-ai-agent'
									) }
								>
									<Button
										variant="link"
										isDestructive
										onClick={ handleClearKey }
										isBusy={ clearing }
										disabled={ clearing }
										className="gratis-ai-connectors__clear-key-btn"
									>
										{ __( 'Clear', 'gratis-ai-agent' ) }
									</Button>
								</Tooltip>
							</span>
						) }
					</div>
					<div className="gratis-ai-connectors__api-key-input">
						<TextControl
							label={ __( 'Enter API Key', 'gratis-ai-agent' ) }
							hideLabelFromVision
							value={ apiKey }
							onChange={ setApiKey }
							type="password"
							placeholder={
								provider.configured
									? __(
											'Enter new key to replace the existing one',
											'gratis-ai-agent'
									  )
									: __(
											'Paste your API key here',
											'gratis-ai-agent'
									  )
							}
							disabled={ saving }
						/>
						<Button
							variant="primary"
							onClick={ handleSaveKey }
							isBusy={ saving }
							disabled={ saving || ! apiKey.trim() }
						>
							{ saving
								? __( 'Saving…', 'gratis-ai-agent' )
								: __( 'Save Key', 'gratis-ai-agent' ) }
						</Button>
					</div>
				</div>
			</CardBody>
		</Card>
	);
}

// ---------------------------------------------------------------------------
// ConnectorsRoute (main export)
// ---------------------------------------------------------------------------

/**
 * Connectors Route Component.
 *
 * Shows AI provider connector cards on WP 6.9. On WP 7.0+, renders a
 * redirect notice pointing to the native Connectors page.
 *
 * @return {JSX.Element} Connectors route element.
 */
export default function ConnectorsRoute() {
	const [ state, dispatch ] = useReducer( reducer, INITIAL_STATE );

	const fetchProviders = useCallback( async () => {
		try {
			const data = await apiFetch( {
				path: '/gratis-ai-agent/v1/connectors',
			} );
			dispatch( {
				type: 'FETCH_SUCCESS',
				providers: data.providers || [],
				hasNative: !! data.wp_has_native,
			} );
		} catch ( err ) {
			dispatch( {
				type: 'FETCH_ERROR',
				error:
					err?.message ||
					__( 'Failed to load connectors.', 'gratis-ai-agent' ),
			} );
		}
	}, [] );

	useEffect( () => {
		fetchProviders();
	}, [ fetchProviders ] );

	const connectorsUrl =
		window.gratisAiAgentData?.connectorsUrl ||
		'options-general.php?page=options-connectors-wp-admin';

	if ( state.loading ) {
		return (
			<div className="gratis-ai-agent-route gratis-ai-agent-route-connectors">
				<div className="gratis-ai-connectors__loading">
					<Spinner />
					<span>
						{ __( 'Loading connectors…', 'gratis-ai-agent' ) }
					</span>
				</div>
			</div>
		);
	}

	if ( state.error ) {
		return (
			<div className="gratis-ai-agent-route gratis-ai-agent-route-connectors">
				<Notice status="error" isDismissible={ false }>
					{ state.error }
				</Notice>
			</div>
		);
	}

	// On WP 7.0+: show a redirect notice instead of the full UI.
	// The native Connectors page handles everything.
	if ( state.hasNative ) {
		return (
			<div className="gratis-ai-agent-route gratis-ai-agent-route-connectors">
				<div className="gratis-ai-connectors__native-redirect">
					<h2>{ __( 'Connectors', 'gratis-ai-agent' ) }</h2>
					<Notice status="info" isDismissible={ false }>
						{ __(
							'WordPress 7.0+ includes a built-in Connectors page for managing AI provider API keys.',
							'gratis-ai-agent'
						) }
					</Notice>
					<Button variant="primary" href={ connectorsUrl }>
						{ __( 'Open Connectors Page →', 'gratis-ai-agent' ) }
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="gratis-ai-agent-route gratis-ai-agent-route-connectors">
			<div className="gratis-ai-connectors__header">
				<h2>{ __( 'Connectors', 'gratis-ai-agent' ) }</h2>
				<p className="gratis-ai-connectors__intro">
					{ __(
						'Install and configure AI provider plugins. Each provider needs an API key to connect Gratis AI Agent to its models.',
						'gratis-ai-agent'
					) }
				</p>
			</div>

			<div className="gratis-ai-connectors__grid">
				{ state.providers.map( ( provider ) => (
					<ProviderCard
						key={ provider.id }
						provider={ provider }
						onRefresh={ fetchProviders }
					/>
				) ) }
			</div>
		</div>
	);
}
