<?php
/**
 * Test case for PHPUnit database configuration safety checks.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Core;

use WP_UnitTestCase;

/**
 * Test token-based parsing of PHPUnit database declarations.
 */
class WpPhpunitDatabaseTest extends WP_UnitTestCase {

	/**
	 * Temporary PHPUnit configuration file.
	 *
	 * @var string
	 */
	private string $config_file;

	/**
	 * Set up the temporary configuration file.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->config_file = (string) tempnam( sys_get_temp_dir(), 'sd-ai-agent-phpunit-config-' );
	}

	/**
	 * Remove the temporary configuration file.
	 */
	public function tear_down(): void {
		if ( is_file( $this->config_file ) ) {
			unlink( $this->config_file );
		}

		parent::tear_down();
	}

	/**
	 * Write a configuration fixture.
	 *
	 * @param string $contents Configuration contents.
	 */
	private function write_config( string $contents ): void {
		$this->assertNotFalse( file_put_contents( $this->config_file, $contents ) );
	}

	/**
	 * Commented declarations must not affect validation.
	 */
	public function test_ignores_commented_database_declarations(): void {
		$this->write_config(
			"<?php\n"
			. "/* define( 'DB_NAME', 'scratch_tests' ); */\n"
			. "define( 'DB_NAME', 'local_wordpress' );\n"
			. "define( 'DB_USER', 'root' );\n"
			. "define( 'DB_PASSWORD', '' );\n"
			. "define( 'DB_HOST', 'localhost' );\n"
			. "define( 'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true );\n"
		);

		$this->assertSame(
			'local_wordpress',
			sd_ai_agent_phpunit_read_config_definitions( $this->config_file )['DB_NAME']
		);
		$this->assertSame(
			'Refusing to use a database whose name is not clearly a test database. Set WP_TESTS_DB_NAME to a separate name containing test, tests, phpunit, or ci.',
			sd_ai_agent_phpunit_validate_test_config_file( $this->config_file )
		);
	}

	/**
	 * Active duplicate declarations must fail closed.
	 */
	public function test_rejects_ambiguous_database_declarations(): void {
		$this->write_config(
			"<?php\n"
			. "define( 'DB_NAME', 'first_tests' );\n"
			. "define( 'DB_NAME', 'second_tests' );\n"
			. "define( 'DB_USER', 'root' );\n"
			. "define( 'DB_PASSWORD', '' );\n"
			. "define( 'DB_HOST', 'localhost' );\n"
			. "define( 'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true );\n"
		);

		$this->assertSame(
			'WordPress test config contains unsupported or ambiguous safety declarations. Create a fresh isolated PHPUnit cache before running tests.',
			sd_ai_agent_phpunit_validate_test_config_file( $this->config_file )
		);
	}

	/**
	 * Commented isolation markers must not be accepted.
	 */
	public function test_ignores_commented_isolation_marker(): void {
		$this->write_config(
			"<?php\n"
			. "define( 'DB_NAME', 'sd_ai_agent_tests' );\n"
			. "define( 'DB_USER', 'root' );\n"
			. "define( 'DB_PASSWORD', '' );\n"
			. "define( 'DB_HOST', 'localhost' );\n"
			. "// define( 'SD_AI_AGENT_PHPUNIT_DATABASE_ISOLATED', true );\n"
		);

		$this->assertSame(
			'WordPress test config is not marked as isolated. Create a fresh WP_PHPUNIT_CACHE_DIR and run `pnpm run test:php:setup` with a dedicated WP_TESTS_DB_NAME.',
			sd_ai_agent_phpunit_validate_test_config_file( $this->config_file )
		);
	}
}
