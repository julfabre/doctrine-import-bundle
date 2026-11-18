<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * A pure join table, with its foreign keys in primary key order.
 */
final readonly class JoinTable
{
    /**
     * @param ForeignKeyMetadata $owningKey  foreign key of the first primary key column: its target owns the association
     * @param ForeignKeyMetadata $inverseKey foreign key of the second primary key column
     */
    public function __construct(
        public TableMetadata $table,
        public ForeignKeyMetadata $owningKey,
        public ForeignKeyMetadata $inverseKey,
    ) {
    }
}
