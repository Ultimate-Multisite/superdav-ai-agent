<?php
/**
 * DI handler for LLM provider HTTP trace hooks.
 *
 * Replaces the `ProviderTraceLogger::register()` call in CoreServicesHandler
 * by wiring the two WordPress HTTP-API filters directly via `#[Filter]`
 * attributes.
 *
 * The underlying recording logic lives in
 * {@see \SdAiAgent\Core\ProviderTraceLogger}. This handler is a thin
 * DI bridge — its sole responsibility is hook registration and arg forwarding.
 *
 * @package SdAiAgent\Bootstrap
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Bootstrap;

use SdAiAgent\Core\ProviderTraceLogger;
use SdAiAgent\Models\ProviderTrace;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures outgoing HTTP requests and responses for LLM provider tracing.
 *
 * CTX_GLOBAL ensures the filters are active in every request context — AI
 * calls can originate from admin (manual runs), REST (webhook triggers), CLI,
 * and cron (scheduled tasks).
 *
 * Both filter callbacks are no-ops when WP_DEBUG is not active. The DI
 * container always instantiates this handler, but the actual recording never
 * happens on production sites where WP_DEBUG is false or undefined.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_GLOBAL,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class HttpTraceHandler {

	/**
	 * Capture outgoing request details before the HTTP call is made.
	 *
	 * Returns `$preempt` unchanged — this filter is used only for its
	 * side-effect of recording in-flight request metadata. No-op when
	 * WP_DEBUG is not active.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt     A preemptive return value. Default false.
	 * @param array<string,mixed>                 $parsed_args HTTP request arguments.
	 * @param string                              $url         The request URL.
	 * @return false|array<string,mixed>|\WP_Error Unchanged $preempt.
	 */
	#[Filter( tag: 'pre_http_request', priority: 10 )]
	public function on_pre_http_request( mixed $preempt, array $parsed_args, string $url ): mixed {
		if ( ! ProviderTrace::is_debug_mode() ) {
			return $preempt;
		}
		return ProviderTraceLogger::on_pre_http_request( $preempt, $parsed_args, $url );
	}

	/**
	 * Capture response details and write a trace record.
	 *
	 * Returns `$response` unchanged — this filter is used only for its
	 * side-effect of persisting the completed trace row. No-op when
	 * WP_DEBUG is not active.
	 *
	 * @param array<string,mixed> $response    HTTP response array.
	 * @param array<string,mixed> $parsed_args HTTP request arguments.
	 * @param string              $url         The request URL.
	 * @return array<string,mixed> Unchanged $response.
	 */
	#[Filter( tag: 'http_response', priority: 10 )]
	public function on_http_response( array $response, array $parsed_args, string $url ): array {
		if ( ! ProviderTrace::is_debug_mode() ) {
			return $response;
		}
		return ProviderTraceLogger::on_http_response( $response, $parsed_args, $url );
	}
}
