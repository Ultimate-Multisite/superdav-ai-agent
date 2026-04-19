/**
 * Skills slice — skill entries CRUD + usage stats + update checks.
 */

/**
 * @typedef {import('../../types').Skill} Skill
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	skills: [],
	skillsLoaded: false,
	skillStats: {},
	skillStatsLoaded: false,
	skillUpdates: {},
	skillUpdatesChecking: false,
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
	 * Replace skill usage stats map.
	 *
	 * @param {Object} stats - Map of skill_id => stats object.
	 * @return {Object} Redux action.
	 */
	setSkillStats( stats ) {
		return { type: 'SET_SKILL_STATS', stats };
	},

	/**
	 * Replace skill updates map (results from check-updates endpoint).
	 *
	 * @param {Object} updates - Map of skill_id => update info.
	 * @return {Object} Redux action.
	 */
	setSkillUpdates( updates ) {
		return { type: 'SET_SKILL_UPDATES', updates };
	},

	/**
	 * Set whether a skill update check is in progress.
	 *
	 * @param {boolean} checking - True while the check request is in flight.
	 * @return {Object} Redux action.
	 */
	setSkillUpdatesChecking( checking ) {
		return { type: 'SET_SKILL_UPDATES_CHECKING', checking };
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
	 * Fetch aggregated skill usage statistics from the REST API.
	 *
	 * Stats are indexed by skill_id (numeric string keys).
	 * Each entry: { skill_id, total_loads, helpful_count, neutral_count, negative_count, last_used_at }.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSkillStats() {
		return async ( { dispatch } ) => {
			try {
				const stats = await apiFetch( {
					path: '/gratis-ai-agent/v1/skills/stats',
				} );
				dispatch.setSkillStats( stats );
			} catch {
				dispatch.setSkillStats( {} );
			}
		};
	},

	/**
	 * Trigger a remote manifest check for skill updates.
	 *
	 * Calls POST /skills/check-updates. If skill_auto_update is enabled in
	 * settings, updates to unmodified built-in skills are applied automatically
	 * on the server side. The response map is stored in skillUpdates so the UI
	 * can show "Update Available" badges on affected skill cards.
	 *
	 * @return {Function} Redux thunk that resolves with the updates map or null on error.
	 */
	checkSkillUpdates() {
		return async ( { dispatch } ) => {
			dispatch.setSkillUpdatesChecking( true );
			try {
				const updates = await apiFetch( {
					path: '/gratis-ai-agent/v1/skills/check-updates',
					method: 'POST',
				} );
				dispatch.setSkillUpdates( updates );
				// Refresh skill list in case updates were auto-applied.
				dispatch.fetchSkills();
				return updates;
			} catch {
				return null;
			} finally {
				dispatch.setSkillUpdatesChecking( false );
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

	/**
	 * Aggregated usage stats indexed by skill_id.
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {Object} Stats map: { [skill_id]: { total_loads, helpful_count, neutral_count, negative_count, last_used_at } }
	 */
	getSkillStats( state ) {
		return state.skillStats;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether skill stats have been fetched.
	 */
	getSkillStatsLoaded( state ) {
		return state.skillStatsLoaded;
	},

	/**
	 * Remote update availability map, populated after checkSkillUpdates().
	 *
	 * @param {import('../../types').StoreState} state
	 * @return {Object} Updates map: { [skill_id]: { has_update, remote_version, applied, user_modified } }
	 */
	getSkillUpdates( state ) {
		return state.skillUpdates;
	},

	/**
	 * @param {import('../../types').StoreState} state
	 * @return {boolean} Whether a check-updates request is in flight.
	 */
	getSkillUpdatesChecking( state ) {
		return state.skillUpdatesChecking;
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
		case 'SET_SKILL_STATS':
			return {
				...state,
				skillStats: action.stats,
				skillStatsLoaded: true,
			};
		case 'SET_SKILL_UPDATES':
			return {
				...state,
				skillUpdates: action.updates,
			};
		case 'SET_SKILL_UPDATES_CHECKING':
			return {
				...state,
				skillUpdatesChecking: action.checking,
			};
		default:
			return state;
	}
}
