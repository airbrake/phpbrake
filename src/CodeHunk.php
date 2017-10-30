<?php

namespace Airbrake;

use cash\LRUCache;

class CodeHunk
{
    private $cache;

    public function __construct()
    {
        $this->cache = new LRUCache(1000);
    }

    public function get($file, $line)
    {
        $cacheKey = $file . $line;
        $lines = $this->cache->get($cacheKey, false);
        if ($lines !== false) {
            return $lines;
        }

        $lines = $this->_get($file, $line);
        $this->cache->put($cacheKey, $lines);
        return $lines;
    }

    private function _get($file, $line)
    {
        $fh = fopen($file, 'r');
        if (!$fh) {
            return null;
        }

        $start = $line - 2;
        $end = $line + 2;

        $i = 0;
        $lines = [];
        while (($text = fgets($fh)) !== false) {
            $i++;
            if ($i < $start) {
                continue;
            }
            if ($i > $end) {
                break;
            }
            $lines[$i] = rtrim($text);
        }

        fclose($fh);

        return $lines;
    }
}
