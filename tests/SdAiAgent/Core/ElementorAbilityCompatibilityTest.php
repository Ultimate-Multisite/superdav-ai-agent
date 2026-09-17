<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\ElementorAbilityCompatibility;
use SdAiAgent\Core\Settings;
use WP_UnitTestCase;

/**
 * Covers runtime classification of Elementor's official ability catalog.
 */
class ElementorAbilityCompatibilityTest extends WP_UnitTestCase {

	/**
	 * Ability IDs registered by a test and removed in tear_down().
	 *
	 * @var string[]
	 */
	private array $registered_abilities = array();

	/** Whether this test registered the Elementor ability category. */
	private bool $registered_elementor_category = false;

	public function set_up(): void {
		parent::set_up();
		update_option( Settings::OPTION_NAME, array( 'third_party_mode' => 'auto' ) );
	}

	public function tear_down(): void {
		if ( function_exists( 'wp_unregister_ability' ) ) {
			foreach ( $this->registered_abilities as $ability_id ) {
				wp_unregister_ability( $ability_id );
			}
		}
		$this->registered_abilities = array();
		if ( $this->registered_elementor_category && function_exists( 'wp_unregister_ability_category' ) ) {
			wp_unregister_ability_category( 'elementor' );
		}
		$this->registered_elementor_category = false;
		delete_option( Settings::OPTION_NAME );
		parent::tear_down();
	}

	public function test_no_elementor_abilities_reports_explicit_limitations(): void {
		$report = ElementorAbilityCompatibility::get_report();

		$this->assertFalse( $report['document_listing']['available'] );
		$this->assertFalse( $report['document_listing']['visible'] );
		$this->assertSame( 'elementor/list-posts', $report['document_listing']['ability_id'] );
		$this->assertNotSame( '', $report['element_mutation']['limitation'] );
	}

	public function test_partial_catalog_only_enables_registered_workflows(): void {
		$this->register_elementor_ability( 'elementor/list-posts' );
		$this->register_elementor_ability( 'elementor/get-page-structure' );

		$report = ElementorAbilityCompatibility::get_report();

		$this->assertTrue( $report['document_listing']['available'] );
		$this->assertTrue( $report['structure_reading']['available'] );
		$this->assertFalse( $report['element_mutation']['available'] );
		$this->assertStringContainsString( 'not registered', $report['element_mutation']['limitation'] );
	}

	public function test_full_catalog_enables_every_document_workflow(): void {
		foreach (
			array(
				'elementor/list-posts',
				'elementor/create-page',
				'elementor/get-page-structure',
				'elementor/update-page-settings',
				'elementor/manage-elements',
				'elementor/build-composition',
				'elementor/create-preview-link',
				'elementor/publish-document',
			) as $ability_id
		) {
			$this->register_elementor_ability( $ability_id );
		}

		$report = ElementorAbilityCompatibility::get_report();
		foreach ( $report as $capability ) {
			$this->assertTrue( $capability['available'], $capability['ability_id'] );
			$this->assertTrue( $capability['visible'], $capability['ability_id'] );
			$this->assertSame( '', $capability['limitation'], $capability['ability_id'] );
		}
	}

	public function test_hidden_ability_remains_unavailable_in_strict_mode(): void {
		$this->register_elementor_ability(
			'elementor/manage-elements',
			array( 'ai_hidden' => true )
		);

		$report = ElementorAbilityCompatibility::get_report();

		$this->assertFalse( $report['element_mutation']['available'] );
		$this->assertFalse( $report['element_mutation']['visible'] );
		$this->assertStringContainsString( 'visibility policy', $report['element_mutation']['limitation'] );
	}

	public function test_callable_policy_keeps_visible_but_permission_denied_ability_unavailable(): void {
		$this->register_elementor_ability( 'elementor/manage-elements' );

		$report = ElementorAbilityCompatibility::get_report( array() );

		$this->assertFalse( $report['element_mutation']['available'] );
		$this->assertTrue( $report['element_mutation']['visible'] );
		$this->assertStringContainsString( 'permission policy', $report['element_mutation']['limitation'] );
	}

	/**
	 * Register a minimal official-looking ability for the current test.
	 *
	 * @param string              $ability_id Ability name.
	 * @param array<string,mixed> $meta Ability metadata.
	 */
	private function register_elementor_ability( string $ability_id, array $meta = array() ): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->fail( 'wp_register_ability() is not available.' );
		}

		$this->ensure_elementor_category();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress hook stack global.
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init';

		try {
			$ability = wp_register_ability(
				$ability_id,
				array(
					'label'               => 'Elementor test ability',
					'description'         => 'A registered official Elementor ability.',
					'category'            => 'elementor',
					'execute_callback'    => '__return_true',
					'permission_callback' => '__return_true',
					'meta'                => $meta,
				)
			);
			$this->assertInstanceOf( \WP_Ability::class, $ability );
		} finally {
			array_pop( $wp_current_filter );
		}

		$this->registered_abilities[] = $ability_id;
	}

	/** Register the category required by WordPress before registering test abilities. */
	private function ensure_elementor_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ! function_exists( 'wp_has_ability_category' ) ) {
			return;
		}

		if ( wp_has_ability_category( 'elementor' ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Standard WordPress hook stack global.
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init';

		try {
			wp_register_ability_category(
				'elementor',
				array(
					'label'       => 'Elementor',
					'description' => 'Elementor test abilities.',
				)
			);
		} finally {
			array_pop( $wp_current_filter );
		}

		$this->registered_elementor_category = true;
	}
}
