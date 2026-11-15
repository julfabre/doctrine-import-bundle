<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

/**
 * Selects tables with case-sensitive glob patterns. An exclusion always wins over an inclusion.
 */
final readonly class TableFilter
{
    /**
     * @param list<string> $include no pattern means every table
     * @param list<string> $exclude
     */
    public function __construct(
        private array $include = [],
        private array $exclude = [],
    ) {
    }

    public function accepts(string $table): bool
    {
        if ($this->matchesAny($table, $this->exclude)) {
            return false;
        }

        return [] === $this->include || $this->matchesAny($table, $this->include);
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $table, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $table)) {
                return true;
            }
        }

        return false;
    }
}
