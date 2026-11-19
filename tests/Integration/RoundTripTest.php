<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\ORM\Tools\SchemaTool;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\BlogSchemaFixture;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\DerivedIdentitySchemaFixture;
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
    #[DataProvider('allPlatforms')]
    public function it_regenerates_composite_primary_keys_in_their_order(string $platform): void
    {
        $connection = $this->connect($platform);
        DerivedIdentitySchemaFixture::create($connection);

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

    #[Test]
    public function it_regenerates_foreign_keys_to_serial_primary_keys_on_postgresql(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE city (id SERIAL PRIMARY KEY, name TEXT NOT NULL)');
        $connection->executeStatement('CREATE TABLE address (id SERIAL PRIMARY KEY, city_id INT NOT NULL REFERENCES city (id))');
        $connection->executeStatement('CREATE INDEX idx_address_city ON address (city_id)');
        $connection->executeStatement('CREATE TABLE mayor (id SERIAL PRIMARY KEY, city_id INT NOT NULL UNIQUE REFERENCES city (id))');
        $connection->executeStatement('CREATE TABLE tag (id SERIAL PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE address_tag (address_id INT NOT NULL REFERENCES address (id), tag_id INT NOT NULL REFERENCES tag (id), PRIMARY KEY (address_id, tag_id))');

        [, $entityDirectory] = $this->generateEntities($connection, 'postgresql');

        $this->assertNoDifference($this->diff($connection, $entityDirectory));
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_regenerates_a_derived_identity_created_in_sql_on_mysql(string $platform): void
    {
        $connection = $this->connect($platform);
        // As in Sakila: actor_id is only indexed by the primary key, film_id by the index MySQL creates for its foreign key.
        $connection->executeStatement('CREATE TABLE actor (actor_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE film (film_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE film_actor (actor_id INT NOT NULL, film_id INT NOT NULL, last_update TIMESTAMP NOT NULL, PRIMARY KEY (actor_id, film_id), KEY idx_fk_film_id (film_id), CONSTRAINT fk_film_actor_actor FOREIGN KEY (actor_id) REFERENCES actor (actor_id), CONSTRAINT fk_film_actor_film FOREIGN KEY (film_id) REFERENCES film (film_id))');

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
