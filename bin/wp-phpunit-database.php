<?php
/**
 * Shared safety checks for the local WordPress PHPUnit database.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/**
 * Read literal, unconditional configuration declarations without executing PHP.
 *
 * Comments and unrelated strings cannot supply declarations. Duplicate,
 * conditional, interpolated, or computed declarations fail closed.
 *
 * @param string $config_file Configuration path.
 * @return array<string, string|bool>|false False for unsupported or ambiguous declarations.
 */
function sd_ai_agent_phpunit_read_config_definitions(string $config_file): array|false
{
	$content = is_file($config_file) ? file_get_contents($config_file) : false;
	if (false === $content) {
		return array();
	}

	$tokens = array_values(array_filter(token_get_all($content), static function ($token): bool {
		return ! is_array($token) || ! in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true);
	}));
	$names = array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED');
	$constants = array();
	$depth = 0;
	foreach ($tokens as $index => $token) {
		// Dynamic define names and const declarations cannot be checked
		// against the selected connection, so refuse them before bootstrap.
		if (is_array($token) && in_array($token[0], array(T_STRING, T_NAME_FULLY_QUALIFIED), true)
			&& 'define' === strtolower(ltrim($token[1], '\\')) && '(' === ($tokens[$index + 1] ?? null)) {
			$name_token = $tokens[$index + 2] ?? null;
			if (! is_array($name_token) || T_CONSTANT_ENCAPSED_STRING !== $name_token[0] || ',' !== ($tokens[$index + 3] ?? null) || str_contains($name_token[1], '\\')) {
				return false;
			}
		}
		if (is_array($token) && T_CONST === $token[0]) {
			for ($cursor = $index + 1; isset($tokens[$cursor]) && ';' !== $tokens[$cursor]; ++$cursor) {
				if (is_array($tokens[$cursor]) && T_STRING === $tokens[$cursor][0] && in_array($tokens[$cursor][1], $names, true)) {
					return false;
				}
			}
		}
		if (in_array($token, array('{', '(', '['), true)) {
			++$depth;
		} elseif (in_array($token, array('}', ')', ']'), true)) {
			--$depth;
		}
		if (! is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
			continue;
		}
		$name = substr($token[1], 1, -1);
		if (! in_array($name, $names, true)) {
			continue;
		}
		$function = $tokens[$index - 2] ?? null;
		// Ignore names in unrelated strings, but never accept a nested define.
		if (! is_array($function) || ! in_array($function[0], array(T_STRING, T_NAME_FULLY_QUALIFIED), true) || 'define' !== strtolower(ltrim($function[1], '\\'))) {
			continue;
		}
		$previous = $tokens[$index - 3] ?? null;
		$value = $tokens[$index + 2] ?? null;
		if (1 !== $depth || isset($constants[$name])
			|| ! (';' === $previous || (is_array($previous) && T_OPEN_TAG === $previous[0]))
			|| '(' !== ($tokens[$index - 1] ?? null) || ',' !== ($tokens[$index + 1] ?? null)
			|| ')' !== ($tokens[$index + 3] ?? null) || ';' !== ($tokens[$index + 4] ?? null)
			|| ! is_array($value)) {
			return false;
		}
		if ('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED' === $name) {
			if (T_STRING !== $value[0] || 'true' !== strtolower($value[1])) {
				return false;
			}
			$constants[$name] = true;
		} else {
			if (T_CONSTANT_ENCAPSED_STRING !== $value[0]) {
				return false;
			}
			$literal = substr($value[1], 1, -1);
			// Support PHP single-quoted escapes; reject double-quoted escapes
			// rather than risk decoding a different connection than PHP uses.
			if ('"' === $value[1][0] && str_contains($literal, '\\')) {
				return false;
			}
			$constants[$name] = str_replace(array("\\'", '\\\\'), array("'", '\\'), $literal);
		}
	}
	return $constants;
}

/**
 * Read the selected test connection without executing configuration code.
 *
 * @param string $tests_dir WordPress PHPUnit test-library directory.
 * @return array<string, string>
 */
function sd_ai_agent_phpunit_read_config_database(string $tests_dir): array
{
	$definitions = sd_ai_agent_phpunit_read_config_definitions(rtrim($tests_dir, '/') . '/wp-tests-config.php');
	if (false === $definitions) {
		return array();
	}
	unset($definitions['SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED']);
	return $definitions;
}

/**
 * Return the validation error for a test database name, if any.
 *
 * A test-like name makes destructive setup visibly distinct from a local site
 * database. WP_LIVE_DB_NAME optionally declares the local site database so a
 * collision is always rejected even when its name happens to include "test".
 *
 * @param string $database_name Database name selected for PHPUnit.
 * @param string|null $live_config Shared WordPress config path (defaults to ../wordpress).
 * @return string|null
 */
function sd_ai_agent_phpunit_validate_database_name(string $database_name, ?string $live_config = null): ?string
{
	if ('' === $database_name) {
		return 'WordPress PHPUnit requires a dedicated test database. Set WP_TESTS_DB_NAME to a separate database name.';
	}

	if (1 !== preg_match('/(?:^|[_-])(?:test|tests|phpunit|ci)(?:[_-]|$)/i', $database_name)) {
		return 'Refusing to use a database whose name is not clearly a test database. Set WP_TESTS_DB_NAME to a separate name containing test, tests, phpunit, or ci.';
	}

	$live_database = getenv('WP_LIVE_DB_NAME');
	$live_config ??= dirname(__DIR__, 2) . '/wordpress/wp-config.php';
	if (is_file($live_config)) {
		$live = sd_ai_agent_phpunit_read_config_definitions($live_config);
		if (! isset($live['DB_NAME']) && (false === $live_database || '' === $live_database)) {
			return 'Cannot determine the shared WordPress database. Set WP_LIVE_DB_NAME before PHPUnit setup or execution.';
		}
		if (($live['DB_NAME'] ?? null) === $database_name) {
			return 'Refusing to use the shared WordPress database. Configure a separate test database.';
		}
	}
	if (false !== $live_database && '' !== $live_database && hash_equals($live_database, $database_name)) {
		return 'Refusing to use the database declared by WP_LIVE_DB_NAME. Configure WP_TESTS_DB_NAME with a separate test database.';
	}

	return null;
}

/**
 * Return the validation error for an installed WordPress PHPUnit config.
 *
 * @param string $tests_dir WordPress PHPUnit test-library directory.
 * @return string|null
 */
function sd_ai_agent_phpunit_validate_test_config(string $tests_dir): ?string
{
	$config_file = rtrim($tests_dir, '/') . '/wp-tests-config.php';
	if (defined('WP_TESTS_CONFIG_FILE_PATH') && realpath((string) WP_TESTS_CONFIG_FILE_PATH) !== realpath($config_file)) {
		return 'Refusing an alternate WP_TESTS_CONFIG_FILE_PATH. Use the validated test-library configuration.';
	}
	return sd_ai_agent_phpunit_validate_test_config_file($config_file);
}

/**
 * Validate the declarations in a specific configuration file.
 *
 * @param string $config_file WordPress PHPUnit configuration file.
 * @return string|null
 */
function sd_ai_agent_phpunit_validate_test_config_file(string $config_file): ?string
{
	if (! is_file($config_file)) {
		return 'WordPress test config not found. Run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.';
	}
	$database = sd_ai_agent_phpunit_read_config_definitions($config_file);
	if (false === $database) {
		return 'WordPress test config contains unsupported or ambiguous safety declarations. Create a fresh isolated PHPUnit cache before running tests.';
	}
	if (($database['SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED'] ?? null) !== true) {
		return 'WordPress test config is not marked as isolated. Create a fresh WP_PHPUNIT_CACHE_DIR and run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.';
	}

	foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $constant) {
		if (! isset($database[$constant])) {
			return 'WordPress test config does not declare a complete database connection. Create a fresh isolated PHPUnit cache before running tests.';
		}
	}

	return sd_ai_agent_phpunit_validate_database_name($database['DB_NAME']);
}

// The shell installer uses exactly the same parser and policy as PHPUnit.
if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
	$error = sd_ai_agent_phpunit_validate_database_name($argv[2] ?? '');
	if (null === $error && is_file(($argv[1] ?? '') . '/wp-tests-config.php')) {
		$error = sd_ai_agent_phpunit_validate_test_config($argv[1]);
		$database = sd_ai_agent_phpunit_read_config_database($argv[1]);
		if (null === $error && ($database['DB_NAME'] ?? null) !== ($argv[2] ?? '')) {
			$error = 'Existing test config selects a different database. Use a fresh WP_PHPUNIT_CACHE_DIR.';
		}
	}
	if (null !== $error) {
		fwrite(STDERR, $error . PHP_EOL);
		exit(1);
	}
}
