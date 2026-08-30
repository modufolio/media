<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Layout;

use Modufolio\Media\Layout\LayoutSettingsFactory;
use PHPUnit\Framework\TestCase;

final class LayoutSettingsFactoryTest extends TestCase
{
    public function testEveryDeclaredLayoutIsConstructible(): void
    {
        foreach (LayoutSettingsFactory::layouts() as $layout) {
            $this->assertTrue(LayoutSettingsFactory::isLayout($layout));
            $settings = LayoutSettingsFactory::make($layout);
            $this->assertNotSame([], $settings->toArray());
        }
    }

    public function testUnknownLayoutIsRejected(): void
    {
        $this->assertFalse(LayoutSettingsFactory::isLayout('mosaic'));

        $this->expectException(\InvalidArgumentException::class);
        LayoutSettingsFactory::make('mosaic');
    }

    public function testSliderSpeedIsClamped(): void
    {
        $this->assertSame(LayoutSettingsFactory::MIN_SPEED, LayoutSettingsFactory::clampSpeed(1));
        $this->assertSame(LayoutSettingsFactory::MAX_SPEED, LayoutSettingsFactory::clampSpeed(999999));
        $this->assertSame(3000, LayoutSettingsFactory::clampSpeed(3000));
    }
}
