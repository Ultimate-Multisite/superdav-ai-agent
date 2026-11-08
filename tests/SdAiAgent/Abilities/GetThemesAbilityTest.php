<?php

declare(strict_types=1);
/**
 * Test case for GetThemesAbility.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Abilities;

use SdAiAgent\Abilities\GetThemesAbility;
use WP_UnitTestCase;

class GetThemesAbilityTest extends WP_UnitTestCase {

	private function make_ability(): GetThemesAbility {
		return new GetThemesAbility( 'sd-ai-agent/get-themes' );
	}

	public function test_schema_exposes_bounded_continuation_arguments(): void {
		$schema = $this->make_ability()->get_input_schema();

		$this->assertSame( 0, $schema['properties']['offset']['minimum'] );
		$this->assertSame( 100, $schema['properties']['limit']['maximum'] );
	}

	public function test_inventory_is_compact_and_reports_exact_page_metadata(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$ability = $this->make_ability();
		$full    = $ability->run( [] );

		$this->assertIsArray( $full );
		$this->assertSame( count( wp_get_themes() ), $full['total'] );
		$this->assertSame( count( $full['themes'] ), $full['showing'] );
		$this->assertSame( get_stylesheet(), $full['active'] );
		foreach ( $full['themes'] as $theme ) {
			$this->assertSame( [ 'slug', 'name', 'version', 'active' ], array_keys( $theme ) );
		}

		$page = $ability->run(
			[
				'offset' => 0,
				'limit'  => 1,
			]
		);
		$this->assertIsArray( $page );
		$this->assertSame( min( 1, $page['total'] ), $page['showing'] );
		$this->assertSame( $page['total'] > 1, $page['has_more'] );
		$this->assertSame( $page['has_more'] ? 1 : null, $page['next_offset'] );

		$empty_page = $ability->run(
			[
				'offset' => $page['total'],
				'limit'  => 1,
			]
		);
		$this->assertIsArray( $empty_page );
		$this->assertSame( 0, $empty_page['showing'] );
		$this->assertFalse( $empty_page['has_more'] );
		$this->assertNull( $empty_page['next_offset'] );
	}
}
