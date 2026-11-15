<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\GenerationModel;

/**
 * Renders every entity of a model, followed by its repository.
 */
final readonly class CodeGenerator
{
    public function __construct(
        private MappingStrategyInterface $mapping = new AttributeMappingStrategy(),
        private RepositoryClassBuilder $repositories = new RepositoryClassBuilder(),
    ) {
    }

    /**
     * @return list<GeneratedFile>
     */
    public function generate(GenerationModel $model, RenderingOptions $options): array
    {
        $files = [];
        foreach ($model->entities as $entity) {
            $files = [...$files, ...$this->mapping->render($entity, $options), $this->repositories->build($entity, $options)];
        }

        return $files;
    }
}
