<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

/**
 * Reads the structure of a database into the bundle read-only model.
 */
interface SchemaReaderInterface
{
    public function read(TableFilter $filter): SchemaMetadata;
}
