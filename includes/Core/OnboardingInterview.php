<?php

declare(strict_types=1);
/**
 * Onboarding Interview — asks targeted questions after the site scan completes.
 *
 * Generates a set of questions tailored to the detected site type and stores
 * the user's answers as agent memories so the AI has immediate context about
 * the site's goals, audience, and preferred automations.
 *
 * Flow:
 *  1. SiteScanner completes → scan status = 'complete'.
 *  2. Admin UI polls /onboarding/status and sees scan is done.
 *  3. UI fetches GET /onboarding/interview → receives question list.
 *  4. User answers questions in the chat-style interview UI.
 *  5. UI posts answers to POST /onboarding/interview.
 *  6. Answers are stored as memories; interview marked complete.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Models\Memory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnboardingInterview {

	/**
	 * Option key that records whether the interview has been completed.
	 */
	const COMPLETE_OPTION = 'gratis_ai_agent_interview_complete';

	/**
	 * Option key that records whether the interview has been skipped.
	 */
	const SKIPPED_OPTION = 'gratis_ai_agent_interview_skipped';

	// ── Status helpers ────────────────────────────────────────────────────

	/**
	 * Whether the interview has been completed or skipped.
	 */
	public static function is_done(): bool {
		return (bool) get_option( self::COMPLETE_OPTION )
			|| (bool) get_option( self::SKIPPED_OPTION );
	}

	/**
	 * Whether the interview is ready to be shown.
	 *
	 * Conditions: scan is complete AND interview not yet done.
	 */
	public static function is_ready(): bool {
		return SiteScanner::is_complete() && ! self::is_done();
	}

	/**
	 * Mark the interview as complete.
	 */
	public static function mark_complete(): void {
		update_option( self::COMPLETE_OPTION, true, false );
	}

	/**
	 * Mark the interview as skipped (user dismissed without answering).
	 */
	public static function mark_skipped(): void {
		update_option( self::SKIPPED_OPTION, true, false );
	}

	/**
	 * Reset interview state (allows re-running).
	 */
	public static function reset(): void {
		delete_option( self::COMPLETE_OPTION );
		delete_option( self::SKIPPED_OPTION );
	}

	// ── Question generation ───────────────────────────────────────────────

	/**
	 * Generate interview questions tailored to the detected site type.
	 *
	 * Returns an ordered list of question objects. Each question has:
	 *  - id:          Unique slug used as the memory key.
	 *  - question:    The question text shown to the user.
	 *  - placeholder: Input placeholder hint.
	 *  - memory_category: Which memory category to store the answer in.
	 *  - required:    Whether the question must be answered (vs skippable).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_questions(): array {
		$scan = SiteScanner::get_status();
		$type = $scan['site_type'] ?? 'brochure';
		$woo  = ! empty( $scan['woocommerce_active'] );
		// @phpstan-ignore-next-line
		$count = (int) ( $scan['post_count'] ?? 0 );

		// Core questions asked of every site.
		$questions = [
			[
				'id'              => 'primary_goal',
				'question'        => __(
					'What is the primary goal of this site? (e.g. generate leads, sell products, publish content, build a community)',
					'gratis-ai-agent'
				),
				'placeholder'     => __( 'e.g. Generate leads for my consulting business', 'gratis-ai-agent' ),
				'memory_category' => 'site_info',
				'required'        => true,
			],
			[
				'id'              => 'target_audience',
				'question'        => __(
					'Who is your target audience?',
					'gratis-ai-agent'
				),
				'placeholder'     => __( 'e.g. Small business owners in the UK', 'gratis-ai-agent' ),
				'memory_category' => 'site_info',
				'required'        => true,
			],
			[
				'id'              => 'content_tone',
				'question'        => __(
					'What tone should the AI use when writing content for this site?',
					'gratis-ai-agent'
				),
				'placeholder'     => __( 'e.g. Professional and authoritative, or friendly and conversational', 'gratis-ai-agent' ),
				'memory_category' => 'user_preferences',
				'required'        => false,
			],
		];

		// Site-type-specific questions.
		switch ( $type ) {
			case 'ecommerce':
				$questions[] = [
					'id'              => 'ecommerce_focus',
					'question'        => __(
						'What types of products do you sell, and what is your main sales challenge?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Handmade jewellery — I need help writing product descriptions', 'gratis-ai-agent' ),
					'memory_category' => 'site_info',
					'required'        => false,
				];
				$questions[] = [
					'id'              => 'ecommerce_automations',
					'question'        => __(
						'Which e-commerce tasks would you most like the AI to help automate?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Writing product descriptions, responding to reviews, updating stock', 'gratis-ai-agent' ),
					'memory_category' => 'workflows',
					'required'        => false,
				];
				break;

			case 'lms':
				$questions[] = [
					'id'              => 'lms_subject',
					'question'        => __(
						'What subjects or skills do your courses cover?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Digital marketing, Python programming, yoga', 'gratis-ai-agent' ),
					'memory_category' => 'site_info',
					'required'        => false,
				];
				$questions[] = [
					'id'              => 'lms_automations',
					'question'        => __(
						'How can the AI best support your learners and course creation?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Draft lesson content, answer student questions, create quizzes', 'gratis-ai-agent' ),
					'memory_category' => 'workflows',
					'required'        => false,
				];
				break;

			case 'membership':
				$questions[] = [
					'id'              => 'membership_value',
					'question'        => __(
						'What exclusive value do members receive?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Premium tutorials, community access, monthly reports', 'gratis-ai-agent' ),
					'memory_category' => 'site_info',
					'required'        => false,
				];
				$questions[] = [
					'id'              => 'membership_automations',
					'question'        => __(
						'Which membership tasks would you like the AI to help with?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Welcome emails, member content, renewal reminders', 'gratis-ai-agent' ),
					'memory_category' => 'workflows',
					'required'        => false,
				];
				break;

			case 'blog':
				$questions[] = [
					'id'              => 'blog_topics',
					'question'        => __(
						'What topics does this blog cover?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Personal finance, travel, technology news', 'gratis-ai-agent' ),
					'memory_category' => 'site_info',
					'required'        => false,
				];
				$questions[] = [
					'id'              => 'blog_frequency',
					'question'        => __(
						'How often do you publish, and how can the AI help with your content workflow?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Weekly — help me draft posts and suggest topics', 'gratis-ai-agent' ),
					'memory_category' => 'workflows',
					'required'        => false,
				];
				break;

			case 'portfolio':
				$questions[] = [
					'id'              => 'portfolio_services',
					'question'        => __(
						'What services or work do you showcase in your portfolio?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Brand identity design, web development, photography', 'gratis-ai-agent' ),
					'memory_category' => 'site_info',
					'required'        => false,
				];
				break;

			default: // brochure / generic.
				$questions[] = [
					'id'              => 'key_pages',
					'question'        => __(
						'What are the most important pages or sections on this site?',
						'gratis-ai-agent'
					),
					'placeholder'     => __( 'e.g. Services, About, Contact, Pricing', 'gratis-ai-agent' ),
					'memory_category' => 'site_info',
					'required'        => false,
				];
				break;
		}

		// Automation interest — asked of all sites.
		$questions[] = [
			'id'              => 'automation_interest',
			'question'        => __(
				'Are there any repetitive tasks you would like the AI to handle automatically?',
				'gratis-ai-agent'
			),
			'placeholder'     => __( 'e.g. Weekly SEO reports, social media summaries, content updates', 'gratis-ai-agent' ),
			'memory_category' => 'workflows',
			'required'        => false,
		];

		return $questions;
	}

	// ── Answer storage ────────────────────────────────────────────────────

	/**
	 * Save interview answers as agent memories.
	 *
	 * Each answer is stored as a memory in the category specified by its
	 * question definition. Existing interview memories are cleared first to
	 * avoid duplicates if the interview is re-run.
	 *
	 * @param array<string, string> $answers Map of question ID → answer text.
	 * @return bool True on success.
	 */
	public static function save_answers( array $answers ): bool {
		if ( empty( $answers ) ) {
			return false;
		}

		$questions = self::get_questions();

		// Index questions by ID for fast lookup.
		$question_map = [];
		foreach ( $questions as $q ) {
			// @phpstan-ignore-next-line
			$question_map[ $q['id'] ] = $q;
		}

		// Clear previous interview answers to avoid duplicates on re-run.
		Memory::forget_by_topic( 'onboarding interview' );

		foreach ( $answers as $id => $answer ) {
			$answer = trim( (string) $answer );
			if ( '' === $answer ) {
				continue;
			}

			$q        = $question_map[ $id ] ?? null;
			$category = $q ? $q['memory_category'] : 'user_preferences';

			// Format: "Site goal: <answer>." so the AI has labelled context.
			$label   = self::get_answer_label( $id );
			$content = $label . ': ' . $answer;

			// @phpstan-ignore-next-line
			Memory::create( $category, $content );
		}

		self::mark_complete();

		return true;
	}

	/**
	 * Return a human-readable label for a question ID.
	 *
	 * Used to prefix memory content so the AI has labelled context.
	 *
	 * @param string $id Question ID.
	 * @return string Label.
	 */
	private static function get_answer_label( string $id ): string {
		$labels = [
			'primary_goal'           => 'Site primary goal',
			'target_audience'        => 'Target audience',
			'content_tone'           => 'Preferred content tone',
			'ecommerce_focus'        => 'Products and sales focus',
			'ecommerce_automations'  => 'Desired e-commerce automations',
			'lms_subject'            => 'Course subjects',
			'lms_automations'        => 'Desired LMS automations',
			'membership_value'       => 'Member value proposition',
			'membership_automations' => 'Desired membership automations',
			'blog_topics'            => 'Blog topics',
			'blog_frequency'         => 'Publishing frequency and workflow',
			'portfolio_services'     => 'Portfolio services',
			'key_pages'              => 'Key site pages',
			'automation_interest'    => 'Automation interests',
		];

		return $labels[ $id ] ?? ucwords( str_replace( '_', ' ', $id ) );
	}
}
