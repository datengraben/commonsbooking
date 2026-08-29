<?php

namespace CommonsBooking\Tests\Benchmark\Service;

use CommonsBooking\Plugin;

/**
 * Micro-benchmark for cache-key generation in the Cache service.
 *
 * Cache::getCacheId() runs on every cache read and write and derives the key
 * from the call stack via debug_backtrace(). In a real WordPress request the
 * stack from a shortcode down to a repository method is dozens of frames deep,
 * so this benchmark drives getCacheId() from an artificially deep stack to
 * measure the cost of key generation independently of the surrounding query
 * work.
 *
 * Compare runs before and after limiting the backtrace, e.g.:
 *   vendor/bin/phpbench run tests/benchmark/Service/CacheKeyBench.php --report=all
 *
 * @BeforeMethods({"setUp"})
 */
class CacheKeyBench {

	private const STACK_DEPTH = 30;

	/**
	 * Representative arguments, mirroring a Timeframe repository call
	 * (locations, items, types, date, ...), so the serialize() of the
	 * frame arguments is also exercised.
	 *
	 * @var array
	 */
	private array $args;

	public function setUp(): void {
		$this->args = [
			[ 1, 2, 3, 4, 5 ],       // locations
			[ 10, 20, 30, 40, 50 ],  // items
			[ 'type-a', 'type-b' ],  // types
			'2023-05-01',
			false,
			null,
			[ 'confirmed', 'unconfirmed', 'publish', 'inherit' ],
		];
	}

	/**
	 * @Iterations(5)
	 * @Revs(1000)
	 * @return void
	 */
	public function benchGetCacheIdDeepStack(): void {
		$this->recurse( self::STACK_DEPTH );
	}

	/**
	 * Build a deep call stack, then generate a cache id at the bottom.
	 */
	private function recurse( int $depth ): string {
		if ( $depth > 0 ) {
			return $this->recurse( $depth - 1 );
		}

		return $this->callLikeRepository( ...$this->args );
	}

	/**
	 * Stands in for a repository method: its arguments are what getCacheId()
	 * reads from backtrace frame #2.
	 */
	private function callLikeRepository( ...$args ): string {
		return $this->invokeCacheLayer();
	}

	/**
	 * Stands in for getCacheItem()/setCacheItem(): the frame directly above
	 * getCacheId(), so debug_backtrace()[2] resolves to callLikeRepository().
	 */
	private function invokeCacheLayer(): string {
		return Plugin::getCacheId();
	}
}
