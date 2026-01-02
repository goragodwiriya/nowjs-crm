<?php

namespace Kotchasan\Cache;

use Kotchasan\Exception\ConfigurationException;

/**
 * Class FileCache
 *
 * File-based cache implementation.
 *
 * @package Kotchasan\Cache
 */
class FileCache implements CacheInterface
{
    /**
     * @var string The cache directory.
     */
    private string $cacheDir;

    /**
     * FileCache constructor.
     *
     * @param string $cacheDir The directory to store cache files.
     * @throws ConfigurationException If the cache directory doesn't exist and can't be created.
     */
    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\').DIRECTORY_SEPARATOR;

        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new ConfigurationException("Cache directory {$this->cacheDir} does not exist and could not be created.");
            }
        }

        if (!is_writable($this->cacheDir)) {
            throw new ConfigurationException("Cache directory {$this->cacheDir} is not writable.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        $filename = $this->getFilename($key);
        $data = file_get_contents($filename);

        if ($data === false) {
            return $default;
        }

        $cacheData = unserialize($data);

        return $cacheData['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $filename = $this->getFilename($key);

        $expiration = ($ttl === null) ? null : time() + $ttl;

        $cacheData = [
            'key' => $key,
            'value' => $value,
            'expiration' => $expiration
        ];

        $data = serialize($cacheData);

        return file_put_contents($filename, $data) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return false;
        }

        $data = file_get_contents($filename);

        if ($data === false) {
            return false;
        }

        $cacheData = unserialize($data);

        if ($cacheData['expiration'] !== null && $cacheData['expiration'] < time()) {
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
        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir.'*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

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
        $success = true;

        foreach ($values as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    /**
     * Gets the filename for a cache key.
     *
     * @param string $key The cache key.
     * @return string The filename.
     */
    private function getFilename(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir.$hash.'.cache';
    }
}
