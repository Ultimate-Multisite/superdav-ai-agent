<?php
/**
 * DI handler for change-logging hooks.
 *
 * Replaces the `ChangeLogger::register()` call in CoreServicesHandler by
 * wiring each WordPress hook directly via `#[Action]` attributes.
 *
 * The underlying logic lives in {@see \SdAiAgent\Core\ChangeLogger},
 * which maintains the thread-local `$active` flag and does the actual
 * recording. This handler is a thin DI bridge — its only job is hook
 * registration and arg forwarding.
 *
 * @package SdAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Bootstrap;

use SdAiAgent\Core\ChangeLogger;
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
	container: 'sd-ai-agent',
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
	 * Cache the term's current name before it is overwritten by wp_update_term().
	 *
	 * @param int    $term_id  Term ID being edited.
	 * @param string $taxonomy Taxonomy slug.
	 */
	#[Action( tag: 'edit_terms', priority: 10 )]
	public function on_edit_terms( int $term_id, string $taxonomy ): void {
		ChangeLogger::on_edit_terms( $term_id, $taxonomy );
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

	// ── GAP 1: Post meta ─────────────────────────────────────────────────────

	/**
	 * Cache the meta before-value before WordPress overwrites it.
	 *
	 * Registered with accepted_args=3; the 4th hook arg (new value) is read via
	 * get_post_meta() inside ChangeLogger so we never need it here.
	 *
	 * @param int    $meta_id   Meta row ID (unused).
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 */
	#[Action( tag: 'update_post_meta', priority: 10 )]
	public function on_update_post_meta( int $meta_id, int $object_id, string $meta_key ): void {
		ChangeLogger::on_update_post_meta( $meta_id, $object_id, $meta_key );
	}

	/**
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $value      New value after the save.
	 */
	#[Action( tag: 'updated_post_meta', priority: 10 )]
	public function on_updated_post_meta( int $meta_id, int $object_id, string $meta_key, mixed $value ): void {
		ChangeLogger::on_updated_post_meta( $meta_id, $object_id, $meta_key, $value );
	}

	/**
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $value      New value.
	 */
	#[Action( tag: 'added_post_meta', priority: 10 )]
	public function on_added_post_meta( int $meta_id, int $object_id, string $meta_key, mixed $value ): void {
		ChangeLogger::on_added_post_meta( $meta_id, $object_id, $meta_key, $value );
	}

	/**
	 * @param int[]  $meta_ids  Deleted meta row IDs.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $value     Value that was deleted.
	 */
	#[Action( tag: 'deleted_post_meta', priority: 10 )]
	public function on_deleted_post_meta( array $meta_ids, int $object_id, string $meta_key, mixed $value ): void {
		ChangeLogger::on_deleted_post_meta( $meta_ids, $object_id, $meta_key, $value );
	}

	// ── GAP 2: Deleted options ────────────────────────────────────────────────

	/**
	 * @param string $option Option name.
	 */
	#[Action( tag: 'delete_option', priority: 10 )]
	public function on_pre_delete_option( string $option ): void {
		ChangeLogger::on_pre_delete_option( $option );
	}

	/**
	 * @param string $option Option name.
	 */
	#[Action( tag: 'deleted_option', priority: 10 )]
	public function on_deleted_option( string $option ): void {
		ChangeLogger::on_deleted_option( $option );
	}

	// ── GAP 3: Nav menus ─────────────────────────────────────────────────────

	/**
	 * @param int                 $term_id   Nav menu term ID.
	 * @param array<string,mixed> $menu_data Menu creation arguments.
	 */
	#[Action( tag: 'wp_create_nav_menu', priority: 10 )]
	public function on_created_nav_menu( int $term_id, array $menu_data ): void {
		ChangeLogger::on_created_nav_menu( $term_id, $menu_data );
	}

	/**
	 * @param \WP_Term|false $menu Menu term object, or false.
	 */
	#[Action( tag: 'wp_delete_nav_menu', priority: 10 )]
	public function on_pre_delete_nav_menu( mixed $menu ): void {
		ChangeLogger::on_pre_delete_nav_menu( $menu );
	}

	/**
	 * @param int    $term_id      Deleted term ID.
	 * @param int    $tt_id        Term taxonomy ID.
	 * @param string $taxonomy     Taxonomy slug.
	 * @param object $deleted_term The deleted WP_Term.
	 */
	#[Action( tag: 'deleted_term', priority: 10 )]
	public function on_deleted_nav_menu_term( int $term_id, int $tt_id, string $taxonomy, object $deleted_term ): void {
		ChangeLogger::on_deleted_nav_menu_term( $term_id, $tt_id, $taxonomy, $deleted_term );
	}

	// ── GAP 4: User role changes ──────────────────────────────────────────────

	/**
	 * @param int               $user_id   User ID.
	 * @param string            $new_role  New role slug.
	 * @param array<int,string> $old_roles Previous role slugs.
	 */
	#[Action( tag: 'set_user_role', priority: 10 )]
	public function on_set_user_role( int $user_id, string $new_role, array $old_roles ): void {
		ChangeLogger::on_set_user_role( $user_id, $new_role, $old_roles );
	}
}
