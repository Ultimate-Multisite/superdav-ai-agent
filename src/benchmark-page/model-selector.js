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

	// Build available models from the providers API endpoint.
	// Models are now loaded dynamically from the WordPress AI SDK
	// and any configured connectors - no hardcoded model lists.
	const availableModels = useMemo( () => {
		const models = [];

		// Start with WP AI Client (empty provider_id uses SDK default)
		models.push( {
			provider_id: '',
			provider_name: __( 'WordPress AI Client', 'gratis-ai-agent' ),
			model_id: '',
			model_name: __( 'Default Model', 'gratis-ai-agent' ),
		} );

		// Add all models from configured providers
		if ( providers && providers.length > 0 ) {
			providers.forEach( ( provider ) => {
				if ( provider.models && provider.models.length > 0 ) {
					provider.models.forEach( ( model ) => {
						models.push( {
							provider_id: provider.id,
							provider_name: provider.name,
							model_id: model.id,
							model_name: model.name || model.id,
						} );
					} );
				} else {
					// Provider exists but has no models listed -
					// still include it as an option (SDK will list available models)
					models.push( {
						provider_id: provider.id,
						provider_name: provider.name,
						model_id: '',
						model_name: __( 'Default Model', 'gratis-ai-agent' ),
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
