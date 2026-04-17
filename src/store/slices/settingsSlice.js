/**
 * Settings slice — plugin settings.
 */

/**
 * @typedef {import('../../types').Settings} Settings
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	settings: null,
	settingsLoaded: false,
	settingsLoading: false,
};

export const actions = {
	/**
	 * Replace the plugin settings.
	 *
	 * @param {Settings} settings - Plugin settings object.
	 * @return {Object} Redux action.
	 */
	setSettings( settings ) {
		return { type: 'SET_SETTINGS', settings };
	},

	/**
	 * Fetch plugin settings from the REST API.
	 *
	 * Deduplicates concurrent in-flight calls: if a fetch is already in-flight
	 * (tracked via the shared Redux store, so cross-bundle dedup works too),
	 * the call is a no-op. This prevents duplicate REST requests when multiple
	 * plugin bundles mount components that each call fetchSettings() on mount.
	 * Intentional refreshes are not blocked once the in-flight fetch settles.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSettings() {
		return async ( { dispatch, select } ) => {
			// Skip if a fetch is already in-flight.
			if ( select.getSettingsLoading() ) {
				return;
			}
			dispatch( { type: 'SET_SETTINGS_LOADING', loading: true } );
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
				} );
				dispatch.setSettings( settings );
			} catch {
				dispatch.setSettings( {} );
			} finally {
				dispatch( { type: 'SET_SETTINGS_LOADING', loading: false } );
			}
		};
	},

	/**
	 * Save plugin settings via the REST API.
	 *
	 * @param {Partial<Settings>} data - Settings fields to update.
	 * @return {Function} Redux thunk that resolves with the saved settings.
	 */
	saveSettings( data ) {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
					method: 'POST',
					data,
				} );
				dispatch.setSettings( settings );
				return settings;
			} catch ( err ) {
				throw err;
			}
		};
	},
};

export const selectors = {
	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Settings|null} Plugin settings, or null if not yet loaded.
	 */
	getSettings( state ) {
		return state.settings;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether settings have been fetched.
	 */
	getSettingsLoaded( state ) {
		return state.settingsLoaded;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether a settings fetch is currently in-flight.
	 */
	getSettingsLoading( state ) {
		return state.settingsLoading;
	},

	// YOLO mode (skip all confirmations)
	isYoloMode( state ) {
		return state.settings?.yolo_mode ?? false;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_SETTINGS':
			return {
				...state,
				settings: action.settings,
				settingsLoaded: true,
			};
		case 'SET_SETTINGS_LOADING':
			return { ...state, settingsLoading: action.loading };
		default:
			return state;
	}
}
