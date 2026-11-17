<?php

namespace Tests\Unit;

use App\Support\Brand;
use PHPUnit\Framework\TestCase;

class BrandTest extends TestCase
{
    public function test_rejects_values_that_could_inject_css(): void
    {
        $this->assertSame('#123456', Brand::sanitizeHex('#123456', '#000000'));
        $this->assertSame('#abcdef', Brand::sanitizeHex('#ABCDEF', '#000000'));
        $this->assertSame('#000000', Brand::sanitizeHex('red; } body { display:none', '#000000'));
        $this->assertSame('#000000', Brand::sanitizeHex('#fff', '#000000'));
        $this->assertSame('#000000', Brand::sanitizeHex(null, '#000000'));
    }

    public function test_picks_readable_text_color(): void
    {
        $this->assertSame('#000000', Brand::readableTextOn('#facc15')); // amarillo
        $this->assertSame('#ffffff', Brand::readableTextOn('#0f172a')); // azul marino
        // Verde medio: negro da 6.4:1 vs blanco 3.3:1 (WCAG pide 4.5:1)
        $this->assertSame('#000000', Brand::readableTextOn('#16a34a'));
    }
}
