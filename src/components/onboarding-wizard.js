/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ProviderSelector from './provider-selector';

/**
 * Multi-step onboarding wizard shown on first activation.
 *
 * Steps: Welcome → Choose AI Provider → Configure Abilities → All Set.
 * Saves settings (default provider/model, disabled abilities, onboarding_complete)
 * on finish or skip.
 *
 * @param {Object}   props            - Component props.
 * @param {Function} props.onComplete - Called when the wizard is finished or skipped.
 * @return {JSX.Element} The onboarding wizard element.
 */
export default function OnboardingWizard( { onComplete } ) {
	const [ step, setStep ] = useState( 0 );
	const [ abilities, setAbilities ] = useState( [] );
	const [ disabledAbilities, setDisabledAbilities ] = useState( [] );
	const { saveSettings } = useDispatch( STORE_NAME );
	const { providers, selectedProviderId, selectedModelId } = useSelect(
		( select ) => ( {
			providers: select( STORE_NAME ).getProviders(),
			selectedProviderId: select( STORE_NAME ).getSelectedProviderId(),
			selectedModelId: select( STORE_NAME ).getSelectedModelId(),
		} ),
		[]
	);

	useEffect( () => {
		apiFetch( { path: '/gratis-ai-agent/v1/abilities' } )
			.then( setAbilities )
			.catch( () => {} );
	}, [] );

	const handleFinish = useCallback( async () => {
		await saveSettings( {
			onboarding_complete: true,
			default_provider: selectedProviderId,
			default_model: selectedModelId,
			disabled_abilities: disabledAbilities,
		} );
		onComplete();
	}, [
		saveSettings,
		selectedProviderId,
		selectedModelId,
		disabledAbilities,
		onComplete,
	] );

	const steps = [
		// Step 0: Welcome
		{
			title: __( 'Welcome to Gratis AI Agent', 'gratis-ai-agent' ),
			content: (
				<div className="gratis-ai-agent-wizard-welcome">
					<p>
						{ __(
							'Gratis AI Agent is an intelligent assistant that can interact with your WordPress site using registered abilities (tools).',
							'gratis-ai-agent'
						) }
					</p>
					<p>
						{ __(
							"It can manage content, query data, run commands, and more — all through a natural chat interface. Let's get set up!",
							'gratis-ai-agent'
						) }
					</p>
				</div>
			),
		},
		// Step 1: Provider
		{
			title: __( 'Choose AI Provider', 'gratis-ai-agent' ),
			content: (
				<div className="gratis-ai-agent-wizard-provider">
					{ providers.length === 0 ? (
						<div>
							<p>
								{ __(
									'No AI providers are configured yet.',
									'gratis-ai-agent'
								) }
							</p>
							<p>
								{ __(
									'Go to Settings > AI to configure a provider, then come back here.',
									'gratis-ai-agent'
								) }
							</p>
						</div>
					) : (
						<>
							<p>
								{ __(
									'Select which AI provider and model to use by default.',
									'gratis-ai-agent'
								) }
							</p>
							<ProviderSelector />
						</>
					) }
				</div>
			),
		},
		// Step 2: Abilities
		{
			title: __( 'Configure Abilities', 'gratis-ai-agent' ),
			content: (
				<div className="gratis-ai-agent-wizard-abilities">
					<p>
						{ __(
							'Choose which abilities the AI agent can use. You can change these later in settings.',
							'gratis-ai-agent'
						) }
					</p>
					{ abilities.length === 0 && (
						<p className="description">
							{ __(
								'No abilities registered yet. They will appear once plugins register them.',
								'gratis-ai-agent'
							) }
						</p>
					) }
					{ abilities.map( ( ability ) => {
						const disabled = disabledAbilities.includes(
							ability.name
						);
						return (
							<ToggleControl
								key={ ability.name }
								label={ ability.label || ability.name }
								help={ ability.description || '' }
								checked={ ! disabled }
								onChange={ ( enabled ) => {
									if ( enabled ) {
										setDisabledAbilities( ( prev ) =>
											prev.filter(
												( n ) => n !== ability.name
											)
										);
									} else {
										setDisabledAbilities( ( prev ) => [
											...prev,
											ability.name,
										] );
									}
								} }
								__nextHasNoMarginBottom
							/>
						);
					} ) }
				</div>
			),
		},
		// Step 3: Done
		{
			title: __( 'All Set!', 'gratis-ai-agent' ),
			content: (
				<div className="gratis-ai-agent-wizard-done">
					<p>
						{ __(
							"You're all set! Gratis AI Agent is ready to help you manage your WordPress site.",
							'gratis-ai-agent'
						) }
					</p>
					<p>
						{ __(
							'You can access it from the floating chat bubble on any admin page, or from the full-page chat under Tools > Gratis AI Agent.',
							'gratis-ai-agent'
						) }
					</p>
				</div>
			),
		},
	];

	const current = steps[ step ];
	const isLast = step === steps.length - 1;

	return (
		<div className="gratis-ai-agent-wizard">
			<div className="gratis-ai-agent-wizard-header">
				<h2>{ current.title }</h2>
				<div className="gratis-ai-agent-wizard-progress">
					{ steps.map( ( _, i ) => (
						<span
							key={ i }
							className={ `gratis-ai-agent-wizard-dot ${
								i === step ? 'is-active' : ''
							} ${ i < step ? 'is-complete' : '' }` }
						/>
					) ) }
				</div>
			</div>
			<div className="gratis-ai-agent-wizard-body">
				{ current.content }
			</div>
			<div className="gratis-ai-agent-wizard-footer">
				{ step > 0 && (
					<Button
						variant="tertiary"
						onClick={ () => setStep( step - 1 ) }
					>
						{ __( 'Back', 'gratis-ai-agent' ) }
					</Button>
				) }
				<Button
					variant="link"
					onClick={ handleFinish }
					className="gratis-ai-agent-wizard-skip"
				>
					{ __( 'Skip', 'gratis-ai-agent' ) }
				</Button>
				{ isLast ? (
					<Button variant="primary" onClick={ handleFinish }>
						{ __( 'Start Chatting', 'gratis-ai-agent' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={ () => setStep( step + 1 ) }
					>
						{ __( 'Next', 'gratis-ai-agent' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
