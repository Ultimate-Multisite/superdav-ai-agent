<?php
/**
 * Shared safety checks for the local WordPress PHPUnit database.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/**
 * Read supported PHPUnit safety declarations without loading configuration.
 *
 * @param string $config_file WordPress PHPUnit configuration file.
 * @return array<string, string|bool>|false False when a relevant declaration is unsupported or ambiguous.
 */
function sd_ai_agent_phpunit_read_config_definitions(string $config_file): array|false
{
	if (! is_file($config_file)) {
		return array();
	}

	$content = file_get_contents($config_file);
	if (false === $content) {
		return array();
	}

	$definitions = array();
	$constants   = array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED');
	$tokens      = token_get_all($content);

	for ($index = 0; isset($tokens[$index]); ++$index) {
		if (! is_array($tokens[$index]) || T_STRING !== $tokens[$index][0] || 'define' !== strtolower($tokens[$index][1])) {
			continue;
		}

		$next_token = static function () use ($tokens, &$index) {
			do {
				++$index;
			} while (isset($tokens[$index]) && is_array($tokens[$index]) && in_array($tokens[$index][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true));

			return $tokens[$index] ?? null;
		};

		if ('(' !== $next_token() || ! is_array($next_token()) || T_CONSTANT_ENCAPSED_STRING !== $tokens[$index][0]) {
			continue;
		}

		$name_literal = $tokens[$index][1];
		$name         = substr($name_literal, 1, -1);
		if (! in_array($name, $constants, true)) {
			continue;
		}

		if (',' !== $next_token()) {
			return false;
		}

		$value_token = $next_token();
		if (isset($definitions[$name]) || ')' !== $next_token()) {
			return false;
		}

		if ('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED' === $name) {
			if (! is_array($value_token) || T_STRING !== $value_token[0] || 'true' !== strtolower($value_token[1])) {
				return false;
			}
			$definitions[$name] = true;
			continue;
		}

		if (! is_array($value_token) || T_CONSTANT_ENCAPSED_STRING !== $value_token[0]) {
			return false;
		}

		$value_literal       = $value_token[1];
		$quote               = $value_literal[0];
		$definitions[$name] = str_replace(array('\\\\', '\\' . $quote), array('\\', $quote), substr($value_literal, 1, -1));
	}

	return $definitions;
}

/**
 * Read database connection values from a WordPress PHPUnit config without
 * loading executable configuration code.
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

	return array_filter(
		$definitions,
		static fn (string $constant): bool => in_array($constant, array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'), true),
		ARRAY_FILTER_USE_KEY
	);
}

/**
 * Return the validation error for a test database name, if any.
 *
 * A test-like name makes destructive setup visibly distinct from a local site
 * database. WP_LIVE_DB_NAME optionally declares the local site database so a
 * collision is always rejected even when its name happens to include "test".
 *
 * @param string $database_name Database name selected for PHPUnit.
 * @return string|null
 */
function sd_ai_agent_phpunit_validate_database_name(string $database_name): ?string
{
	if ('' === $database_name) {
		return 'WordPress PHPUnit requires a dedicated test database. Set WP_TESTS_DB_NAME to a separate database name.';
	}

	if (1 !== preg_match('/(?:^|[_-])(?:test|tests|phpunit|ci)(?:[_-]|$)/i', $database_name)) {
		return 'Refusing to use a database whose name is not clearly a test database. Set WP_TESTS_DB_NAME to a separate name containing test, tests, phpunit, or ci.';
	}

	$live_database = getenv('WP_LIVE_DB_NAME');
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
	return sd_ai_agent_phpunit_validate_test_config_file(rtrim($tests_dir, '/') . '/wp-tests-config.php');
}

/**
 * Return the validation error for a specific WordPress PHPUnit config file.
 *
 * @param string $config_file WordPress PHPUnit configuration file.
 * @return string|null
 */
function sd_ai_agent_phpunit_validate_test_config_file(string $config_file): ?string
{
	if (! is_file($config_file)) {
		return 'WordPress test config not found. Run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.';
	}

	$content = file_get_contents($config_file);
	if (false === $content) {
		return 'WordPress test config could not be read. Create a fresh isolated PHPUnit cache before running tests.';
	}

	$definitions = sd_ai_agent_phpunit_read_config_definitions($config_file);
	if (false === $definitions) {
		return 'WordPress test config contains unsupported or ambiguous safety declarations. Create a fresh isolated PHPUnit cache before running tests.';
	}

	if (! isset($definitions['SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED'])) {
		return 'WordPress test config is not marked as isolated. Create a fresh WP_PHPUNIT_CACHE_DIR and run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.';
	}

	$database = array_filter(
		$definitions,
		static fn (string $constant): bool => in_array($constant, array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'), true),
		ARRAY_FILTER_USE_KEY
	);
	foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $constant) {
		if (! isset($database[$constant])) {
			return 'WordPress test config does not declare a complete database connection. Create a fresh isolated PHPUnit cache before running tests.';
		}
	}

	return sd_ai_agent_phpunit_validate_database_name($database['DB_NAME']);
}
