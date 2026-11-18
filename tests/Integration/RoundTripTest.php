<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\ORM\Tools\SchemaTool;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\BlogSchemaFixture;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\GeneratedColumnFixture;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\ManyToManySchemaFixture;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\OneToOneSchemaFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The reference test of the bundle: entities generated from a database describe that same database.
 */
#[CoversNothing]
final class RoundTripTest extends DatabaseTestCase
{
    use GeneratesEntities;

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_regenerates_the_original_schema_from_the_generated_entities(string $platform): void
    {
        $connection = $this->connect($platform);
        BlogSchemaFixture::create($connection);
        GeneratedColumnFixture::add($connection, $platform, 'order_line', 'quantity_doubled', 'quantity * 2');

        [, $entityDirectory] = $this->generateEntities($connection, $platform);

        $this->assertNoDifference($this->diff($connection, $entityDirectory));
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_regenerates_a_one_to_one_association(string $platform): void
    {
        $connection = $this->connect($platform);
        OneToOneSchemaFixture::create($connection);

        [, $entityDirectory] = $this->generateEntities($connection, $platform);

        $this->assertNoDifference($this->diff($connection, $entityDirectory));
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_regenerates_a_many_to_many_association(string $platform): void
    {
        $connection = $this->connect($platform);
        ManyToManySchemaFixture::create($connection);

        [, $entityDirectory] = $this->generateEntities($connection, $platform);

        $this->assertNoDifference($this->diff($connection, $entityDirectory));
    }

    #[Test]
    public function it_regenerates_a_serial_table_on_postgresql(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE product (id SERIAL PRIMARY KEY, name TEXT NOT NULL)');

        [, $entityDirectory] = $this->generateEntities($connection, 'postgresql');

        $this->assertNoDifference($this->diff($connection, $entityDirectory));
    }

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
    public function it_regenerates_enum_columns_on_mysql(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement("CREATE TABLE film (id INT AUTO_INCREMENT PRIMARY KEY, rating ENUM('G', 'PG', 'PG-13') NOT NULL DEFAULT 'G', status ENUM('draft', 'published') NULL)");

        [, $entityDirectory] = $this->generateEntities($connection, $platform);

        $this->assertNoDifference($this->diff($connection, $entityDirectory));
    }

    private function diff(Connection $connection, string $entityDirectory): SchemaDiff
    {
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $entityDirectory);
        $schemaManager = $connection->createSchemaManager();

        return $schemaManager->createComparator()->compareSchemas(
            $schemaManager->introspectSchema(),
            (new SchemaTool($entityManager))->getSchemaFromMetadata($entityManager->getMetadataFactory()->getAllMetadata()),
        );
    }

    /**
     * Database namespaces (such as PostgreSQL "public") are not part of an ORM mapping: tables and sequences are.
     */
    private function assertNoDifference(SchemaDiff $diff): void
    {
        self::assertSame([], $diff->getCreatedTables(), 'Tables missing from the database.');
        self::assertSame([], $diff->getDroppedTables(), 'Tables missing from the mapping.');
        self::assertSame([], $diff->getAlteredTables(), \sprintf('Tables mapped differently: %s', print_r($diff->getAlteredTables(), true)));
        self::assertSame([], [...$diff->getCreatedSequences(), ...$diff->getAlteredSequences(), ...$diff->getDroppedSequences()], 'Sequences differ.');
    }
}
