<?php
/**
 * Test case for WordPressAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\WordPressAbilities;
use WP_UnitTestCase;

/**
 * Test WordPressAbilities handler methods.
 */
class WordPressAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_get_plugins returns plugin list.
	 */
	public function test_handle_get_plugins_returns_array() {
		$result = WordPressAbilities::handle_get_plugins();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'plugins', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'active_count', $result );
		$this->assertIsArray( $result['plugins'] );
		$this->assertIsInt( $result['total'] );
		$this->assertIsInt( $result['active_count'] );
	}

	/**
	 * Test handle_get_plugins total matches plugins array count.
	 */
	public function test_handle_get_plugins_total_matches_count() {
		$result = WordPressAbilities::handle_get_plugins();

		$this->assertSame( count( $result['plugins'] ), $result['total'] );
	}

	/**
	 * Test handle_get_plugins each plugin has required fields.
	 */
	public function test_handle_get_plugins_plugin_structure() {
		$result = WordPressAbilities::handle_get_plugins();

		if ( ! empty( $result['plugins'] ) ) {
			$plugin = $result['plugins'][0];
			$this->assertArrayHasKey( 'file', $plugin );
			$this->assertArrayHasKey( 'name', $plugin );
			$this->assertArrayHasKey( 'version', $plugin );
			$this->assertArrayHasKey( 'active', $plugin );
			$this->assertIsBool( $plugin['active'] );
		}
	}

	/**
	 * Test handle_get_themes returns theme list.
	 */
	public function test_handle_get_themes_returns_array() {
		$result = WordPressAbilities::handle_get_themes();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'themes', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'active', $result );
		$this->assertIsArray( $result['themes'] );
		$this->assertIsInt( $result['total'] );
		// In the test environment, get_stylesheet() may return false if no theme is active.
		$this->assertTrue(
			is_string( $result['active'] ) || false === $result['active'],
			'active should be a string or false.'
		);
	}

	/**
	 * Test handle_get_themes total matches themes array count.
	 */
	public function test_handle_get_themes_total_matches_count() {
		$result = WordPressAbilities::handle_get_themes();

		$this->assertSame( count( $result['themes'] ), $result['total'] );
	}

	/**
	 * Test handle_get_themes each theme has required fields.
	 */
	public function test_handle_get_themes_theme_structure() {
		$result = WordPressAbilities::handle_get_themes();

		if ( ! empty( $result['themes'] ) ) {
			$theme = $result['themes'][0];
			$this->assertArrayHasKey( 'slug', $theme );
			$this->assertArrayHasKey( 'name', $theme );
			$this->assertArrayHasKey( 'version', $theme );
			$this->assertArrayHasKey( 'active', $theme );
			$this->assertIsBool( $theme['active'] );
		}
	}

	/**
	 * Test handle_get_themes active theme is marked correctly.
	 *
	 * In the test environment, get_stylesheet() may return false if no theme is
	 * registered. We skip the active-theme assertions in that case.
	 */
	public function test_handle_get_themes_active_theme_marked() {
		$result      = WordPressAbilities::handle_get_themes();
		$active_slug = $result['active'];

		// If no active theme in test env, skip the active-theme assertions.
		if ( false === $active_slug || '' === $active_slug ) {
			$this->markTestSkipped( 'No active theme registered in test environment.' );
		}

		$active_themes = array_filter(
			$result['themes'],
			function ( $theme ) {
				return $theme['active'] === true;
			}
		);

		// At least one theme should be active.
		$this->assertNotEmpty( $active_themes );

		// The active slug should match one of the active themes.
		$active_slugs = array_column( array_values( $active_themes ), 'slug' );
		$this->assertContains( $active_slug, $active_slugs );
	}

	/**
	 * Test handle_install_plugin with empty slug returns WP_Error.
	 */
	public function test_handle_install_plugin_empty_slug() {
		$result = WordPressAbilities::handle_install_plugin( [ 'slug' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_empty_slug', $result->get_error_code() );
	}

	/**
	 * Test handle_install_plugin with missing slug returns WP_Error.
	 */
	public function test_handle_install_plugin_missing_slug() {
		$result = WordPressAbilities::handle_install_plugin( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_run_php with empty code returns WP_Error.
	 */
	public function test_handle_run_php_empty_code() {
		$result = WordPressAbilities::handle_run_php( [ 'code' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_empty_code', $result->get_error_code() );
	}

	/**
	 * Test handle_run_php with missing code returns WP_Error.
	 */
	public function test_handle_run_php_missing_code() {
		$result = WordPressAbilities::handle_run_php( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_run_php executes simple expression.
	 */
	public function test_handle_run_php_simple_expression() {
		$result = WordPressAbilities::handle_run_php( [
			'code' => 'return 1 + 1;',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'result', $result );
		$this->assertArrayHasKey( 'output', $result );
		$this->assertSame( 2, $result['result'] );
	}

	/**
	 * Test handle_run_php captures output.
	 */
	public function test_handle_run_php_captures_output() {
		$result = WordPressAbilities::handle_run_php( [
			'code' => 'echo "hello world"; return null;',
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'hello world', $result['output'] );
	}

	/**
	 * Test handle_run_php can call WordPress functions.
	 *
	 * Uses get_bloginfo('version') which always returns a non-empty string
	 * in the test environment (unlike get_option('siteurl') which may return false).
	 */
	public function test_handle_run_php_wordpress_functions() {
		$result = WordPressAbilities::handle_run_php( [
			'code' => 'return get_bloginfo("version");',
		] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result['result'] );
		$this->assertIsString( $result['result'] );
	}

	/**
	 * Test handle_run_php with PHP error returns WP_Error.
	 */
	public function test_handle_run_php_php_error() {
		$result = WordPressAbilities::handle_run_php( [
			'code' => 'throw new \Exception("test error");',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_php_error', $result->get_error_code() );
	}

	/**
	 * Test handle_run_php with syntax error returns WP_Error.
	 */
	public function test_handle_run_php_syntax_error() {
		$result = WordPressAbilities::handle_run_php( [
			'code' => 'this is not valid php !!!',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
