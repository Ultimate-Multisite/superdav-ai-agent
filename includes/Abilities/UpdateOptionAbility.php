<?php

declare(strict_types=1);
/**
 * Options management abilities for the AI agent.
 *
 * Provides get, update, and delete operations for WordPress options. Writes
 * are default-deny: only plugin-owned options and site-allowlisted option
 * names can be modified, and critical core options remain blocklisted.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateOptionAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Update Option', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Create or update an allowed WordPress option. Default write access is limited to plugin-owned options; critical system options remain blocked.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'option_name'  => [
					'type'        => 'string',
					'description' => 'The allowed option name to create or update. By default, write access is limited to sd_ai_agent_ options unless site code extends the allowlist.',
				],
				'option_value' => [
					'type'        => array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ),
					'description' => 'The new value to store. Strings, numbers, booleans, arrays, and objects are all supported.',
				],
				'autoload'     => [
					'type'        => 'string',
					'enum'        => [ 'yes', 'no' ],
					'description' => 'Whether to autoload this option on every page load. Use "no" for large or infrequently-accessed options. Defaults to "yes".',
					'default'     => 'yes',
				],
			],
			'required'   => [ 'option_name', 'option_value' ],
		];
	}

	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'option_name' => [ 'type' => 'string' ],
				'status'      => [ 'type' => 'string' ],
				'message'     => [ 'type' => 'string' ],
			],
		];
	}

	protected function execute_callback( $input ) {
		/** @var array<string, mixed> $input */
		$option_name = isset( $input['option_name'] ) ? (string) $input['option_name'] : '';

		if ( '' === $option_name ) {
			return new WP_Error(
				'sd_ai_agent_empty_option_name',
				__( 'The "option_name" parameter is required.', 'superdav-ai-agent' )
			);
		}

		// Blocklist check.
		$blocklist = OptionsAbilities::get_write_blocklist();
		if ( in_array( $option_name, $blocklist, true ) ) {
			return new WP_Error(
				'sd_ai_agent_option_blocked',
				sprintf(
					/* translators: %s: option name */
					__( 'The option "%s" is protected and cannot be modified by the AI agent.', 'superdav-ai-agent' ),
					$option_name
				)
			);
		}

		if ( ! OptionsAbilities::is_write_allowed_option( $option_name ) ) {
			return new WP_Error(
				'sd_ai_agent_option_not_allowed',
				sprintf(
					/* translators: %s: option name */
					__( 'The option "%s" is not in the AI agent write allowlist. Only plugin-owned options and options explicitly allowed by site code can be modified by this ability.', 'superdav-ai-agent' ),
					$option_name
				),
				array( 'status' => 403 )
			);
		}

		if ( ! array_key_exists( 'option_value', $input ) ) {
			return new WP_Error(
				'sd_ai_agent_missing_option_value',
				__( 'The "option_value" parameter is required.', 'superdav-ai-agent' )
			);
		}

		$option_value = $input['option_value'];
		// WordPress 7.0+ update_option() accepts bool|null for $autoload.
		// false = do not autoload, true = autoload, null = keep existing setting.
		$autoload = isset( $input['autoload'] ) && 'no' === $input['autoload'] ? false : true;

		$updated = update_option( $option_name, $option_value, $autoload );

		if ( $updated ) {
			return [
				'option_name'  => $option_name,
				'status'       => 'updated',
				'message'      => sprintf(
					/* translators: %s: option name */
					__( 'Option "%s" updated successfully.', 'superdav-ai-agent' ),
					$option_name
				),
				'verification' => [
					'persisted_value' => get_option( $option_name ),
				],
			];
		}

		// update_option() returns false both when the value is unchanged and
		// when the option does not exist yet (add_option path). Distinguish
		// the two cases so the caller gets accurate feedback.
		// Use a sentinel object so options storing literal false are not
		// misdetected as non-existent.
		$sentinel = new \stdClass();
		$exists   = get_option( $option_name, $sentinel ) !== $sentinel;

		if ( $exists ) {
			return [
				'option_name'  => $option_name,
				'status'       => 'unchanged',
				'message'      => sprintf(
					/* translators: %s: option name */
					__( 'Option "%s" already has the requested value — no change made.', 'superdav-ai-agent' ),
					$option_name
				),
				'verification' => [
					'persisted_value' => get_option( $option_name ),
				],
			];
		}

		// Option did not exist and add_option (called internally by update_option) failed.
		return new WP_Error(
			'sd_ai_agent_update_failed',
			sprintf(
				/* translators: %s: option name */
				__( 'Failed to update option "%s".', 'superdav-ai-agent' ),
				$option_name
			)
		);
	}

	protected function permission_callback( $input = null ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
