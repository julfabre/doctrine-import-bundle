<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Juf\DoctrineImportBundle\Application\GenerateEntities;
use Juf\DoctrineImportBundle\Application\GenerationRequest;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Writer\WriteOptions;

trait GeneratesEntities
{
    /**
     * Generates the entities of the whole database into a temporary directory and loads them.
     *
     * @return array{string, string} namespace and directory of the generated entities
     */
    private function generateEntities(Connection $connection, string $platform): array
    {
        $directory = sys_get_temp_dir().'/juf_doctrine_import/generated_'.bin2hex(random_bytes(6));
        // A namespace per run: generated classes cannot be declared twice in the same process.
        $namespace = 'JufGenerated\\'.ucfirst($platform).bin2hex(random_bytes(4));

        $report = (new GenerateEntities())->generate(new DbalSchemaReader($connection), new GenerationRequest(
            new TableFilter(),
            [],
            new RenderingOptions($namespace.'\Entity', $namespace.'\Repository', $namespace.'\Enum'),
            $directory.'/Entity',
            $directory.'/Repository',
            $directory.'/Enum',
            new WriteOptions(dryRun: false, force: false),
        ));
        foreach ($report->files as $file) {
            require_once $file->path;
        }

        return [$namespace.'\Entity', $directory.'/Entity'];
    }
}
