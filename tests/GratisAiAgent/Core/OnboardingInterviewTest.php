<?php

declare(strict_types=1);
/**
 * Test case for OnboardingInterview class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\OnboardingInterview;
use GratisAiAgent\Core\SiteScanner;
use WP_UnitTestCase;

/**
 * Test OnboardingInterview functionality.
 */
class OnboardingInterviewTest extends WP_UnitTestCase {

	/**
	 * Reset interview state before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		OnboardingInterview::reset();
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i',
				$wpdb->prefix . 'gratis_ai_agent_memories'
			)
		);
	}

	/**
	 * Reset interview state after each test.
	 */
	public function tear_down(): void {
		OnboardingInterview::reset();
		delete_option( SiteScanner::STATUS_OPTION );
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i',
				$wpdb->prefix . 'gratis_ai_agent_memories'
			)
		);
		parent::tear_down();
	}

	// ── constants ─────────────────────────────────────────────────────────

	/**
	 * COMPLETE_OPTION constant is defined.
	 */
	public function test_complete_option_constant_is_defined(): void {
		$this->assertSame( 'gratis_ai_agent_interview_complete', OnboardingInterview::COMPLETE_OPTION );
	}

	/**
	 * SKIPPED_OPTION constant is defined.
	 */
	public function test_skipped_option_constant_is_defined(): void {
		$this->assertSame( 'gratis_ai_agent_interview_skipped', OnboardingInterview::SKIPPED_OPTION );
	}

	// ── is_done ───────────────────────────────────────────────────────────

	/**
	 * is_done() returns false when neither option is set.
	 */
	public function test_is_done_returns_false_initially(): void {
		$this->assertFalse( OnboardingInterview::is_done() );
	}

	/**
	 * is_done() returns true after mark_complete().
	 */
	public function test_is_done_returns_true_after_mark_complete(): void {
		OnboardingInterview::mark_complete();
		$this->assertTrue( OnboardingInterview::is_done() );
	}

	/**
	 * is_done() returns true after mark_skipped().
	 */
	public function test_is_done_returns_true_after_mark_skipped(): void {
		OnboardingInterview::mark_skipped();
		$this->assertTrue( OnboardingInterview::is_done() );
	}

	// ── mark_complete / mark_skipped / reset ──────────────────────────────

	/**
	 * mark_complete() sets the complete option.
	 */
	public function test_mark_complete_sets_option(): void {
		OnboardingInterview::mark_complete();
		$this->assertTrue( (bool) get_option( OnboardingInterview::COMPLETE_OPTION ) );
	}

	/**
	 * mark_skipped() sets the skipped option.
	 */
	public function test_mark_skipped_sets_option(): void {
		OnboardingInterview::mark_skipped();
		$this->assertTrue( (bool) get_option( OnboardingInterview::SKIPPED_OPTION ) );
	}

	/**
	 * reset() clears both options.
	 */
	public function test_reset_clears_both_options(): void {
		OnboardingInterview::mark_complete();
		OnboardingInterview::mark_skipped();

		OnboardingInterview::reset();

		$this->assertFalse( (bool) get_option( OnboardingInterview::COMPLETE_OPTION ) );
		$this->assertFalse( (bool) get_option( OnboardingInterview::SKIPPED_OPTION ) );
		$this->assertFalse( OnboardingInterview::is_done() );
	}

	// ── is_ready ──────────────────────────────────────────────────────────

	/**
	 * is_ready() returns false when scan is not complete.
	 */
	public function test_is_ready_returns_false_when_scan_not_complete(): void {
		delete_option( SiteScanner::STATUS_OPTION );
		$this->assertFalse( OnboardingInterview::is_ready() );
	}

	/**
	 * is_ready() returns false when scan is complete but interview is done.
	 */
	public function test_is_ready_returns_false_when_interview_done(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete' ] );
		OnboardingInterview::mark_complete();

		$this->assertFalse( OnboardingInterview::is_ready() );
	}

	/**
	 * is_ready() returns true when scan is complete and interview is not done.
	 */
	public function test_is_ready_returns_true_when_scan_complete_and_not_done(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete' ] );

		$this->assertTrue( OnboardingInterview::is_ready() );
	}

	// ── get_questions ─────────────────────────────────────────────────────

	/**
	 * get_questions() returns a non-empty array.
	 */
	public function test_get_questions_returns_non_empty_array(): void {
		$questions = OnboardingInterview::get_questions();
		$this->assertIsArray( $questions );
		$this->assertNotEmpty( $questions );
	}

	/**
	 * get_questions() always includes core questions.
	 */
	public function test_get_questions_includes_core_questions(): void {
		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'primary_goal', $ids );
		$this->assertContains( 'target_audience', $ids );
		$this->assertContains( 'content_tone', $ids );
		$this->assertContains( 'automation_interest', $ids );
	}

	/**
	 * get_questions() returns questions with required fields.
	 */
	public function test_get_questions_have_required_fields(): void {
		$questions = OnboardingInterview::get_questions();

		foreach ( $questions as $q ) {
			$this->assertArrayHasKey( 'id', $q );
			$this->assertArrayHasKey( 'question', $q );
			$this->assertArrayHasKey( 'placeholder', $q );
			$this->assertArrayHasKey( 'memory_category', $q );
			$this->assertArrayHasKey( 'required', $q );
		}
	}

	/**
	 * get_questions() returns ecommerce-specific questions for ecommerce site type.
	 */
	public function test_get_questions_includes_ecommerce_questions_for_ecommerce_type(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete', 'site_type' => 'ecommerce' ] );

		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'ecommerce_focus', $ids );
		$this->assertContains( 'ecommerce_automations', $ids );
	}

	/**
	 * get_questions() returns blog-specific questions for blog site type.
	 */
	public function test_get_questions_includes_blog_questions_for_blog_type(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete', 'site_type' => 'blog' ] );

		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'blog_topics', $ids );
		$this->assertContains( 'blog_frequency', $ids );
	}

	/**
	 * get_questions() returns lms-specific questions for lms site type.
	 */
	public function test_get_questions_includes_lms_questions_for_lms_type(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete', 'site_type' => 'lms' ] );

		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'lms_subject', $ids );
		$this->assertContains( 'lms_automations', $ids );
	}

	/**
	 * get_questions() returns membership-specific questions for membership site type.
	 */
	public function test_get_questions_includes_membership_questions_for_membership_type(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete', 'site_type' => 'membership' ] );

		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'membership_value', $ids );
		$this->assertContains( 'membership_automations', $ids );
	}

	/**
	 * get_questions() returns portfolio-specific questions for portfolio site type.
	 */
	public function test_get_questions_includes_portfolio_questions_for_portfolio_type(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete', 'site_type' => 'portfolio' ] );

		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'portfolio_services', $ids );
	}

	/**
	 * get_questions() returns brochure-specific questions for brochure site type.
	 */
	public function test_get_questions_includes_brochure_questions_for_brochure_type(): void {
		update_option( SiteScanner::STATUS_OPTION, [ 'status' => 'complete', 'site_type' => 'brochure' ] );

		$questions = OnboardingInterview::get_questions();
		$ids       = array_column( $questions, 'id' );

		$this->assertContains( 'key_pages', $ids );
	}

	/**
	 * get_questions() primary_goal is required.
	 */
	public function test_primary_goal_question_is_required(): void {
		$questions = OnboardingInterview::get_questions();

		foreach ( $questions as $q ) {
			if ( 'primary_goal' === $q['id'] ) {
				$this->assertTrue( $q['required'] );
				return;
			}
		}

		$this->fail( 'primary_goal question not found' );
	}

	/**
	 * get_questions() content_tone is not required.
	 */
	public function test_content_tone_question_is_not_required(): void {
		$questions = OnboardingInterview::get_questions();

		foreach ( $questions as $q ) {
			if ( 'content_tone' === $q['id'] ) {
				$this->assertFalse( $q['required'] );
				return;
			}
		}

		$this->fail( 'content_tone question not found' );
	}

	// ── save_answers ──────────────────────────────────────────────────────

	/**
	 * save_answers() returns false for empty answers.
	 */
	public function test_save_answers_returns_false_for_empty_answers(): void {
		$result = OnboardingInterview::save_answers( [] );
		$this->assertFalse( $result );
	}

	/**
	 * save_answers() returns true on success.
	 */
	public function test_save_answers_returns_true_on_success(): void {
		$result = OnboardingInterview::save_answers( [
			'primary_goal' => 'Generate leads for my business',
		] );

		$this->assertTrue( $result );
	}

	/**
	 * save_answers() marks interview as complete.
	 */
	public function test_save_answers_marks_interview_complete(): void {
		OnboardingInterview::save_answers( [
			'primary_goal' => 'Sell products online',
		] );

		$this->assertTrue( OnboardingInterview::is_done() );
		$this->assertTrue( (bool) get_option( OnboardingInterview::COMPLETE_OPTION ) );
	}

	/**
	 * save_answers() skips blank answers.
	 */
	public function test_save_answers_skips_blank_answers(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'gratis_ai_agent_memories';

		// Only non-blank answers should be stored.
		$result = OnboardingInterview::save_answers( [
			'primary_goal'    => 'Real answer',
			'target_audience' => '   ', // whitespace only — should be skipped.
		] );

		$this->assertTrue( $result );

		// Content is stored as "Label: answer". Verify primary_goal was stored.
		$primary = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT content FROM %i WHERE content LIKE %s",
				$table,
				'%Real answer'
			)
		);
		$this->assertNotNull( $primary );
		$this->assertStringContainsString( 'Real answer', $primary );

		// Verify blank target_audience was not stored (no "Target audience:" row).
		$blank = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE content LIKE %s",
				$table,
				'Target audience:%'
			)
		);
		$this->assertSame( '0', (string) $blank );
	}

	/**
	 * save_answers() stores memories for each non-blank answer.
	 */
	public function test_save_answers_stores_memories(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'gratis_ai_agent_memories';

		OnboardingInterview::save_answers( [
			'primary_goal'    => 'Generate leads',
			'target_audience' => 'Small business owners',
		] );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i",
				$table
			)
		);
		$this->assertGreaterThanOrEqual( 2, $count );

		// Content is stored as "Label: answer". Verify specific memories.
		$primary = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT content FROM %i WHERE content LIKE %s",
				$table,
				'%Generate leads'
			)
		);
		$this->assertNotNull( $primary );
		$this->assertStringContainsString( 'Generate leads', $primary );

		$audience = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT content FROM %i WHERE content LIKE %s",
				$table,
				'%Small business owners'
			)
		);
		$this->assertNotNull( $audience );
		$this->assertStringContainsString( 'Small business owners', $audience );
	}

	/**
	 * save_answers() uses fallback label for unknown question IDs.
	 */
	public function test_save_answers_uses_fallback_label_for_unknown_id(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'gratis_ai_agent_memories';

		$result = OnboardingInterview::save_answers( [
			'unknown_question_id' => 'Some answer',
		] );

		$this->assertTrue( $result );

		// Verify a memory row was inserted with the fallback label.
		// Unknown IDs use ucwords(str_replace('_', ' ', $id)) as label.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE content LIKE %s",
				$table,
				'%Some answer'
			)
		);
		$this->assertNotNull( $row );
		// Verify the fallback label was used (not the question ID verbatim).
		$this->assertStringContainsString( 'Unknown Question Id', $row->content );
	}
}
