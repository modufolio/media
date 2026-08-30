<?php

declare(strict_types = 1);

namespace Modufolio\Media\Tests\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Modufolio\Media\Contract\UploaderInterface;
use Modufolio\Media\Database\AlbumTriggers;
use Modufolio\Media\Entity\Album;
use Modufolio\Media\Entity\Media;
use Modufolio\Media\Model\AlbumModel;
use Modufolio\Media\Repository\AlbumMediaRepository;
use Modufolio\Media\Repository\AlbumRepository;
use Modufolio\Media\Repository\MediaRepository;
use Modufolio\Media\Tests\Fixture\TestUploader;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;

/**
 * A package-standalone harness: in-memory SQLite, the package entities plus
 * the test fixtures, the UploaderInterface resolved to TestUploader, and the
 * integrity triggers installed — the same environment the entities meet in a
 * consuming application, with no application present.
 */
abstract class MediaTestCase extends TestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        if (!\Doctrine\DBAL\Types\Type::hasType(UuidType::NAME)) {
            \Doctrine\DBAL\Types\Type::addType(UuidType::NAME, UuidType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Entity', dirname(__DIR__) . '/Fixture'],
            isDevMode: true,
        );

        $connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $config,
        );

        $this->em = new EntityManager($connection, $config);

        $resolver = new ResolveTargetEntityListener();
        $resolver->addResolveTargetEntity(UploaderInterface::class, TestUploader::class, []);
        $this->em->getEventManager()->addEventSubscriber($resolver);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->em))->createSchema($metadata);

        foreach (AlbumTriggers::all() as $sql) {
            $connection->executeStatement($sql);
        }
    }

    protected function tearDown(): void
    {
        MediaRepository::useTagEntity(null);
        $this->em->close();

        parent::tearDown();
    }

    protected function albumRepo(): AlbumRepository
    {
        $repo = $this->em->getRepository(Album::class);
        assert($repo instanceof AlbumRepository);

        return $repo;
    }

    protected function mediaRepo(): MediaRepository
    {
        $repo = $this->em->getRepository(Media::class);
        assert($repo instanceof MediaRepository);

        return $repo;
    }

    protected function albumModel(): AlbumModel
    {
        $albumMediaRepo = $this->em->getRepository(\Modufolio\Media\Entity\AlbumMedia::class);
        assert($albumMediaRepo instanceof AlbumMediaRepository);

        return new AlbumModel($this->em, $this->albumRepo(), $albumMediaRepo, $this->mediaRepo());
    }

    protected function makeMedia(string $filename = 'photo.jpg'): Media
    {
        $media = new Media();
        $media->setFilename($filename)
            ->setOriginalFilename($filename)
            ->setFilePath('/uploads/tus/aa/' . $filename)
            ->setMimeType('image/jpeg')
            ->setFileSize(1234);

        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }
}
