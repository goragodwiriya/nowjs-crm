<?php

namespace Kotchasan\Cache;

use Kotchasan\Exception\ConfigurationException;

/**
 * Class RedisCache
 *
 * Redis-based cache implementation.
 * Requires the Redis extension (https://github.com/phpredis/phpredis).
 *
 * @package Kotchasan\Cache
 */
class RedisCache implements CacheInterface
{
    /**
     * @var \Redis The Redis instance.
     */
    private $redis;

    /**
     * @var string Optional prefix for cache keys.
     */
    private string $prefix;

    /**
     * RedisCache constructor.
     *
     * @param array $config Redis connection configuration.
     * @param string $prefix Optional prefix for cache keys.
     * @throws ConfigurationException If Redis extension is not loaded or connection fails.
     */
    public function __construct(array $config, string $prefix = 'db_cache:')
    {
        if (!extension_loaded('redis') || !class_exists('\\Redis')) {
            throw new ConfigurationException('Redis extension is not loaded.');
        }

        $this->prefix = $prefix;
        $this->redis = new \Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;
        $retryInterval = $config['retry_interval'] ?? 0;

        if (!@$this->redis->connect($host, $port, $timeout, null, $retryInterval)) {
            throw new ConfigurationException("Could not connect to Redis server at {$host}:{$port}.");
        }

        if (isset($config['password']) && !empty($config['password'])) {
            if (!$this->redis->auth($config['password'])) {
                throw new ConfigurationException('Redis authentication failed.');
            }
        }

        if (isset($config['database']) && is_int($config['database'])) {
            if (!$this->redis->select($config['database'])) {
                throw new ConfigurationException("Could not select Redis database {$config['database']}.");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        $value = $this->redis->get($this->prefix.$key);

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $serialized = serialize($value);
        $prefixedKey = $this->prefix.$key;

        if ($ttl === null) {
            return $this->redis->set($prefixedKey, $serialized);
        }

        return $this->redis->setex($prefixedKey, $ttl, $serialized);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix.$key) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix.$key) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $keys = $this->redis->keys($this->prefix.'*');

        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, $default = null): array
    {
        $prefixedKeys = array_map(function ($key) {
            return $this->prefix.$key;
        }, $keys);

        $values = $this->redis->mGet($prefixedKeys);
        $result = [];

        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? false;
            $result[$key] = $value === false ? $default : unserialize($value);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        if (empty($values)) {
            return true;
        }

        $success = true;

        if ($ttl === null) {
            $prefixedValues = [];

            foreach ($values as $key => $value) {
                $prefixedValues[$this->prefix.$key] = serialize($value);
            }

            return $this->redis->mSet($prefixedValues);
        }

        // For TTL we need to use pipeline since mSetEx doesn't exist
        $pipe = $this->redis->pipeline();

        foreach ($values as $key => $value) {
            $pipe->setex($this->prefix.$key, $ttl, serialize($value));
        }

        $results = $pipe->exec();

        // Check if all operations succeeded
        foreach ($results as $result) {
            $success = $success && $result;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $prefixedKeys = array_map(function ($key) {
            return $this->prefix.$key;
        }, $keys);

        return $this->redis->del($prefixedKeys) >= 0;
    }

    /**
     * Gets the Redis instance.
     *
     * @return object The Redis instance.
     */
    public function getRedis()
    {
        return $this->redis;
    }
}
