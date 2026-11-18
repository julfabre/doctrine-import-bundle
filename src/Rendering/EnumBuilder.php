<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

/**
 * Renders the string-backed PHP enum of an ENUM column.
 */
final readonly class EnumBuilder
{
    public function build(EnumDefinition $enum, RenderingOptions $options): GeneratedFile
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $phpEnum = $file->addNamespace($options->enumNamespace)->addEnum($enum->className)->setType('string');
        foreach ($enum->cases as $case) {
            $phpEnum->addCase($case->name, $case->value);
        }

        return new GeneratedFile($options->enumNamespace.'\\'.$enum->className, (new PsrPrinter())->printFile($file));
    }
}
