<?php
/**
 * Database safety preflights run without booting WordPress or touching MySQL.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/bin/wp-phpunit-database.php';

class PhpunitDatabaseSafetyTest extends TestCase {
	private string $fixtureDir;
	private string|false $liveDatabase;

	protected function setUp(): void {
		$this->fixtureDir = sys_get_temp_dir() . '/sd-agent-db-guard-' . bin2hex(random_bytes(8));
		mkdir($this->fixtureDir);
		$this->liveDatabase = getenv('WP_LIVE_DB_NAME');
		putenv('WP_LIVE_DB_NAME=fixture_live_tests');
	}

	protected function tearDown(): void {
		unlink($this->fixtureDir . '/wp-tests-config.php');
		rmdir($this->fixtureDir);
		putenv(false === $this->liveDatabase ? 'WP_LIVE_DB_NAME' : 'WP_LIVE_DB_NAME=' . $this->liveDatabase);
	}

	private function write_config(string $declarations): void {
		file_put_contents($this->fixtureDir . '/wp-tests-config.php', "<?php\n" . $declarations . "\ndefine('DB_USER', 'fixture');\ndefine('DB_PASSWORD', '');\ndefine('DB_HOST', 'localhost');\n");
	}

	public function test_accepts_literal_isolated_config(): void {
		$this->write_config("define('DB_NAME', 'fixture_safe_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertNull(sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_commented_database_cannot_hide_live_collision(): void {
		$this->write_config("// define('DB_NAME', 'fixture_safe_tests');\ndefine('DB_NAME', 'fixture_live_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertStringContainsString('WP_LIVE_DB_NAME', sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_commented_marker_is_rejected(): void {
		$this->write_config("define('DB_NAME', 'fixture_safe_tests');\n/* define('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true); */");
		self::assertNotNull(sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_embedded_string_is_not_a_marker(): void {
		$this->write_config('define("DB_NAME", "fixture_safe_tests"); $example = "define(\'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED\', true);";');
		self::assertNotNull(sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_duplicate_database_is_rejected(): void {
		$this->write_config("define('DB_NAME', 'fixture_safe_tests');\ndefine('DB_NAME', 'fixture_live_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertSame(array(), sd_ai_agent_phpunit_read_config_database($this->fixtureDir));
	}

	public function test_const_database_cannot_hide_live_connection(): void {
		$this->write_config("const DB_NAME = 'fixture_live_tests';\ndefine('DB_NAME', 'fixture_safe_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertSame(array(), sd_ai_agent_phpunit_read_config_database($this->fixtureDir));
	}

	public function test_dynamic_constant_name_is_rejected(): void {
		$this->write_config("define('DB_' . 'NAME', 'fixture_live_tests');\ndefine('DB_NAME', 'fixture_safe_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertSame(array(), sd_ai_agent_phpunit_read_config_database($this->fixtureDir));
	}

	public function test_conditional_database_is_rejected(): void {
		$this->write_config("if (false) { define('DB_NAME', 'fixture_safe_tests'); }\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertNotNull(sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_computed_database_is_rejected_without_execution(): void {
		$this->write_config("define('DB_NAME', exit(99));\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		self::assertNotNull(sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_double_quoted_declarations_are_supported(): void {
		$this->write_config('define("DB_NAME", "fixture_safe_tests"); define("SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED", true);');
		self::assertNull(sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}

	public function test_single_quoted_escapes_match_php_literals(): void {
		$password = "fixture\\\\'value\\tail";
		file_put_contents($this->fixtureDir . '/wp-tests-config.php', "<?php\ndefine('DB_PASSWORD', " . var_export($password, true) . ");\n");
		self::assertSame($password, sd_ai_agent_phpunit_read_config_database($this->fixtureDir)['DB_PASSWORD']);
	}

	public function test_shared_database_is_detected_without_environment_hint(): void {
		putenv('WP_LIVE_DB_NAME');
		$this->write_config("define('DB_NAME', 'fixture_shared_tests');");
		self::assertStringContainsString('shared WordPress database', sd_ai_agent_phpunit_validate_database_name('fixture_shared_tests', $this->fixtureDir . '/wp-tests-config.php'));
		self::assertNull(sd_ai_agent_phpunit_validate_database_name('fixture_safe_tests', $this->fixtureDir . '/wp-tests-config.php'));
	}

	public function test_computed_live_database_requires_explicit_hint(): void {
		putenv('WP_LIVE_DB_NAME');
		$this->write_config("define('DB_NAME', getenv('EXAMPLE_DB_NAME'));");
		self::assertStringContainsString('Cannot determine', sd_ai_agent_phpunit_validate_database_name('fixture_safe_tests', $this->fixtureDir . '/wp-tests-config.php'));
		putenv('WP_LIVE_DB_NAME=fixture_shared_tests');
		self::assertNull(sd_ai_agent_phpunit_validate_database_name('fixture_safe_tests', $this->fixtureDir . '/wp-tests-config.php'));
	}

	public function test_installer_rechecks_config_after_waiting_for_lock(): void {
		$this->write_config("define('DB_NAME', 'fixture_safe_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		$lock = $this->fixtureDir . '/.wordpress-phpunit-trunk.lock';
		mkdir($lock);
		file_put_contents($lock . '/pid', (string) getmypid());
		$environment = getenv();
		$environment['WP_PHPUNIT_CACHE_DIR'] = $this->fixtureDir;
		$environment['WP_TESTS_DIR'] = $this->fixtureDir;
		$environment['WP_CORE_DIR'] = $this->fixtureDir . '/core';
		$process = proc_open(array('bash', dirname(__DIR__, 3) . '/bin/install-wp-tests.sh', 'fixture_safe_tests', 'fixture', '', 'localhost', 'trunk', 'true'), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $environment);
		self::assertIsResource($process);
		try {
			self::assertStringContainsString('Waiting for another process', (string) fgets($pipes[1]));
			$this->write_config("define('DB_NAME', 'fixture_live_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		} finally {
			unlink($lock . '/pid');
			rmdir($lock);
		}
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		self::assertSame(1, proc_close($process));
		self::assertStringContainsString('WP_LIVE_DB_NAME', $error);
		self::assertStringNotContainsString('Downloading', $output);
		self::assertFileDoesNotExist($this->fixtureDir . '/core/wp-settings.php');
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_alternate_config_path_is_rejected(): void {
		$this->write_config("define('DB_NAME', 'fixture_safe_tests');\ndefine('SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true);");
		define('WP_TESTS_CONFIG_FILE_PATH', $this->fixtureDir . '/alternate.php');
		self::assertStringContainsString('alternate', sd_ai_agent_phpunit_validate_test_config($this->fixtureDir));
	}
}
