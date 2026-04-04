/**
 * WordPress dependencies
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, SearchControl, Button } from '@wordpress/components';

/**
 * Model Selector Component
 *
 * @param {Object}   props                Component props.
 * @param {Array}    props.providers      Available providers.
 * @param {Array}    props.selectedModels Selected models.
 * @param {Function} props.onChange       Change callback.
 * @param {boolean}  props.disabled       Disabled state.
 * @return {JSX.Element} Component element.
 */
export default function ModelSelector( {
	providers,
	selectedModels,
	onChange,
	disabled,
} ) {
	const [ searchTerm, setSearchTerm ] = useState( '' );

	// Define available models by provider
	const availableModels = useMemo( () => {
		const models = [];

		// Built-in WordPress AI Client models
		models.push( {
			provider_id: '',
			provider_name: __( 'WordPress AI Client', 'gratis-ai-agent' ),
			model_id: 'claude-sonnet-4',
			model_name: 'Claude Sonnet 4',
		} );

		// Anthropic models
		models.push(
			{
				provider_id: 'anthropic',
				provider_name: __( 'Anthropic', 'gratis-ai-agent' ),
				model_id: 'claude-sonnet-4-20250514',
				model_name: 'Claude Sonnet 4 (2025-05-14)',
			},
			{
				provider_id: 'anthropic',
				provider_name: __( 'Anthropic', 'gratis-ai-agent' ),
				model_id: 'claude-opus-4-20250514',
				model_name: 'Claude Opus 4 (2025-05-14)',
			},
			{
				provider_id: 'anthropic',
				provider_name: __( 'Anthropic', 'gratis-ai-agent' ),
				model_id: 'claude-haiku-4-20250514',
				model_name: 'Claude Haiku 4 (2025-05-14)',
			}
		);

		// OpenAI models
		models.push(
			{
				provider_id: 'openai',
				provider_name: __( 'OpenAI', 'gratis-ai-agent' ),
				model_id: 'gpt-4o',
				model_name: 'GPT-4o',
			},
			{
				provider_id: 'openai',
				provider_name: __( 'OpenAI', 'gratis-ai-agent' ),
				model_id: 'gpt-4o-mini',
				model_name: 'GPT-4o Mini',
			},
			{
				provider_id: 'openai',
				provider_name: __( 'OpenAI', 'gratis-ai-agent' ),
				model_id: 'gpt-4-turbo',
				model_name: 'GPT-4 Turbo',
			},
			{
				provider_id: 'openai',
				provider_name: __( 'OpenAI', 'gratis-ai-agent' ),
				model_id: 'gpt-3.5-turbo',
				model_name: 'GPT-3.5 Turbo',
			}
		);

		// Google models
		models.push(
			{
				provider_id: 'google',
				provider_name: __( 'Google', 'gratis-ai-agent' ),
				model_id: 'gemini-2.5-pro',
				model_name: 'Gemini 2.5 Pro',
			},
			{
				provider_id: 'google',
				provider_name: __( 'Google', 'gratis-ai-agent' ),
				model_id: 'gemini-2.5-flash',
				model_name: 'Gemini 2.5 Flash',
			},
			{
				provider_id: 'google',
				provider_name: __( 'Google', 'gratis-ai-agent' ),
				model_id: 'gemini-1.5-pro',
				model_name: 'Gemini 1.5 Pro',
			}
		);

		// Add any custom providers from the API
		if ( providers && providers.length > 0 ) {
			providers.forEach( ( provider ) => {
				if ( provider.models ) {
					provider.models.forEach( ( model ) => {
						// Skip if already added
						const exists = models.some(
							( m ) =>
								m.provider_id === provider.id &&
								m.model_id === model.id
						);
						if ( ! exists ) {
							models.push( {
								provider_id: provider.id,
								provider_name: provider.name,
								model_id: model.id,
								model_name: model.name || model.id,
							} );
						}
					} );
				}
			} );
		}

		return models;
	}, [ providers ] );

	// Filter models by search term
	const filteredModels = useMemo( () => {
		if ( ! searchTerm ) {
			return availableModels;
		}
		const term = searchTerm.toLowerCase();
		return availableModels.filter(
			( model ) =>
				model.model_name.toLowerCase().includes( term ) ||
				model.provider_name.toLowerCase().includes( term )
		);
	}, [ availableModels, searchTerm ] );

	// Group by provider
	const groupedModels = useMemo( () => {
		const groups = {};
		filteredModels.forEach( ( model ) => {
			if ( ! groups[ model.provider_name ] ) {
				groups[ model.provider_name ] = [];
			}
			groups[ model.provider_name ].push( model );
		} );
		return groups;
	}, [ filteredModels ] );

	const isSelected = ( model ) => {
		return selectedModels.some(
			( m ) =>
				m.provider_id === model.provider_id &&
				m.model_id === model.model_id
		);
	};

	const toggleModel = ( model ) => {
		if ( isSelected( model ) ) {
			onChange(
				selectedModels.filter(
					( m ) =>
						! (
							m.provider_id === model.provider_id &&
							m.model_id === model.model_id
						)
				)
			);
		} else {
			onChange( [
				...selectedModels,
				{
					provider_id: model.provider_id,
					model_id: model.model_id,
				},
			] );
		}
	};

	const selectAll = () => {
		onChange(
			filteredModels.map( ( model ) => ( {
				provider_id: model.provider_id,
				model_id: model.model_id,
			} ) )
		);
	};

	const deselectAll = () => {
		onChange( [] );
	};

	return (
		<div className="gratis-ai-agent-model-selector">
			<SearchControl
				value={ searchTerm }
				onChange={ setSearchTerm }
				placeholder={ __( 'Search models…', 'gratis-ai-agent' ) }
			/>

			<div
				className="gratis-ai-agent-model-selector-actions"
				style={ { margin: '12px 0' } }
			>
				<Button
					variant="secondary"
					onClick={ selectAll }
					disabled={ disabled }
					size="small"
				>
					{ __( 'Select All', 'gratis-ai-agent' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ deselectAll }
					disabled={ disabled }
					size="small"
					style={ { marginLeft: '8px' } }
				>
					{ __( 'Deselect All', 'gratis-ai-agent' ) }
				</Button>
				<span style={ { marginLeft: '12px', color: '#646970' } }>
					{ selectedModels.length }{ ' ' }
					{ __( 'models selected', 'gratis-ai-agent' ) }
				</span>
			</div>

			{ Object.entries( groupedModels ).map(
				( [ providerName, models ] ) => (
					<div
						key={ providerName }
						className="gratis-ai-agent-model-provider"
					>
						<h4>{ providerName }</h4>
						<div className="gratis-ai-agent-model-list">
							{ models.map( ( model ) => (
								<div
									key={ `${ model.provider_id }-${ model.model_id }` }
									className="gratis-ai-agent-model-item"
								>
									<CheckboxControl
										label={ model.model_name }
										checked={ isSelected( model ) }
										onChange={ () => toggleModel( model ) }
										disabled={ disabled }
									/>
								</div>
							) ) }
						</div>
					</div>
				)
			) }

			{ filteredModels.length === 0 && (
				<p
					style={ {
						color: '#646970',
						textAlign: 'center',
						padding: '20px',
					} }
				>
					{ __(
						'No models found matching your search.',
						'gratis-ai-agent'
					) }
				</p>
			) }
		</div>
	);
}
