# Distributed Rate Limiter with Multiple Strategies

**Difficulty:** Level 4 (Principal)  
**Estimated Time:** 3-4 hours  
**Skills:** Distributed Systems, Algorithm Design, Concurrency Control, Observability, Trade-off Analysis

## Context

Rate limiting is a critical component of modern distributed systems, protecting APIs from abuse and ensuring fair resource allocation. However, implementing rate limiting in distributed environments introduces significant challenges: clock skew, network partitions, consistency vs. availability trade-offs, and the need for observability.

This challenge mirrors real-world scenarios where you must build a rate limiter that works across multiple nodes, supports different algorithms (token bucket, sliding window, fixed window), and provides operational visibility into decisions.

## Requirements

### Functional Requirements

1. **Multi-Algorithm Support**
   - Implement Token Bucket (bursty traffic, allows bursts)
   - Implement Sliding Window Log (precise, memory intensive)
   - Implement Fixed Window Counter (simple, allows spikes at boundaries)
   - Allow runtime algorithm selection per rate limit configuration

2. **Distributed Coordination**
   - Support multiple backend storage adapters (Redis, In-Memory for testing)
   - Handle clock skew gracefully (assume max 1 second skew between nodes)
   - Implement proper distributed locking or atomic operations

3. **Operational Requirements**
   - Expose decision metadata (why was a request allowed/denied?)
   - Include retry-after headers calculation
   - Support rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset)
   - Provide hooks for metrics emission (attempts, rejects, algorithm used)

4. **Edge Cases**
   - Handle storage unavailability (fail open vs fail closed configurable)
   - Graceful degradation when precision is lost
   - Key prefixing for multi-tenant scenarios

### Non-Functional Requirements

1. **Performance**: P99 decision latency < 10ms for in-memory, < 50ms for Redis
2. **Precision**: Token bucket and sliding window must not over-allocate by more than 1% under normal conditions
3. **Testability**: All external dependencies must be mockable/injectable
4. **Observability**: Every decision must be explainable and auditable

## Definition of Done

- [ ] All three algorithms pass precision tests under single-node conditions
- [ ] Distributed tests verify behavior under simulated clock skew
- [ ] Storage adapter interface allows swapping Redis ↔ InMemory without code changes
- [ ] Fail open/closed behavior is tested and configurable
- [ ] Metrics hooks receive correct data for all decision paths
- [ ] Retry-after calculation is accurate within 1 second for all algorithms
- [ ] Code passes static analysis (PHPStan level 8)

## Acceptance Tests

### Algorithm Correctness Tests
1. **Token Bucket**: Verify burst allowance, gradual refill, and never exceeds capacity
2. **Sliding Window**: Verify precise window boundary enforcement
3. **Fixed Window**: Verify counter reset behavior and boundary spike allowance

### Distributed Behavior Tests
1. **Clock Skew**: Simulate 500ms skew between nodes, verify no over-allocation > 1%
2. **Race Conditions**: 100 concurrent requests, verify total allowed ≤ limit
3. **Storage Failure**: Verify configurable fail open/closed behavior

### Operational Tests
1. **Headers**: Verify all rate limit headers present and correct
2. **Metrics**: Verify hooks called with correct algorithm, result, and timing
3. **Decision Explanation**: Verify metadata explains why request was allowed/denied

### Integration Tests
1. **Multi-tenant isolation**: Keys with different prefixes don't interfere
2. **Algorithm switching**: Same key can use different algorithms over time
3. **High cardinality**: 10,000 unique keys, verify no memory leaks or degradation

## Hints

<details>
<summary>Algorithm Selection Guidance</summary>

- **Token Bucket**: Best for APIs needing burst tolerance. Refill rate controls steady state, bucket size controls burst.
- **Sliding Window**: Best when strict limits matter. Most precise but O(n) memory per key.
- **Fixed Window**: Best for simple counting. O(1) memory but allows 2x traffic at window boundaries.

</details>

<details>
<summary>Distributed Locking Strategy</summary>

Consider Redis Lua scripts for atomic compare-and-set operations. For sliding window, you need to atomically add timestamp and count entries in window. For token bucket, atomic check-and-decrement of tokens.

</details>

<details>
<summary>Clock Skew Handling</summary>

Use storage-provided timestamps when available (Redis TIME). When not available, accept that skew causes temporary imprecision. Document the trade-off.

</details>

## Extensions

1. **Adaptive Rate Limiting**: Implement gradient-based rate adjustment based on downstream health
2. **Global + Local Limits**: Combine per-node and cluster-wide limits (e.g., 100/min per node, 1000/min cluster)
3. **Priority Classes**: Different rate limits for different request priorities
4. **Circuit Breaker Integration**: Pause rate limiting when downstream is unhealthy

## Evaluation Criteria

Beyond test passage, solutions will be evaluated on:

1. **Interface Design**: Is the API intuitive for callers? Are configuration options clear?
2. **Extensibility**: How easy to add new algorithms? New storage backends?
3. **Operational Clarity**: Can an SRE understand why a request was rejected at 3am?
4. **Resource Efficiency**: Memory and network usage patterns
5. **Failure Modes**: Are all failure paths handled explicitly?
