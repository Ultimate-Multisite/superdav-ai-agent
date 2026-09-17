<?php

declare(strict_types=1);
/**
 * Registers persisted remote MCP snapshots as local WordPress Abilities.
 *
 * @package SdAiAgent\Mcp
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Mcp;

use SdAiAgent\Abilities\ToolCapabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMcpAbilityRegistrar {

	public function __construct( private readonly RemoteMcpConnectionRepository $connections ) {}

	/**
	 * Register only persisted snapshots; this hook must never make network calls.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		foreach ( $this->connections->list_enabled() as $connection ) {
			foreach ( $connection['tools'] ?? array() as $tool ) {
				if ( ! is_array( $tool ) || empty( $tool['enabled'] ) || empty( $tool['name'] ) ) {
					continue;
				}
				$ability_name = self::ability_name( (string) $connection['id'], (string) $tool['name'] );
				$schema       = isset( $tool['input_schema'] ) && is_array( $tool['input_schema'] ) ? $tool['input_schema'] : array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				);
				wp_register_ability(
					$ability_name,
					array(
						'label'               => (string) ( $connection['name'] ?? __( 'Remote MCP', 'superdav-ai-agent' ) ) . ': ' . (string) $tool['name'],
						'description'         => (string) ( $tool['description'] ?? __( 'Remote MCP tool.', 'superdav-ai-agent' ) ),
						'category'            => 'sd-ai-agent',
						'input_schema'        => $schema,
						'meta'                => array(
							'show_in_rest' => true,
							'remote_mcp'   => true,
						),
						'permission_callback' => static fn(): bool => ToolCapabilities::current_user_can( $ability_name ),
						'execute_callback'    => function ( array $input ) use ( $connection, $tool ) {
							$repository = new RemoteMcpConnectionRepository();
							$transport  = new RemoteMcpHttpTransport( $repository );
							$client     = new RemoteMcpClient( $repository, $transport );
							$executor   = new RemoteMcpToolExecutor( $repository, $client );
							return $executor->execute( (string) $connection['id'], (string) $tool['name'], $input );
						},
					)
				);
			}
		}
	}

	/**
	 * Derive a collision-resistant local ID while retaining the exact remote name.
	 */
	public static function ability_name( string $connection_id, string $remote_name ): string {
		$slug = sanitize_title( $remote_name );
		$slug = '' !== $slug ? substr( $slug, 0, 48 ) : 'tool';
		return 'sd-ai-agent/mcp-' . sanitize_key( $connection_id ) . '-' . $slug . '-' . substr( hash( 'sha256', $remote_name ), 0, 10 );
	}
}
