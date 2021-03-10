<?php

namespace Airbrake\Tests;

use Airbrake\TempCache;
use PHPUnit\Framework\TestCase;

class TempCacheTest extends TestCase
{
    private $cachedValue = ['key' => 'value'];

    protected function setUp()
    {
        $this->tempCache = new TempCacheMock();
    }

    public function testSuccessfulWrite()
    {
        $this->assertTrue($this->tempCache->write($this->cachedValue));
    }

    public function testWriteWithErrors()
    {
        $this->tempCache->throwWriteException = true;
        $this->assertSame(
            $this->tempCache->write($this->cachedValue),
            false
        );
    }

    public function testReadWithErrors()
    {
        $this->tempCache->throwReadException = true;
        $this->tempCache->write($this->cachedValue);

        $this->assertSame(
            $this->tempCache->read(),
            false
        );
    }

    public function testCanWriteWithAvailableTempDir()
    {
        $this->tempCache->mockAvailableTempDir();
        $this->assertTrue($this->tempCache->canWrite());
    }

    public function testCanWriteWithUnavailableTempDir()
    {
        $this->tempCache->mockUnavailableTempDir();
        $this->assertSame(
            $this->tempCache->canWrite(),
            false
        );
    }
}
