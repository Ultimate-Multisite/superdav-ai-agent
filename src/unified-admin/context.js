/**
 * WordPress dependencies
 */
import { createContext, useContext } from '@wordpress/element';

const AppContext = createContext( null );

export const AppProvider = AppContext.Provider;

/**
 * Use the app context.
 *
 * @return {Object} App context value.
 */
export function useApp() {
	const context = useContext( AppContext );
	if ( ! context ) {
		throw new Error( 'useApp must be used within an AppProvider' );
	}
	return context;
}

export default AppContext;
