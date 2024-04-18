<?php

namespace Airbrake\Tests;

use Airbrake\TempCache;
use org\bovigo\vfs\vfsStream;

class TempCacheMock extends TempCache
{
    public $mockTempDir;
    public $filename;
    public $file;
    public $ttl;

    // Helper vars to inspect the last read or write calls.
    public $wasRead = false;
    public $lastReadValue = null;
    public $wasWritten = false;
    public $lastWrittenValue = null;

    // If unset, real canWrite() behavior is used.
    // If set to true, can write to cache.
    // If set to false, can't write to cache.
    public $mockCanWrite;

    // If unset, real expired() behavior is used.
    // If set to true, the cache acts expired.
    // If set to false, the cache acts fresh.
    public $mockExpired;

    // Set to true when you want to throw exceptions for reads or writes.
    public $throwReadException = false;
    public $throwWriteException = false;

    public function __construct($filename = 'airbrake_temp_cache.json', $ttl = 600)
    {
        $this->filename = $filename;
        $this->ttl = $ttl;
        $this->mockTempDir = vfsStream::setup($this->getSystemTempDirectory());
        $this->file = $this->mockTempDir->url() . "/" . $this->filename;
    }

    public function mockAvailableTempDir()
    {
        $this->mockTempDir->chmod(0777);
    }

    public function mockUnavailableTempDir()
    {
        $this->mockTempDir->chmod(0000);
    }

    public function canWrite()
    {
        if (isset($this->mockCanWrite)) {
            return $this->mockCanWrite;
        }

        return parent::canWrite();
    }

    public function expired()
    {
        if (isset($this->mockExpired)) {
            return $this->mockExpired;
        }

        return parent::expired();
    }

    protected function readCacheFile()
    {
        if ($this->throwReadException) {
            throw new \Exception('read fails');
        }

        $value = parent::readCacheFile();
        $this->wasRead = true;
        $this->lastReadValue = $value;

        return $value;
    }

    protected function writeCacheFile($value)
    {
        if ($this->throwWriteException) {
            throw new \Exception('Write fails');
        }

        $parentWrite = parent::writeCacheFile($value);
        $this->wasWritten = true;
        $this->lastWrittenValue = $value;

        return $parentWrite;
    }

    protected function getSystemTempDirectory()
    {
        return 'test_temp_dir';
    }
}
