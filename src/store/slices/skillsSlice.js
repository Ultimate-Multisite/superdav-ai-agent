/**
 * Skills slice — skill entries CRUD.
 */

/**
 * @typedef {import('../../types').Skill} Skill
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	skills: [],
	skillsLoaded: false,
};

export const actions = {
	/**
	 * Replace the skills list.
	 *
	 * @param {Skill[]} skills - Skill entries.
	 * @return {Object} Redux action.
	 */
	setSkills( skills ) {
		return { type: 'SET_SKILLS', skills };
	},

	/**
	 * Fetch all skill entries from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSkills() {
		return async ( { dispatch } ) => {
			try {
				const skills = await apiFetch( {
					path: '/gratis-ai-agent/v1/skills',
				} );
				dispatch.setSkills( skills );
			} catch {
				dispatch.setSkills( [] );
			}
		};
	},

	/**
	 * Create a new skill.
	 *
	 * @param {Partial<Skill>} data - Skill fields.
	 * @return {Function} Redux thunk.
	 */
	createSkill( data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/skills',
				method: 'POST',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Update an existing skill.
	 *
	 * @param {number}         id   - Skill identifier.
	 * @param {Partial<Skill>} data - Fields to update.
	 * @return {Function} Redux thunk.
	 */
	updateSkill( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Delete a skill.
	 *
	 * @param {number} id - Skill identifier.
	 * @return {Function} Redux thunk.
	 */
	deleteSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Reset a skill to its built-in defaults.
	 *
	 * @param {number} id - Skill identifier.
	 * @return {Function} Redux thunk.
	 */
	resetSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }/reset`,
				method: 'POST',
			} );
			dispatch.fetchSkills();
		};
	},
};

export const selectors = {
	/**
	 * @param {import('../../types').StoreState} state
	 * @return {Skill[]} Skill entries.
	 */
	getSkills( state ) {
		return state.skills;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether skills have been fetched.
	 */
	getSkillsLoaded( state ) {
		return state.skillsLoaded;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_SKILLS':
			return {
				...state,
				skills: action.skills,
				skillsLoaded: true,
			};
		default:
			return state;
	}
}
