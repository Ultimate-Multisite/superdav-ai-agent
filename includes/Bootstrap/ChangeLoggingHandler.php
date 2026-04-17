<?php
/**
 * DI handler for change-logging hooks.
 *
 * Replaces the `ChangeLogger::register()` call in CoreServicesHandler by
 * wiring each WordPress hook directly via `#[Action]` attributes.
 *
 * The underlying logic lives in {@see \GratisAiAgent\Core\ChangeLogger},
 * which maintains the thread-local `$active` flag and does the actual
 * recording. This handler is a thin DI bridge — its only job is hook
 * registration and arg forwarding.
 *
 * @package GratisAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\Core\ChangeLogger;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress hooks that feed the AI change log.
 *
 * CTX_GLOBAL ensures the hooks are attached in every request context
 * (admin, REST, CLI, cron) because AI-initiated changes can originate
 * from any of them.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class ChangeLoggingHandler {

	/**
	 * Record post field changes.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after update.
	 * @param \WP_Post $post_before Post object before update.
	 */
	#[Action( tag: 'post_updated', priority: 10 )]
	public function on_post_updated( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		ChangeLogger::on_post_updated( $post_id, $post_after, $post_before );
	}

	/**
	 * Record option value changes.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	#[Action( tag: 'updated_option', priority: 10 )]
	public function on_updated_option( string $option, mixed $old_value, mixed $new_value ): void {
		ChangeLogger::on_updated_option( $option, $old_value, $new_value );
	}

	/**
	 * Record newly added options.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	#[Action( tag: 'added_option', priority: 10 )]
	public function on_added_option( string $option, mixed $value ): void {
		ChangeLogger::on_added_option( $option, $value );
	}

	/**
	 * Record term changes.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	#[Action( tag: 'edited_term', priority: 10 )]
	public function on_edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		ChangeLogger::on_edited_term( $term_id, $tt_id, $taxonomy );
	}

	/**
	 * Record user profile changes.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data User object before update.
	 */
	#[Action( tag: 'profile_update', priority: 10 )]
	public function on_profile_update( int $user_id, \WP_User $old_user_data ): void {
		ChangeLogger::on_profile_update( $user_id, $old_user_data );
	}
}
