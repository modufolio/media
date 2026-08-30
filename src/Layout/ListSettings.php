<?php

declare(strict_types=1);

namespace Modufolio\Media\Layout;

/**
 * Options for the list layout.
 *
 * `captionAlign` may prove more control than the list needs; dropping it
 * later is a property deletion, not a migration, since options live in the
 * layout_options JSON.
 */
final class ListSettings implements LayoutSettingsInterface
{
    public const ALIGNMENTS = ['left', 'center', 'right'];

    public readonly int $speed;
    public readonly string $captionAlign;

    public function __construct(
        int $speed = LayoutSettingsFactory::DEFAULT_SPEED,
        string $captionAlign = 'center',
    ) {
        if (!in_array($captionAlign, self::ALIGNMENTS, true)) {
            throw new \InvalidArgumentException(
                'Invalid caption alignment. Allowed: ' . implode(', ', self::ALIGNMENTS)
            );
        }

        $this->speed = LayoutSettingsFactory::clampSpeed($speed);
        $this->captionAlign = $captionAlign;
    }

    public static function layout(): string
    {
        return 'list';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['speed'] ?? LayoutSettingsFactory::DEFAULT_SPEED),
            (string) ($data['caption_align'] ?? 'center'),
        );
    }

    public function toArray(): array
    {
        return [
            'speed' => $this->speed,
            'caption_align' => $this->captionAlign,
        ];
    }
}
