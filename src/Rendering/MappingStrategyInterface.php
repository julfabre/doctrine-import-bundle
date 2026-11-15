<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\EntityDefinition;

/**
 * Renders an entity with its mapping in a given format (attributes, XML...).
 */
interface MappingStrategyInterface
{
    /**
     * @return list<GeneratedFile> the entity class, plus the mapping files of formats that need them
     */
    public function render(EntityDefinition $entity, RenderingOptions $options): array;
}
