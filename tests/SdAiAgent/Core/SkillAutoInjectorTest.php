<?php
/**
 * Test case for SkillAutoInjector.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\SkillAutoInjector;
use SdAiAgent\Models\Skill;
use WP_UnitTestCase;

/**
 * Tests for SkillAutoInjector — inject_for_message() and get_index_description().
 */
class SkillAutoInjectorTest extends WP_UnitTestCase {

	/**
	 * Ensure built-in skills are seeded and enabled before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Skill::seed_builtins();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'UPDATE ' . Skill::table_name() . ' SET enabled = 1 WHERE is_builtin = 1' );
	}

	// ─── inject_for_message() ─────────────────────────────────────────

	/**
	 * Empty message returns empty string.
	 */
	public function test_inject_empty_message_returns_empty(): void {
		$this->assertSame( '', SkillAutoInjector::inject_for_message( '' ) );
		$this->assertSame( '', SkillAutoInjector::inject_for_message( '   ' ) );
	}

	/**
	 * A message with no keyword matches returns empty string.
	 */
	public function test_inject_no_match_returns_empty(): void {
		$result = SkillAutoInjector::inject_for_message( 'Tell me a joke about penguins.' );
		$this->assertSame( '', $result );
	}

	/**
	 * A WooCommerce message injects the WooCommerce skill section.
	 */
	public function test_inject_woocommerce_message_injects_skill(): void {
		// Enable WooCommerce skill explicitly for this test.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Skill::table_name() . " SET enabled = 1 WHERE slug = %s", 'woocommerce' ) );

		$result = SkillAutoInjector::inject_for_message( 'How do I add a product to my WooCommerce store?' );

		$this->assertStringContainsString( 'Active Skill Guide', $result );
	}

	/**
	 * A Gutenberg-related message injects the gutenberg-blocks skill.
	 */
	public function test_inject_gutenberg_message_injects_skill(): void {
		$result = SkillAutoInjector::inject_for_message( 'Create a landing page with Gutenberg blocks' );

		$this->assertStringContainsString( 'Active Skill Guide', $result );
	}

	/**
	 * MAX_INJECTED_SKILLS is 1 — two pattern matches should still inject
	 * at most one skill.
	 */
	public function test_inject_caps_at_one_skill(): void {
		// This message triggers both seo-optimization AND analytics-reporting.
		// With MAX_INJECTED_SKILLS = 1, only the first match should be injected.
		$result = SkillAutoInjector::inject_for_message(
			'Can you audit my SEO keywords and generate an analytics report for growth metrics?'
		);

		// Result should have exactly one "Active Skill Guide" section header.
		$count = substr_count( $result, '## Active Skill Guide' );
		$this->assertLessThanOrEqual( 1, $count, 'inject_for_message() must inject at most 1 skill (MAX_INJECTED_SKILLS=1).' );
	}

	// ─── get_index_description() ──────────────────────────────────────

	/**
	 * Empty message returns empty string.
	 */
	public function test_get_index_description_empty_message_returns_empty(): void {
		$this->assertSame( '', SkillAutoInjector::get_index_description( '' ) );
		$this->assertSame( '', SkillAutoInjector::get_index_description( '   ' ) );
	}

	/**
	 * A message with no keyword matches returns empty string.
	 */
	public function test_get_index_description_no_match_returns_empty(): void {
		$result = SkillAutoInjector::get_index_description( 'What is the capital of France?' );
		$this->assertSame( '', $result );
	}

	/**
	 * A matching message returns a non-empty hint mentioning the skill slug.
	 */
	public function test_get_index_description_returns_hint_for_match(): void {
		$result = SkillAutoInjector::get_index_description( 'How do I debug a fatal error on my site?' );

		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'site-troubleshooting', $result );
		$this->assertStringContainsString( 'skill-load', $result );
	}

	/**
	 * Hint for a Gutenberg message contains the correct slug.
	 */
	public function test_get_index_description_contains_gutenberg_slug(): void {
		$result = SkillAutoInjector::get_index_description( 'Build a page layout with blocks and columns' );

		$this->assertStringContainsString( 'gutenberg-blocks', $result );
	}

	/**
	 * get_index_description() hint is significantly shorter than the full injection.
	 *
	 * The whole point of the strong-model path is to avoid injecting 1 500+
	 * tokens. Verify the hint is much shorter than inject_for_message().
	 */
	public function test_get_index_description_shorter_than_full_injection(): void {
		$message = 'Create a landing page with Gutenberg blocks';

		$full_injection = SkillAutoInjector::inject_for_message( $message );
		$hint           = SkillAutoInjector::get_index_description( $message );

		if ( '' === $full_injection ) {
			$this->markTestSkipped( 'Full injection returned empty — built-in skill content missing.' );
		}

		$this->assertGreaterThan( strlen( $hint ), strlen( $full_injection ), 'Full injection must be longer than the index hint.' );
		$this->assertLessThan( 200, strlen( $hint ), 'Index hint should be under 200 characters (got ' . strlen( $hint ) . ').' );
	}
}
