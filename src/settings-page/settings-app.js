/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	TabPanel,
	TextControl,
	TextareaControl,
	RangeControl,
	ToggleControl,
	Button,
	Notice,
	Spinner,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	useAvailableVoices,
	isTTSSupported,
} from '../components/use-text-to-speech';

/**
 * Internal dependencies
 */
import './style.css';
import STORE_NAME from '../store';
import ErrorBoundary from '../components/error-boundary';
import ModelPricingSelector from '../components/model-pricing-selector';
import MemoryManager from './memory-manager';
import SkillManager from './skill-manager';
import KnowledgeManager from './knowledge-manager';
import UsageDashboard from './usage-dashboard';
import CustomToolsManager from './custom-tools-manager';
import AutomationsManager from './automations-manager';
import EventsManager from './events-manager';
import RolePermissionsManager from './role-permissions-manager';
import AgentBuilder from './agent-builder';
import BrandingManager from './branding-manager';
import AbilitiesManager from './abilities-manager';
import ProviderTraceViewer from './provider-trace-viewer';

/**
 *
 */
export default function SettingsApp() {
	const {
		fetchSettings,
		fetchProviders,
		saveSettings,
		setTtsEnabled,
		setTtsVoiceURI,
		setTtsRate,
		setTtsPitch,
	} = useDispatch( STORE_NAME );
	const {
		settings,
		settingsLoaded,
		providers,
		ttsEnabled,
		ttsVoiceURI,
		ttsRate,
		ttsPitch,
	} = useSelect(
		( select ) => ( {
			settings: select( STORE_NAME ).getSettings(),
			settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
			providers: select( STORE_NAME ).getProviders(),
			ttsEnabled: select( STORE_NAME ).isTtsEnabled(),
			ttsVoiceURI: select( STORE_NAME ).getTtsVoiceURI(),
			ttsRate: select( STORE_NAME ).getTtsRate(),
			ttsPitch: select( STORE_NAME ).getTtsPitch(),
		} ),
		[]
	);

	// Available TTS voices (loaded asynchronously in some browsers).
	const ttsVoices = useAvailableVoices();

	const [ local, setLocal ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ abilities, setAbilities ] = useState( [] );
	const [ activeTab, setActiveTab ] = useState( 'general' );

	// Scroll affordance: ref to the wrapper div, state for fade indicators.
	const tabsWrapperRef = useRef( null );
	const [ hasScrollLeft, setHasScrollLeft ] = useState( false );
	const [ hasScrollRight, setHasScrollRight ] = useState( false );

	// Tabs that manage their own save actions — hide the global Save Settings button.
	const SELF_SAVING_TABS = [ 'access-branding', 'provider-trace' ];

	// Google Analytics integration state.
	const [ gaPropertyId, setGaPropertyId ] = useState( '' );
	const [ gaServiceJson, setGaServiceJson ] = useState( '' );
	const [ gaStatus, setGaStatus ] = useState( null ); // { has_credentials, property_id, has_service_key }
	const [ gaSaving, setGaSaving ] = useState( false );
	const [ gaNotice, setGaNotice ] = useState( null );

	// Brave Search API key state.
	const [ braveApiKey, setBraveApiKey ] = useState( '' );
	const [ braveConfigured, setBraveConfigured ] = useState( false );
	const [ braveSaving, setBraveSaving ] = useState( false );
	const [ braveNotice, setBraveNotice ] = useState( null );

	useEffect( () => {
		fetchSettings();
		fetchProviders();
		// Fetch abilities list.
		apiFetch( { path: '/gratis-ai-agent/v1/abilities' } )
			.then( setAbilities )
			.catch( () => {} );
		// Fetch Google Analytics credential status.
		apiFetch( { path: '/gratis-ai-agent/v1/settings/google-analytics' } )
			.then( ( data ) => {
				setGaStatus( data );
				if ( data?.property_id ) {
					setGaPropertyId( data.property_id );
				}
			} )
			.catch( () => {} );
		// Fetch Brave Search key status from the general settings response.
		apiFetch( { path: '/gratis-ai-agent/v1/settings' } )
			.then( ( data ) => {
				setBraveConfigured( !! data?._brave_search_key_configured );
			} )
			.catch( () => {} );
	}, [ fetchSettings, fetchProviders ] );

	const handleGaSave = useCallback( async () => {
		setGaSaving( true );
		setGaNotice( null );
		try {
			const result = await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/google-analytics',
				method: 'POST',
				data: {
					property_id: gaPropertyId,
					service_account_json: gaServiceJson,
				},
			} );
			setGaStatus( {
				has_credentials: true,
				has_property_id: true,
				property_id: result.property_id,
				has_service_key: true,
			} );
			setGaServiceJson( '' ); // Clear the JSON field after saving.
			setGaNotice( {
				status: 'success',
				message: __(
					'Google Analytics credentials saved.',
					'gratis-ai-agent'
				),
			} );
		} catch ( err ) {
			setGaNotice( {
				status: 'error',
				message:
					err?.message ||
					__(
						'Failed to save Google Analytics credentials.',
						'gratis-ai-agent'
					),
			} );
		}
		setGaSaving( false );
	}, [ gaPropertyId, gaServiceJson ] );

	const handleGaClear = useCallback( async () => {
		setGaSaving( true );
		setGaNotice( null );
		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/google-analytics',
				method: 'DELETE',
			} );
			setGaStatus( {
				has_credentials: false,
				has_property_id: false,
				property_id: '',
				has_service_key: false,
			} );
			setGaPropertyId( '' );
			setGaServiceJson( '' );
			setGaNotice( {
				status: 'success',
				message: __(
					'Google Analytics credentials cleared.',
					'gratis-ai-agent'
				),
			} );
		} catch {
			setGaNotice( {
				status: 'error',
				message: __(
					'Failed to clear Google Analytics credentials.',
					'gratis-ai-agent'
				),
			} );
		}
		setGaSaving( false );
	}, [] );

	const handleBraveSave = useCallback( async () => {
		setBraveSaving( true );
		setBraveNotice( null );
		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/brave-search-key',
				method: 'POST',
				data: { api_key: braveApiKey },
			} );
			setBraveConfigured( true );
			setBraveApiKey( '' ); // Clear the field after saving.
			setBraveNotice( {
				status: 'success',
				message: __( 'Brave Search API key saved.', 'gratis-ai-agent' ),
			} );
		} catch ( err ) {
			setBraveNotice( {
				status: 'error',
				message:
					err?.message ||
					__(
						'Failed to save Brave Search API key.',
						'gratis-ai-agent'
					),
			} );
		}
		setBraveSaving( false );
	}, [ braveApiKey ] );

	const handleBraveClear = useCallback( async () => {
		setBraveSaving( true );
		setBraveNotice( null );
		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/settings/brave-search-key',
				method: 'DELETE',
			} );
			setBraveConfigured( false );
			setBraveNotice( {
				status: 'success',
				message: __(
					'Brave Search API key removed.',
					'gratis-ai-agent'
				),
			} );
		} catch {
			setBraveNotice( {
				status: 'error',
				message: __(
					'Failed to remove Brave Search API key.',
					'gratis-ai-agent'
				),
			} );
		}
		setBraveSaving( false );
	}, [] );

	useEffect( () => {
		if ( settings && ! local ) {
			setLocal( { ...settings } );
		}
	}, [ settings, local ] );

	// Attach scroll affordance listener to the tab bar.
	useEffect( () => {
		const wrapper = tabsWrapperRef.current;
		if ( ! wrapper ) {
			return;
		}
		const tabBar = wrapper.querySelector( '.components-tab-panel__tabs' );
		if ( ! tabBar ) {
			return;
		}

		const updateIndicators = () => {
			const { scrollLeft, scrollWidth, clientWidth } = tabBar;
			setHasScrollLeft( scrollLeft > 0 );
			setHasScrollRight( scrollLeft + clientWidth < scrollWidth - 1 );
		};

		// Initial check.
		updateIndicators();

		tabBar.addEventListener( 'scroll', updateIndicators, {
			passive: true,
		} );

		// Re-check on resize (e.g. window resize changes available width).
		const resizeObserver = new ResizeObserver( updateIndicators );
		resizeObserver.observe( tabBar );

		return () => {
			tabBar.removeEventListener( 'scroll', updateIndicators );
			resizeObserver.disconnect();
		};
	} );

	// Scroll the active tab into view whenever activeTab changes.
	useEffect( () => {
		const wrapper = tabsWrapperRef.current;
		if ( ! wrapper ) {
			return;
		}
		const activeButton = wrapper.querySelector(
			`.components-tab-panel__tabs-item.is-active`
		);
		if ( activeButton ) {
			activeButton.scrollIntoView( {
				behavior: 'smooth',
				block: 'nearest',
				inline: 'nearest',
			} );
		}
	}, [ activeTab ] );

	const updateField = useCallback( ( key, value ) => {
		setLocal( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}, [] );

	const handleSave = useCallback( async () => {
		setSaving( true );
		setNotice( null );
		try {
			await saveSettings( local );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'gratis-ai-agent' ),
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: __( 'Failed to save settings.', 'gratis-ai-agent' ),
			} );
		}
		setSaving( false );
	}, [ local, saveSettings ] );

	if ( ! settingsLoaded || ! local ) {
		return (
			<div className="gratis-ai-agent-settings-loading">
				<Spinner />
			</div>
		);
	}

	// Build provider/model options.
	const providerOptions = [
		{ label: __( '(default)', 'gratis-ai-agent' ), value: '' },
		...providers.map( ( p ) => ( { label: p.name, value: p.id } ) ),
	];

	const selectedProvider = providers.find(
		( p ) => p.id === local.default_provider
	);

	// Consolidated tab list. Providers are configured network-wide via the
	// WP Multisite WaaS Connectors page, so no Providers tab is rendered here.
	const tabs = [
		{
			name: 'general',
			title: __( 'General', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'memory-knowledge',
			title: __( 'Memory & Knowledge', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'skills',
			title: __( 'Skills', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'tools',
			title: __( 'Tools', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'automations',
			title: __( 'Automations', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'agents',
			title: __( 'Agents', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'access-branding',
			title: __( 'Access & Branding', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'usage',
			title: __( 'Usage', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'provider-trace',
			title: __( 'Provider Trace', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'advanced',
			title: __( 'Advanced', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
	];

	const scrollWrapperClasses = [
		'gratis-ai-agent-tabs-scroll-wrapper',
		hasScrollLeft ? 'has-scroll-left' : '',
		hasScrollRight ? 'has-scroll-right' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className="gratis-ai-agent-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<Notice
				status="info"
				isDismissible={ false }
				className="gratis-ai-agent-providers-link-notice"
			>
				{ __(
					'Provider API keys are configured on the Connectors page.',
					'gratis-ai-agent'
				) }{ ' ' }
				<a
					href={
						window.gratisAiAgentData?.connectorsUrl ||
						'options-connectors.php'
					}
				>
					{ __( 'Open Connectors →', 'gratis-ai-agent' ) }
				</a>
			</Notice>
			<div ref={ tabsWrapperRef } className={ scrollWrapperClasses }>
				<TabPanel tabs={ tabs } onSelect={ setActiveTab }>
					{ ( tab ) => {
						switch ( tab.name ) {
							case 'general':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __( 'Model', 'gratis-ai-agent' ) }
										</h3>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-default-provider">
															{ __(
																'Default Provider',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<SelectControl
															id="gratis-default-provider"
															value={
																local.default_provider
															}
															options={
																providerOptions
															}
															onChange={ ( v ) =>
																updateField(
																	'default_provider',
																	v
																)
															}
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-default-model">
															{ __(
																'Default Model',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<ModelPricingSelector
															id="gratis-default-model"
															value={
																local.default_model
															}
															models={
																selectedProvider?.models ||
																[]
															}
															providerName={
																selectedProvider?.name ||
																''
															}
															onChange={ ( v ) =>
																updateField(
																	'default_model',
																	v
																)
															}
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-max-iterations">
															{ __(
																'Max Iterations',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-max-iterations"
															type="number"
															min={ 1 }
															max={ 50 }
															value={
																local.max_iterations
															}
															onChange={ ( v ) =>
																updateField(
																	'max_iterations',
																	parseInt(
																		v,
																		10
																	) || 10
																)
															}
															help={ __(
																'Maximum tool-call iterations per request.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Chat Behaviour',
												'gratis-ai-agent'
											) }
										</h3>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-greeting-message">
															{ __(
																'Greeting Message',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextareaControl
															id="gratis-greeting-message"
															value={
																local.greeting_message
															}
															onChange={ ( v ) =>
																updateField(
																	'greeting_message',
																	v
																)
															}
															placeholder={
																settings
																	?._defaults
																	?.greeting_message ||
																''
															}
															help={ __(
																'Shown in the chat before the first message. Leave empty for the default.',
																'gratis-ai-agent'
															) }
															rows={ 2 }
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-keyboard-shortcut">
															{ __(
																'Keyboard Shortcut',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-keyboard-shortcut"
															value={
																local.keyboard_shortcut ??
																'alt+a'
															}
															onChange={ ( v ) =>
																updateField(
																	'keyboard_shortcut',
																	v
																)
															}
															help={ __(
																'Shortcut to open/close the floating chat widget. Use modifier keys joined by "+", e.g. "alt+a" or "ctrl+shift+k". Leave empty to disable.',
																'gratis-ai-agent'
															) }
															placeholder="alt+a"
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														{ __(
															'YOLO Mode',
															'gratis-ai-agent'
														) }
													</th>
													<td>
														<div className="gratis-ai-agent-settings-yolo-section">
															<ToggleControl
																label={ __(
																	'Skip all confirmation dialogs',
																	'gratis-ai-agent'
																) }
																checked={
																	!! local.yolo_mode
																}
																onChange={ (
																	v
																) =>
																	updateField(
																		'yolo_mode',
																		v
																	)
																}
																help={ __(
																	'Destructive actions will run without prompting. Use with caution.',
																	'gratis-ai-agent'
																) }
																__nextHasNoMarginBottom
															/>
															{ local.yolo_mode && (
																<div className="gratis-ai-agent-yolo-warning">
																	{ __(
																		'Warning: YOLO mode is active. All tool confirmations are skipped automatically. Destructive operations will execute without asking.',
																		'gratis-ai-agent'
																	) }
																</div>
															) }
														</div>
													</td>
												</tr>
												<tr>
													<th scope="row">
														{ __(
															'Frontend Widget',
															'gratis-ai-agent'
														) }
													</th>
													<td>
														<ToggleControl
															label={ __(
																'Show on public-facing pages',
																'gratis-ai-agent'
															) }
															checked={
																!! local.show_on_frontend
															}
															onChange={ ( v ) =>
																updateField(
																	'show_on_frontend',
																	v
																)
															}
															help={ __(
																'Display the floating chat widget on public-facing pages for logged-in administrators.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														{ __(
															'Token Costs',
															'gratis-ai-agent'
														) }
													</th>
													<td>
														<ToggleControl
															label={ __(
																'Show token count and estimated cost',
																'gratis-ai-agent'
															) }
															checked={
																local.show_token_costs !==
																false
															}
															onChange={ ( v ) =>
																updateField(
																	'show_token_costs',
																	v
																)
															}
															help={ __(
																'Display token count and estimated cost below the chat input and after each AI response.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'System Prompt',
												'gratis-ai-agent'
											) }
										</h3>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-system-prompt">
															{ __(
																'Custom System Prompt',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextareaControl
															id="gratis-system-prompt"
															value={
																local.system_prompt
															}
															onChange={ ( v ) =>
																updateField(
																	'system_prompt',
																	v
																)
															}
															placeholder={
																settings
																	?._defaults
																	?.system_prompt ||
																''
															}
															rows={ 12 }
															help={ __(
																'Leave empty to use the built-in default shown above. Memories are appended automatically.',
																'gratis-ai-agent'
															) }
														/>
													</td>
												</tr>
											</tbody>
										</table>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'AI Image Generation',
												'gratis-ai-agent'
											) }
										</h3>
										<p className="description">
											{ __(
												'Settings for the Generate AI Image ability (DALL-E 3). Requires an OpenAI API key configured in the Providers tab.',
												'gratis-ai-agent'
											) }
										</p>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-image-size">
															{ __(
																'Default Image Size',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<SelectControl
															id="gratis-image-size"
															value={
																local.image_generation_size ||
																'1024x1024'
															}
															options={ [
																{
																	label: __(
																		'Square (1024×1024)',
																		'gratis-ai-agent'
																	),
																	value: '1024x1024',
																},
																{
																	label: __(
																		'Landscape (1792×1024)',
																		'gratis-ai-agent'
																	),
																	value: '1792x1024',
																},
																{
																	label: __(
																		'Portrait (1024×1792)',
																		'gratis-ai-agent'
																	),
																	value: '1024x1792',
																},
															] }
															onChange={ ( v ) =>
																updateField(
																	'image_generation_size',
																	v
																)
															}
															help={ __(
																'Default dimensions for generated images. Can be overridden per request.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-image-quality">
															{ __(
																'Default Image Quality',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<SelectControl
															id="gratis-image-quality"
															value={
																local.image_generation_quality ||
																'standard'
															}
															options={ [
																{
																	label: __(
																		'Standard',
																		'gratis-ai-agent'
																	),
																	value: 'standard',
																},
																{
																	label: __(
																		'HD (higher detail, higher cost)',
																		'gratis-ai-agent'
																	),
																	value: 'hd',
																},
															] }
															onChange={ ( v ) =>
																updateField(
																	'image_generation_quality',
																	v
																)
															}
															help={ __(
																'HD produces finer details and greater consistency but costs more per image.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-image-style">
															{ __(
																'Default Image Style',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<SelectControl
															id="gratis-image-style"
															value={
																local.image_generation_style ||
																'vivid'
															}
															options={ [
																{
																	label: __(
																		'Vivid (hyper-real, dramatic)',
																		'gratis-ai-agent'
																	),
																	value: 'vivid',
																},
																{
																	label: __(
																		'Natural (subdued, realistic)',
																		'gratis-ai-agent'
																	),
																	value: 'natural',
																},
															] }
															onChange={ ( v ) =>
																updateField(
																	'image_generation_style',
																	v
																)
															}
															help={ __(
																'Vivid is hyper-real and dramatic; Natural is more subdued and realistic.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Spending Limits',
												'gratis-ai-agent'
											) }
										</h3>
										<p className="description">
											{ __(
												'Set daily and monthly budget caps to prevent runaway API costs. Spend is estimated from the usage log.',
												'gratis-ai-agent'
											) }
										</p>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-budget-daily">
															{ __(
																'Daily Budget Cap (USD)',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-budget-daily"
															type="number"
															min={ 0 }
															step={ 0.01 }
															value={
																local.budget_daily_cap ??
																''
															}
															onChange={ ( v ) =>
																updateField(
																	'budget_daily_cap',
																	v === ''
																		? 0
																		: parseFloat(
																				v
																		  ) || 0
																)
															}
															placeholder="0.00"
															help={ __(
																'Maximum estimated spend per day in USD. Set to 0 for unlimited.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-budget-monthly">
															{ __(
																'Monthly Budget Cap (USD)',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-budget-monthly"
															type="number"
															min={ 0 }
															step={ 0.01 }
															value={
																local.budget_monthly_cap ??
																''
															}
															onChange={ ( v ) =>
																updateField(
																	'budget_monthly_cap',
																	v === ''
																		? 0
																		: parseFloat(
																				v
																		  ) || 0
																)
															}
															placeholder="0.00"
															help={ __(
																'Maximum estimated spend per month in USD. Set to 0 for unlimited.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-budget-warning-threshold">
															{ __(
																'Warning Threshold (%)',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<RangeControl
															id="gratis-budget-warning-threshold"
															value={
																local.budget_warning_threshold ??
																80
															}
															onChange={ ( v ) =>
																updateField(
																	'budget_warning_threshold',
																	v
																)
															}
															min={ 50 }
															max={ 99 }
															step={ 1 }
															help={ __(
																'Show a warning banner when spend reaches this percentage of the cap.',
																'gratis-ai-agent'
															) }
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-budget-exceeded-action">
															{ __(
																'Action When Budget Exceeded',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<SelectControl
															id="gratis-budget-exceeded-action"
															value={
																local.budget_exceeded_action ||
																'pause'
															}
															options={ [
																{
																	label: __(
																		'Pause — block new requests',
																		'gratis-ai-agent'
																	),
																	value: 'pause',
																},
																{
																	label: __(
																		'Warn — show warning but allow',
																		'gratis-ai-agent'
																	),
																	value: 'warn',
																},
															] }
															onChange={ ( v ) =>
																updateField(
																	'budget_exceeded_action',
																	v
																)
															}
															help={ __(
																'"Pause" stops all new AI requests until the period resets. "Warn" shows a banner but still allows requests.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Text-to-Speech',
												'gratis-ai-agent'
											) }
										</h3>
										{ ! isTTSSupported && (
											<p className="description">
												{ __(
													'Text-to-speech is not supported in this browser.',
													'gratis-ai-agent'
												) }
											</p>
										) }
										{ isTTSSupported && (
											<table className="form-table gratis-ai-agent-form-table">
												<tbody>
													<tr>
														<th scope="row">
															{ __(
																'Text-to-Speech',
																'gratis-ai-agent'
															) }
														</th>
														<td>
															<ToggleControl
																label={ __(
																	'Read AI responses aloud automatically',
																	'gratis-ai-agent'
																) }
																checked={
																	ttsEnabled
																}
																onChange={
																	setTtsEnabled
																}
																help={ __(
																	'Use the speaker button in the chat header to toggle on the fly.',
																	'gratis-ai-agent'
																) }
																__nextHasNoMarginBottom
															/>
														</td>
													</tr>
													{ ttsVoices.length > 0 && (
														<tr>
															<th scope="row">
																<label htmlFor="gratis-tts-voice">
																	{ __(
																		'Voice',
																		'gratis-ai-agent'
																	) }
																</label>
															</th>
															<td>
																<SelectControl
																	id="gratis-tts-voice"
																	value={
																		ttsVoiceURI
																	}
																	options={ [
																		{
																			label: __(
																				'(Browser default)',
																				'gratis-ai-agent'
																			),
																			value: '',
																		},
																		...ttsVoices.map(
																			(
																				v
																			) => ( {
																				label: `${ v.name } (${ v.lang })`,
																				value: v.voiceURI,
																			} )
																		),
																	] }
																	onChange={
																		setTtsVoiceURI
																	}
																	help={ __(
																		'Select the voice used for speech synthesis.',
																		'gratis-ai-agent'
																	) }
																	__nextHasNoMarginBottom
																/>
															</td>
														</tr>
													) }
													<tr>
														<th scope="row">
															<label htmlFor="gratis-tts-rate">
																{ __(
																	'Speech Rate',
																	'gratis-ai-agent'
																) }
															</label>
														</th>
														<td>
															<RangeControl
																id="gratis-tts-rate"
																value={
																	ttsRate
																}
																onChange={
																	setTtsRate
																}
																min={ 0.5 }
																max={ 2 }
																step={ 0.1 }
																help={ __(
																	'Speed of speech. 1 is normal speed.',
																	'gratis-ai-agent'
																) }
															/>
														</td>
													</tr>
													<tr>
														<th scope="row">
															<label htmlFor="gratis-tts-pitch">
																{ __(
																	'Pitch',
																	'gratis-ai-agent'
																) }
															</label>
														</th>
														<td>
															<RangeControl
																id="gratis-tts-pitch"
																value={
																	ttsPitch
																}
																onChange={
																	setTtsPitch
																}
																min={ 0 }
																max={ 2 }
																step={ 0.1 }
																help={ __(
																	'Pitch of speech. 1 is normal pitch.',
																	'gratis-ai-agent'
																) }
															/>
														</td>
													</tr>
												</tbody>
											</table>
										) }
									</div>
								);

							case 'memory-knowledge':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Memory',
												'gratis-ai-agent'
											) }
										</h3>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														{ __(
															'Auto-Memory',
															'gratis-ai-agent'
														) }
													</th>
													<td>
														<ToggleControl
															label={ __(
																'Proactively save and recall memories',
																'gratis-ai-agent'
															) }
															checked={
																local.auto_memory
															}
															onChange={ ( v ) =>
																updateField(
																	'auto_memory',
																	v
																)
															}
															help={ __(
																'When enabled, the AI can proactively save and recall memories.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>
										<ErrorBoundary
											label={ __(
												'Memory manager',
												'gratis-ai-agent'
											) }
										>
											<MemoryManager />
										</ErrorBoundary>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Knowledge Base',
												'gratis-ai-agent'
											) }
										</h3>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														{ __(
															'Knowledge Base',
															'gratis-ai-agent'
														) }
													</th>
													<td>
														<ToggleControl
															label={ __(
																'Enable knowledge base search',
																'gratis-ai-agent'
															) }
															checked={
																local.knowledge_enabled
															}
															onChange={ ( v ) =>
																updateField(
																	'knowledge_enabled',
																	v
																)
															}
															help={ __(
																'When enabled, the AI can search indexed documents and posts for relevant context.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														{ __(
															'Auto-Index',
															'gratis-ai-agent'
														) }
													</th>
													<td>
														<ToggleControl
															label={ __(
																'Index posts on publish or update',
																'gratis-ai-agent'
															) }
															checked={
																local.knowledge_auto_index
															}
															onChange={ ( v ) =>
																updateField(
																	'knowledge_auto_index',
																	v
																)
															}
															help={ __(
																'Automatically index posts when they are published or updated.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>
										<ErrorBoundary
											label={ __(
												'Knowledge manager',
												'gratis-ai-agent'
											) }
										>
											<KnowledgeManager />
										</ErrorBoundary>
									</div>
								);

							case 'skills':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Skills',
												'gratis-ai-agent'
											) }
										</h3>
										<ErrorBoundary
											label={ __(
												'Skill manager',
												'gratis-ai-agent'
											) }
										>
											<SkillManager />
										</ErrorBoundary>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Abilities',
												'gratis-ai-agent'
											) }
										</h3>
										<p className="description">
											{ __(
												'Control how each tool behaves. "Auto" runs without asking, "Confirm" pauses to ask before running, "Disabled" prevents the tool from being used.',
												'gratis-ai-agent'
											) }
										</p>
										<AbilitiesManager
											abilities={ abilities }
											toolPermissions={
												local.tool_permissions || {}
											}
											onPermChange={ ( name, value ) => {
												const updated = {
													...( local.tool_permissions ||
														{} ),
												};
												if ( value === 'auto' ) {
													delete updated[ name ];
												} else {
													updated[ name ] = value;
												}
												updateField(
													'tool_permissions',
													updated
												);
											} }
										/>
									</div>
								);

							case 'tools':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Custom Tools',
												'gratis-ai-agent'
											) }
										</h3>
										<ErrorBoundary
											label={ __(
												'Custom tools manager',
												'gratis-ai-agent'
											) }
										>
											<CustomToolsManager />
										</ErrorBoundary>
									</div>
								);

							case 'automations':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Automations',
												'gratis-ai-agent'
											) }
										</h3>
										<ErrorBoundary
											label={ __(
												'Automations manager',
												'gratis-ai-agent'
											) }
										>
											<AutomationsManager />
										</ErrorBoundary>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Events',
												'gratis-ai-agent'
											) }
										</h3>
										<ErrorBoundary
											label={ __(
												'Events manager',
												'gratis-ai-agent'
											) }
										>
											<EventsManager />
										</ErrorBoundary>
									</div>
								);

							case 'agents':
								return (
									<div className="gratis-ai-agent-settings-section">
										<ErrorBoundary
											label={ __(
												'Agent builder',
												'gratis-ai-agent'
											) }
										>
											<AgentBuilder />
										</ErrorBoundary>
									</div>
								);

							case 'access-branding':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Role Permissions',
												'gratis-ai-agent'
											) }
										</h3>
										<ErrorBoundary
											label={ __(
												'Role permissions manager',
												'gratis-ai-agent'
											) }
										>
											<RolePermissionsManager />
										</ErrorBoundary>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Branding',
												'gratis-ai-agent'
											) }
										</h3>
										<BrandingManager
											local={ local }
											updateField={ updateField }
										/>
									</div>
								);

							case 'usage':
								return (
									<div className="gratis-ai-agent-settings-section">
										<ErrorBoundary
											label={ __(
												'Usage dashboard',
												'gratis-ai-agent'
											) }
										>
											<UsageDashboard />
										</ErrorBoundary>
									</div>
								);

							case 'provider-trace':
								return (
									<div className="gratis-ai-agent-settings-section">
										<ErrorBoundary
											label={ __(
												'Provider trace viewer',
												'gratis-ai-agent'
											) }
										>
											<ProviderTraceViewer />
										</ErrorBoundary>
									</div>
								);

							case 'advanced':
								return (
									<div className="gratis-ai-agent-settings-section">
										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Model Parameters',
												'gratis-ai-agent'
											) }
										</h3>
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-temperature">
															{ __(
																'Temperature',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<RangeControl
															id="gratis-temperature"
															value={
																local.temperature
															}
															onChange={ ( v ) =>
																updateField(
																	'temperature',
																	v
																)
															}
															min={ 0 }
															max={ 1 }
															step={ 0.1 }
															help={ __(
																'Higher = more creative, lower = more deterministic.',
																'gratis-ai-agent'
															) }
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-max-output-tokens">
															{ __(
																'Max Output Tokens',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-max-output-tokens"
															type="number"
															min={ 256 }
															max={ 32768 }
															value={
																local.max_output_tokens
															}
															onChange={ ( v ) =>
																updateField(
																	'max_output_tokens',
																	parseInt(
																		v,
																		10
																	) || 4096
																)
															}
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-context-window">
															{ __(
																'Default Context Window',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-context-window"
															type="number"
															min={ 4096 }
															max={ 2000000 }
															value={
																local.context_window_default
															}
															onChange={ ( v ) =>
																updateField(
																	'context_window_default',
																	parseInt(
																		v,
																		10
																	) || 128000
																)
															}
															help={ __(
																'Used as fallback when model context size is unknown.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>

										<h3 className="gratis-ai-agent-settings-section-title">
											{ __(
												'Integrations',
												'gratis-ai-agent'
											) }
										</h3>
										<h4 className="gratis-ai-agent-settings-subsection-title">
											{ __(
												'Google Analytics 4',
												'gratis-ai-agent'
											) }
										</h4>
										<p className="description">
											{ __(
												'Connect to Google Analytics 4 to enable traffic analysis in the AI chat. You need a GA4 property ID and a Google service account JSON key with the "Viewer" role on your GA4 property.',
												'gratis-ai-agent'
											) }
										</p>
										{ gaStatus?.has_credentials && (
											<Notice
												status="success"
												isDismissible={ false }
											>
												{ __(
													'Google Analytics is connected.',
													'gratis-ai-agent'
												) }{ ' ' }
												{ gaStatus.property_id && (
													<strong>
														{ __(
															'Property ID:',
															'gratis-ai-agent'
														) }{ ' ' }
														{ gaStatus.property_id }
													</strong>
												) }
											</Notice>
										) }
										{ gaNotice && (
											<Notice
												status={ gaNotice.status }
												isDismissible
												onDismiss={ () =>
													setGaNotice( null )
												}
											>
												{ gaNotice.message }
											</Notice>
										) }
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-ga-property-id">
															{ __(
																'GA4 Property ID',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-ga-property-id"
															value={
																gaPropertyId
															}
															onChange={
																setGaPropertyId
															}
															placeholder="123456789"
															help={ __(
																'Your numeric GA4 property ID. Found in Google Analytics > Admin > Property Settings.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-ga-service-json">
															{ __(
																'Service Account JSON Key',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextareaControl
															id="gratis-ga-service-json"
															value={
																gaServiceJson
															}
															onChange={
																setGaServiceJson
															}
															placeholder={ __(
																'Paste the contents of your service account JSON key file here.',
																'gratis-ai-agent'
															) }
															help={ __(
																'Download from Google Cloud Console > IAM & Admin > Service Accounts > Keys. Grant the service account "Viewer" access in GA4 Admin > Property Access Management.',
																'gratis-ai-agent'
															) }
															rows={ 6 }
														/>
													</td>
												</tr>
											</tbody>
										</table>
										<div className="gratis-ai-agent-settings-row-actions">
											<Button
												variant="primary"
												onClick={ handleGaSave }
												isBusy={ gaSaving }
												disabled={
													gaSaving ||
													! gaPropertyId ||
													! gaServiceJson
												}
											>
												{ __(
													'Save GA Credentials',
													'gratis-ai-agent'
												) }
											</Button>
											{ gaStatus?.has_credentials && (
												<Button
													variant="secondary"
													onClick={ handleGaClear }
													isBusy={ gaSaving }
													disabled={ gaSaving }
												>
													{ __(
														'Disconnect',
														'gratis-ai-agent'
													) }
												</Button>
											) }
										</div>

										<h4 className="gratis-ai-agent-settings-subsection-title">
											{ __(
												'Internet Search (Brave Search API)',
												'gratis-ai-agent'
											) }
										</h4>
										<p className="description">
											{ __(
												'Enable richer internet search results by connecting a Brave Search API key. Without a key, the agent uses DuckDuckGo instant answers (free, no setup required). Get a free Brave Search API key at brave.com/search/api/',
												'gratis-ai-agent'
											) }
										</p>
										{ braveConfigured && (
											<Notice
												status="success"
												isDismissible={ false }
											>
												{ __(
													'Brave Search API key is configured. The agent will use Brave Search for internet searches.',
													'gratis-ai-agent'
												) }
											</Notice>
										) }
										{ ! braveConfigured && (
											<Notice
												status="info"
												isDismissible={ false }
											>
												{ __(
													'No Brave Search API key configured. The agent will use DuckDuckGo instant answers (zero-config fallback).',
													'gratis-ai-agent'
												) }
											</Notice>
										) }
										{ braveNotice && (
											<Notice
												status={ braveNotice.status }
												isDismissible
												onDismiss={ () =>
													setBraveNotice( null )
												}
											>
												{ braveNotice.message }
											</Notice>
										) }
										<table className="form-table gratis-ai-agent-form-table">
											<tbody>
												<tr>
													<th scope="row">
														<label htmlFor="gratis-brave-api-key">
															{ __(
																'Brave Search API Key',
																'gratis-ai-agent'
															) }
														</label>
													</th>
													<td>
														<TextControl
															id="gratis-brave-api-key"
															type="password"
															value={
																braveApiKey
															}
															onChange={
																setBraveApiKey
															}
															placeholder={
																braveConfigured
																	? __(
																			'Key saved — enter a new key to replace it',
																			'gratis-ai-agent'
																	  )
																	: 'BSA...'
															}
															help={ __(
																'Get a free API key at brave.com/search/api/ — the free tier includes 2,000 queries/month.',
																'gratis-ai-agent'
															) }
															__nextHasNoMarginBottom
														/>
													</td>
												</tr>
											</tbody>
										</table>
										<div className="gratis-ai-agent-settings-row-actions">
											<Button
												variant="primary"
												onClick={ handleBraveSave }
												isBusy={ braveSaving }
												disabled={
													braveSaving || ! braveApiKey
												}
											>
												{ __(
													'Save Brave API Key',
													'gratis-ai-agent'
												) }
											</Button>
											{ braveConfigured && (
												<Button
													variant="secondary"
													onClick={ handleBraveClear }
													isBusy={ braveSaving }
													disabled={ braveSaving }
												>
													{ __(
														'Remove Key',
														'gratis-ai-agent'
													) }
												</Button>
											) }
										</div>
									</div>
								);

							default:
								return null;
						}
					} }
				</TabPanel>
			</div>
			{ ! SELF_SAVING_TABS.includes( activeTab ) && (
				<div className="gratis-ai-agent-settings-actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving }
					>
						{ __( 'Save Settings', 'gratis-ai-agent' ) }
					</Button>
				</div>
			) }
		</div>
	);
}
