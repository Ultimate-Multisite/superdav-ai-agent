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

	const providerOptions = providers.map( ( p ) => ( {
		label: p.name,
		value: p.id,
	} ) );

	if ( ! providerOptions.length ) {
		providerOptions.push( {
			label: __( '(no providers)', 'gratis-ai-agent' ),
			value: '',
		} );
	}

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
