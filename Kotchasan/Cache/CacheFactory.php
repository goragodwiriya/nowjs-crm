<?php

namespace Kotchasan\Cache;

use Kotchasan\Exception\ConfigurationException;

/**
 * Class CacheFactory
 *
 * Factory for creating cache instances.
 *
 * @package Kotchasan\Cache
 */
class CacheFactory
{
    /**
     * Creates a cache instance based on the provided configuration.
     *
     * @param array $config The cache configuration.
     * @return CacheInterface The cache instance.
     * @throws ConfigurationException If the cache type is invalid.
     */
    public static function create(array $config): CacheInterface
    {
        $type = $config['type'] ?? 'memory';

        switch ($type) {
            case 'memory':
                return new MemoryCache();

            case 'file':
                if (!isset($config['directory'])) {
                    throw new ConfigurationException('File cache requires a directory configuration.');
                }
                return new FileCache($config['directory']);

            case 'redis':
                return new RedisCache($config);

            default:
                throw new ConfigurationException("Unsupported cache type: {$type}");
        }
    }
}
