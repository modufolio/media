<?php

declare(strict_types=1);

namespace Modufolio\Media\Layout;

/**
 * Options for the grid layout.
 *
 * "original" is the justified/masonry flow where each photo keeps its own
 * ratio; the other three modes crop to a fixed ratio laid out on a column
 * grid.
 */
final class GridSettings implements LayoutSettingsInterface
{
    public const MODES = ['original', 'square', 'landscape', 'portrait'];

    // Columns 2–8, gap 1–15px.
    public const MIN_COLUMNS = 2;
    public const MAX_COLUMNS = 8;
    public const MIN_GAP = 1;
    public const MAX_GAP = 15;

    public readonly string $mode;
    public readonly int $columns;
    public readonly int $gap;
    public readonly int $speed;

    public function __construct(
        string $mode = 'original',
        int $columns = 3,
        int $gap = 5,
        int $speed = LayoutSettingsFactory::DEFAULT_SPEED,
    ) {
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException(
                'Invalid grid mode. Allowed: ' . implode(', ', self::MODES)
            );
        }

        $this->mode = $mode;
        $this->columns = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));
        $this->gap = max(self::MIN_GAP, min(self::MAX_GAP, $gap));
        $this->speed = LayoutSettingsFactory::clampSpeed($speed);
    }

    public static function layout(): string
    {
        return 'grid';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['mode'] ?? 'original'),
            (int) ($data['columns'] ?? 3),
            (int) ($data['gap'] ?? 5),
            (int) ($data['speed'] ?? LayoutSettingsFactory::DEFAULT_SPEED),
        );
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'columns' => $this->columns,
            'gap' => $this->gap,
            'speed' => $this->speed,
        ];
    }

    /** The justified flow sizes itself from each photo's ratio, not a column count. */
    public function isJustified(): bool
    {
        return $this->mode === 'original';
    }
}
