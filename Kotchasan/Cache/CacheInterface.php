<?php

namespace Kotchasan\Cache;

/**
 * Interface CacheInterface
 *
 * Defines methods for caching query results.
 *
 * @package Kotchasan\Cache
 */
interface CacheInterface
{
    /**
     * Gets a value from the cache.
     *
     * @param string $key The cache key.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The cached value or the default value.
     */
    public function get(string $key, $default = null);

    /**
     * Sets a value in the cache.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to cache.
     * @param int|null $ttl The time-to-live in seconds (null for indefinite).
     * @return bool True if the value was stored, false otherwise.
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * Checks if a key exists in the cache.
     *
     * @param string $key The cache key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Removes a value from the cache.
     *
     * @param string $key The cache key.
     * @return bool True if the value was removed, false otherwise.
     */
    public function delete(string $key): bool;

    /**
     * Clears the entire cache.
     *
     * @return bool True if the cache was cleared, false otherwise.
     */
    public function clear(): bool;

    /**
     * Gets multiple values from the cache.
     *
     * @param array $keys The cache keys.
     * @param mixed $default The default value to return for keys that are not found.
     * @return array An array of values indexed by key.
     */
    public function getMultiple(array $keys, $default = null): array;

    /**
     * Sets multiple values in the cache.
     *
     * @param array $values An array of key-value pairs.
     * @param int|null $ttl The time-to-live in seconds (null for indefinite).
     * @return bool True if all values were stored, false otherwise.
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Removes multiple values from the cache.
     *
     * @param array $keys The cache keys.
     * @return bool True if all values were removed, false otherwise.
     */
    public function deleteMultiple(array $keys): bool;
}
