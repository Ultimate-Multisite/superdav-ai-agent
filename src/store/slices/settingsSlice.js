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
	 * @return {Function} Redux thunk.
	 */
	fetchSettings() {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
				} );
				dispatch.setSettings( settings );
			} catch {
				dispatch.setSettings( {} );
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
		default:
			return state;
	}
}
