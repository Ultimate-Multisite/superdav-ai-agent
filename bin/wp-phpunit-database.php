<?php
/**
 * Shared safety checks for the local WordPress PHPUnit database.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

/**
 * Read database connection values from a WordPress PHPUnit config without
 * loading executable configuration code.
 *
 * @param string $tests_dir WordPress PHPUnit test-library directory.
 * @return array<string, string>
 */
function sd_ai_agent_phpunit_read_config_database(string $tests_dir): array
{
	$config_file = rtrim($tests_dir, '/') . '/wp-tests-config.php';
	if (! is_file($config_file)) {
		return array();
	}

	$content = file_get_contents($config_file);
	if (false === $content) {
		return array();
	}

	$database = array();
	foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $constant) {
		$patterns = array(
			"/define\\s*\\(\\s*'" . $constant . "'\\s*,\\s*'((?:\\\\\\\\.|[^'])*)'\\s*\\)/",
			'/define\\s*\\(\\s*"' . $constant . '"\\s*,\\s*"((?:\\\\\\\\.|[^\"])*)"\\s*\\)/',
		);

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $content, $matches)) {
				$database[$constant] = str_replace(array("\\'", '\\\\'), array("'", '\\'), $matches[1]);
				break;
			}
		}
	}

	return $database;
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
	$config_file = rtrim($tests_dir, '/') . '/wp-tests-config.php';
	if (! is_file($config_file)) {
		return 'WordPress test config not found. Run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.';
	}

	$content = file_get_contents($config_file);
	if (false === $content) {
		return 'WordPress test config could not be read. Create a fresh isolated PHPUnit cache before running tests.';
	}

	if (1 !== preg_match("/define\\s*\\(\\s*'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED'\\s*,\\s*true\\s*\\)/", $content)) {
		return 'WordPress test config is not marked as isolated. Create a fresh WP_PHPUNIT_CACHE_DIR and run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.';
	}

	$database = sd_ai_agent_phpunit_read_config_database($tests_dir);
	foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $constant) {
		if (! isset($database[$constant])) {
			return 'WordPress test config does not declare a complete database connection. Create a fresh isolated PHPUnit cache before running tests.';
		}
	}

	return sd_ai_agent_phpunit_validate_database_name($database['DB_NAME']);
}
