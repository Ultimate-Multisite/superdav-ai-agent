<?php

declare(strict_types=1);
/**
 * Plugin Generator — AI-powered WordPress plugin plan and code generation.
 *
 * Uses wp_ai_client_prompt() (WordPress 7.0+ AI Client SDK) to:
 *   1. Generate an implementation plan from a natural-language description.
 *   2. Generate the full plugin PHP code from that plan.
 *
 * @package GratisAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\PluginBuilder;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PluginGenerator — generates WordPress plugins via the AI Client SDK.
 *
 * @since 1.5.0
 */
class PluginGenerator {

	/**
	 * Generate a plugin implementation plan from a description.
	 *
	 * @param string $description Natural-language plugin description.
	 * @param string $model_id    Optional AI model ID (empty = SDK default).
	 * @return array{plan: string}|\WP_Error
	 */
	public static function generate_plan( string $description, string $model_id = '' ): array|\WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'ai_client_unavailable',
				__( 'wp_ai_client_prompt() is not available.', 'gratis-ai-agent' )
			);
		}

		if ( empty( trim( $description ) ) ) {
			return new WP_Error(
				'gratis_ai_agent_empty_description',
				__( 'Plugin description must not be empty.', 'gratis-ai-agent' )
			);
		}

		$system_instruction = <<<'INSTRUCTION'
You are an expert WordPress plugin architect. Your task is to produce a concise implementation plan for a WordPress plugin.

The plan must:
- List the plugin files to be created (at minimum one main PHP file).
- For each file: its path relative to the plugin directory, purpose, and key classes/functions.
- Specify any WordPress hooks (actions/filters) used and why.
- Be no more than 400 words.
- Use plain text with section headings — no markdown code blocks.
INSTRUCTION;

		$prompt = "Create an implementation plan for a WordPress plugin with this description:\n\n" . $description;

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system_instruction );

		if ( ! empty( $model_id ) ) {
			$builder = $builder->using_model_preference( $model_id );
		}

		$result = $builder->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'plan' => (string) $result ];
	}

	/**
	 * Generate the full plugin PHP source code from a plan and description.
	 *
	 * Returns an array keyed by relative file path, with PHP source as values:
	 *   [ 'my-plugin/my-plugin.php' => '<?php ...', ... ]
	 *
	 * @param string $description Natural-language plugin description.
	 * @param string $plan        Implementation plan from generate_plan().
	 * @param string $slug        Plugin slug (e.g. "cookie-consent").
	 * @param string $model_id    Optional AI model ID.
	 * @return array{files: array<string,string>, plugin_file: string, slug: string}|\WP_Error
	 */
	public static function generate_code(
		string $description,
		string $plan,
		string $slug,
		string $model_id = ''
	): array|\WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'ai_client_unavailable',
				__( 'wp_ai_client_prompt() is not available.', 'gratis-ai-agent' )
			);
		}

		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			$slug = 'generated-plugin-' . time();
		}

		$system_instruction = <<<'INSTRUCTION'
You are an expert WordPress plugin developer. Generate production-ready WordPress plugin code.

Rules:
- Output ONLY valid PHP code. No explanations before or after file blocks.
- Use the following file block format for each file:

===FILE: {filename}===
<?php
... code ...
===ENDFILE===

- The main plugin file must include the standard WordPress plugin header comment.
- Use declare(strict_types=1) in every PHP file.
- Follow WordPress Coding Standards: snake_case functions, PascalCase classes.
- Sanitize all inputs, escape all outputs.
- Prefix all function and class names with the plugin slug (underscores, not hyphens).
- The plugin must be self-contained — no external dependencies beyond WordPress core.
INSTRUCTION;

		$prompt  = "Plugin description: {$description}\n\n";
		$prompt .= "Implementation plan:\n{$plan}\n\n";
		$prompt .= "Plugin slug: {$slug}\n\n";
		$prompt .= 'Generate the complete PHP source code for this plugin.';

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system_instruction );

		if ( ! empty( $model_id ) ) {
			$builder = $builder->using_model_preference( $model_id );
		}

		$raw = $builder->generate_text();

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$files       = self::parse_file_blocks( (string) $raw, $slug );
		$plugin_file = self::detect_main_file( $files, $slug );

		if ( empty( $files ) ) {
			return new WP_Error(
				'gratis_ai_agent_no_files_generated',
				__( 'AI did not produce any file blocks. Try again with a more detailed description.', 'gratis-ai-agent' )
			);
		}

		return [
			'files'       => $files,
			'plugin_file' => $plugin_file,
			'slug'        => $slug,
		];
	}

	/**
	 * Parse ===FILE: ... ===ENDFILE=== blocks from AI output.
	 *
	 * @param string $raw  Raw AI response.
	 * @param string $slug Plugin slug for fallback.
	 * @return array<string,string> Map of relative path → source code.
	 */
	public static function parse_file_blocks( string $raw, string $slug ): array {
		$files   = [];
		$pattern = '/===FILE:\s*([^\n=]+)===[^\n]*\n(.*?)===ENDFILE===/s';
		preg_match_all( $pattern, $raw, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$path           = trim( $match[1] );
			$code           = $match[2];
			$files[ $path ] = $code;
		}

		// Fallback: if no blocks found, treat entire output as the main file.
		if ( empty( $files ) && str_contains( $raw, '<?php' ) ) {
			$files[ $slug . '/' . $slug . '.php' ] = $raw;
		}

		return $files;
	}

	/**
	 * Detect the main plugin file from the generated file map.
	 *
	 * The main file is the one containing the WordPress plugin header comment.
	 * Falls back to the first .php file in the map.
	 *
	 * @param array<string,string> $files Map of relative path → source code.
	 * @param string               $slug  Plugin slug.
	 * @return string Relative path to main plugin file, or empty string.
	 */
	public static function detect_main_file( array $files, string $slug ): string {
		foreach ( $files as $path => $code ) {
			if ( str_contains( $code, 'Plugin Name:' ) ) {
				return $path;
			}
		}

		// Fallback: look for file matching slug/slug.php.
		$candidate = $slug . '/' . $slug . '.php';
		if ( isset( $files[ $candidate ] ) ) {
			return $candidate;
		}

		// Last resort: first PHP file.
		foreach ( array_keys( $files ) as $path ) {
			if ( str_ends_with( $path, '.php' ) ) {
				return $path;
			}
		}

		return '';
	}
}
