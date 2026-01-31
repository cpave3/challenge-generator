<?php

declare(strict_types=1);

namespace RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use RateLimiter\Storage\InMemoryStorage;

/**
 * Tests for storage implementation correctness.
 */
final class StorageImplementationTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    protected function tearDown(): void
    {
        $this->storage->clear();
    }

    public function testIncrementCreatesNewKey(): void
    {
        $result = $this->storage->increment('new:key', 1, 60.0);
        $this->assertEquals(1, $result);
    }

    public function testIncrementAccumulates(): void
    {
        $this->storage->increment('accumulate', 1, 60.0);
        $result = $this->storage->increment('accumulate', 2, 60.0);
        $this->assertEquals(3, $result);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $this->assertNull($this->storage->get('missing:key'));
    }

    public function testGetReturnsValue(): void
    {
        $this->storage->increment('exists', 5, 60.0);
        $this->assertEquals(5, $this->storage->get('exists'));
    }

    public function testSetNxSucceedsWhenMissing(): void
    {
        $result = $this->storage->setNx('newnx', 10, 60.0);
        $this->assertTrue($result);
        $this->assertEquals(10, $this->storage->get('newnx'));
    }

    public function testSetNxFailsWhenExists(): void
    {
        $this->storage->increment('existsnx', 5, 60.0);
        $result = $this->storage->setNx('existsnx', 10, 60.0);
        $this->assertFalse($result);
        $this->assertEquals(5, $this->storage->get('existsnx'));
    }

    public function testTimestampsTracking(): void
    {
        $this->storage->setTimeOverride(fn() => 1000.0);

        $this->storage->addTimestamp('sw', 1000.0, 60.0);
        $this->storage->addTimestamp('sw', 1005.0, 60.0);
        $this->storage->addTimestamp('sw', 1010.0, 60.0);

        $count = $this->storage->countTimestamps('sw', 1000.0, 1010.0);
        $this->assertEquals(3, $count);

        $count = $this->storage->countTimestamps('sw', 1005.0, 1010.0);
        $this->assertEquals(2, $count);
    }

    public function testRemoveTimestampsBefore(): void
    {
        $this->storage->setTimeOverride(fn() => 1000.0);

        $this->storage->addTimestamp('sw', 1000.0, 60.0);
        $this->storage->addTimestamp('sw', 1005.0, 60.0);
        $this->storage->addTimestamp('sw', 1010.0, 60.0);

        $this->storage->removeTimestampsBefore('sw', 1005.0);

        $count = $this->storage->countTimestamps('sw', 1000.0, 1010.0);
        $this->assertEquals(2, $count); // 1005 and 1010 remain
    }

    public function testTokenBucketDecrement(): void
    {
        $now = 1000.0;
        $this->storage->setTimeOverride(fn() => $now);

        // First decrement should succeed (bucket starts full)
        $result = $this->storage->decrementToken('bucket', 1, $now, $now, 10, 120.0);
        $this->assertTrue($result);

        // Exhaust bucket
        for ($i = 0; $i < 9; $i++) {
            $this->storage->decrementToken('bucket', 1, $now, $now, 10, 120.0);
        }

        // 11th should fail
        $result = $this->storage->decrementToken('bucket', 1, $now, $now, 10, 120.0);
        $this->assertFalse($result);
    }

    public function testTokenBucketRefill(): void
    {
        $start = 1000.0;
        $this->storage->setTimeOverride(fn() => $start);

        // Exhaust bucket of 10
        for ($i = 0; $i < 10; $i++) {
            $this->storage->decrementToken('refill', 1, $start, $start, 10, 20.0); // 10 tokens / 10 seconds = 1/sec
        }

        // Should fail immediately
        $result = $this->storage->decrementToken('refill', 1, $start, $start, 10, 20.0);
        $this->assertFalse($result);

        // After 2 seconds, should have 2 tokens
        $this->storage->setTimeOverride(fn() => $start + 2.0);
        $result = $this->storage->decrementToken('refill', 1, $start, $start + 2.0, 10, 20.0);
        $this->assertTrue($result);

        $result = $this->storage->decrementToken('refill', 1, $start + 2.0, $start + 2.0, 10, 20.0);
        $this->assertTrue($result);

        // Third should fail (only 2 refilled)
        $result = $this->storage->decrementToken('refill', 1, $start + 2.0, $start + 2.0, 10, 20.0);
        $this->assertFalse($result);
    }

    public function testExpiration(): void
    {
        $now = 1000.0;
        $this->storage->setTimeOverride(fn() => $now);

        $this->storage->increment('expires', 1, 5.0); // 5 second TTL
        $this->assertEquals(1, $this->storage->get('expires'));

        // Before expiration
        $this->storage->setTimeOverride(fn() => $now + 4.9);
        $this->assertEquals(1, $this->storage->get('expires'));

        // After expiration
        $this->storage->setTimeOverride(fn() => $now + 5.1);
        $this->assertNull($this->storage->get('expires'));
    }

    public function testTimeOverride(): void
    {
        $customTime = 1234567890.5;
        $this->storage->setTimeOverride(fn() => $customTime);

        $this->assertEquals($customTime, $this->storage->getTime());

        $this->storage->clearTimeOverride();
        $actualTime = $this->storage->getTime();

        // Should be close to real time
        $this->assertGreaterThan($customTime, $actualTime);
        $this->assertLessThan($customTime + 1.0, $actualTime);
    }

    public function testAvailabilityToggle(): void
    {
        $this->assertTrue($this->storage->isAvailable());

        $this->storage->setAvailable(false);
        $this->assertFalse($this->storage->isAvailable());

        $this->storage->setAvailable(true);
        $this->assertTrue($this->storage->isAvailable());
    }

    public function testClearRemovesAllData(): void
    {
        $this->storage->increment('counter', 1, 60.0);
        $this->storage->addTimestamp('sw', 1000.0, 60.0);
        $this->storage->decrementToken('bucket', 1, 1000.0, 1000.0, 10, 60.0);

        $this->storage->clear();

        $this->assertNull($this->storage->get('counter'));
        $this->assertEquals(0, $this->storage->countTimestamps('sw', 0, 2000));
    }
}
