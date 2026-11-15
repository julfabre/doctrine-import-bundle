<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Diagnostic;

/**
 * Collects the diagnostics of a single run. Create one per run: it is not a shared service.
 */
final class DiagnosticCollector
{
    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function __construct(private array $diagnostics = [])
    {
    }

    public function report(Severity $severity, string $code, string $message, ?string $table = null, ?string $column = null): void
    {
        $this->diagnostics[] = new Diagnostic($severity, $code, $message, $table, $column);
    }

    /**
     * @return list<Diagnostic>
     */
    public function all(): array
    {
        return $this->diagnostics;
    }
}
