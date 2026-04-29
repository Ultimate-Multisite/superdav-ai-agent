<?php
/**
 * Test case for the AbilityUsageTracker.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\Tools;

use SdAiAgent\Tools\AbilityUsageTracker;
use WP_UnitTestCase;

class AbilityUsageTrackerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		AbilityUsageTracker::reset();
	}

	public function tear_down(): void {
		parent::tear_down();
		AbilityUsageTracker::reset();
	}

	public function test_record_increments_count(): void {
		AbilityUsageTracker::record( 'sd-ai-agent/get-plugins' );
		AbilityUsageTracker::record( 'sd-ai-agent/get-plugins' );
		AbilityUsageTracker::record( 'sd-ai-agent/get-plugins' );

		$top = AbilityUsageTracker::top( 5 );
		$this->assertSame( [ 'sd-ai-agent/get-plugins' ], $top );
	}

	public function test_top_orders_by_count_descending(): void {
		AbilityUsageTracker::record( 'a/one' );
		AbilityUsageTracker::record( 'b/two' );
		AbilityUsageTracker::record( 'b/two' );
		AbilityUsageTracker::record( 'c/three' );
		AbilityUsageTracker::record( 'c/three' );
		AbilityUsageTracker::record( 'c/three' );

		$top = AbilityUsageTracker::top( 3 );
		$this->assertSame( [ 'c/three', 'b/two', 'a/one' ], $top );
	}

	public function test_top_with_zero_returns_empty(): void {
		AbilityUsageTracker::record( 'a/one' );
		$this->assertSame( [], AbilityUsageTracker::top( 0 ) );
	}

	public function test_top_returns_empty_when_no_history(): void {
		$this->assertSame( [], AbilityUsageTracker::top( 10 ) );
	}

	public function test_record_ignores_empty_name(): void {
		AbilityUsageTracker::record( '' );
		$this->assertSame( [], AbilityUsageTracker::top( 5 ) );
	}

	public function test_lru_cap_bounds_persisted_size(): void {
		// Recording more distinct names than the cap should never let the
		// persisted map grow beyond MAX_ENTRIES. We don't assert which
		// specific entries survive — last_used is a unix-second timestamp
		// so a tight loop produces ties and the prune order is undefined.
		$max = AbilityUsageTracker::MAX_ENTRIES;
		for ( $i = 0; $i < $max + 10; $i++ ) {
			AbilityUsageTracker::record( "ability/{$i}" );
		}

		$persisted = get_option( AbilityUsageTracker::OPTION_NAME, [] );
		$this->assertLessThanOrEqual( $max, count( $persisted ) );
	}
}
