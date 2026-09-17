/**
 * Elementor V2 editor package entry point.
 *
 * Elementor documents the `window.elementorV2.{camelCasePackage}.init()`
 * contract for editor packages. The PHP handler loads this external bundle only
 * after Elementor's public editor-mcp package is registered.
 */

import { registerCategory } from '../abilities/registry';
import {
	installElementorEditorMcpBridge,
	registerElementorEditorMcpAbilities,
} from '../abilities/elementor-editor-mcp';

const BRIDGE_REGISTRATION_KEY = '__sdAiAgentElementorEditorMcpRegistration';

/**
 * Register bridge abilities only from Elementor's optional editor package.
 *
 * @return {Promise<void>} Bridge ability registration completion.
 */
function registerBridgeAbilities() {
	if ( window[ BRIDGE_REGISTRATION_KEY ] ) {
		return window[ BRIDGE_REGISTRATION_KEY ];
	}

	window[ BRIDGE_REGISTRATION_KEY ] = ( async () => {
		await registerCategory();
		await registerElementorEditorMcpAbilities();
	} )().catch( () => undefined );

	return window[ BRIDGE_REGISTRATION_KEY ];
}

/**
 * Install the adapter through the documented external-package init contract.
 *
 * @return {boolean} Whether the bridge installed successfully.
 */
export function init() {
	const installed = installElementorEditorMcpBridge();
	if ( installed ) {
		registerBridgeAbilities();
	}

	return installed;
}

if ( typeof window !== 'undefined' && window.elementorV2 ) {
	window.elementorV2.sdAiAgentElementorMcp = { init };
	init();
}
