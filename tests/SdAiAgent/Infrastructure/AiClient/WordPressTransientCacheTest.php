<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Infrastructure\AiClient;

use SdAiAgent\Infrastructure\AiClient\CacheInvalidArgumentException;
use SdAiAgent\Infrastructure\AiClient\WordPressTransientCache;
use WP_UnitTestCase;

/** Persistent bounded cache coverage for AI Client metadata. */
final class WordPressTransientCacheTest extends WP_UnitTestCase {

	private WordPressTransientCache $cache;

	public function set_up(): void {
		parent::set_up();
		$this->cache = new WordPressTransientCache();
		$this->cache->clear();
	}

	public function tear_down(): void {
		$this->cache->clear();
		parent::tear_down();
	}

	/** Values survive separate adapter instances and expire within five minutes. */
	public function test_persists_values_for_the_bounded_metadata_ttl(): void {
		$this->assertSame( 300, WordPressTransientCache::MAX_TTL );
		$this->assertTrue( $this->cache->set( 'ai_client_models', array( 'speedy' ), DAY_IN_SECONDS ) );

		$next_request_cache = new WordPressTransientCache();
		$this->assertTrue( $next_request_cache->has( 'ai_client_models' ) );
		$this->assertSame( array( 'speedy' ), $next_request_cache->get( 'ai_client_models' ) );
	}

	/** Clear changes the cache generation without requiring a transient table scan. */
	public function test_clear_invalidates_existing_values(): void {
		$this->cache->set( 'ai_client_models', array( 'speedy' ) );
		$this->assertTrue( $this->cache->clear() );
		$this->assertFalse( $this->cache->has( 'ai_client_models' ) );
		$this->assertSame( 'miss', $this->cache->get( 'ai_client_models', 'miss' ) );
	}

	/** PSR-16 multiple operations retain falsey values and support deletion. */
	public function test_multiple_operations_and_falsey_values(): void {
		$this->assertTrue( $this->cache->setMultiple( array( 'zero' => 0, 'false' => false ) ) );
		$this->assertSame(
			array( 'zero' => 0, 'false' => false, 'missing' => null ),
			$this->cache->getMultiple( array( 'zero', 'false', 'missing' ) )
		);
		$this->assertTrue( $this->cache->deleteMultiple( array( 'zero', 'false' ) ) );
		$this->assertFalse( $this->cache->has( 'zero' ) );
		$this->assertFalse( $this->cache->has( 'false' ) );
	}

	/** Reserved PSR-16 key characters are rejected before touching WordPress. */
	public function test_rejects_invalid_cache_keys(): void {
		$this->expectException( CacheInvalidArgumentException::class );
		$this->cache->get( 'invalid/key' );
	}
}
