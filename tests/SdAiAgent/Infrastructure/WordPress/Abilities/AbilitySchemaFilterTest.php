<?php

declare(strict_types=1);
/**
 * Tests for the central ability registration filter.
 *
 * @package SdAiAgent\Tests\Infrastructure\WordPress\Abilities
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Infrastructure\WordPress\Abilities;

use SdAiAgent\Abilities\GetThemesAbility;
use SdAiAgent\Infrastructure\Schema\EmptyJsonObject;
use SdAiAgent\Infrastructure\WordPress\Abilities\AbilitySchemaFilter;
use WP_Ability;
use WP_UnitTestCase;

final class AbilitySchemaFilterTest extends WP_UnitTestCase {

	/** First-party class registrations receive the category core validates before instantiation. */
	public function test_first_party_ability_class_receives_plugin_category(): void {
		$result = ( new AbilitySchemaFilter() )->normalize_ability_args(
			array(
				'ability_class' => GetThemesAbility::class,
			)
		);

		$this->assertSame( 'sd-ai-agent', $result['category'] );
		$this->assertSame( 'object', $result['input_schema']['type'] );
		$this->assertInstanceOf( EmptyJsonObject::class, $result['input_schema']['properties'] );
	}

	/** Explicit categories and unrelated registrations remain under caller control. */
	public function test_filter_preserves_explicit_and_third_party_categories(): void {
		$filter = new AbilitySchemaFilter();

		$explicit = $filter->normalize_ability_args(
			array(
				'ability_class' => GetThemesAbility::class,
				'category'      => 'custom-category',
			)
		);
		$this->assertSame( 'custom-category', $explicit['category'] );

		$third_party = $filter->normalize_ability_args(
			array(
				'ability_class' => WP_Ability::class,
			)
		);
		$this->assertArrayNotHasKey( 'category', $third_party );
	}
}
