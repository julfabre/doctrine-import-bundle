<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

/**
 * Renders the repository of an entity as a DoctrineBundle ServiceEntityRepository.
 */
final readonly class RepositoryClassBuilder
{
    private const SERVICE_ENTITY_REPOSITORY = 'Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository';

    public function build(EntityDefinition $entity, RenderingOptions $options): GeneratedFile
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($options->repositoryNamespace)
            ->addUse($options->entityNamespace.'\\'.$entity->className)
            ->addUse(self::SERVICE_ENTITY_REPOSITORY)
            ->addUse('Doctrine\Persistence\ManagerRegistry');

        $class = $namespace->addClass($entity->repositoryClassName)
            ->setExtends(self::SERVICE_ENTITY_REPOSITORY)
            ->addComment(\sprintf('@extends ServiceEntityRepository<%s>', $entity->className));

        $constructor = $class->addMethod('__construct')
            ->setBody(\sprintf('parent::__construct($registry, %s::class);', $entity->className));
        $constructor->addParameter('registry')->setType('Doctrine\Persistence\ManagerRegistry');

        return new GeneratedFile($options->repositoryNamespace.'\\'.$entity->repositoryClassName, (new PsrPrinter())->printFile($file));
    }
}
