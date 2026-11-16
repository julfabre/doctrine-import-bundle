<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * A PostgreSQL SERIAL primary key is filled from its existing sequence, which other clients keep using.
 */
#[CoversNothing]
final class SerialPrimaryKeyPersistenceTest extends DatabaseTestCase
{
    use GeneratesEntities;

    #[Test]
    public function it_persists_entities_of_a_serial_table_and_gets_their_ids(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE product (id SERIAL PRIMARY KEY, name TEXT NOT NULL)');

        $ids = $this->persistProducts($connection, 2);

        self::assertSame([1, 2], $ids);
    }

    #[Test]
    public function it_continues_the_existing_sequence(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE product (id SERIAL PRIMARY KEY, name TEXT NOT NULL)');
        $connection->executeStatement("INSERT INTO product (name) SELECT 'product ' || n FROM generate_series(1, 5) n");

        $ids = $this->persistProducts($connection, 1);

        self::assertSame([6], $ids);
    }

    /**
     * @return list<mixed> identifiers of the persisted products
     */
    private function persistProducts(Connection $connection, int $count): array
    {
        [$namespace, $directory] = $this->generateEntities($connection, 'postgresql');
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $directory);
        $class = $namespace.'\Product';
        if (!class_exists($class)) {
            self::fail(\sprintf('Class "%s" was not generated.', $class));
        }

        $metadata = $entityManager->getClassMetadata($class);
        $products = [];
        for ($index = 1; $index <= $count; ++$index) {
            $product = $metadata->newInstance();
            $metadata->setFieldValue($product, 'name', 'product '.$index);
            $entityManager->persist($product);
            $products[] = $product;
        }

        $entityManager->flush();

        return array_map(static fn (object $product): mixed => $metadata->getFieldValue($product, 'id'), $products);
    }
}
