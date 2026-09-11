<?php

declare(strict_types = 1);

namespace Modufolio\Media\Database\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;

/**
 * A BLOB column hydrated as a plain PHP string instead of BlobType's stream
 * resource.
 *
 * Media::$videoCover needs portable large-binary storage: a ~1MB video-cover
 * frame doesn't fit MySQL's VARBINARY(n) (65,535-byte cap) or SQL Server's
 * (8,000-byte cap), but BlobType's engine-aware TINYBLOB/BLOB/MEDIUMBLOB/
 * LONGBLOB sizing (AbstractMySQLPlatform::getBlobTypeDeclarationSQL(), keyed
 * off the mapping's `length`) is exactly the DDL this needs — without
 * changing Media's existing `?string` getter/setter contract the way
 * switching to plain BlobType (stream resources) would.
 */
final class BinaryBlobType extends BlobType
{
    public const NAME = 'binary_blob';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $value = parent::convertToPHPValue($value, $platform);

        return \is_resource($value) ? stream_get_contents($value) : $value;
    }
}
