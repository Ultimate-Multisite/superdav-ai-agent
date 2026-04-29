/**
 * WordPress dependencies
 */
import {
	createReduxStore,
	register,
	select as wpSelect,
} from '@wordpress/data';

/**
 * @typedef {import('../types').StoreState} StoreState
 * @typedef {import('../types').Provider} Provider
 * @typedef {import('../types').Session} Session
 * @typedef {import('../types').Message} Message
 * @typedef {import('../types').ToolCall} ToolCall
 * @typedef {import('../types').TokenUsage} TokenUsage
 * @typedef {import('../types').PendingConfirmation} PendingConfirmation
 * @typedef {import('../types').Settings} Settings
 * @typedef {import('../types').Memory} Memory
 * @typedef {import('../types').Skill} Skill
 */

/**
 * Domain slices — each slice owns its own state, actions, selectors, and reducer.
 */
import {
	initialState as providersInitialState,
	actions as providersActions,
	selectors as providersSelectors,
	reducer as providersReducer,
} from './slices/providersSlice';

import {
	initialState as sessionsInitialState,
	actions as sessionsActions,
	selectors as sessionsSelectors,
	reducer as sessionsReducer,
} from './slices/sessionsSlice';

import {
	initialState as settingsInitialState,
	actions as settingsActions,
	selectors as settingsSelectors,
	reducer as settingsReducer,
} from './slices/settingsSlice';

import {
	initialState as memoryInitialState,
	actions as memoryActions,
	selectors as memorySelectors,
	reducer as memoryReducer,
} from './slices/memorySlice';

import {
	initialState as skillsInitialState,
	actions as skillsActions,
	selectors as skillsSelectors,
	reducer as skillsReducer,
} from './slices/skillsSlice';

import {
	initialState as agentsInitialState,
	actions as agentsActions,
	selectors as agentsSelectors,
	reducer as agentsReducer,
} from './slices/agentsSlice';

import {
	initialState as conversationTemplatesInitialState,
	actions as conversationTemplatesActions,
	selectors as conversationTemplatesSelectors,
	reducer as conversationTemplatesReducer,
} from './slices/conversationTemplatesSlice';

import {
	initialState as sessionFiltersInitialState,
	actions as sessionFiltersActions,
	selectors as sessionFiltersSelectors,
	reducer as sessionFiltersReducer,
} from './slices/sessionFiltersSlice';

import {
	initialState as uiInitialState,
	actions as uiActions,
	selectors as uiSelectors,
	reducer as uiReducer,
} from './slices/uiSlice';

import {
	initialState as jobInitialState,
	actions as jobActions,
	selectors as jobSelectors,
	reducer as jobReducer,
} from './slices/jobSlice';

const STORE_NAME = 'sd-ai-agent';

// Migrate localStorage keys from old "aiAgent" prefix to "sdAiAgent".
[ 'Provider', 'Model', 'DebugMode' ].forEach( ( key ) => {
	const oldKey = `aiAgent${ key }`;
	const newKey = `sdAiAgent${ key }`;
	if (
		localStorage.getItem( oldKey ) !== null &&
		localStorage.getItem( newKey ) === null
	) {
		localStorage.setItem( newKey, localStorage.getItem( oldKey ) );
		localStorage.removeItem( oldKey );
	}
} );

/**
 * Combined initial state from all domain slices.
 */
const DEFAULT_STATE = {
	...providersInitialState,
	...sessionsInitialState,
	...settingsInitialState,
	...memoryInitialState,
	...skillsInitialState,
	...agentsInitialState,
	...conversationTemplatesInitialState,
	...sessionFiltersInitialState,
	...uiInitialState,
	...jobInitialState,
};

/**
 * Combined actions from all domain slices.
 */
const actions = {
	...providersActions,
	...sessionsActions,
	...settingsActions,
	...memoryActions,
	...skillsActions,
	...agentsActions,
	...conversationTemplatesActions,
	...sessionFiltersActions,
	...uiActions,
	...jobActions,
};

/**
 * Combined selectors from all domain slices, plus cross-slice derived selectors.
 */
const selectors = {
	...providersSelectors,
	...sessionsSelectors,
	...settingsSelectors,
	...memorySelectors,
	...skillsSelectors,
	...agentsSelectors,
	...conversationTemplatesSelectors,
	...sessionFiltersSelectors,
	...uiSelectors,
	...jobSelectors,

	// ─── Cross-slice derived selectors ───────────────────────────

	/**
	 * Calculate the context window usage as a percentage (0–100+).
	 *
	 * @param {StoreState} state
	 * @return {number} Percentage of context window consumed by prompt tokens.
	 */
	getContextPercentage( state ) {
		const contextLimit = providersSelectors.getModelContextWindow(
			state,
			state.selectedModelId
		);
		return ( state.tokenUsage.prompt / contextLimit ) * 100;
	},

	/**
	 * Whether the context window usage exceeds the 80% warning threshold.
	 *
	 * @param {StoreState} state
	 * @return {boolean} True when context usage is above 80%.
	 */
	isContextWarning( state ) {
		const contextLimit = providersSelectors.getModelContextWindow(
			state,
			state.selectedModelId
		);
		return ( state.tokenUsage.prompt / contextLimit ) * 100 > 80;
	},
};

/**
 * Redux reducer for the Superdav AI Agent store.
 * Delegates to each domain slice reducer in turn.
 *
 * @param {StoreState} state  - Current state (defaults to DEFAULT_STATE).
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
const reducer = ( state = DEFAULT_STATE, action ) => {
	// Each slice reducer handles its own action types and returns state unchanged
	// for actions it does not own. Applying them in sequence is equivalent to
	// combineReducers but preserves the flat state shape required by @wordpress/data.
	let next = state;
	next = providersReducer( next, action );
	next = sessionsReducer( next, action );
	next = settingsReducer( next, action );
	next = memoryReducer( next, action );
	next = skillsReducer( next, action );
	next = agentsReducer( next, action );
	next = conversationTemplatesReducer( next, action );
	next = sessionFiltersReducer( next, action );
	next = uiReducer( next, action );
	next = jobReducer( next, action );
	return next;
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

// Guard against double-registration: both floating-widget.js and
// screen-meta.js import this module. The first bundle to load registers
// the store; subsequent bundles on the same page skip registration so
// the existing store instance (and its state) is preserved.
if ( ! wpSelect( STORE_NAME ) ) {
	register( store );
}

export default STORE_NAME;
