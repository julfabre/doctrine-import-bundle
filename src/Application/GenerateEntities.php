<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Application;

use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\SchemaAnalyzer;
use Juf\DoctrineImportBundle\Rendering\CodeGenerator;
use Juf\DoctrineImportBundle\Schema\SchemaReaderInterface;
use Juf\DoctrineImportBundle\Writer\FileWriter;
use Juf\DoctrineImportBundle\Writer\OutputLocation;
use Juf\DoctrineImportBundle\Writer\UnmappedClassException;
use Juf\DoctrineImportBundle\Writer\WriteFailedException;

/**
 * Reads a database schema, turns it into entities and repositories, and writes them.
 */
final readonly class GenerateEntities
{
    public function __construct(
        private CodeGenerator $codeGenerator = new CodeGenerator(),
        private FileWriter $writer = new FileWriter(),
    ) {
    }

    /**
     * @throws UnmappedClassException
     * @throws WriteFailedException
     */
    public function generate(SchemaReaderInterface $reader, GenerationRequest $request): GenerationReport
    {
        $schema = $reader->read($request->filter);
        $model = (new SchemaAnalyzer(new DefaultNamingStrategy($request->tablePrefixes)))->analyze($schema);

        $files = $this->writer->write(
            $this->codeGenerator->generate($model, $request->rendering),
            [
                new OutputLocation($request->rendering->entityNamespace, $request->entityDirectory),
                new OutputLocation($request->rendering->repositoryNamespace, $request->repositoryDirectory),
            ],
            $request->write,
        );

        return new GenerationReport($files, $model->diagnostics);
    }
}
