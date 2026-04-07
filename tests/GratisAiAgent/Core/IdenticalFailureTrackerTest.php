<?php
/**
 * Test case for IdenticalFailureTracker.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\IdenticalFailureTracker;
use WP_UnitTestCase;

class IdenticalFailureTrackerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		IdenticalFailureTracker::reset();
	}

	public function tear_down(): void {
		parent::tear_down();
		IdenticalFailureTracker::reset();
	}

	public function test_record_increments_for_identical_calls(): void {
		$args = array( 'foo' => 'bar' );

		$this->assertSame(
			1,
			IdenticalFailureTracker::record( 'a/x', $args, 'ability_invalid_input' )
		);
		$this->assertSame(
			2,
			IdenticalFailureTracker::record( 'a/x', $args, 'ability_invalid_input' )
		);
		$this->assertSame(
			3,
			IdenticalFailureTracker::record( 'a/x', $args, 'ability_invalid_input' )
		);
	}

	public function test_different_args_are_tracked_separately(): void {
		IdenticalFailureTracker::record( 'a/x', array( 'foo' => 1 ), 'err' );
		IdenticalFailureTracker::record( 'a/x', array( 'foo' => 1 ), 'err' );

		$count = IdenticalFailureTracker::record( 'a/x', array( 'foo' => 2 ), 'err' );
		$this->assertSame( 1, $count, 'A different args shape resets the counter for that signature.' );
	}

	public function test_different_error_codes_are_tracked_separately(): void {
		$args = array( 'foo' => 'bar' );

		IdenticalFailureTracker::record( 'a/x', $args, 'ability_invalid_input' );
		$count = IdenticalFailureTracker::record( 'a/x', $args, 'something_else' );
		$this->assertSame( 1, $count );
	}

	public function test_should_nudge_threshold(): void {
		$this->assertFalse( IdenticalFailureTracker::should_nudge( 1 ) );
		$this->assertTrue( IdenticalFailureTracker::should_nudge( 2 ) );
		$this->assertTrue( IdenticalFailureTracker::should_nudge( 5 ) );
	}

	public function test_reset_clears_history(): void {
		IdenticalFailureTracker::record( 'a/x', array(), 'err' );
		IdenticalFailureTracker::record( 'a/x', array(), 'err' );

		IdenticalFailureTracker::reset();

		$count = IdenticalFailureTracker::record( 'a/x', array(), 'err' );
		$this->assertSame( 1, $count );
	}

	public function test_nudge_message_includes_ability_and_schema(): void {
		$msg = IdenticalFailureTracker::nudge_message(
			'multisite-ultimate/membership-create-item',
			array(
				'type'     => 'object',
				'required' => array( 'customer_id', 'product_id' ),
			)
		);

		$this->assertStringContainsString( 'STOP', $msg );
		$this->assertStringContainsString( 'multisite-ultimate/membership-create-item', $msg );
		$this->assertStringContainsString( 'customer_id', $msg );
		$this->assertStringContainsString( 'product_id', $msg );
	}
}
