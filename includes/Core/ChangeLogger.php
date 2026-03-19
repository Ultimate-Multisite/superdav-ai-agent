<?php

declare(strict_types=1);
/**
 * Change logger — hooks into WordPress core actions to record AI-made changes.
 *
 * Uses a thread-local flag (set by AgentLoop before executing abilities) to
 * distinguish AI-initiated changes from user-initiated ones. Only records
 * changes when the flag is active.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Models\ChangesLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChangeLogger {

	/**
	 * Whether AI-initiated change logging is currently active.
	 *
	 * Set to true by AgentLoop before executing abilities, false after.
	 *
	 * @var bool
	 */
	private static bool $active = false;

	/**
	 * Current session ID for attributing changes.
	 *
	 * @var int
	 */
	private static int $session_id = 0;

	/**
	 * Current ability name for attributing changes.
	 *
	 * @var string
	 */
	private static string $ability_name = '';

	/**
	 * Begin recording changes for an AI session.
	 *
	 * Call this before executing an ability that may make changes.
	 *
	 * @param int    $session_id   Session ID.
	 * @param string $ability_name Ability slug being executed.
	 */
	public static function begin( int $session_id = 0, string $ability_name = '' ): void {
		self::$active       = true;
		self::$session_id   = $session_id;
		self::$ability_name = $ability_name;
	}

	/**
	 * Stop recording changes.
	 */
	public static function end(): void {
		self::$active       = false;
		self::$session_id   = 0;
		self::$ability_name = '';
	}

	/**
	 * Whether change logging is currently active.
	 */
	public static function is_active(): bool {
		return self::$active;
	}

	/**
	 * Register WordPress hooks for change logging.
	 */
	public static function register(): void {
		// Post content/title/status changes.
		add_action( 'post_updated', [ __CLASS__, 'on_post_updated' ], 10, 3 );

		// Option changes.
		add_action( 'updated_option', [ __CLASS__, 'on_updated_option' ], 10, 3 );
		add_action( 'added_option', [ __CLASS__, 'on_added_option' ], 10, 2 );

		// Term changes.
		add_action( 'edited_term', [ __CLASS__, 'on_edited_term' ], 10, 3 );

		// User profile changes.
		add_action( 'profile_update', [ __CLASS__, 'on_profile_update' ], 10, 2 );
	}

	/**
	 * Record post field changes.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after update.
	 * @param \WP_Post $post_before Post object before update.
	 */
	public static function on_post_updated( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( ! self::$active ) {
			return;
		}

		$tracked_fields = [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_name',
		];

		foreach ( $tracked_fields as $field ) {
			$before = $post_before->$field ?? '';
			$after  = $post_after->$field ?? '';

			if ( $before === $after ) {
				continue;
			}

			ChangesLog::record(
				[
					'session_id'   => self::$session_id,
					'object_type'  => 'post',
					'object_id'    => $post_id,
					'object_title' => $post_after->post_title,
					'ability_name' => self::$ability_name,
					'field_name'   => $field,
					'before_value' => (string) $before,
					'after_value'  => (string) $after,
				]
			);
		}
	}

	/**
	 * Record option value changes.
	 *
	 * Skips transients and internal WordPress options to avoid noise.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	public static function on_updated_option( string $option, $old_value, $new_value ): void {
		if ( ! self::$active ) {
			return;
		}

		if ( self::is_noisy_option( $option ) ) {
			return;
		}

		$before = is_scalar( $old_value ) ? (string) $old_value : wp_json_encode( $old_value );
		$after  = is_scalar( $new_value ) ? (string) $new_value : wp_json_encode( $new_value );

		if ( $before === $after ) {
			return;
		}

		if ( self::is_sensitive_option( $option ) ) {
			$before = self::redact_value();
			$after  = self::redact_value();
		}

		ChangesLog::record(
			[
				'session_id'   => self::$session_id,
				'object_type'  => 'option',
				'object_id'    => 0,
				'object_title' => $option,
				'ability_name' => self::$ability_name,
				'field_name'   => $option,
				'before_value' => (string) $before,
				'after_value'  => (string) $after,
			]
		);
	}

	/**
	 * Record newly added options.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	public static function on_added_option( string $option, $value ): void {
		if ( ! self::$active ) {
			return;
		}

		if ( self::is_noisy_option( $option ) ) {
			return;
		}

		$after = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );

		if ( self::is_sensitive_option( $option ) ) {
			$after = self::redact_value();
		}

		ChangesLog::record(
			[
				'session_id'   => self::$session_id,
				'object_type'  => 'option',
				'object_id'    => 0,
				'object_title' => $option,
				'ability_name' => self::$ability_name,
				'field_name'   => $option,
				'before_value' => '',
				'after_value'  => (string) $after,
			]
		);
	}

	/**
	 * Record term changes.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function on_edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! self::$active ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		ChangesLog::record(
			[
				'session_id'   => self::$session_id,
				'object_type'  => 'term',
				'object_id'    => $term_id,
				'object_title' => $term->name,
				'ability_name' => self::$ability_name,
				'field_name'   => $taxonomy,
				'before_value' => '',
				'after_value'  => $term->name,
			]
		);
	}

	/**
	 * Record user profile changes.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data User object before update.
	 */
	public static function on_profile_update( int $user_id, \WP_User $old_user_data ): void {
		if ( ! self::$active ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$tracked_fields = [ 'user_email', 'display_name', 'user_url' ];

		foreach ( $tracked_fields as $field ) {
			$before = $old_user_data->$field ?? '';
			$after  = $user->$field ?? '';

			if ( $before === $after ) {
				continue;
			}

			// Redact PII fields — log that the field changed without storing the value.
			if ( 'user_email' === $field ) {
				$before = self::redact_value();
				$after  = self::redact_value();
			}

			ChangesLog::record(
				[
					'session_id'   => self::$session_id,
					'object_type'  => 'user',
					'object_id'    => $user_id,
					'object_title' => $user->display_name,
					'ability_name' => self::$ability_name,
					'field_name'   => $field,
					'before_value' => (string) $before,
					'after_value'  => (string) $after,
				]
			);
		}
	}

	/**
	 * Whether an option name is too noisy to track.
	 *
	 * Skips transients, cron schedules, and other high-frequency internal options.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function is_noisy_option( string $option ): bool {
		$noisy_prefixes = [
			'_transient_',
			'_site_transient_',
			'_transient_timeout_',
			'_site_transient_timeout_',
			'cron',
			'rewrite_rules',
			'gratis_ai_agent_',
		];

		foreach ( $noisy_prefixes as $prefix ) {
			if ( str_starts_with( $option, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an option name likely contains sensitive data (API keys, tokens, passwords, etc.).
	 *
	 * Matches common naming patterns used by WordPress core and plugins for
	 * credential-like options to prevent secrets/PII from being stored in the
	 * change log in plain text.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function is_sensitive_option( string $option ): bool {
		return (bool) preg_match(
			'/(api[_-]?key|token|secret|password|pass|auth|client|authorization|cookie)/i',
			$option
		);
	}

	/**
	 * Redact a value that should not be stored in plain text.
	 *
	 * Returns a constant placeholder so the change log records that a sensitive
	 * field was modified without persisting the actual value.
	 *
	 * @return string
	 */
	private static function redact_value(): string {
		return '[REDACTED]';
	}
}
