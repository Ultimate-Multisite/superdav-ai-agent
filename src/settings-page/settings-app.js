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
import MemoryManager from './memory-manager';
import SkillManager from './skill-manager';
import KnowledgeManager from './knowledge-manager';
import UsageDashboard from './usage-dashboard';
import CustomToolsManager from './custom-tools-manager';
import ToolProfilesManager from './tool-profiles-manager';
import AutomationsManager from './automations-manager';
import ProvidersManager from './providers-manager';
import EventsManager from './events-manager';

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
		apiFetch( { path: '/ai-agent/v1/abilities' } )
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
				message: __( 'Settings saved.', 'ai-agent' ),
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: __( 'Failed to save settings.', 'ai-agent' ),
			} );
		}
		setSaving( false );
	}, [ local, saveSettings ] );

	if ( ! settingsLoaded || ! local ) {
		return (
			<div className="ai-agent-settings-loading">
				<Spinner />
			</div>
		);
	}

	// Build provider/model options.
	const providerOptions = [
		{ label: __( '(default)', 'ai-agent' ), value: '' },
		...providers.map( ( p ) => ( { label: p.name, value: p.id } ) ),
	];

	const selectedProvider = providers.find(
		( p ) => p.id === local.default_provider
	);
	const modelOptions = [
		{ label: __( '(default)', 'ai-agent' ), value: '' },
		...( selectedProvider?.models || [] ).map( ( m ) => ( {
			label: m.name || m.id,
			value: m.id,
		} ) ),
	];

	const tabs = [
		{
			name: 'providers',
			title: __( 'Providers', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'general',
			title: __( 'General', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'system-prompt',
			title: __( 'System Prompt', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'memory',
			title: __( 'Memory', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'skills',
			title: __( 'Skills', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'knowledge',
			title: __( 'Knowledge', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'custom-tools',
			title: __( 'Custom Tools', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'tool-profiles',
			title: __( 'Tool Profiles', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'automations',
			title: __( 'Automations', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'events',
			title: __( 'Events', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'abilities',
			title: __( 'Abilities', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'usage',
			title: __( 'Usage', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
		{
			name: 'advanced',
			title: __( 'Advanced', 'ai-agent' ),
			className: 'ai-agent-settings-tab',
		},
	];

	return (
		<div className="ai-agent-settings">
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
								<div className="ai-agent-settings-section">
									<ProvidersManager
										providerKeys={
											settings?._provider_keys || {}
										}
									/>
								</div>
							);

						case 'general':
							return (
								<div className="ai-agent-settings-section">
									<SelectControl
										label={ __(
											'Default Provider',
											'ai-agent'
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
											'ai-agent'
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
											'ai-agent'
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
											'ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<TextareaControl
										label={ __(
											'Greeting Message',
											'ai-agent'
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
											'ai-agent'
										) }
										rows={ 2 }
									/>
								</div>
							);

						case 'system-prompt':
							return (
								<div className="ai-agent-settings-section">
									<TextareaControl
										label={ __(
											'Custom System Prompt',
											'ai-agent'
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
											'ai-agent'
										) }
									/>
								</div>
							);

						case 'memory':
							return (
								<div className="ai-agent-settings-section">
									<ToggleControl
										label={ __(
											'Auto-Memory',
											'ai-agent'
										) }
										checked={ local.auto_memory }
										onChange={ ( v ) =>
											updateField( 'auto_memory', v )
										}
										help={ __(
											'When enabled, the AI can proactively save and recall memories.',
											'ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<MemoryManager />
								</div>
							);

						case 'skills':
							return (
								<div className="ai-agent-settings-section">
									<SkillManager />
								</div>
							);

						case 'knowledge':
							return (
								<div className="ai-agent-settings-section">
									<ToggleControl
										label={ __(
											'Enable Knowledge Base',
											'ai-agent'
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
											'ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<ToggleControl
										label={ __(
											'Auto-Index on Post Save',
											'ai-agent'
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
											'ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<KnowledgeManager />
								</div>
							);

						case 'custom-tools':
							return (
								<div className="ai-agent-settings-section">
									<CustomToolsManager />
								</div>
							);

						case 'tool-profiles':
							return (
								<div className="ai-agent-settings-section">
									<ToolProfilesManager />
								</div>
							);

						case 'automations':
							return (
								<div className="ai-agent-settings-section">
									<AutomationsManager />
								</div>
							);

						case 'events':
							return (
								<div className="ai-agent-settings-section">
									<EventsManager />
								</div>
							);

						case 'abilities':
							return (
								<div className="ai-agent-settings-section">
									<p className="description">
										{ __(
											'Control how each tool behaves. "Auto" runs without asking, "Confirm" pauses to ask before running, "Disabled" prevents the tool from being used.',
											'ai-agent'
										) }
									</p>
									{ abilities.length === 0 && (
										<p>
											{ __(
												'No abilities registered.',
												'ai-agent'
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
															'ai-agent'
														),
														value: 'auto',
													},
													{
														label: __(
															'Confirm (ask before use)',
															'ai-agent'
														),
														value: 'confirm',
													},
													{
														label: __(
															'Disabled',
															'ai-agent'
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
								<div className="ai-agent-settings-section">
									<UsageDashboard />
								</div>
							);

						case 'advanced':
							return (
								<div className="ai-agent-settings-section">
									<RangeControl
										label={ __(
											'Temperature',
											'ai-agent'
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
											'ai-agent'
										) }
									/>
									<TextControl
										label={ __(
											'Max Output Tokens',
											'ai-agent'
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
											'ai-agent'
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
											'ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									<SelectControl
										label={ __(
											'Tool Discovery Mode',
											'ai-agent'
										) }
										value={
											local.tool_discovery_mode || 'auto'
										}
										options={ [
											{
												label: __(
													'Auto (enable when tools exceed threshold)',
													'ai-agent'
												),
												value: 'auto',
											},
											{
												label: __(
													'Always (always use discovery)',
													'ai-agent'
												),
												value: 'always',
											},
											{
												label: __(
													'Never (load all tools directly)',
													'ai-agent'
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
											'ai-agent'
										) }
										__nextHasNoMarginBottom
									/>
									{ ( local.tool_discovery_mode ||
										'auto' ) === 'auto' && (
										<TextControl
											label={ __(
												'Discovery Threshold',
												'ai-agent'
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
												'ai-agent'
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
			<div className="ai-agent-settings-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save Settings', 'ai-agent' ) }
				</Button>
			</div>
		</div>
	);
}
