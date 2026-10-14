<?php
/**
 * Provider context-size audit harness (repository-only; excluded from builds by /tools in .distignore).
 *
 * Replays a multi-turn chat through the real AgentLoop, real abilities and real
 * session persistence, and records every provider request body. By default the
 * provider is faked at `pre_http_request`; set SD_AUDIT_LIVE=1 to send real
 * requests and only record them. See README.md in this directory.
 *
 * Abilities execute for real: scenarios must use read-only abilities only.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

// WP-CLI developer tool, never loaded by the plugin: plain local file I/O is intended.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited

use SdAiAgent\Core\AgentLoop;
use SdAiAgent\Core\ConversationSerializer;
use SdAiAgent\Core\ConversationTrimmer;
use SdAiAgent\Core\Database;

wp_set_current_user( 1 );

$mode     = getenv( 'SD_AUDIT_MODE' ) ?: 'native';
$scenario = json_decode( (string) file_get_contents( getenv( 'SD_AUDIT_SCENARIO' ) ), true );
// Request bodies can contain site data; keep them out of the repository by default.
$out_dir = getenv( 'SD_AUDIT_OUT' ) ?: sys_get_temp_dir() . '/sd-ai-agent-context-audit';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0700, true );
}

if ( 'chat' === $mode ) {
	add_filter( 'sd_ai_agent_openai_tool_search_enabled', '__return_false', 999 );
}

$GLOBALS['sd_audit'] = array(
	'queue'    => array(),
	'requests' => array(),
	'turn'     => 0,
	'seq'      => 0,
);

function sd_audit_len( $v ): int {
	return is_string( $v ) ? strlen( $v ) : strlen( (string) wp_json_encode( $v ) );
}

function sd_audit_analyze( string $url, string $body ): array {
	$data = json_decode( $body, true );
	$row  = array(
		'turn'     => $GLOBALS['sd_audit']['turn'],
		'endpoint' => preg_replace( '#^.*/v1/#', '', $url ),
		'bytes'    => strlen( $body ),
	);
	if ( ! is_array( $data ) ) {
		return $row;
	}
	if ( isset( $data['input'] ) ) {
		$row['instructions'] = sd_audit_len( $data['instructions'] ?? '' );
		$parts               = array();
		foreach ( $data['input'] as $item ) {
			$k           = $item['type'] ?? ( 'role:' . ( $item['role'] ?? '?' ) );
			$parts[ $k ] = ( $parts[ $k ] ?? 0 ) + sd_audit_len( $item );
			if ( 'function_call_output' === $k ) {
				$row['fco_sizes'][] = sd_audit_len( $item['output'] ?? '' );
			}
		}
		$row['input']       = $parts;
		$row['input_count'] = count( $data['input'] );
		$deferred           = 0;
		$immediate          = 0;
		$ns_overhead        = 0;
		$tools_total        = sd_audit_len( $data['tools'] ?? array() );
		foreach ( $data['tools'] ?? array() as $ns ) {
			foreach ( $ns['tools'] ?? array() as $fn ) {
				if ( ! empty( $fn['defer_loading'] ) ) {
					$deferred += sd_audit_len( $fn );
				} else {
					$immediate += sd_audit_len( $fn );
				}
			}
		}
		$row['tools_total']     = $tools_total;
		$row['tools_deferred']  = $deferred;
		$row['tools_immediate'] = $immediate;
		$row['tools_ns_meta']   = $tools_total - $deferred - $immediate;
		$row['model_visible']   = strlen( $body ) - $deferred;
	} elseif ( isset( $data['messages'] ) ) {
		$parts = array();
		foreach ( $data['messages'] as $m ) {
			$k           = $m['role'] ?? '?';
			$parts[ $k ] = ( $parts[ $k ] ?? 0 ) + sd_audit_len( $m );
			if ( 'tool' === $k ) {
				$row['tool_msg_sizes'][] = sd_audit_len( $m['content'] ?? '' );
			}
		}
		$row['messages']      = $parts;
		$row['msg_count']     = count( $data['messages'] );
		$row['tools_total']   = sd_audit_len( $data['tools'] ?? array() );
		$row['tools_count']   = count( $data['tools'] ?? array() );
		$row['model_visible'] = strlen( $body );
	}
	return $row;
}

function sd_audit_fn_name( string $ability ): string {
	return class_exists( 'WP_AI_Client_Ability_Function_Resolver' )
		? WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $ability )
		: 'wpab__' . str_replace( '/', '__', $ability );
}

function sd_audit_fake_response( string $url, array $data ): array {
	$seq  = ++$GLOBALS['sd_audit']['seq'];
	$step = array_shift( $GLOBALS['sd_audit']['queue'] ) ?? array( 'text' => 'Done.' );
	$is_r = str_ends_with( $url, '/responses' );

	$available = array();
	if ( $is_r ) {
		foreach ( $data['tools'] ?? array() as $ns ) {
			foreach ( $ns['tools'] ?? array() as $fn ) {
				$available[ $fn['name'] ] = $ns;
			}
		}
	} else {
		foreach ( $data['tools'] ?? array() as $t ) {
			$available[ $t['function']['name'] ?? '' ] = true;
		}
	}

	$call = null;
	if ( isset( $step['call'] ) ) {
		$fn   = sd_audit_fn_name( $step['call'] );
		$args = $step['args'] ?? new stdClass();
		if ( ! isset( $available[ $fn ] ) ) {
			// Non-native path: use the generic dispatcher when the schema is not direct.
			$fn   = sd_audit_fn_name( 'sd-ai-agent/ability-call' );
			$args = array(
				'ability'   => $step['call'],
				'arguments' => $step['args'] ?? new stdClass(),
			);
		}
		$call = array( $fn, $args );
	}

	if ( $is_r ) {
		$output   = array();
		$output[] = array(
			'type'              => 'reasoning',
			'id'                => 'rs_' . $seq,
			'summary'           => array(),
			'encrypted_content' => bin2hex( random_bytes( 1200 ) ),
		);
		if ( $call ) {
			$ns = $available[ $call[0] ] ?? null;
			if ( is_array( $ns ) && ! empty( $ns['tools'][0]['defer_loading'] ) && empty( $step['no_search'] ) ) {
				$loaded = $ns;
				foreach ( $loaded['tools'] as &$t ) {
					unset( $t['defer_loading'] );
				}
				unset( $t );
				$output[] = array(
					'type'      => 'tool_search_call',
					'id'        => 'tsc_' . $seq,
					'call_id'   => 'ts_' . $seq,
					'status'    => 'completed',
					'execution' => 'server',
					'arguments' => array( 'query' => $step['call'] ),
				);
				$output[] = array(
					'type'      => 'tool_search_output',
					'id'        => 'tso_' . $seq,
					'call_id'   => 'ts_' . $seq,
					'status'    => 'completed',
					'execution' => 'server',
					'tools'     => array( $loaded ),
				);
			}
			$output[] = array(
				'type'      => 'function_call',
				'id'        => 'fc_' . $seq,
				'call_id'   => 'call_' . $seq,
				'name'      => $call[0],
				'arguments' => wp_json_encode( $call[1] ),
				'status'    => 'completed',
			);
		} else {
			$output[] = array(
				'type'    => 'message',
				'id'      => 'msg_' . $seq,
				'role'    => 'assistant',
				'status'  => 'completed',
				'content' => array(
					array(
						'type' => 'output_text',
						'text' => $step['text'],
					),
				),
			);
		}
		$body = array(
			'id'     => 'resp_' . $seq,
			'status' => 'completed',
			'output' => $output,
			'usage'  => array(
				'input_tokens'  => 1,
				'output_tokens' => 1,
				'total_tokens'  => 2,
			),
		);
	} else {
		$message = array( 'role' => 'assistant' );
		if ( $call ) {
			$message['content']    = null;
			$message['tool_calls'] = array(
				array(
					'id'       => 'call_' . $seq,
					'type'     => 'function',
					'function' => array(
						'name'      => $call[0],
						'arguments' => wp_json_encode( $call[1] ),
					),
				),
			);
		} else {
			$message['content'] = $step['text'];
		}
		$body = array(
			'id'      => 'chatcmpl_' . $seq,
			'object'  => 'chat.completion',
			'model'   => $data['model'] ?? 'x',
			'choices' => array(
				array(
					'index'         => 0,
					'message'       => $message,
					'finish_reason' => $call ? 'tool_calls' : 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 1,
				'completion_tokens' => 1,
				'total_tokens'      => 2,
			),
		);
	}

	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode( $body ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

add_filter(
	'pre_http_request',
	static function ( $response, $args, $url ) use ( $out_dir ) {
		if ( ! preg_match( '#/(responses|chat/completions)$#', (string) $url ) ) {
			return $response;
		}
		$body = is_string( $args['body'] ?? null ) ? $args['body'] : '';
		$row  = sd_audit_analyze( (string) $url, $body );
		$n    = count( $GLOBALS['sd_audit']['requests'] ) + 1;
		file_put_contents( sprintf( '%s/req-%02d-t%d.json', $out_dir, $n, $GLOBALS['sd_audit']['turn'] ), $body );
		if ( is_wp_error( $response ) ) {
			$row['rejected']                   = $response->get_error_code();
			$GLOBALS['sd_audit']['requests'][] = $row;
			return $response;
		}
		$GLOBALS['sd_audit']['requests'][] = $row;
		if ( getenv( 'SD_AUDIT_LIVE' ) ) {
			return $response;
		}
		$data = json_decode( $body, true );
		return sd_audit_fake_response( (string) $url, is_array( $data ) ? $data : array() );
	},
	PHP_INT_MAX,
	3
);

// Real session so native continuation and persistence behave like production.
$session_id = Database::create_session(
	array(
		'user_id' => 1,
		'title'   => 'ctx audit ' . $mode,
	)
);

foreach ( $scenario as $i => $turn ) {
	$GLOBALS['sd_audit']['turn']  = $i + 1;
	$GLOBALS['sd_audit']['queue'] = $turn['steps'];

	if ( (int) getenv( 'SD_AUDIT_DROP_CURSOR_TURN' ) === $i + 1 ) {
		delete_transient( 'sd_ai_agent_responses_' . get_current_blog_id() . '_' . $session_id . '_1' );
	}
	$session  = Database::get_session( $session_id );
	$messages = json_decode( (string) $session->messages, true ) ?: array();
	$history  = empty( $messages ) ? array() : ConversationTrimmer::validate_tool_pairs( ConversationSerializer::deserialize( array_values( $messages ) ) );

	$loop   = new AgentLoop(
		$turn['user'],
		array(),
		$history,
		array(
			'session_id'                  => $session_id,
			'yolo_mode'                   => true,
			'provider_retry_max_attempts' => 1,
		)
	);
	$result = $loop->run();
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, sprintf( "turn %d error: %s %s\n", $i + 1, $result->get_error_code(), $result->get_error_message() ) );
		$GLOBALS['sd_audit']['requests'][] = array(
			'turn'  => $i + 1,
			'error' => $result->get_error_code(),
		);
		break;
	}
	if ( ! empty( $result['awaiting_confirmation'] ) || ! empty( $result['pending_client_tool_calls'] ) ) {
		fwrite( STDERR, 'turn ' . ( $i + 1 ) . " paused\n" );
	}
	if ( getenv( 'SD_AUDIT_LIVE' ) ) {
		fwrite( STDERR, 'REPLY ' . ( $i + 1 ) . ': ' . substr( (string) ( $result['reply'] ?? '' ), 0, 400 ) . "\n" );
	}
	$full     = $result['history'] ?? array();
	$appended = array_slice( $full, count( $messages ) );
	Database::append_to_session( $session_id, array_values( $appended ), $result['tool_calls'] ?? array() );
}

file_put_contents( $out_dir . '/summary.json', wp_json_encode( $GLOBALS['sd_audit']['requests'], JSON_PRETTY_PRINT ) );
foreach ( $GLOBALS['sd_audit']['requests'] as $r ) {
	WP_CLI::line( wp_json_encode( $r ) );
}
Database::delete_session( (int) $session_id );
