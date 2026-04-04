/**
 * Agents slice — agent list, selected agent, and agent CRUD thunks.
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	agents: [],
	agentsLoaded: false,
	selectedAgentId: null,
};

export const actions = {
	setAgents( agents ) {
		return { type: 'SET_AGENTS', agents };
	},

	setSelectedAgentId( agentId ) {
		return { type: 'SET_SELECTED_AGENT_ID', agentId };
	},

	fetchAgents() {
		return async ( { dispatch } ) => {
			try {
				const agents = await apiFetch( {
					path: '/gratis-ai-agent/v1/agents',
				} );
				dispatch.setAgents( agents );
			} catch {
				dispatch.setAgents( [] );
			}
		};
	},

	createAgent( data ) {
		return async ( { dispatch } ) => {
			const agent = await apiFetch( {
				path: '/gratis-ai-agent/v1/agents',
				method: 'POST',
				data,
			} );
			dispatch.fetchAgents();
			return agent;
		};
	},

	updateAgent( id, data ) {
		return async ( { dispatch, select } ) => {
			const updated = await apiFetch( {
				path: `/gratis-ai-agent/v1/agents/${ id }`,
				method: 'PATCH',
				data,
			} );
			// Optimistically update the agent in the store so the card
			// reflects the new name immediately without waiting for a re-fetch.
			const current = select.getAgents();
			const merged = current.map( ( a ) =>
				a.id === id ? { ...a, ...( updated || data ) } : a
			);
			dispatch.setAgents( merged );
			// Re-fetch to confirm server state.
			dispatch.fetchAgents();
		};
	},

	deleteAgent( id ) {
		return async ( { dispatch, select } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/agents/${ id }`,
				method: 'DELETE',
			} );
			// Clear selection if the deleted agent was selected.
			if ( select.getSelectedAgentId() === id ) {
				dispatch.setSelectedAgentId( null );
			}
			// Optimistically remove the agent from the store so the card
			// disappears immediately without waiting for a re-fetch.
			const current = select.getAgents();
			dispatch.setAgents( current.filter( ( a ) => a.id !== id ) );
			// Re-fetch to confirm server state.
			dispatch.fetchAgents();
		};
	},
};

export const selectors = {
	getAgents( state ) {
		return state.agents;
	},

	getAgentsLoaded( state ) {
		return state.agentsLoaded;
	},

	getSelectedAgentId( state ) {
		return state.selectedAgentId;
	},

	getSelectedAgent( state ) {
		if ( ! state.selectedAgentId ) {
			return null;
		}
		return (
			state.agents.find( ( a ) => a.id === state.selectedAgentId ) || null
		);
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_AGENTS':
			return {
				...state,
				agents: action.agents,
				agentsLoaded: true,
			};
		case 'SET_SELECTED_AGENT_ID':
			return { ...state, selectedAgentId: action.agentId };
		default:
			return state;
	}
}
