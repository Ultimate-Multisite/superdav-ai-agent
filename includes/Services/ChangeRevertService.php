<?php

declare(strict_types=1);
/**
 * Service class for reverting AI-made WordPress changes.
 *
 * Extracted from ChangesController to separate domain concerns from HTTP handling.
 * Knows about WordPress object types (post, option, term, user) and applies
 * the appropriate WordPress API calls to restore prior values.
 *
 * @package GratisAiAgent\Services
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies revert operations for AI-made changes to WordPress objects.
 */
final class ChangeRevertService {

	/**
	 * Apply the revert operation for a change record.
	 *
	 * Dispatches to the appropriate WordPress API function based on
	 * the object type recorded in the change. Third-party code can
	 * extend support for custom object types via the
	 * `gratis_ai_agent_revert_change` filter.
	 *
	 * @param object $change Change record row from the database.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function apply_revert( object $change ): true|WP_Error {
		switch ( $change->object_type ) {
			case 'post':
				$result = wp_update_post(
					array(
						'ID'                => (int) $change->object_id,
						$change->field_name => $change->before_value,
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return true;

			case 'option':
				update_option( $change->field_name, $change->before_value );
				return true;

			case 'term':
				$result = wp_update_term(
					(int) $change->object_id,
					$change->field_name,
					array( 'name' => $change->before_value )
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return true;

			case 'user':
				$result = wp_update_user(
					array(
						'ID'                => (int) $change->object_id,
						$change->field_name => $change->before_value,
					)
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return true;

			default:
				/**
				 * Allow third-party code to handle revert for custom object types.
				 *
				 * @param true|WP_Error $result  Default WP_Error (unhandled).
				 * @param object        $change  Change record row.
				 */
				$result = apply_filters(
					'gratis_ai_agent_revert_change',
					new WP_Error(
						'unsupported_object_type',
						sprintf(
							/* translators: %s: object type slug */
							__( 'Revert is not supported for object type "%s".', 'gratis-ai-agent' ),
							$change->object_type
						),
						array( 'status' => 422 )
					),
					$change
				);
				return $result;
		}
	}
}
