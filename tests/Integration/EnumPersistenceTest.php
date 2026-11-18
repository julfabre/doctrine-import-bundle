<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use BackedEnum;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * An ENUM column is read and written through its generated PHP enum.
 */
#[CoversNothing]
final class EnumPersistenceTest extends DatabaseTestCase
{
    use GeneratesEntities;

    /**
     * @return iterable<string, array{string}>
     */
    public static function mySqlPlatforms(): iterable
    {
        yield 'mysql' => ['mysql'];
        yield 'mariadb' => ['mariadb'];
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_persists_and_reloads_an_enum_value(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement("CREATE TABLE film (id INT AUTO_INCREMENT PRIMARY KEY, rating ENUM('G', 'PG-13') NOT NULL DEFAULT 'G')");
        [$namespace, $directory] = $this->generateEntities($connection, $platform);
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $directory);
        $entityClass = $namespace.'\Film';
        $enumClass = substr($namespace, 0, -\strlen('Entity')).'Enum\FilmRating';
        if (!class_exists($entityClass) || !is_subclass_of($enumClass, BackedEnum::class)) {
            self::fail('The entity and its enum were not generated.');
        }

        $metadata = $entityManager->getClassMetadata($entityClass);
        $film = $metadata->newInstance();
        $metadata->setFieldValue($film, 'rating', $enumClass::from('PG-13'));
        $entityManager->persist($film);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find($entityClass, $metadata->getFieldValue($film, 'id'));

        self::assertSame('PG-13', $connection->fetchOne('SELECT rating FROM film'));
        // ClassMetadata::getFieldValue() returns the backing value of an enum field: the property holds the enum case.
        self::assertSame($enumClass::from('PG-13'), null === $reloaded ? null : (new ReflectionProperty($entityClass, 'rating'))->getValue($reloaded));
    }
}
