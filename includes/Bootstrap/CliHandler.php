<?php
/**
 * Handler: register WP-CLI subcommands for the plugin.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace GratisAiAgent\Bootstrap;

use GratisAiAgent\CLI\BenchmarkCommand;
use GratisAiAgent\CLI\CliCommand;
use GratisAiAgent\CLI\TraceCommand;
use WP_CLI;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's WP-CLI subcommands under both the canonical
 * `ai-agent` namespace and the legacy `gratis-ai-agent` alias.
 *
 * Uses the `#[Handler(context: CTX_CLI)]` guard so the container skips
 * loading this class outside of WP-CLI requests. Each subcommand class
 * (`CliCommand`, `TraceCommand`, `BenchmarkCommand`) remains a plain
 * `WP_CLI_Command` subclass — we are not yet migrating them to the
 * `#[CLI_Handler]` / `#[CLI_Command]` decorators, which would require
 * deeper restructuring of their docblock-driven subcommand APIs.
 *
 * PR 5 of the DI refactor will migrate the CLI command classes themselves
 * into attribute-driven handlers; this PR simply moves the registration
 * wiring out of the plugin root file.
 */
#[Handler(
	container: 'gratis-ai-agent',
	context: Handler::CTX_CLI,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class CliHandler {

	/**
	 * Command-to-class map used to derive both primary and alias registrations.
	 *
	 * @var array<string,class-string>
	 */
	private const COMMANDS = array(
		'prompt'    => CliCommand::class,
		'trace'     => TraceCommand::class,
		'benchmark' => BenchmarkCommand::class,
	);

	/**
	 * Primary and alias root namespaces under which every subcommand is exposed.
	 *
	 * @var list<string>
	 */
	private const NAMESPACES = array( 'ai-agent', 'gratis-ai-agent' );

	/**
	 * Register every subcommand with WP-CLI.
	 *
	 * Hooked on `cli_init` — guaranteed to fire only when WP-CLI is active,
	 * which removes the need for the legacy `defined('WP_CLI')` guard that
	 * used to live in the plugin bootstrap file.
	 */
	#[Action( tag: 'cli_init', priority: 10 )]
	public function register_commands(): void {
		foreach ( self::NAMESPACES as $ns ) {
			foreach ( self::COMMANDS as $sub => $class ) {
				WP_CLI::add_command( "{$ns} {$sub}", $class );
			}
		}
	}
}
