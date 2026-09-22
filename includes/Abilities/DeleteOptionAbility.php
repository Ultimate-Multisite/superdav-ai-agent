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

class DeleteOptionAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Delete Option', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Delete an allowed WordPress option by name. Default delete access is limited to plugin-owned options; critical system options remain blocked.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'option_name' => [
					'type'        => 'string',
					'description' => 'The allowed option name to delete. By default, delete access is limited to sd_ai_agent_ options unless site code extends the allowlist.',
				],
			],
			'required'   => [ 'option_name' ],
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
					__( 'The option "%s" is protected and cannot be deleted by the AI agent.', 'superdav-ai-agent' ),
					$option_name
				)
			);
		}

		if ( ! OptionsAbilities::is_write_allowed_option( $option_name ) ) {
			return new WP_Error(
				'sd_ai_agent_option_not_allowed',
				sprintf(
					/* translators: %s: option name */
					__( 'The option "%s" is not in the AI agent write allowlist. Only plugin-owned options and options explicitly allowed by site code can be deleted by this ability.', 'superdav-ai-agent' ),
					$option_name
				),
				array( 'status' => 403 )
			);
		}

		// Check existence before deleting so we can report accurately.
		// Use a sentinel object so options storing literal false are not
		// misdetected as non-existent.
		$sentinel = new \stdClass();
		$exists   = get_option( $option_name, $sentinel ) !== $sentinel;

		if ( ! $exists ) {
			return [
				'option_name' => $option_name,
				'status'      => 'not_found',
				'message'     => sprintf(
					/* translators: %s: option name */
					__( 'Option "%s" does not exist.', 'superdav-ai-agent' ),
					$option_name
				),
			];
		}

		$deleted = delete_option( $option_name );

		if ( $deleted ) {
			return [
				'option_name' => $option_name,
				'status'      => 'deleted',
				'message'     => sprintf(
					/* translators: %s: option name */
					__( 'Option "%s" deleted successfully.', 'superdav-ai-agent' ),
					$option_name
				),
			];
		}

		return new WP_Error(
			'sd_ai_agent_delete_failed',
			sprintf(
				/* translators: %s: option name */
				__( 'Failed to delete option "%s".', 'superdav-ai-agent' ),
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
				'destructive' => true,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
