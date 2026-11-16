<?php

namespace Tests\Unit;

use App\Support\UploadLimits;
use PHPUnit\Framework\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_ini_values_are_converted_to_bytes()
    {
        $this->assertSame(8 * 1024 * 1024, UploadLimits::iniBytes('8M'));
        $this->assertSame(2 * 1024 ** 3, UploadLimits::iniBytes('2G'));
        $this->assertSame(512 * 1024, UploadLimits::iniBytes('512k'));
        $this->assertSame(1000, UploadLimits::iniBytes('1000'));
        $this->assertSame(0, UploadLimits::iniBytes(''));
    }
}
