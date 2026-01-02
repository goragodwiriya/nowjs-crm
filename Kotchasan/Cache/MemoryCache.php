<?php

namespace Kotchasan\Cache;

/**
 * Class MemoryCache
 *
 * Simple in-memory cache implementation.
 *
 * @package Kotchasan\Cache
 */
class MemoryCache implements CacheInterface
{
    /**
     * @var array The cached items.
     */
    private array $cache = [];

    /**
     * @var array The expiration timestamps for cached items.
     */
    private array $expires = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->cache[$key] = $value;

        if ($ttl !== null) {
            $this->expires[$key] = time() + $ttl;
        } else {
            $this->expires[$key] = null;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        if (isset($this->expires[$key]) && $this->expires[$key] !== null && $this->expires[$key] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        unset($this->cache[$key], $this->expires[$key]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->expires = [];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }
}
