/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardHeader,
	CardBody,
	TabPanel,
	Notice,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsApp from '../../settings-page/settings-app';
import ProvidersManager from '../../settings-page/providers-manager';

/**
 * Settings Route Component
 *
 * @param {Object} props          Component props.
 * @param {string} props.subRoute Current sub-route.
 * @return {JSX.Element} Settings route element.
 */
export default function SettingsRoute( { subRoute } ) {
	const initialTab = subRoute === 'advanced' ? 'advanced' : 'general';
	const [ providerKeys, setProviderKeys ] = useState( {} );

	useEffect( () => {
		apiFetch( { path: '/gratis-ai-agent/v1/settings/provider-keys' } )
			.then( ( data ) => setProviderKeys( data || {} ) )
			.catch( () => {} );
	}, [] );

	const tabs = [
		{ name: 'general', title: __( 'General', 'gratis-ai-agent' ) },
		{ name: 'providers', title: __( 'Providers', 'gratis-ai-agent' ) },
		{ name: 'advanced', title: __( 'Advanced', 'gratis-ai-agent' ) },
	];

	return (
		<div className="gratis-ai-agent-route gratis-ai-agent-route-settings">
			<Card>
				<CardHeader>
					<h2>{ __( 'Settings', 'gratis-ai-agent' ) }</h2>
				</CardHeader>
				<CardBody>
					<TabPanel
						className="gratis-ai-agent-settings-tabs"
						activeClass="is-active"
						tabs={ tabs }
						initialTabName={ initialTab }
					>
						{ ( tab ) => {
							switch ( tab.name ) {
								case 'general':
									return <SettingsApp />;
								case 'providers':
									return (
										<ProvidersManager
											providerKeys={ providerKeys }
										/>
									);
								case 'advanced':
									return <AdvancedSettings />;
								default:
									return null;
							}
						} }
					</TabPanel>
				</CardBody>
			</Card>
		</div>
	);
}

/**
 * Advanced Settings Tab.
 *
 * @return {JSX.Element} Advanced settings element.
 */
function AdvancedSettings() {
	return (
		<div className="gratis-ai-advanced-settings">
			<div className="gratis-ai-agent-benchmark-section">
				<h4>{ __( 'Model Benchmark', 'gratis-ai-agent' ) }</h4>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Model Benchmark will be available in a follow-up release.',
						'gratis-ai-agent'
					) }
				</Notice>
			</div>
		</div>
	);
}
