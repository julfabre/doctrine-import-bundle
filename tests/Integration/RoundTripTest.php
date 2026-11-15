<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\SchemaTool;
use Juf\DoctrineImportBundle\Application\GenerateEntities;
use Juf\DoctrineImportBundle\Application\GenerationRequest;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\BlogSchemaFixture;
use Juf\DoctrineImportBundle\Writer\WriteOptions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The reference test of the bundle: entities generated from a database describe that same database.
 */
#[CoversNothing]
final class RoundTripTest extends DatabaseTestCase
{
    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_regenerates_the_original_schema_from_the_generated_entities(string $platform): void
    {
        $connection = $this->connect($platform);
        BlogSchemaFixture::create($connection);
        $directory = sys_get_temp_dir().'/juf_doctrine_import/round_trip_'.bin2hex(random_bytes(6));
        // A namespace per run: generated classes cannot be declared twice in the same process.
        $namespace = 'JufRoundTrip\\'.ucfirst($platform).bin2hex(random_bytes(4));

        $report = (new GenerateEntities())->generate(new DbalSchemaReader($connection), new GenerationRequest(
            new TableFilter(),
            [],
            new RenderingOptions($namespace.'\Entity', $namespace.'\Repository'),
            $directory.'/Entity',
            $directory.'/Repository',
            new WriteOptions(dryRun: false, force: false),
        ));
        foreach ($report->files as $file) {
            require_once $file->path;
        }

        $diff = $connection->createSchemaManager()->createComparator()
            ->compareSchemas($connection->createSchemaManager()->introspectSchema(), $this->schemaFromEntities($connection, $directory.'/Entity'));

        // Database namespaces (such as PostgreSQL "public") are not part of an ORM mapping: tables and sequences are.
        self::assertSame([], $diff->getCreatedTables(), 'Tables missing from the database.');
        self::assertSame([], $diff->getDroppedTables(), 'Tables missing from the mapping.');
        self::assertSame([], $diff->getAlteredTables(), \sprintf('Tables mapped differently: %s', print_r($diff->getAlteredTables(), true)));
        self::assertSame([], [...$diff->getCreatedSequences(), ...$diff->getAlteredSequences(), ...$diff->getDroppedSequences()], 'Sequences differ.');
    }

    private function schemaFromEntities(Connection $connection, string $entityDirectory): Schema
    {
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $entityDirectory);

        return (new SchemaTool($entityManager))->getSchemaFromMetadata($entityManager->getMetadataFactory()->getAllMetadata());
    }
}
