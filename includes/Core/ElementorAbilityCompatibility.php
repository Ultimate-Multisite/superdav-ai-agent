<?php

declare(strict_types=1);
/**
 * Runtime compatibility report for Elementor's official WordPress abilities.
 *
 * Elementor releases its abilities independently of its plugin version. This
 * class therefore reports only the official IDs registered for this request;
 * it never inspects Elementor internals, enables experiments, or executes an
 * ability while discovering the available workflow.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies the official Elementor ability catalog registered at runtime.
 */
final class ElementorAbilityCompatibility {

	/**
	 * Official Elementor ability IDs grouped by the workflows Superdav can
	 * describe to an agent. The keys are stable semantic capabilities rather
	 * than Elementor release or experiment names.
	 *
	 * @var array<string, array{ability_id: string, missing_reason: string}>
	 */
	private const CAPABILITIES = array(
		'document_listing'      => array(
			'ability_id'     => 'elementor/list-posts',
			'missing_reason' => 'Elementor document listing is unavailable because its official ability is not registered.',
		),
		'document_creation'     => array(
			'ability_id'     => 'elementor/create-page',
			'missing_reason' => 'Elementor document creation is unavailable because its official ability is not registered.',
		),
		'structure_reading'     => array(
			'ability_id'     => 'elementor/get-page-structure',
			'missing_reason' => 'Elementor structure reading is unavailable because its official ability is not registered.',
		),
		'document_settings'     => array(
			'ability_id'     => 'elementor/update-page-settings',
			'missing_reason' => 'Elementor document settings are unavailable because its official ability is not registered.',
		),
		'element_mutation'      => array(
			'ability_id'     => 'elementor/manage-elements',
			'missing_reason' => 'Elementor element editing is unavailable because its official ability is not registered.',
		),
		'composition_building'  => array(
			'ability_id'     => 'elementor/build-composition',
			'missing_reason' => 'Elementor composition building is unavailable because its official ability is not registered.',
		),
		'preview_link_creation' => array(
			'ability_id'     => 'elementor/create-preview-link',
			'missing_reason' => 'Elementor preview-link creation is unavailable because its official ability is not registered.',
		),
		'publication'           => array(
			'ability_id'     => 'elementor/publish-document',
			'missing_reason' => 'Elementor publication is unavailable because its official ability is not registered.',
		),
	);

	/**
	 * Build a compatibility report from the registered ability catalog.
	 *
	 * A caller that already applied additional agent policy (role, tool, or
	 * anonymous-chat restrictions) can pass its callable IDs. The report then
	 * preserves the independent visibility result while preventing an ability
	 * from being presented as callable when that policy denies it.
	 *
	 * @param string[]|null $callable_ability_ids IDs callable by the current agent, or null when no additional policy has been applied.
	 * @return array<string, array{available: bool, ability_id: string, visible: bool, limitation: string}>
	 */
	public static function get_report( ?array $callable_ability_ids = null ): array {
		$abilities = self::registered_abilities();
		$callable  = null;
		if ( null !== $callable_ability_ids ) {
			$callable = array_fill_keys( $callable_ability_ids, true );
		}

		$report = array();
		foreach ( self::CAPABILITIES as $capability => $definition ) {
			$ability_id = $definition['ability_id'];
			$ability    = $abilities[ $ability_id ] ?? null;

			if ( ! $ability instanceof \WP_Ability ) {
				$report[ $capability ] = array(
					'available'  => false,
					'ability_id' => $ability_id,
					'visible'    => false,
					'limitation' => $definition['missing_reason'],
				);
				continue;
			}

			$visible = AbilityVisibility::for_ai_chat( $ability );
			if ( ! $visible ) {
				$report[ $capability ] = array(
					'available'  => false,
					'ability_id' => $ability_id,
					'visible'    => false,
					'limitation' => 'The registered Elementor ability is hidden by the site visibility policy.',
				);
				continue;
			}

			if ( null !== $callable && ! isset( $callable[ $ability_id ] ) ) {
				$report[ $capability ] = array(
					'available'  => false,
					'ability_id' => $ability_id,
					'visible'    => true,
					'limitation' => 'The registered Elementor ability is unavailable to the current agent permission policy.',
				);
				continue;
			}

			$report[ $capability ] = array(
				'available'  => true,
				'ability_id' => $ability_id,
				'visible'    => true,
				'limitation' => '',
			);
		}

		return $report;
	}

	/**
	 * Index the current WordPress ability registry without probing unknown IDs.
	 *
	 * @return array<string, \WP_Ability>
	 */
	private static function registered_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$registered = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( $ability instanceof \WP_Ability ) {
				$registered[ $ability->get_name() ] = $ability;
			}
		}

		return $registered;
	}
}
