/**
 * Unit tests for white-label branding helpers.
 */

import {
	getBranding,
	getBrandingCssVariables,
	getBrandingStyle,
} from '../branding';

describe( 'branding helpers', () => {
	afterEach( () => {
		delete window.sdAiAgentBranding;
	} );

	test( 'normalizes localized greetingMessage to greeting', () => {
		window.sdAiAgentBranding = {
			agentName: 'Crowds AI',
			greetingMessage: 'Hey! How are you?',
		};

		expect( getBranding() ).toMatchObject( {
			agentName: 'Crowds AI',
			greeting: 'Hey! How are you?',
			greetingMessage: 'Hey! How are you?',
		} );
	} );

	test( 'maps brand colors to floating and shared chat tokens', () => {
		window.sdAiAgentBranding = {
			primaryColor: '#14558a',
			textColor: '#ffffff',
		};

		expect( getBrandingCssVariables() ).toMatchObject( {
			'--sdaa-w-primary': '#14558a',
			'--sdaa-w-primary-dark': '#14558a',
			'--sdaa-w-on-primary': '#ffffff',
			'--sdaa-primary': '#14558a',
			'--sdaa-primary-dark': '#14558a',
			'--sdaa-on-primary': '#ffffff',
		} );
		expect( getBrandingStyle() ).toMatchObject( {
			background: '#14558a',
			color: '#ffffff',
		} );
	} );
} );
