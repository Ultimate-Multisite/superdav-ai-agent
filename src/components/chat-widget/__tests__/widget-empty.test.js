/**
 * Unit tests for the floating widget empty state.
 */

import { createElement, createRoot } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { act } from 'react';

import WidgetEmpty from '../widget-empty';

global.IS_REACT_ACT_ENVIRONMENT = true;

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '../../../store', () => 'sd-ai-agent' );
jest.mock( '../../../utils/branding', () => ( {
	getBranding: () => ( {} ),
} ) );

/**
 * Render the empty state with the requested selected agent.
 *
 * @param {Object|null} selectedAgent Selected agent data.
 * @return {Promise<{container: HTMLElement, root: import('@wordpress/element').Root}>}
 *   Rendered container and root.
 */
async function renderEmptyState( selectedAgent ) {
	useSelect.mockImplementation( ( callback ) =>
		callback( () => ( { getSelectedAgent: () => selectedAgent } ) )
	);
	useDispatch.mockReturnValue( { sendMessage: jest.fn() } );

	const container = document.createElement( 'div' );
	const root = createRoot( container );

	await act( async () => {
		root.render( createElement( WidgetEmpty ) );
	} );

	return { container, root };
}

describe( 'WidgetEmpty suggestions', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'suppresses suggestion UI for an explicitly empty agent list', async () => {
		const { container, root } = await renderEmptyState( {
			name: 'Setup Assistant',
			suggestions: [],
		} );

		expect( container.querySelector( '.sdaa-w-empty-label' ) ).toBeNull();
		expect(
			container.querySelector( '.sdaa-w-suggestion-list' )
		).toBeNull();

		await act( async () => root.unmount() );
	} );

	test( 'keeps defaults when no agent-specific list is available', async () => {
		const { container, root } = await renderEmptyState( null );

		expect(
			container.querySelectorAll( '.sdaa-w-suggestion-card' )
		).toHaveLength( 4 );

		await act( async () => root.unmount() );
	} );
} );
