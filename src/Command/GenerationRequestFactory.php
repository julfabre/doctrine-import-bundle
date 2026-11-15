<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Command;

use Juf\DoctrineImportBundle\Application\GenerationRequest;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Writer\WriteOptions;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Merges the command line options over the bundle configuration. Repeatable options replace configured lists.
 *
 * @phpstan-type Defaults array{
 *     entity_manager: ?string,
 *     table_prefixes: list<string>,
 *     tables: array{include: list<string>, exclude: list<string>},
 *     entity: array{namespace: string, dir: string},
 *     repository: array{namespace: string, dir: string},
 * }
 */
final readonly class GenerationRequestFactory
{
    /**
     * @param Defaults $defaults
     */
    public function __construct(
        private array $defaults,
    ) {
    }

    public function fromInput(InputInterface $input): GenerationRequest
    {
        return new GenerationRequest(
            new TableFilter(
                $this->listOption($input, 'table') ?? $this->defaults['tables']['include'],
                $this->listOption($input, 'exclude') ?? $this->defaults['tables']['exclude'],
            ),
            $this->listOption($input, 'table-prefix') ?? $this->defaults['table_prefixes'],
            new RenderingOptions(
                $this->stringOption($input, 'namespace') ?? $this->defaults['entity']['namespace'],
                $this->stringOption($input, 'repository-namespace') ?? $this->defaults['repository']['namespace'],
            ),
            $this->stringOption($input, 'dir') ?? $this->defaults['entity']['dir'],
            $this->stringOption($input, 'repository-dir') ?? $this->defaults['repository']['dir'],
            new WriteOptions(true === $input->getOption('dry-run'), true === $input->getOption('force')),
        );
    }

    public function entityManager(InputInterface $input): ?string
    {
        return $this->stringOption($input, 'em') ?? $this->defaults['entity_manager'];
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return list<string>|null null when the option is not given
     */
    private function listOption(InputInterface $input, string $name): ?array
    {
        $values = array_values(array_filter((array) $input->getOption($name), \is_string(...)));

        return [] === $values ? null : $values;
    }
}
