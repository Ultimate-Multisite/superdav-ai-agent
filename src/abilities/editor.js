/**
 * Client-side insert-block ability.
 *
 * Inserts a Gutenberg block into the active block editor. Guards on
 * wp.data.select('core/block-editor') being defined so this module is safe
 * to import on non-editor screens.
 *
 * Annotated readonly: false because it writes to the editor state.
 */

import { registerClientAbility } from './registry';

/**
 * Execute the insert-block ability.
 *
 * @param {Object} args
 * @param {string} args.blockName    Block name, e.g. "core/paragraph".
 * @param {Object} [args.attributes] Block attributes.
 * @param {string} [args.innerHTML]  Optional inner HTML for the block.
 * @return {{ inserted: boolean, clientId: string, blockName: string }} Insert result.
 */
function executeInsertBlock( args ) {
	const { blockName, attributes = {}, innerHTML } = args || {};

	if ( ! blockName ) {
		return { inserted: false, clientId: '', blockName: '' };
	}

	// Guard: only run on editor screens.
	if (
		typeof wp === 'undefined' ||
		! wp.data ||
		! wp.data.select( 'core/block-editor' )
	) {
		return { inserted: false, clientId: '', blockName };
	}

	try {
		const { createBlock } = wp.blocks;
		const { dispatch } = wp.data;

		if ( ! createBlock || ! dispatch ) {
			return { inserted: false, clientId: '', blockName };
		}

		// Build the block — if innerHTML is provided, use it as the content.
		const blockAttributes =
			innerHTML && blockName === 'core/paragraph'
				? { ...attributes, content: innerHTML }
				: attributes;

		const block = createBlock( blockName, blockAttributes );
		dispatch( 'core/block-editor' ).insertBlocks( block );

		return {
			inserted: true,
			clientId: block.clientId || '',
			blockName,
		};
	} catch ( err ) {
		return { inserted: false, clientId: '', blockName };
	}
}

/**
 * Register the insert-block ability with the client-side abilities registry.
 *
 * Called by src/abilities/index.js after the sd-ai-agent-js category
 * has been registered. Must NOT self-register at module-eval time — ES
 * module imports are hoisted and would race the category registration
 * (the bug t166 fixes).
 *
 * Safe to call on non-editor screens — the executeInsertBlock callback
 * itself guards on `wp.data.select('core/block-editor')` being defined,
 * so calling the registered ability from a non-editor context returns
 * `{ inserted: false, ... }` instead of throwing.
 *
 * @return {void}
 */
export async function registerEditorAbility() {
	await registerClientAbility( {
		name: 'sd-ai-agent-js/insert-block',
		label: 'Insert Block',
		description:
			'Insert a Gutenberg block into the active block editor. Only available on editor screens.',
		inputSchema: {
			type: 'object',
			properties: {
				blockName: {
					type: 'string',
					description: 'Block name, e.g. "core/paragraph".',
				},
				attributes: {
					type: 'object',
					description: 'Block attributes.',
				},
				innerHTML: {
					type: 'string',
					description: 'Optional inner HTML for the block.',
				},
			},
			required: [ 'blockName' ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				inserted: { type: 'boolean' },
				clientId: { type: 'string' },
				blockName: { type: 'string' },
			},
		},
		annotations: { readonly: false },
		callback: executeInsertBlock,
	} );
}
