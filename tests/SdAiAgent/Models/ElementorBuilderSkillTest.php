<?php

declare(strict_types=1);

/**
 * Tests for the bundled Elementor Builder skill.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Models;

use SdAiAgent\Models\Skill;
use WP_UnitTestCase;

/** Verifies the bundled Elementor Builder skill remains available to agents. */
class ElementorBuilderSkillTest extends WP_UnitTestCase {

	public function test_elementor_builder_skill_is_registered_as_builtin(): void {
		$definitions = Skill::get_builtin_definitions();

		$this->assertArrayHasKey( 'elementor-builder', $definitions );
		$this->assertSame( 'Elementor Builder', $definitions['elementor-builder']['name'] );
		$this->assertFalse( $definitions['elementor-builder']['enabled'] );
	}

	public function test_elementor_builder_skill_documents_the_official_ability_workflow(): void {
		$content = Skill::get_builtin_definitions()['elementor-builder']['content'];

		foreach ( [
			'sd-ai-agent/ability-search',
			'elementor/get-page-structure',
			'elementor/manage-elements',
			'elementor/build-composition',
			'Re-read after composition',
			'Preview before publication',
			'_elementor_data',
			'generic post-meta mutation',
			'Gutenberg block-tree',
			'conversational widget mutation is unavailable',
		] as $required ) {
			$this->assertStringContainsString( $required, $content );
		}
	}
}
