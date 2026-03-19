/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
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
import STORE_NAME from '../store';
import ErrorBoundary from '../components/error-boundary';
import ModelPricingSelector from '../components/model-pricing-selector';
import ProvidersManager from './providers-manager';
import MemoryManager from './memory-manager';
import SkillManager from './skill-manager';
import KnowledgeManager from './knowledge-manager';
import UsageDashboard from './usage-dashboard';
import CustomToolsManager from './custom-tools-manager';
import ToolProfilesManager from './tool-profiles-manager';
import AutomationsManager from './automations-manager';
import EventsManager from './events-manager';
import RolePermissionsManager from './role-permissions-manager';
import AgentBuilder from './agent-builder';
import BrandingManager from './branding-manager';
import AbilitiesManager from './abilities-manager';

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
	const [ activeTab, setActiveTab ] = useState( 'providers' );

	// Tabs that manage their own save actions — hide the global Save Settings button.
	const SELF_SAVING_TABS = [ 'permissions', 'integrations' ];

	// Google Analytics integration state.
	const [ gaPropertyId, setGaPropertyId ] = useState( '' );
	const [ gaServiceJson, setGaServiceJson ] = useState( '' );
	const [ gaStatus, setGaStatus ] = useState( null ); // { has_credentials, property_id, has_service_key }
	const [ gaSaving, setGaSaving ] = useState( false );
	const [ gaNotice, setGaNotice ] = useState( null );

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

	useEffect( () => {
		if ( settings && ! local ) {
			setLocal( { ...settings } );
		}
	}, [ settings, local ] );

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

	const tabs = [
		{
			name: 'providers',
			title: __( 'Providers', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'general',
			title: __( 'General', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'system-prompt',
			title: __( 'System Prompt', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'memory',
			title: __( 'Memory', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'skills',
			title: __( 'Skills', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'knowledge',
			title: __( 'Knowledge', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'custom-tools',
			title: __( 'Custom Tools', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'tool-profiles',
			title: __( 'Tool Profiles', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'automations',
			title: __( 'Automations', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'events',
			title: __( 'Events', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'agents',
			title: __( 'Agents', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'abilities',
			title: __( 'Abilities', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'permissions',
			title: __( 'Permissions', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'usage',
			title: __( 'Usage', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'branding',
			title: __( 'Branding', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'tts',
			title: __( 'Text-to-Speech', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'integrations',
			title: __( 'Integrations', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'advanced',
			title: __( 'Advanced', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
	];

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
			<TabPanel tabs={ tabs } onSelect={ setActiveTab }>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'providers':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ProvidersManager
										providerKeys={
											local?._provider_keys || {}
										}
									/>
								</div>
							);
						case 'general':
							return (
								<div className="gratis-ai-agent-settings-section">
									<SelectControl
										label={ __(
											'Default Provider',
											'gratis-ai-agent'
										) }
										value={ local.default_provider }
										options={ providerOptions }
										onChange={ ( v ) =>
											updateField( 'default_provider', v )
										}
										__nextHasNoMarginBottom
									/>
									<ModelPricingSelector
										label={ __(
											'Default Model',
											'gratis-ai-agent'
										) }
										value={ local.default_model }
										models={
											selectedProvider?.models || []
										}
										providerName={
											selectedProvider?.name || ''
										}
										onChange={ ( v ) =>
											updateField( 'default_model', v )
										}
									/>
									<TextControl
										label={ __(
											'Max Iterations',
											'gratis-ai-agent'
										) }
										type="number"
										min={ 1 }
										max={ 50 }
										value={ local.max_iterations }
										onChange={ ( v ) =>
											updateField(
												'max_iterations',
												parseInt( v, 10 ) || 10
											)
										}
										help={ __(
											'Maximum tool-call iterations per request.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<TextareaControl
										label={ __(
											'Greeting Message',
											'gratis-ai-agent'
										) }
										value={ local.greeting_message }
										onChange={ ( v ) =>
											updateField( 'greeting_message', v )
										}
										placeholder={
											settings?._defaults
												?.greeting_message || ''
										}
										help={ __(
											'Shown in the chat before the first message. Leave empty for the default above.',
											'gratis-ai-agent'
										) }
										rows={ 2 }
									/>
									<TextControl
										label={ __(
											'Keyboard Shortcut',
											'gratis-ai-agent'
										) }
										value={
											local.keyboard_shortcut ?? 'alt+a'
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
									<div className="ai-agent-settings-yolo-section">
										<ToggleControl
											label={ __(
												'YOLO Mode',
												'gratis-ai-agent'
											) }
											checked={ !! local.yolo_mode }
											onChange={ ( v ) =>
												updateField( 'yolo_mode', v )
											}
											help={ __(
												'Skip all confirmation dialogs for tool operations. Use with caution — destructive actions will run without prompting.',
												'gratis-ai-agent'
											) }
											__nextHasNoMarginBottom
										/>
										{ local.yolo_mode && (
											<div className="ai-agent-yolo-warning">
												{ __(
													'Warning: YOLO mode is active. All tool confirmations are skipped automatically. Destructive operations will execute without asking.',
													'gratis-ai-agent'
												) }
											</div>
										) }
									</div>
									<ToggleControl
										label={ __(
											'Show Widget on Frontend',
											'gratis-ai-agent'
										) }
										checked={ !! local.show_on_frontend }
										onChange={ ( v ) =>
											updateField( 'show_on_frontend', v )
										}
										help={ __(
											'Display the floating chat widget on public-facing pages for logged-in administrators.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<ToggleControl
										label={ __(
											'Show Token Costs',
											'gratis-ai-agent'
										) }
										checked={
											local.show_token_costs !== false
										}
										onChange={ ( v ) =>
											updateField( 'show_token_costs', v )
										}
										help={ __(
											'Display token count and estimated cost below the chat input and after each AI response.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<hr />
									<h3>
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
									<SelectControl
										label={ __(
											'Default Image Size',
											'gratis-ai-agent'
										) }
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
									<SelectControl
										label={ __(
											'Default Image Quality',
											'gratis-ai-agent'
										) }
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
									<SelectControl
										label={ __(
											'Default Image Style',
											'gratis-ai-agent'
										) }
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
								</div>
							);

						case 'system-prompt':
							return (
								<div className="gratis-ai-agent-settings-section">
									<TextareaControl
										label={ __(
											'Custom System Prompt',
											'gratis-ai-agent'
										) }
										value={ local.system_prompt }
										onChange={ ( v ) =>
											updateField( 'system_prompt', v )
										}
										placeholder={
											settings?._defaults
												?.system_prompt || ''
										}
										rows={ 12 }
										help={ __(
											'Leave empty to use the built-in default shown above. Memories are appended automatically.',
											'gratis-ai-agent'
										) }
									/>
								</div>
							);

						case 'memory':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ToggleControl
										label={ __(
											'Auto-Memory',
											'gratis-ai-agent'
										) }
										checked={ local.auto_memory }
										onChange={ ( v ) =>
											updateField( 'auto_memory', v )
										}
										help={ __(
											'When enabled, the AI can proactively save and recall memories.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<ErrorBoundary
										label={ __(
											'Memory manager',
											'gratis-ai-agent'
										) }
									>
										<MemoryManager />
									</ErrorBoundary>
								</div>
							);

						case 'skills':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ErrorBoundary
										label={ __(
											'Skill manager',
											'gratis-ai-agent'
										) }
									>
										<SkillManager />
									</ErrorBoundary>
								</div>
							);

						case 'knowledge':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ToggleControl
										label={ __(
											'Enable Knowledge Base',
											'gratis-ai-agent'
										) }
										checked={ local.knowledge_enabled }
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
									<ToggleControl
										label={ __(
											'Auto-Index on Post Save',
											'gratis-ai-agent'
										) }
										checked={ local.knowledge_auto_index }
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

						case 'custom-tools':
							return (
								<div className="gratis-ai-agent-settings-section">
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

						case 'tool-profiles':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ErrorBoundary
										label={ __(
											'Tool profiles manager',
											'gratis-ai-agent'
										) }
									>
										<ToolProfilesManager />
									</ErrorBoundary>
								</div>
							);

						case 'automations':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ErrorBoundary
										label={ __(
											'Automations manager',
											'gratis-ai-agent'
										) }
									>
										<AutomationsManager />
									</ErrorBoundary>
								</div>
							);

						case 'events':
							return (
								<div className="gratis-ai-agent-settings-section">
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

						case 'abilities':
							return (
								<div className="gratis-ai-agent-settings-section">
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

						case 'permissions':
							return (
								<div className="gratis-ai-agent-settings-section">
									<ErrorBoundary
										label={ __(
											'Role permissions manager',
											'gratis-ai-agent'
										) }
									>
										<RolePermissionsManager />
									</ErrorBoundary>
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

						case 'branding':
							return (
								<div className="gratis-ai-agent-settings-section">
									<BrandingManager
										local={ local }
										updateField={ updateField }
									/>
								</div>
							);

						case 'tts':
							return (
								<div className="gratis-ai-agent-settings-section">
									{ ! isTTSSupported && (
										<p className="description">
											{ __(
												'Text-to-speech is not supported in this browser.',
												'gratis-ai-agent'
											) }
										</p>
									) }
									{ isTTSSupported && (
										<>
											<ToggleControl
												label={ __(
													'Enable Text-to-Speech',
													'gratis-ai-agent'
												) }
												checked={ ttsEnabled }
												onChange={ setTtsEnabled }
												help={ __(
													'When enabled, AI responses are read aloud automatically. Use the speaker button in the chat header to toggle on the fly.',
													'gratis-ai-agent'
												) }
												__nextHasNoMarginBottom
											/>
											{ ttsVoices.length > 0 && (
												<SelectControl
													label={ __(
														'Voice',
														'gratis-ai-agent'
													) }
													value={ ttsVoiceURI }
													options={ [
														{
															label: __(
																'(Browser default)',
																'gratis-ai-agent'
															),
															value: '',
														},
														...ttsVoices.map(
															( v ) => ( {
																label: `${ v.name } (${ v.lang })`,
																value: v.voiceURI,
															} )
														),
													] }
													onChange={ setTtsVoiceURI }
													help={ __(
														'Select the voice used for speech synthesis.',
														'gratis-ai-agent'
													) }
													__nextHasNoMarginBottom
												/>
											) }
											<RangeControl
												label={ __(
													'Speech Rate',
													'gratis-ai-agent'
												) }
												value={ ttsRate }
												onChange={ setTtsRate }
												min={ 0.5 }
												max={ 2 }
												step={ 0.1 }
												help={ __(
													'Speed of speech. 1 is normal speed.',
													'gratis-ai-agent'
												) }
											/>
											<RangeControl
												label={ __(
													'Pitch',
													'gratis-ai-agent'
												) }
												value={ ttsPitch }
												onChange={ setTtsPitch }
												min={ 0 }
												max={ 2 }
												step={ 0.1 }
												help={ __(
													'Pitch of speech. 1 is normal pitch.',
													'gratis-ai-agent'
												) }
											/>
										</>
									) }
								</div>
							);

						case 'advanced':
							return (
								<div className="gratis-ai-agent-settings-section">
									<RangeControl
										label={ __(
											'Temperature',
											'gratis-ai-agent'
										) }
										value={ local.temperature }
										onChange={ ( v ) =>
											updateField( 'temperature', v )
										}
										min={ 0 }
										max={ 1 }
										step={ 0.1 }
										help={ __(
											'Higher = more creative, lower = more deterministic.',
											'gratis-ai-agent'
										) }
									/>
									<TextControl
										label={ __(
											'Max Output Tokens',
											'gratis-ai-agent'
										) }
										type="number"
										min={ 256 }
										max={ 32768 }
										value={ local.max_output_tokens }
										onChange={ ( v ) =>
											updateField(
												'max_output_tokens',
												parseInt( v, 10 ) || 4096
											)
										}
										__nextHasNoMarginBottom
									/>
									<TextControl
										label={ __(
											'Default Context Window',
											'gratis-ai-agent'
										) }
										type="number"
										min={ 4096 }
										max={ 2000000 }
										value={ local.context_window_default }
										onChange={ ( v ) =>
											updateField(
												'context_window_default',
												parseInt( v, 10 ) || 128000
											)
										}
										help={ __(
											'Used as fallback when model context size is unknown.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<SelectControl
										label={ __(
											'Tool Discovery Mode',
											'gratis-ai-agent'
										) }
										value={
											local.tool_discovery_mode || 'auto'
										}
										options={ [
											{
												label: __(
													'Auto (enable when tools exceed threshold)',
													'gratis-ai-agent'
												),
												value: 'auto',
											},
											{
												label: __(
													'Always (always use discovery)',
													'gratis-ai-agent'
												),
												value: 'always',
											},
											{
												label: __(
													'Never (load all tools directly)',
													'gratis-ai-agent'
												),
												value: 'never',
											},
										] }
										onChange={ ( v ) =>
											updateField(
												'tool_discovery_mode',
												v
											)
										}
										help={ __(
											'When active, only priority tools are loaded directly. Other tools are discoverable via meta-tools, saving tokens.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									{ ( local.tool_discovery_mode ||
										'auto' ) === 'auto' && (
										<TextControl
											label={ __(
												'Discovery Threshold',
												'gratis-ai-agent'
											) }
											type="number"
											min={ 5 }
											max={ 500 }
											value={
												local.tool_discovery_threshold ||
												20
											}
											onChange={ ( v ) =>
												updateField(
													'tool_discovery_threshold',
													parseInt( v, 10 ) || 20
												)
											}
											help={ __(
												'Enable discovery mode when total registered tools exceed this number.',
												'gratis-ai-agent'
											) }
											__nextHasNoMarginBottom
										/>
									) }
								</div>
							);

						case 'integrations':
							return (
								<div className="gratis-ai-agent-settings-section">
									<h3>
										{ __(
											'Google Analytics 4',
											'gratis-ai-agent'
										) }
									</h3>
									<p>
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
									<TextControl
										label={ __(
											'GA4 Property ID',
											'gratis-ai-agent'
										) }
										value={ gaPropertyId }
										onChange={ setGaPropertyId }
										placeholder="123456789"
										help={ __(
											'Your numeric GA4 property ID. Found in Google Analytics > Admin > Property Settings.',
											'gratis-ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<TextareaControl
										label={ __(
											'Service Account JSON Key',
											'gratis-ai-agent'
										) }
										value={ gaServiceJson }
										onChange={ setGaServiceJson }
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
									<div
										style={ {
											display: 'flex',
											gap: '8px',
											marginTop: '16px',
										} }
									>
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
								</div>
							);

						default:
							return null;
					}
				} }
			</TabPanel>
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
