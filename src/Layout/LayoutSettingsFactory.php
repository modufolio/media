<?php

declare(strict_types=1);

namespace Modufolio\Media\Layout;

/**
 * Maps an album's layout key to its settings class.
 *
 * Adding a layout (a hero or filmstrip, say) means writing one settings class
 * and adding it to MAP — no schema change, because the options live in the
 * albums.layout_options JSON column.
 */
final class LayoutSettingsFactory
{
    /** Shared by every layout: the lightGallery slideshow pause, in ms. */
    public const DEFAULT_SPEED = 3000;
    public const MIN_SPEED = 500;
    public const MAX_SPEED = 5000;

    /** @var array<string, class-string<LayoutSettingsInterface>> */
    private const MAP = [
        'grid' => GridSettings::class,
        'slider' => SliderSettings::class,
        'list' => ListSettings::class,
    ];

    /** @return string[] */
    public static function layouts(): array
    {
        return array_keys(self::MAP);
    }

    public static function isLayout(string $layout): bool
    {
        return isset(self::MAP[$layout]);
    }

    /**
     * Hydrate the settings for a layout, falling back to that layout's
     * defaults for anything the stored data does not carry.
     *
     * @param array<string, mixed> $data
     */
    public static function make(string $layout, array $data = []): LayoutSettingsInterface
    {
        if (!self::isLayout($layout)) {
            throw new \InvalidArgumentException(
                'Invalid layout. Allowed: ' . implode(', ', self::layouts())
            );
        }

        return (self::MAP[$layout])::fromArray($data);
    }

    public static function clampSpeed(int $speed): int
    {
        return max(self::MIN_SPEED, min(self::MAX_SPEED, $speed));
    }
}
