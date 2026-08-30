<?php

declare(strict_types=1);

namespace Modufolio\Media\Layout;

/**
 * Options for the slider layout.
 *
 * `speed` is flickity's autoPlay interval and only matters while autoplay is
 * on. The slider has no lightbox, so there is no slideshow pause to
 * configure. Cell spacing is not an option either — the flickity stylesheet
 * handles it.
 */
final class SliderSettings implements LayoutSettingsInterface
{
    public readonly bool $autoplay;
    public readonly int $speed;
    public readonly bool $pagedots;

    public function __construct(
        bool $autoplay = false,
        int $speed = LayoutSettingsFactory::DEFAULT_SPEED,
        bool $pagedots = false,
    ) {
        $this->autoplay = $autoplay;
        $this->speed = LayoutSettingsFactory::clampSpeed($speed);
        $this->pagedots = $pagedots;
    }

    public static function layout(): string
    {
        return 'slider';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['autoplay'] ?? false),
            (int) ($data['speed'] ?? LayoutSettingsFactory::DEFAULT_SPEED),
            (bool) ($data['pagedots'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'autoplay' => $this->autoplay,
            'speed' => $this->speed,
            'pagedots' => $this->pagedots,
        ];
    }
}
