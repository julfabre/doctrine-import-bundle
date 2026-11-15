<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Command;

use Juf\DoctrineImportBundle\Application\GenerationReport;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Writer\FileStatus;
use Juf\DoctrineImportBundle\Writer\WrittenFile;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints the files and the diagnostics of a generation, errors first.
 */
final readonly class GenerationReportPrinter
{
    private const SEVERITY_ORDER = [Severity::Error, Severity::Warning, Severity::Info];

    public function __construct(
        private string $projectDirectory,
    ) {
    }

    public function print(SymfonyStyle $io, GenerationReport $report, bool $dryRun): void
    {
        $io->section('Files');
        $io->table(['File', 'Status'], array_map(
            fn (WrittenFile $file): array => [$this->relativePath($file->path), $this->status($file->status, $dryRun)],
            $report->files,
        ));

        $diagnostics = $this->sorted($report->diagnostics);
        if ([] !== $diagnostics) {
            $io->section('Diagnostics');
            $io->table(['Severity', 'Code', 'Table', 'Column', 'Message'], array_map(
                static fn (Diagnostic $diagnostic): array => [$diagnostic->severity->value, $diagnostic->code, $diagnostic->table ?? '', $diagnostic->column ?? '', $diagnostic->message],
                $diagnostics,
            ));
        }

        if ($dryRun) {
            $io->note('Dry run: no file was written.');
        }

        $summary = \sprintf('%d error(s), %d warning(s).', $this->count($diagnostics, Severity::Error), $this->count($diagnostics, Severity::Warning));
        $report->hasErrors()
            ? $io->error('Generation incomplete: '.$summary.' Skipped tables and columns are listed above.')
            : $io->success('Generation complete: '.$summary);
    }

    private function relativePath(string $path): string
    {
        $prefix = rtrim($this->projectDirectory, '/').'/';

        return str_starts_with($path, $prefix) ? substr($path, \strlen($prefix)) : $path;
    }

    private function status(FileStatus $status, bool $dryRun): string
    {
        return match ($status) {
            FileStatus::Created => $dryRun ? 'to create' : 'created',
            FileStatus::Overwritten => $dryRun ? 'to overwrite' : 'overwritten',
            FileStatus::Skipped => 'skipped (exists, use --force)',
        };
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return list<Diagnostic>
     */
    private function sorted(array $diagnostics): array
    {
        usort($diagnostics, static fn (Diagnostic $left, Diagnostic $right): int => array_search($left->severity, self::SEVERITY_ORDER, true) <=> array_search($right->severity, self::SEVERITY_ORDER, true));

        return $diagnostics;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    private function count(array $diagnostics, Severity $severity): int
    {
        return \count(array_filter($diagnostics, static fn (Diagnostic $diagnostic): bool => $severity === $diagnostic->severity));
    }
}
