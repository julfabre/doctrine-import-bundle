<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use DateTimeImmutable;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\DerivedIdentitySchemaFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * An entity identified by its foreign keys is written and found again by its composite identifier.
 */
#[CoversNothing]
final class DerivedIdentityPersistenceTest extends DatabaseTestCase
{
    use GeneratesEntities;

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_persists_an_entity_identified_by_its_foreign_keys_and_finds_it_again(string $platform): void
    {
        $connection = $this->connect($platform);
        DerivedIdentitySchemaFixture::create($connection);
        [$namespace, $directory] = $this->generateEntities($connection, $platform);
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $directory);
        $filmClass = $namespace.'\\Film';
        $actorClass = $namespace.'\\Actor';
        $filmActorClass = $namespace.'\\FilmActor';
        if (!class_exists($filmClass) || !class_exists($actorClass) || !class_exists($filmActorClass)) {
            self::fail('The film, actor and film_actor entities were not generated.');
        }

        $film = new $filmClass();
        $actor = new $actorClass();
        $filmActorMetadata = $entityManager->getClassMetadata($filmActorClass);
        self::assertTrue($filmActorMetadata->hasAssociation('film'), 'FilmActor has no association to its film.');
        $filmActor = new $filmActorClass();
        $filmActorMetadata->setFieldValue($filmActor, 'film', $film);
        $filmActorMetadata->setFieldValue($filmActor, 'actor', $actor);
        $filmActorMetadata->setFieldValue($filmActor, 'lastUpdate', new DateTimeImmutable('2026-11-18 10:00:00'));

        $entityManager->persist($film);
        $entityManager->persist($actor);
        $entityManager->persist($filmActor);
        $entityManager->flush();

        $identifier = $filmActorMetadata->getIdentifierValues($filmActor);
        $entityManager->clear();

        $found = $entityManager->find($filmActorClass, $identifier);

        self::assertSame(['actor', 'film'], $filmActorMetadata->getIdentifierFieldNames());
        self::assertNotNull($found);
        self::assertEquals(new DateTimeImmutable('2026-11-18 10:00:00'), $filmActorMetadata->getFieldValue($found, 'lastUpdate'));
    }
}
