/**
 * Conversation templates slice — template list and CRUD thunks.
 */

import apiFetch from '@wordpress/api-fetch';

export const initialState = {
	conversationTemplates: [],
	conversationTemplatesLoaded: false,
};

export const actions = {
	setConversationTemplates( templates ) {
		return { type: 'SET_CONVERSATION_TEMPLATES', templates };
	},

	fetchConversationTemplates( category = null ) {
		return async ( { dispatch } ) => {
			try {
				const path = category
					? `/gratis-ai-agent/v1/conversation-templates?category=${ encodeURIComponent(
							category
					  ) }`
					: '/gratis-ai-agent/v1/conversation-templates';
				const templates = await apiFetch( { path } );
				dispatch.setConversationTemplates( templates );
			} catch {
				dispatch.setConversationTemplates( [] );
			}
		};
	},

	createConversationTemplate( data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/conversation-templates',
				method: 'POST',
				data,
			} );
			dispatch.fetchConversationTemplates();
		};
	},

	updateConversationTemplate( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/conversation-templates/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchConversationTemplates();
		};
	},

	deleteConversationTemplate( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/conversation-templates/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchConversationTemplates();
		};
	},
};

export const selectors = {
	getConversationTemplates( state ) {
		return state.conversationTemplates;
	},

	getConversationTemplatesLoaded( state ) {
		return state.conversationTemplatesLoaded;
	},
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_CONVERSATION_TEMPLATES':
			return {
				...state,
				conversationTemplates: action.templates,
				conversationTemplatesLoaded: true,
			};
		default:
			return state;
	}
}
