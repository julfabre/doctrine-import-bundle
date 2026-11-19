<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * SQLite numbers an INTEGER PRIMARY KEY itself: the generated entity lets it do so.
 */
#[CoversNothing]
final class SqliteRowidPersistenceTest extends DatabaseTestCase
{
    use GeneratesEntities;

    #[Test]
    public function it_persists_entities_of_a_table_whose_integer_primary_key_is_numbered_by_sqlite(): void
    {
        $connection = $this->connect('sqlite');
        $connection->executeStatement('CREATE TABLE artist (ArtistId INTEGER NOT NULL, Name TEXT, PRIMARY KEY (ArtistId))');
        [$namespace, $directory] = $this->generateEntities($connection, 'sqlite');
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $directory);
        $class = $namespace.'\Artist';
        if (!class_exists($class)) {
            self::fail(\sprintf('Class "%s" was not generated.', $class));
        }

        $metadata = $entityManager->getClassMetadata($class);
        $first = new $class();
        $second = new $class();
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        self::assertSame([1, 2], [$metadata->getFieldValue($first, 'artistId'), $metadata->getFieldValue($second, 'artistId')]);
    }
}
