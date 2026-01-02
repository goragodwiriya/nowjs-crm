<?php

namespace Kotchasan\Cache;

use Kotchasan\QueryBuilder\QueryBuilderInterface;

/**
 * Class QueryCache
 *
 * Handles caching of query results.
 *
 * @package Kotchasan\Cache
 */
class QueryCache
{
    /**
     * @var CacheInterface The cache implementation.
     */
    private CacheInterface $cache;

    /**
     * @var bool Whether caching is enabled.
     */
    private bool $enabled = true;

    /**
     * @var int|null The default TTL for cache entries.
     */
    private ?int $defaultTtl;

    /**
     * QueryCache constructor.
     *
     * @param CacheInterface $cache The cache implementation.
     * @param int|null $defaultTtl The default TTL for cache entries (null for indefinite).
     */
    public function __construct(CacheInterface $cache, ?int $defaultTtl = 3600)
    {
        $this->cache = $cache;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Enables query caching.
     *
     * @return self
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Disables query caching.
     *
     * @return self
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Checks if caching is enabled.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Sets the default TTL for cache entries.
     *
     * @param int|null $ttl The default TTL in seconds (null for indefinite).
     * @return self
     */
    public function setDefaultTtl(?int $ttl): self
    {
        $this->defaultTtl = $ttl;
        return $this;
    }

    /**
     * Gets the default TTL for cache entries.
     *
     * @return int|null The default TTL in seconds (null for indefinite).
     */
    public function getDefaultTtl(): ?int
    {
        return $this->defaultTtl;
    }

    /**
     * Gets a value from the cache.
     *
     * @param QueryBuilderInterface $query The query to get from cache.
     * @return mixed|null The cached result or null if not found.
     */
    public function get(QueryBuilderInterface $query)
    {
        if (!$this->enabled) {
            return null;
        }

        $key = $this->generateCacheKey($query);
        return $this->cache->get($key);
    }

    /**
     * Sets a value in the cache.
     *
     * @param QueryBuilderInterface $query The query to cache.
     * @param mixed $value The result to cache.
     * @param int|null $ttl The time-to-live in seconds (null for default).
     * @return bool True if the value was stored, false otherwise.
     */
    public function set(QueryBuilderInterface $query, $value, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $key = $this->generateCacheKey($query);
        return $this->cache->set($key, $value, $ttl ?? $this->defaultTtl);
    }

    /**
     * Removes a query result from the cache.
     *
     * @param QueryBuilderInterface $query The query to remove from cache.
     * @return bool True if the value was removed, false otherwise.
     */
    public function delete(QueryBuilderInterface $query): bool
    {
        $key = $this->generateCacheKey($query);
        return $this->cache->delete($key);
    }

    /**
     * Flushes the entire cache.
     *
     * @return bool True if the cache was cleared, false otherwise.
     */
    public function flush(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Generates a cache key for a query.
     *
     * @param QueryBuilderInterface $query The query.
     * @return string The cache key.
     */
    public function generateCacheKey(QueryBuilderInterface $query): string
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Sort bindings by key to ensure consistent cache keys
        if (is_array($bindings)) {
            ksort($bindings);
        }

        $key = 'query:'.md5($sql.serialize($bindings));

        return $key;
    }

    /**
     * Gets the underlying cache implementation.
     *
     * @return CacheInterface The cache implementation.
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}
