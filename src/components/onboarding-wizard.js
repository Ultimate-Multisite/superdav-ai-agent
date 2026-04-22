/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, ToggleControl, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ProviderSelector from './provider-selector';

/**
 * Get the URL for the Connectors admin page.
 *
 * @return {string} Connectors page URL.
 */
function getConnectorsUrl() {
	return (
		window.gratisAiAgentData?.connectorsUrl ||
		'admin.php?page=gratis-ai-agent#/connectors'
	);
}

/**
 * Multi-step onboarding wizard shown on first activation.
 *
 * Steps: Welcome → Set Up AI Provider → Configure Abilities → All Set.
 *
 * The provider step directs users to the WordPress Connectors page to
 * configure their API keys. Once at least one provider is available the
 * ProviderSelector appears to choose the default provider/model.
 *
 * Saves settings (default provider/model, disabled abilities,
 * onboarding_complete) on finish or skip.
 *
 * @param {Object}   props            - Component props.
 * @param {Function} props.onComplete - Called when the wizard is finished or skipped.
 * @return {JSX.Element} The onboarding wizard element.
 */
export default function OnboardingWizard( { onComplete } ) {
	const [ step, setStep ] = useState( 0 );
	const [ abilities, setAbilities ] = useState( [] );
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

	const hasAnyProvider = providers.length > 0;

	const handleFinish = useCallback( async () => {
		await saveSettings( {
			onboarding_complete: true,
			default_provider: selectedProviderId,
			default_model: selectedModelId,
		} );
		onComplete();
	}, [ saveSettings, selectedProviderId, selectedModelId, onComplete ] );

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
		// Step 1: Provider setup — directs to the Connectors page
		{
			title: __( 'Set Up an AI Provider', 'gratis-ai-agent' ),
			content: (
				<div className="gratis-ai-agent-wizard-provider">
					<p>
						{ __(
							'The AI agent needs an API key for at least one AI provider (OpenAI, Anthropic, or Google AI). API keys are managed on the Connectors page.',
							'gratis-ai-agent'
						) }
					</p>

					<Notice status="info" isDismissible={ false }>
						<a
							href={ getConnectorsUrl() }
							className="gratis-ai-agent-wizard-connectors-link"
						>
							{ __(
								'Open Connectors page to configure a provider →',
								'gratis-ai-agent'
							) }
						</a>
					</Notice>

					<p className="description">
						{ __(
							'Once you have configured a connector, come back here and continue the setup.',
							'gratis-ai-agent'
						) }
					</p>

					{ hasAnyProvider && (
						<div className="gratis-ai-agent-wizard-provider-selector">
							<p className="gratis-ai-agent-wizard-provider-selector__label">
								{ __(
									'Choose your default provider and model:',
									'gratis-ai-agent'
								) }
							</p>
							<ProviderSelector />
						</div>
					) }
				</div>
			),
		},
		// Step 2: Abilities overview (auto-discovery — no curation needed).
		{
			title: __( 'Abilities', 'gratis-ai-agent' ),
			content: (
				<div className="gratis-ai-agent-wizard-abilities">
					<p>
						{ __(
							'The agent will automatically discover and use any ability registered by your installed plugins. You do not need to curate them — frequently-used abilities are loaded directly each turn, and the rest are reachable via the built-in ability search.',
							'gratis-ai-agent'
						) }
					</p>
					{ abilities.length > 0 && (
						<p className="description">
							{ sprintf(
								/* translators: %d: number of abilities */
								__(
									'%d abilities are currently registered on this site.',
									'gratis-ai-agent'
								),
								abilities.length
							) }
						</p>
					) }
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
