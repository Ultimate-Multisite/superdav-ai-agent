<?php

declare(strict_types=1);
/**
 * Content analysis abilities for the AI agent.
 *
 * @package GratisAiAgent
 */

namespace GratisAiAgent\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContentAbilities {

	// ─── Static proxy methods (for backwards-compatible test access) ─────────

	/**
	 * Analyze content strategy.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_content_analyze( array $input = [] ) {
		$ability = new ContentAnalyzeAbility( 'gratis-ai-agent/content-analyze' );
		return $ability->execute( $input );
	}

	/**
	 * Generate a content performance report.
	 *
	 * @param array<string,mixed> $input Input args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function handle_performance_report( array $input = [] ) {
		$ability = new ContentPerformanceReportAbility( 'gratis-ai-agent/content-performance-report' );
		return $ability->execute( $input );
	}

	/**
	 * Register abilities on init.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register content analysis abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/content-analyze',
			[
				'label'         => __( 'Analyze Content Strategy', 'gratis-ai-agent' ),
				'description'   => __( 'Analyze content strategy: publishing frequency, word counts, category distribution, missing featured images, and content gaps.', 'gratis-ai-agent' ),
				'ability_class' => ContentAnalyzeAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/content-performance-report',
			[
				'label'         => __( 'Content Performance Report', 'gratis-ai-agent' ),
				'description'   => __( 'Generate a content performance summary for a given time period: posts published, category breakdown, word counts, drafts pending.', 'gratis-ai-agent' ),
				'ability_class' => ContentPerformanceReportAbility::class,
			]
		);
	}
}

/**
 * Content Analyze ability.
 *
 * @since 1.0.0
 */
class ContentAnalyzeAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Analyze Content Strategy', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Analyze content strategy: publishing frequency, word counts, category distribution, missing featured images, and content gaps.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_type' => [
					'type'        => 'string',
					'description' => 'Post type to analyze (default: "post").',
				],
				'limit'     => [
					'type'        => 'integer',
					'description' => 'Number of recent posts to analyze (default: 20).',
				],
				'site_url'  => [
					'type'        => 'string',
					'description' => 'Subsite URL for multisite. Omit for the main site.',
				],
			],
			'required'   => [],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_type'      => [ 'type' => 'string' ],
				'total_analyzed' => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );
		$limit     = min( 100, max( 1, (int) ( $input['limit'] ?? 20 ) ) );
		$site_url  = $input['site_url'] ?? '';

		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				wp_parse_url( $site_url, PHP_URL_HOST ),
				wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}
		}

		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$result = $this->analyze_content_strategy( $posts, $post_type );

		if ( $switched ) {
			restore_current_blog();
		}

		return $result;
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'edit_posts' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => false,
		];
	}

	/**
	 * Analyze content strategy across posts.
	 *
	 * @param \WP_Post[] $posts     Array of posts.
	 * @param string     $post_type Post type being analyzed.
	 * @return array Analysis data.
	 */
	private function analyze_content_strategy( array $posts, string $post_type ): array {
		$total = count( $posts );

		if ( $total === 0 ) {
			return [
				'post_type'   => $post_type,
				'total_posts' => 0,
				'message'     => 'No published posts found.',
			];
		}

		$word_counts           = [];
		$category_distribution = [];
		$without_featured      = [];
		$without_meta_desc     = [];
		$dates                 = [];

		foreach ( $posts as $post ) {
			$plain         = wp_strip_all_tags( $post->post_content );
			$wc            = str_word_count( $plain );
			$word_counts[] = $wc;
			$dates[]       = $post->post_date;

			// Categories.
			$cats = wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $cats ) ) {
				foreach ( $cats as $cat ) {
					if ( ! isset( $category_distribution[ $cat ] ) ) {
						$category_distribution[ $cat ] = 0;
					}
					++$category_distribution[ $cat ];
				}
			}

			// Featured image.
			if ( ! has_post_thumbnail( $post->ID ) ) {
				$without_featured[] = [
					'id'    => $post->ID,
					'title' => $post->post_title,
				];
			}

			// Meta description (Yoast or RankMath).
			$meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
			if ( empty( $meta_desc ) ) {
				$meta_desc = get_post_meta( $post->ID, 'rank_math_description', true );
			}
			if ( empty( $meta_desc ) ) {
				$without_meta_desc[] = [
					'id'    => $post->ID,
					'title' => $post->post_title,
				];
			}
		}

		// Publishing frequency.
		$frequency = [];
		if ( count( $dates ) >= 2 ) {
			$newest    = strtotime( $dates[0] );
			$oldest    = strtotime( end( $dates ) );
			$days      = max( 1, ( $newest - $oldest ) / DAY_IN_SECONDS );
			$frequency = [
				'posts_per_week'  => round( ( $total / $days ) * 7, 1 ),
				'posts_per_month' => round( ( $total / $days ) * 30, 1 ),
				'date_range_days' => (int) $days,
			];
		}

		arsort( $category_distribution );

		// Content gaps: categories with few posts.
		$content_gaps = [];
		foreach ( $category_distribution as $cat => $count ) {
			if ( $count <= 2 ) {
				$content_gaps[] = $cat;
			}
		}

		return [
			'post_type'                      => $post_type,
			'total_analyzed'                 => $total,
			'publishing_frequency'           => $frequency,
			'avg_word_count'                 => (int) round( array_sum( $word_counts ) / $total ),
			'min_word_count'                 => min( $word_counts ),
			'max_word_count'                 => max( $word_counts ),
			'category_distribution'          => $category_distribution,
			'posts_without_featured_image'   => $without_featured,
			'posts_without_meta_description' => $without_meta_desc,
			'content_gap_categories'         => $content_gaps,
			'thin_content_count'             => count( array_filter( $word_counts, fn( $wc ) => $wc < 300 ) ),
		];
	}
}

/**
 * Content Performance Report ability.
 *
 * @since 1.0.0
 */
class ContentPerformanceReportAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Content Performance Report', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Generate a content performance summary for a given time period: posts published, category breakdown, word counts, drafts pending.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'days'     => [
					'type'        => 'integer',
					'description' => 'Number of days to look back (default: 30).',
				],
				'site_url' => [
					'type'        => 'string',
					'description' => 'Subsite URL for multisite. Omit for the main site.',
				],
			],
			'required'   => [],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'period_days'     => [ 'type' => 'integer' ],
				'posts_published' => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		$days     = max( 1, min( 365, (int) ( $input['days'] ?? 30 ) ) );
		$site_url = $input['site_url'] ?? '';

		$switched = false;

		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				wp_parse_url( $site_url, PHP_URL_HOST ),
				wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
			);

			if ( $blog_id && $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}
		}

		$result = $this->generate_performance_report( $days );

		if ( $switched ) {
			restore_current_blog();
		}

		return $result;
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'edit_posts' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			],
			'show_in_rest' => false,
		];
	}

	/**
	 * Generate a content performance report.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Report data.
	 */
	private function generate_performance_report( int $days ): array {
		$after_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Published posts in period.
		$published = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'date_query'     => [
					[ 'after' => $after_date ],
				],
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$total = count( $published );

		// Category breakdown.
		$by_category = [];
		$word_counts = [];
		$by_author   = [];

		foreach ( $published as $post ) {
			$plain         = wp_strip_all_tags( $post->post_content );
			$word_counts[] = str_word_count( $plain );

			$cats = wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $cats ) ) {
				foreach ( $cats as $cat ) {
					if ( ! isset( $by_category[ $cat ] ) ) {
						$by_category[ $cat ] = 0;
					}
					++$by_category[ $cat ];
				}
			}

			$author_name = get_the_author_meta( 'display_name', (int) $post->post_author );
			if ( ! isset( $by_author[ $author_name ] ) ) {
				$by_author[ $author_name ] = 0;
			}
			++$by_author[ $author_name ];
		}

		arsort( $by_category );
		arsort( $by_author );

		// Posts by status (all types).
		$status_counts = [];
		foreach ( [ 'publish', 'draft', 'pending', 'future', 'private' ] as $status ) {
			$count = (int) wp_count_posts( 'post' )->$status;
			if ( $count > 0 ) {
				$status_counts[ $status ] = $count;
			}
		}

		// Pending review drafts.
		$pending_drafts = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => [ 'draft', 'pending' ],
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$pending_list = [];
		foreach ( $pending_drafts as $draft ) {
			$pending_list[] = [
				'id'     => $draft->ID,
				'title'  => $draft->post_title,
				'status' => $draft->post_status,
				'date'   => $draft->post_date,
			];
		}

		// Previous period for comparison.
		$prev_after     = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $days * 2 ) . ' days' ) );
		$prev_before    = $after_date;
		$prev_published = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'date_query'     => [
					[
						'after'  => $prev_after,
						'before' => $prev_before,
					],
				],
			]
		);

		return [
			'period_days'               => $days,
			'posts_published'           => $total,
			'previous_period_published' => count( $prev_published ),
			'avg_word_count'            => $total > 0 ? (int) round( array_sum( $word_counts ) / $total ) : 0,
			'posts_by_category'         => $by_category,
			'posts_by_author'           => $by_author,
			'all_posts_by_status'       => $status_counts,
			'drafts_pending_review'     => $pending_list,
			'drafts_pending_count'      => count( $pending_list ),
		];
	}
}
