/**
 * Memory slice — memory entries CRUD.
 */

/**
 * @typedef {import('../../types').Memory} Memory
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	memories: [],
	memoriesLoaded: false,
};

export const actions = {
	/**
	 * Replace the memories list.
	 *
	 * @param {Memory[]} memories - Memory entries.
	 * @return {Object} Redux action.
	 */
	setMemories( memories ) {
		return { type: 'SET_MEMORIES', memories };
	},

	/**
	 * Fetch all memory entries from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchMemories() {
		return async ( { dispatch } ) => {
			try {
				const memories = await apiFetch( {
					path: '/sd-ai-agent/v1/memory',
				} );
				dispatch.setMemories( memories );
			} catch {
				dispatch.setMemories( [] );
			}
		};
	},

	/**
	 * Create a new memory entry.
	 *
	 * @param {string} category - Memory category (e.g. 'general').
	 * @param {string} content  - Memory content text.
	 * @return {Function} Redux thunk.
	 */
	createMemory( category, content ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/sd-ai-agent/v1/memory',
				method: 'POST',
				data: { category, content },
			} );
			dispatch.fetchMemories();
		};
	},

	/**
	 * Update an existing memory entry.
	 *
	 * @param {number}          id   - Memory identifier.
	 * @param {Partial<Memory>} data - Fields to update.
	 * @return {Function} Redux thunk.
	 */
	updateMemory( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/sd-ai-agent/v1/memory/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchMemories();
		};
	},

	/**
	 * Delete a memory entry.
	 *
	 * @param {number} id - Memory identifier.
	 * @return {Function} Redux thunk.
	 */
	deleteMemory( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/sd-ai-agent/v1/memory/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchMemories();
		};
	},
};

export const selectors = {
	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Memory[]} Memory entries.
	 */
	getMemories( state ) {
		return state.memories;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether memories have been fetched.
	 */
	getMemoriesLoaded( state ) {
		return state.memoriesLoaded;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_MEMORIES':
			return {
				...state,
				memories: action.memories,
				memoriesLoaded: true,
			};
		default:
			return state;
	}
}
