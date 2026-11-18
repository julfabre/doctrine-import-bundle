<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;

/**
 * Renders every entity of a model, followed by its repository.
 */
final readonly class CodeGenerator
{
    public function __construct(
        private MappingStrategyInterface $mapping = new AttributeMappingStrategy(),
        private RepositoryClassBuilder $repositories = new RepositoryClassBuilder(),
        private EnumBuilder $enums = new EnumBuilder(),
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
            foreach ($entity->fields as $field) {
                if ($field->enum instanceof EnumDefinition) {
                    $files[] = $this->enums->build($field->enum, $options);
                }
            }
        }

        return $files;
    }
}
