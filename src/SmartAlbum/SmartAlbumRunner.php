<?php

declare(strict_types = 1);

namespace Modufolio\Media\SmartAlbum;

use Modufolio\Media\SmartAlbum\Spec\Params;
use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Media\Entity\Media;

/**
 * Compiles a smart album's specification into a DQL query and runs it.
 */
final class SmartAlbumRunner
{
    private const ORDERABLE = ['createdAt', 'rating', 'featuredOrder', 'filename', 'fileSize'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** The full DQL for an album — also handy in tests and debugging. */
    public function dql(SmartAlbumInterface $album, Params $params): string
    {
        $where = $album->specification()->toDql('m', $params);

        $order = [];
        foreach ($album->orderBy() as $field => $direction) {
            if (!in_array($field, self::ORDERABLE, true)) {
                throw new \LogicException(sprintf(
                    'Smart album "%s" orders by "%s"; allowed: %s',
                    $album->slug(),
                    $field,
                    implode(', ', self::ORDERABLE)
                ));
            }
            $order[] = 'm.' . $field . ' ' . (strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC');
        }

        return sprintf(
            'SELECT m FROM %s m WHERE %s%s',
            Media::class,
            $where,
            $order !== [] ? ' ORDER BY ' . implode(', ', $order) : ''
        );
    }

    /** @return Media[] */
    public function run(SmartAlbumInterface $album): array
    {
        $params = new Params();
        $query  = $this->em->createQuery($this->dql($album, $params));

        foreach ($params->all() as $name => $value) {
            $query->setParameter($name, $value);
        }

        if ($album->limit() !== null) {
            $query->setMaxResults($album->limit());
        }

        return $query->getResult();
    }

    public function count(SmartAlbumInterface $album): int
    {
        $params = new Params();
        $where  = $album->specification()->toDql('m', $params);

        $query = $this->em->createQuery(
            sprintf('SELECT COUNT(m.id) FROM %s m WHERE %s', Media::class, $where)
        );

        foreach ($params->all() as $name => $value) {
            $query->setParameter($name, $value);
        }

        $count = (int) $query->getSingleScalarResult();

        return $album->limit() !== null ? min($count, $album->limit()) : $count;
    }
}
