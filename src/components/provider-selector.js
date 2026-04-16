/**
 * WordPress dependencies
 */
import { SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Provider and model selector dropdowns.
 *
 * Changing the provider auto-selects the first available model for that
 * provider. Both selections are persisted to localStorage via the store.
 *
 * @param {Object}  props                 - Component props.
 * @param {boolean} [props.compact=false] - When true, hides labels and uses
 *                                        compact control sizing.
 * @return {JSX.Element} The provider/model selector element.
 */
export default function ProviderSelector( { compact = false } ) {
	const { providers, selectedProviderId, selectedModelId, models } =
		useSelect( ( select ) => {
			const store = select( STORE_NAME );
			return {
				providers: store.getProviders(),
				selectedProviderId: store.getSelectedProviderId(),
				selectedModelId: store.getSelectedModelId(),
				models: store.getSelectedProviderModels(),
			};
		}, [] );

	const { setSelectedProvider, setSelectedModel } = useDispatch( STORE_NAME );

	if ( ! providers.length ) {
		return (
			<div className="gratis-ai-agent-provider-selector">
				<p>
					<a href="/wp-admin/options-connectors.php">
						{ __( 'Configure a provider', 'gratis-ai-agent' ) }
					</a>
				</p>
			</div>
		);
	}

	const providerOptions = providers.map( ( p ) => ( {
		label: p.name,
		value: p.id,
	} ) );

	const modelOptions = models.length
		? models.map( ( m ) => ( {
				label: m.name || m.id,
				value: m.id,
		  } ) )
		: [ { label: __( '(default)', 'gratis-ai-agent' ), value: '' } ];

	const onProviderChange = ( value ) => {
		setSelectedProvider( value );
		const provider = providers.find( ( p ) => p.id === value );
		if ( provider?.models?.length ) {
			setSelectedModel( provider.models[ 0 ].id );
		} else {
			setSelectedModel( '' );
		}
	};

	return (
		<div
			className={ `gratis-ai-agent-provider-selector ${
				compact ? 'is-compact' : ''
			}` }
		>
			<SelectControl
				label={ compact ? null : __( 'Provider', 'gratis-ai-agent' ) }
				value={ selectedProviderId }
				options={ providerOptions }
				onChange={ onProviderChange }
				__nextHasNoMarginBottom
				size={ compact ? 'compact' : 'default' }
			/>
			<SelectControl
				label={ compact ? null : __( 'Model', 'gratis-ai-agent' ) }
				value={ selectedModelId }
				options={ modelOptions }
				onChange={ setSelectedModel }
				__nextHasNoMarginBottom
				size={ compact ? 'compact' : 'default' }
			/>
		</div>
	);
}
