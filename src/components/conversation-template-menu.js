/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, close } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Category labels for display.
 */
const CATEGORY_LABELS = {
	general: __( 'General', 'gratis-ai-agent' ),
	content: __( 'Content', 'gratis-ai-agent' ),
	writing: __( 'Writing', 'gratis-ai-agent' ),
	development: __( 'Development', 'gratis-ai-agent' ),
	seo: __( 'SEO', 'gratis-ai-agent' ),
};

/**
 * A single template card.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.template Template object from the REST API.
 * @param {Function} props.onSelect Called with the template prompt when selected.
 */
function TemplateCard( { template, onSelect } ) {
	const descId = template.id
		? `gratis-ai-agent-template-desc-${ template.id }`
		: undefined;
	return (
		<button
			type="button"
			className="gratis-ai-agent-template-card"
			onClick={ () => onSelect( template ) }
			aria-describedby={ template.description ? descId : undefined }
		>
			<span className="gratis-ai-agent-template-card__name">
				{ template.name }
			</span>
			{ template.description && (
				<span
					id={ descId }
					className="gratis-ai-agent-template-card__desc"
				>
					{ template.description }
				</span>
			) }
		</button>
	);
}

/**
 * Conversation template menu.
 *
 * Renders a panel of pre-built prompt templates grouped by category.
 * Selecting a template inserts its prompt text into the message input.
 *
 * @param {Object}   props          Component props.
 * @param {Function} props.onSelect Called with the prompt string when a template is selected.
 * @param {Function} props.onClose  Called when the panel should be closed.
 */
export default function ConversationTemplateMenu( { onSelect, onClose } ) {
	const [ activeCategory, setActiveCategory ] = useState( '' );

	const { templates, loaded } = useSelect(
		( select ) => ( {
			templates: select( STORE_NAME ).getConversationTemplates(),
			loaded: select( STORE_NAME ).getConversationTemplatesLoaded(),
		} ),
		[]
	);

	const { fetchConversationTemplates } = useDispatch( STORE_NAME );

	// Fetch templates on mount if not already loaded.
	useEffect( () => {
		if ( ! loaded ) {
			fetchConversationTemplates();
		}
	}, [ loaded, fetchConversationTemplates ] );

	// Derive unique categories from loaded templates.
	const categories = [
		{ value: '', label: __( 'All', 'gratis-ai-agent' ) },
		...[ ...new Set( templates.map( ( t ) => t.category ) ) ]
			.sort()
			.map( ( cat ) => ( {
				value: cat,
				label: CATEGORY_LABELS[ cat ] || cat,
			} ) ),
	];

	const filtered = activeCategory
		? templates.filter( ( t ) => t.category === activeCategory )
		: templates;

	const handleSelect = useCallback(
		( template ) => {
			onSelect( template.prompt );
			onClose();
		},
		[ onSelect, onClose ]
	);

	return (
		<div
			className="gratis-ai-agent-template-menu"
			role="dialog"
			aria-label={ __( 'Conversation templates', 'gratis-ai-agent' ) }
		>
			<div className="gratis-ai-agent-template-menu__header">
				<span className="gratis-ai-agent-template-menu__title">
					{ __( 'Templates', 'gratis-ai-agent' ) }
				</span>
				<Button
					icon={ <Icon icon={ close } /> }
					label={ __( 'Close templates', 'gratis-ai-agent' ) }
					onClick={ onClose }
					className="gratis-ai-agent-template-menu__close"
					isSmall
				/>
			</div>

			{ categories.length > 2 && (
				<div
					className="gratis-ai-agent-template-menu__categories"
					role="tablist"
				>
					{ categories.map( ( cat ) => (
						<button
							key={ cat.value }
							type="button"
							role="tab"
							aria-selected={ activeCategory === cat.value }
							className={ `gratis-ai-agent-template-menu__cat-tab ${
								activeCategory === cat.value ? 'is-active' : ''
							}` }
							onClick={ () => setActiveCategory( cat.value ) }
						>
							{ cat.label }
						</button>
					) ) }
				</div>
			) }

			<div className="gratis-ai-agent-template-menu__grid">
				{ ! loaded && (
					<div className="gratis-ai-agent-template-menu__loading">
						<Spinner />
					</div>
				) }
				{ loaded && filtered.length === 0 && (
					<p className="gratis-ai-agent-template-menu__empty">
						{ __( 'No templates found.', 'gratis-ai-agent' ) }
					</p>
				) }
				{ loaded &&
					filtered.map( ( template ) => (
						<TemplateCard
							key={ template.id }
							template={ template }
							onSelect={ handleSelect }
						/>
					) ) }
			</div>
		</div>
	);
}
