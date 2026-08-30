<?php

declare(strict_types=1);

namespace Modufolio\Media\Layout;

/**
 * Presentation options for one album layout.
 *
 * Each layout owns exactly the settings that mean something for it: the grid
 * has a mode and columns, the slider has autoplay and page dots, and so on —
 * no layout carries options that do nothing for it.
 *
 * Implementations are immutable value objects, persisted as JSON in
 * albums.layout_options and hydrated back through LayoutSettingsFactory.
 */
interface LayoutSettingsInterface
{
    /** The layout key this class configures, as stored in albums.layout. */
    public static function layout(): string;

    /**
     * Build from stored JSON. Unknown keys are ignored and missing keys take
     * their default, so a layout can gain a setting without a migration.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
