<?php
/**
 * WP-CLI Command for the AI Agent.
 *
 * Provides a `wp ai-agent` command to send prompts to the AI agent
 * directly from the terminal for development testing and debugging.
 *
 * @package AiAgent
 * @since   1.1.0
 */

namespace AiAgent;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send prompts to the AI agent from the command line.
 *
 * ## EXAMPLES
 *
 *     # Simple prompt
 *     wp ai-agent "hello"
 *
 *     # With a specific model
 *     wp ai-agent "how many sites we got??" --model=qwen3.5
 *
 *     # With verbose output showing tool calls and token usage
 *     wp ai-agent "list all plugins" --verbose
 *
 *     # Skip all tool usage
 *     wp ai-agent "what day is it?" --skip-tools
 *
 * @since 1.1.0
 */
class CLI_Command extends \WP_CLI_Command {

	/**
	 * Send a prompt to the AI agent.
	 *
	 * Runs the Agent_Loop synchronously and prints the final reply.
	 * Tool calls requiring confirmation are auto-approved in CLI mode.
	 *
	 * ## OPTIONS
	 *
	 * <prompt>
	 * : The prompt to send to the AI agent.
	 *
	 * [--model=<model>]
	 * : Model ID to use (e.g. qwen3.5, claude-sonnet-4).
	 *
	 * [--provider=<provider>]
	 * : Provider ID to use. Defaults to saved setting.
	 *
	 * [--max-iterations=<n>]
	 * : Maximum tool-calling loop iterations.
	 * ---
	 * default: 25
	 * ---
	 *
	 * [--skip-tools]
	 * : Disable all tool/ability usage.
	 *
	 * [--verbose]
	 * : Show tool calls, results, and token usage details.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-agent "how many sites we got??"
	 *     wp ai-agent "how many sites we got??" --model=qwen3.5
	 *     wp ai-agent "list all plugins" --max-iterations=5
	 *     wp ai-agent "what day is it?" --skip-tools
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$prompt         = $args[0];
		$model          = \WP_CLI\Utils\get_flag_value( $assoc_args, 'model', '' );
		$provider       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'provider', '' );
		$max_iterations = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'max-iterations', 25 );
		$no_tools       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-tools', false );
		$verbose        = \WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );

		// Ensure a user is set so ability permission checks pass.
		// WP-CLI doesn't set a current user unless --user is passed.
		if ( ! get_current_user_id() ) {
			$admins = get_super_admins();
			if ( ! empty( $admins ) ) {
				$admin = get_user_by( 'login', $admins[0] );
				if ( $admin ) {
					wp_set_current_user( $admin->ID );
				}
			}
		}

		if ( $verbose ) {
			if ( $model ) {
				WP_CLI::log( "Model: {$model}" );
			}
			if ( $provider ) {
				WP_CLI::log( "Provider: {$provider}" );
			}
			WP_CLI::log( "Max iterations: {$max_iterations}" );
			WP_CLI::log( '' );
		}

		// Build options for Agent_Loop.
		$options = [
			'max_iterations' => $max_iterations,
		];

		if ( $model ) {
			$options['model_id'] = $model;
		}

		if ( $provider ) {
			$options['provider_id'] = $provider;
		}

		// In CLI mode, set all tool permissions to 'auto' so nothing blocks.
		$options['tool_permissions'] = [];

		// If --skip-tools, pass a bogus ability name so nothing resolves.
		$abilities = $no_tools ? [ '__none__' ] : [];

		$start_time = microtime( true );

		WP_CLI::log( 'Sending prompt to AI agent...' );

		$loop   = new Agent_Loop( $prompt, $abilities, [], $options );
		$result = $loop->run();

		// Handle awaiting_confirmation by auto-approving in a loop.
		while ( ! is_wp_error( $result ) && ! empty( $result['awaiting_confirmation'] ) ) {
			$pending = $result['pending_tools'] ?? [];

			if ( $verbose ) {
				WP_CLI::log( '' );
				WP_CLI::log( '--- Tools requiring confirmation (auto-approving) ---' );
				foreach ( $pending as $tool ) {
					$tool_name = $tool['name'] ?? 'unknown';
					$tool_args = isset( $tool['args'] ) ? wp_json_encode( $tool['args'] ) : '{}';
					if ( strlen( $tool_args ) > 200 ) {
						$tool_args = substr( $tool_args, 0, 200 ) . '...';
					}
					WP_CLI::log( "  {$tool_name}({$tool_args})" );
				}
			} else {
				$count = count( $pending );
				WP_CLI::log( "Auto-approving {$count} tool call(s)..." );
			}

			// Resume the loop with the serialized history.
			$history   = Agent_Loop::deserialize_history( $result['history'] ?? [] );
			$remaining = $result['iterations_remaining'] ?? $max_iterations;

			$resume_options                  = $options;
			$resume_options['tool_call_log'] = $result['tool_call_log'] ?? [];
			$resume_options['token_usage']   = $result['token_usage'] ?? [ 'prompt' => 0, 'completion' => 0 ];

			$loop   = new Agent_Loop( '', $abilities, $history, $resume_options );
			$result = $loop->resume_after_confirmation( true, $remaining );
		}

		// Extract data — either from a successful result or from the WP_Error data.
		$is_error   = is_wp_error( $result );
		$tool_calls = [];
		$usage      = [];
		$iterations = 0;
		$model_used = '';
		$reply      = '';

		if ( $is_error ) {
			$error_data = $result->get_error_data();
			if ( is_array( $error_data ) ) {
				$tool_calls = $error_data['tool_calls'] ?? [];
				$usage      = $error_data['token_usage'] ?? [];
				$iterations = $error_data['iterations_used'] ?? 0;
				$model_used = $error_data['model_id'] ?? '';
			}
		} else {
			$tool_calls = $result['tool_calls'] ?? [];
			$usage      = $result['token_usage'] ?? [];
			$iterations = $result['iterations_used'] ?? 0;
			$model_used = $result['model_id'] ?? '';
			$reply      = $result['reply'] ?? '';
		}

		// Print tool call log — always shown, detail level depends on --verbose.
		if ( ! empty( $tool_calls ) ) {
			WP_CLI::log( '' );

			$iteration = 0;

			foreach ( $tool_calls as $entry ) {
				if ( 'call' === $entry['type'] ) {
					$iteration++;

					if ( $verbose ) {
						WP_CLI::log( '' );
						WP_CLI::log( "  [{$iteration}] CALL  {$entry['name']}" );

						$args_str = isset( $entry['args'] ) ? wp_json_encode( $entry['args'], JSON_PRETTY_PRINT ) : '{}';
						if ( strlen( $args_str ) > 500 ) {
							$args_str = substr( $args_str, 0, 500 ) . '...';
						}
						foreach ( explode( "\n", $args_str ) as $line ) {
							WP_CLI::log( "        {$line}" );
						}
					} else {
						// Compact: one-liner with inline args.
						$args_str = isset( $entry['args'] ) ? wp_json_encode( $entry['args'] ) : '{}';
						if ( strlen( $args_str ) > 120 ) {
							$args_str = substr( $args_str, 0, 120 ) . '...';
						}
						WP_CLI::log( "  [{$iteration}] {$entry['name']}({$args_str})" );
					}
				} elseif ( 'response' === $entry['type'] ) {
					$resp_str = '';
					if ( isset( $entry['response'] ) ) {
						$resp_str = is_string( $entry['response'] )
							? $entry['response']
							: wp_json_encode( $entry['response'] );
					}

					if ( $verbose ) {
						// Pretty-print with generous limit.
						$resp_pretty = is_string( $entry['response'] ?? null )
							? $entry['response']
							: wp_json_encode( $entry['response'], JSON_PRETTY_PRINT );
						if ( strlen( $resp_pretty ) > 1000 ) {
							$resp_pretty = substr( $resp_pretty, 0, 1000 ) . "\n... [truncated]";
						}
						WP_CLI::log( '        RESULT:' );
						foreach ( explode( "\n", $resp_pretty ) as $line ) {
							WP_CLI::log( "        {$line}" );
						}
					} else {
						// Compact: short one-liner result.
						$has_error = false;
						if ( is_array( $entry['response'] ?? null ) && isset( $entry['response']['error'] ) ) {
							$resp_str  = $entry['response']['error'];
							$has_error = true;
						}
						if ( strlen( $resp_str ) > 120 ) {
							$resp_str = substr( $resp_str, 0, 120 ) . '...';
						}
						if ( $has_error ) {
							WP_CLI::log( "       ERROR: {$resp_str}" );
						} else {
							WP_CLI::log( "       => {$resp_str}" );
						}
					}
				}
			}
		}

		// Print the reply or error.
		WP_CLI::log( '' );

		if ( $is_error ) {
			WP_CLI::warning( $result->get_error_message() );
		} elseif ( empty( $reply ) ) {
			WP_CLI::warning( 'Agent returned an empty reply.' );
		} else {
			WP_CLI::log( $reply );
		}

		// Footer: token usage and timing.
		if ( $verbose ) {
			WP_CLI::log( '' );

			if ( ! empty( $usage ) ) {
				$prompt_tokens     = $usage['prompt'] ?? 0;
				$completion_tokens = $usage['completion'] ?? 0;
				WP_CLI::log( "Tokens — prompt: {$prompt_tokens}, completion: {$completion_tokens}, total: " . ( $prompt_tokens + $completion_tokens ) );
			}

			WP_CLI::log( "Iterations used: {$iterations}/{$max_iterations}" );

			if ( $model_used ) {
				WP_CLI::log( "Model: {$model_used}" );
			}

			$elapsed = round( microtime( true ) - $start_time, 2 );
			WP_CLI::log( "Total time: {$elapsed}s" );
		}
	}
}
