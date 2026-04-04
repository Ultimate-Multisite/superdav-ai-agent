/**
 * Providers slice — AI provider list and selected provider/model.
 */

/**
 * @typedef {import('../../types').Provider} Provider
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	providers: [],
	providersLoaded: false,
	selectedProviderId: localStorage.getItem( 'gratisAiAgentProvider' ) || '',
	selectedModelId: localStorage.getItem( 'gratisAiAgentModel' ) || '',
};

export const actions = {
	/**
	 * Replace the providers list.
	 *
	 * @param {Provider[]} providers - Available AI providers.
	 * @return {Object} Redux action.
	 */
	setProviders( providers ) {
		return { type: 'SET_PROVIDERS', providers };
	},

	/**
	 * Select an AI provider and persist the choice to localStorage.
	 *
	 * @param {string} providerId - Provider identifier.
	 * @return {Object} Redux action.
	 */
	setSelectedProvider( providerId ) {
		localStorage.setItem( 'gratisAiAgentProvider', providerId );
		return { type: 'SET_SELECTED_PROVIDER', providerId };
	},

	/**
	 * Select a model and persist the choice to localStorage.
	 *
	 * @param {string} modelId - Model identifier.
	 * @return {Object} Redux action.
	 */
	setSelectedModel( modelId ) {
		localStorage.setItem( 'gratisAiAgentModel', modelId );
		return { type: 'SET_SELECTED_MODEL', modelId };
	},

	/**
	 * Fetch available AI providers from the REST API and populate the store.
	 * Auto-selects the first provider/model when none is saved or the saved
	 * provider is no longer available.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchProviders() {
		return async ( { dispatch } ) => {
			try {
				const providers = await apiFetch( {
					path: '/gratis-ai-agent/v1/providers',
				} );
				dispatch.setProviders( providers );

				// Auto-select first provider if none saved or saved one is unavailable.
				const saved = localStorage.getItem( 'gratisAiAgentProvider' );
				if (
					( ! saved ||
						! providers.find( ( p ) => p.id === saved ) ) &&
					providers.length
				) {
					dispatch.setSelectedProvider( providers[ 0 ].id );
					if ( providers[ 0 ].models?.length ) {
						dispatch.setSelectedModel(
							providers[ 0 ].models[ 0 ].id
						);
					} else {
						dispatch.setSelectedModel( '' );
					}
				}
			} catch {
				dispatch.setProviders( [] );
			}
		};
	},
};

export const selectors = {
	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Provider[]} Available AI providers.
	 */
	getProviders( state ) {
		return state.providers;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether providers have been fetched.
	 */
	getProvidersLoaded( state ) {
		return state.providersLoaded;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} Currently selected provider ID.
	 */
	getSelectedProviderId( state ) {
		return state.selectedProviderId;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} Currently selected model ID.
	 */
	getSelectedModelId( state ) {
		return state.selectedModelId;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {import('../../types').ProviderModel[]} Models for the selected provider.
	 */
	getSelectedProviderModels( state ) {
		const provider = state.providers.find(
			( p ) => p.id === state.selectedProviderId
		);
		return provider?.models || [];
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_PROVIDERS':
			return {
				...state,
				providers: action.providers,
				providersLoaded: true,
			};
		case 'SET_SELECTED_PROVIDER':
			return { ...state, selectedProviderId: action.providerId };
		case 'SET_SELECTED_MODEL':
			return { ...state, selectedModelId: action.modelId };
		default:
			return state;
	}
}
