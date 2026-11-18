<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class JoinTableDefinition
{
    /**
     * @param JoinColumnDefinition $joinColumn        column referencing the owning entity
     * @param JoinColumnDefinition $inverseJoinColumn column referencing the target entity
     */
    public function __construct(
        public string $name,
        public JoinColumnDefinition $joinColumn,
        public JoinColumnDefinition $inverseJoinColumn,
    ) {
    }
}
