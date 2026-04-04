/**
 * Session filters slice — filter tab, folder, search query, and folder list.
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	sessionFilter: 'active',
	sessionFolder: '',
	sessionSearch: '',
	folders: [],
	foldersLoaded: false,
};

export const actions = {
	/**
	 * Set the active session filter tab.
	 *
	 * @param {string} filter - Filter key: 'active', 'archived', or 'trash'.
	 * @return {Object} Redux action.
	 */
	setSessionFilter( filter ) {
		return { type: 'SET_SESSION_FILTER', filter };
	},

	/**
	 * Set the active folder filter.
	 *
	 * @param {string} folder - Folder name, or empty string for all.
	 * @return {Object} Redux action.
	 */
	setSessionFolder( folder ) {
		return { type: 'SET_SESSION_FOLDER', folder };
	},

	/**
	 * Set the session search query.
	 *
	 * @param {string} search - Search string.
	 * @return {Object} Redux action.
	 */
	setSessionSearch( search ) {
		return { type: 'SET_SESSION_SEARCH', search };
	},

	/**
	 * Replace the folders list.
	 *
	 * @param {string[]} folders - Available folder names.
	 * @return {Object} Redux action.
	 */
	setFolders( folders ) {
		return { type: 'SET_FOLDERS', folders };
	},

	/**
	 * Fetch the list of folder names from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchFolders() {
		return async ( { dispatch } ) => {
			try {
				const folders = await apiFetch( {
					path: '/gratis-ai-agent/v1/sessions/folders',
				} );
				dispatch.setFolders( folders );
			} catch {
				dispatch.setFolders( [] );
			}
		};
	},
};

export const selectors = {
	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} Active session filter tab ('active', 'archived', 'trash').
	 */
	getSessionFilter( state ) {
		return state.sessionFilter;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} Active folder filter, or '' for all.
	 */
	getSessionFolder( state ) {
		return state.sessionFolder;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string} Active search query.
	 */
	getSessionSearch( state ) {
		return state.sessionSearch;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {string[]} Available folder names.
	 */
	getFolders( state ) {
		return state.folders;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether folders have been fetched.
	 */
	getFoldersLoaded( state ) {
		return state.foldersLoaded;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_SESSION_FILTER':
			return { ...state, sessionFilter: action.filter };
		case 'SET_SESSION_FOLDER':
			return { ...state, sessionFolder: action.folder };
		case 'SET_SESSION_SEARCH':
			return { ...state, sessionSearch: action.search };
		case 'SET_FOLDERS':
			return { ...state, folders: action.folders, foldersLoaded: true };
		default:
			return state;
	}
}
