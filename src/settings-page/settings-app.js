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

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ErrorBoundary from '../components/error-boundary';
import ProvidersManager from './providers-manager';
import MemoryManager from './memory-manager';
import SkillManager from './skill-manager';
import KnowledgeManager from './knowledge-manager';
import UsageDashboard from './usage-dashboard';
import CustomToolsManager from './custom-tools-manager';
import ToolProfilesManager from './tool-profiles-manager';
import AutomationsManager from './automations-manager';
import EventsManager from './events-manager';

/**
 *
 */
export default function SettingsApp() {
	const { fetchSettings, fetchProviders, saveSettings } =
		useDispatch( STORE_NAME );
	const { settings, settingsLoaded, providers } = useSelect(
		( select ) => ( {
			settings: select( STORE_NAME ).getSettings(),
			settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
			providers: select( STORE_NAME ).getProviders(),
		} ),
		[]
	);

	const [ local, setLocal ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ abilities, setAbilities ] = useState( [] );

	useEffect( () => {
		fetchSettings();
		fetchProviders();
		// Fetch abilities list.
		apiFetch( { path: '/gratis-ai-agent/v1/abilities' } )
			.then( setAbilities )
			.catch( () => {} );
	}, [ fetchSettings, fetchProviders ] );

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
	const modelOptions = [
		{ label: __( '(default)', 'gratis-ai-agent' ), value: '' },
		...( selectedProvider?.models || [] ).map( ( m ) => ( {
			label: m.name || m.id,
			value: m.id,
		} ) ),
	];

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
			name: 'abilities',
			title: __( 'Abilities', 'gratis-ai-agent' ),
			className: 'gratis-ai-agent-settings-tab',
		},
		{
			name: 'usage',
			title: __( 'Usage', 'gratis-ai-agent' ),
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
			<TabPanel tabs={ tabs }>
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
									<SelectControl
										label={ __(
											'Default Model',
											'gratis-ai-agent'
										) }
										value={ local.default_model }
										options={ modelOptions }
										onChange={ ( v ) =>
											updateField( 'default_model', v )
										}
										__nextHasNoMarginBottom
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
												'ai-agent'
											) }
											checked={ !! local.yolo_mode }
											onChange={ ( v ) =>
												updateField( 'yolo_mode', v )
											}
											help={ __(
												'Skip all confirmation dialogs for tool operations. Use with caution — destructive actions will run without prompting.',
												'ai-agent'
											) }
											__nextHasNoMarginBottom
										/>
										{ local.yolo_mode && (
											<div className="ai-agent-yolo-warning">
												{ __(
													'Warning: YOLO mode is active. All tool confirmations are skipped automatically. Destructive operations will execute without asking.',
													'ai-agent'
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
											'ai-agent'
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

						case 'abilities':
							return (
								<div className="gratis-ai-agent-settings-section">
									<p className="description">
										{ __(
											'Control how each tool behaves. "Auto" runs without asking, "Confirm" pauses to ask before running, "Disabled" prevents the tool from being used.',
											'gratis-ai-agent'
										) }
									</p>
									{ abilities.length === 0 && (
										<p>
											{ __(
												'No abilities registered.',
												'gratis-ai-agent'
											) }
										</p>
									) }
									{ abilities.map( ( ability ) => {
										const perms =
											local.tool_permissions || {};
										const currentPerm =
											perms[ ability.name ] || 'auto';
										return (
											<SelectControl
												key={ ability.name }
												label={
													ability.label ||
													ability.name
												}
												help={
													ability.description || ''
												}
												value={ currentPerm }
												options={ [
													{
														label: __(
															'Auto (always allow)',
															'gratis-ai-agent'
														),
														value: 'auto',
													},
													{
														label: __(
															'Confirm (ask before use)',
															'gratis-ai-agent'
														),
														value: 'confirm',
													},
													{
														label: __(
															'Disabled',
															'gratis-ai-agent'
														),
														value: 'disabled',
													},
												] }
												onChange={ ( v ) => {
													const updated = {
														...( local.tool_permissions ||
															{} ),
													};
													if ( v === 'auto' ) {
														delete updated[
															ability.name
														];
													} else {
														updated[
															ability.name
														] = v;
													}
													updateField(
														'tool_permissions',
														updated
													);
												} }
												__nextHasNoMarginBottom
											/>
										);
									} ) }
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

						default:
							return null;
					}
				} }
			</TabPanel>
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
		</div>
	);
}
