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

class GetOptionAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Get Option', 'superdav-ai-agent' );
	}

	protected function description(): string {
		return __( 'Read a WordPress option by name. Returns the stored value or a default if the option does not exist.', 'superdav-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'option_name' => [
					'type'        => 'string',
					'description' => 'The option name to retrieve (e.g. "blogname", "blogdescription", "posts_per_page").',
				],
				'default'     => [
					'type'        => array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ),
					'description' => 'Value to return if the option does not exist. Defaults to false.',
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
				'value'       => [
					'type'        => [ 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ],
					'description' => 'The option value. Type varies by option.',
				],
				'exists'      => [ 'type' => 'boolean' ],
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

		// Secret-option read gate. WordPress writes auth keys/salts into
		// `wp_options` when wp-config.php does not define them; returning
		// their values would enable session forgery.
		if ( OptionsAbilities::is_secret_option_name( $option_name ) ) {
			return OptionsAbilities::secret_read_error( $option_name );
		}

		$default = $input['default'] ?? false;

		// Check whether the option exists before fetching so we can report it.
		$raw    = get_option( $option_name, null );
		$exists = null !== $raw;

		$value = $exists ? $raw : $default;

		return [
			'option_name' => $option_name,
			'value'       => $value,
			'exists'      => $exists,
		];
	}

	protected function permission_callback( $input = null ): bool {
		return ToolCapabilities::current_user_can( $this->name );
	}

	protected function meta(): array {
		return [
			'mcp'          => [ 'public' => true ],
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
