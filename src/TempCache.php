<?php

namespace Airbrake;

class TempCache
{
    protected $file;
    protected $ttl;

    public function __construct($filename = 'airbrake_temp_cache.json', $ttl = 600)
    {
        $this->file = $this->getSystemTempDirectory() . "/" . $filename;
        $this->ttl = $ttl;
    }

    public function canWrite()
    {
        return is_writeable(dirname($this->file));
    }

    public function expired()
    {
        if (!is_file($this->file)) {
            return true;
        }

        $expiresAt = filemtime($this->file) + $this->ttl;
        return time() > $expiresAt;
    }

    /**
        Reads the temp cache file and returns the contents or false if reading
        fails.
    **/
    public function read()
    {
        try {
            return $this->readCacheFile();
        } catch (\Exception $e) {
            unset($e);
            return false;
        }
    }

    public function write($value)
    {
        try {
            return $this->writeCacheFile($value);
        } catch (\Exception $e) {
            unset($e);
            return false;
        }
    }

    protected function readCacheFile()
    {
        $value = file_get_contents($this->file);

        if ($value === false || $value == "") {
            return false;
        }

        return unserialize($value);
    }

    /**
       Writes or overwrites the temp cache file with $value.
       Returns true or false depending on success.
    **/
    protected function writeCacheFile($value)
    {
        $success = file_put_contents($this->file, serialize($value));
        return (bool) $success;
    }

    protected function getSystemTempDirectory()
    {
        return sys_get_temp_dir();
    }
}
