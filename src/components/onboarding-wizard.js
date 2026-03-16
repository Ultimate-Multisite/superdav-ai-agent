/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
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
	const [ wooStatus, setWooStatus ] = useState( null );
	const [ wooLoading, setWooLoading ] = useState( false );
	const [ wooOfferAccepted, setWooOfferAccepted ] = useState( false );
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

	// Fetch WooCommerce status when we reach the WooCommerce step (step 3).
	useEffect( () => {
		if ( step !== 3 ) {
			return;
		}
		setWooLoading( true );
		apiFetch( { path: '/gratis-ai-agent/v1/woocommerce/status' } )
			.then( ( data ) => {
				setWooStatus( data );
				setWooLoading( false );
			} )
			.catch( () => {
				setWooStatus( { active: false } );
				setWooLoading( false );
			} );
	}, [ step ] );

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

	/**
	 * Render the WooCommerce onboarding step content.
	 *
	 * Shows a loading spinner while fetching store status, then either:
	 * - A "WooCommerce not detected" message (skip prompt), or
	 * - Store stats + an offer to use AI for product creation.
	 */
	const renderWooCommerceStep = () => {
		if ( wooLoading ) {
			return (
				<div className="gratis-ai-agent-wizard-woo">
					<Spinner />
					<p>
						{ __( 'Checking for WooCommerce…', 'gratis-ai-agent' ) }
					</p>
				</div>
			);
		}

		if ( ! wooStatus || ! wooStatus.active ) {
			return (
				<div className="gratis-ai-agent-wizard-woo">
					<p>
						{ __(
							'WooCommerce is not active on this site. You can skip this step.',
							'gratis-ai-agent'
						) }
					</p>
					<p className="description">
						{ __(
							'If you install WooCommerce later, the AI agent will automatically detect it and offer product management abilities.',
							'gratis-ai-agent'
						) }
					</p>
				</div>
			);
		}

		const {
			version,
			currency,
			published_products: publishedProducts,
			total_products: totalProducts,
			pending_orders: pendingOrders,
			processing_orders: processingOrders,
		} = wooStatus;

		return (
			<div className="gratis-ai-agent-wizard-woo">
				<Notice status="success" isDismissible={ false }>
					{ __( 'WooCommerce store detected!', 'gratis-ai-agent' ) }
				</Notice>

				<div className="gratis-ai-agent-wizard-woo-stats">
					<p>
						<strong>
							{ __( 'Store overview:', 'gratis-ai-agent' ) }
						</strong>
					</p>
					<ul>
						{ version && (
							<li>
								{ __(
									'WooCommerce version:',
									'gratis-ai-agent'
								) }{ ' ' }
								<strong>{ version }</strong>
							</li>
						) }
						{ currency && (
							<li>
								{ __( 'Currency:', 'gratis-ai-agent' ) }{ ' ' }
								<strong>{ currency }</strong>
							</li>
						) }
						<li>
							{ __( 'Published products:', 'gratis-ai-agent' ) }{ ' ' }
							<strong>{ publishedProducts ?? 0 }</strong>
							{ totalProducts > publishedProducts && (
								<span className="description">
									{ ' ' }
									{ sprintf(
										/* translators: %d: number of total products */
										__(
											'(%d total including drafts)',
											'gratis-ai-agent'
										),
										totalProducts
									) }
								</span>
							) }
						</li>
						{ ( pendingOrders > 0 || processingOrders > 0 ) && (
							<li>
								{ __( 'Active orders:', 'gratis-ai-agent' ) }{ ' ' }
								<strong>
									{ ( pendingOrders ?? 0 ) +
										( processingOrders ?? 0 ) }
								</strong>{ ' ' }
								<span className="description">
									{ sprintf(
										/* translators: 1: pending count, 2: processing count */
										__(
											'(%1$d pending, %2$d processing)',
											'gratis-ai-agent'
										),
										pendingOrders ?? 0,
										processingOrders ?? 0
									) }
								</span>
							</li>
						) }
					</ul>
				</div>

				<div className="gratis-ai-agent-wizard-woo-offer">
					<p>
						{ __(
							'The AI agent can help you manage your store:',
							'gratis-ai-agent'
						) }
					</p>
					<ul>
						<li>
							{ __(
								'Create products from a description or idea',
								'gratis-ai-agent'
							) }
						</li>
						<li>
							{ __(
								'List, search, and update existing products',
								'gratis-ai-agent'
							) }
						</li>
						<li>
							{ __(
								'Query orders and check store statistics',
								'gratis-ai-agent'
							) }
						</li>
					</ul>

					<ToggleControl
						label={ __(
							'Enable WooCommerce abilities',
							'gratis-ai-agent'
						) }
						help={ __(
							'Allows the AI agent to create and manage products and query orders. You can change this later in Settings.',
							'gratis-ai-agent'
						) }
						checked={ wooOfferAccepted }
						onChange={ setWooOfferAccepted }
						__nextHasNoMarginBottom
					/>

					{ wooOfferAccepted && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'WooCommerce abilities will be enabled. Try asking: "Create a product called Summer T-Shirt for $29.99"',
								'gratis-ai-agent'
							) }
						</Notice>
					) }
				</div>
			</div>
		);
	};

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
		// Step 3: WooCommerce (content adapts based on detection result)
		{
			title: __( 'WooCommerce Store', 'gratis-ai-agent' ),
			content: renderWooCommerceStep(),
		},
		// Step 4: Done
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
					{ wooStatus?.active && wooOfferAccepted && (
						<p>
							{ __(
								'WooCommerce abilities are enabled. Try asking the agent to create a product or check your store stats.',
								'gratis-ai-agent'
							) }
						</p>
					) }
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
