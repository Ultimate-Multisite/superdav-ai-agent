<?php
/**
 * Test case for WooCommerceAbilities class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Abilities;

use GratisAiAgent\Abilities\WooCommerceAbilities;
use WP_UnitTestCase;

/**
 * Test WooCommerceAbilities handler methods.
 *
 * All handlers check whether WooCommerce is active before proceeding.
 * In the test environment WooCommerce is not installed, so every handler
 * must return a WP_Error with code 'woocommerce_inactive'.
 */
class WooCommerceAbilitiesTest extends WP_UnitTestCase {

	// ─── is_woocommerce_active ────────────────────────────────────

	/**
	 * Test is_woocommerce_active returns false when WooCommerce is not installed.
	 */
	public function test_is_woocommerce_active_returns_false_without_woocommerce() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$this->assertFalse( WooCommerceAbilities::is_woocommerce_active() );
	}

	// ─── handle_get_products ──────────────────────────────────────

	/**
	 * Test handle_get_products returns WP_Error when WooCommerce is inactive.
	 */
	public function test_handle_get_products_woocommerce_inactive_returns_wp_error() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_products( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_get_products with product_id returns WP_Error when WooCommerce inactive.
	 */
	public function test_handle_get_products_with_product_id_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_products( [ 'product_id' => 1 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	// ─── handle_create_product ────────────────────────────────────

	/**
	 * Test handle_create_product returns WP_Error when WooCommerce is inactive.
	 */
	public function test_handle_create_product_woocommerce_inactive_returns_wp_error() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_create_product( [
			'name' => 'Test Product',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_create_product with missing name returns WP_Error when WooCommerce inactive.
	 *
	 * The woocommerce_inactive check fires before the name validation.
	 */
	public function test_handle_create_product_missing_name_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_create_product( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	// ─── handle_update_product ────────────────────────────────────

	/**
	 * Test handle_update_product returns WP_Error when WooCommerce is inactive.
	 */
	public function test_handle_update_product_woocommerce_inactive_returns_wp_error() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_update_product( [
			'product_id' => 1,
			'name'       => 'Updated Name',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_update_product with missing product_id returns WP_Error when WooCommerce inactive.
	 */
	public function test_handle_update_product_missing_product_id_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_update_product( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	// ─── handle_delete_product ────────────────────────────────────

	/**
	 * Test handle_delete_product returns WP_Error when WooCommerce is inactive.
	 */
	public function test_handle_delete_product_woocommerce_inactive_returns_wp_error() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_delete_product( [
			'product_id' => 1,
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_delete_product with missing product_id returns WP_Error when WooCommerce inactive.
	 */
	public function test_handle_delete_product_missing_product_id_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_delete_product( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	// ─── handle_get_orders ────────────────────────────────────────

	/**
	 * Test handle_get_orders returns WP_Error when WooCommerce is inactive.
	 */
	public function test_handle_get_orders_woocommerce_inactive_returns_wp_error() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_orders( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_get_orders with order_id returns WP_Error when WooCommerce inactive.
	 */
	public function test_handle_get_orders_with_order_id_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_orders( [ 'order_id' => 1 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	// ─── handle_get_store_stats ───────────────────────────────────

	/**
	 * Test handle_get_store_stats returns WP_Error when WooCommerce is inactive.
	 */
	public function test_handle_get_store_stats_woocommerce_inactive_returns_wp_error() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_store_stats( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_get_store_stats with date range returns WP_Error when WooCommerce inactive.
	 */
	public function test_handle_get_store_stats_with_dates_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_store_stats( [
			'date_after'  => '2024-01-01',
			'date_before' => '2024-12-31',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}

	/**
	 * Test handle_get_store_stats with invalid date returns WP_Error when WooCommerce inactive.
	 *
	 * The woocommerce_inactive check fires before date validation.
	 */
	public function test_handle_get_store_stats_invalid_date_woocommerce_inactive() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping inactive test.' );
		}

		$result = WooCommerceAbilities::handle_get_store_stats( [
			'date_after'  => 'not-a-date',
			'date_before' => 'also-not-a-date',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woocommerce_inactive', $result->get_error_code() );
	}
}
