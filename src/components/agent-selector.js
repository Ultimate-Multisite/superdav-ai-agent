/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Agent selector dropdown for the chat header.
 *
 * Fetches the list of enabled agents and lets the user switch between them.
 * Selecting an agent causes subsequent messages to use that agent's
 * system prompt, model, and tier 1 tool overrides.
 *
 * @param {Object}  props           - Component props.
 * @param {boolean} [props.compact] - Render in compact mode.
 * @return {JSX.Element|null} Agent selector or null when fewer than 2 agents.
 */
export default function AgentSelector( { compact = false } ) {
	const { fetchAgents, setSelectedAgentId } = useDispatch( STORE_NAME );

	const { agents, agentsLoaded, selectedAgentId } = useSelect(
		( select ) => ( {
			agents: select( STORE_NAME ).getAgents(),
			agentsLoaded: select( STORE_NAME ).getAgentsLoaded(),
			selectedAgentId: select( STORE_NAME ).getSelectedAgentId(),
		} ),
		[]
	);

	useEffect( () => {
		if ( ! agentsLoaded ) {
			fetchAgents();
		}
	}, [ agentsLoaded, fetchAgents ] );

	// Only render when there are multiple agents to choose from.
	const enabledAgents = agents.filter( ( a ) => a.enabled );
	if ( ! agentsLoaded || enabledAgents.length < 2 ) {
		return null;
	}

	const options = enabledAgents.map( ( a ) => ( {
		label: a.name,
		value: String( a.id ),
	} ) );

	return (
		<div
			className={ `sd-ai-agent-selector ${
				compact ? 'is-compact' : ''
			}` }
		>
			<SelectControl
				label={ __( 'Agent', 'sd-ai-agent' ) }
				hideLabelFromVision={ compact }
				value={ selectedAgentId ? String( selectedAgentId ) : '' }
				options={ options }
				onChange={ ( v ) =>
					setSelectedAgentId( v ? parseInt( v, 10 ) : null )
				}
				__nextHasNoMarginBottom
				size={ compact ? 'compact' : 'default' }
			/>
		</div>
	);
}
