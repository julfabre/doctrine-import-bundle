<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Command;

use Juf\DoctrineImportBundle\Application\GenerationReport;
use Juf\DoctrineImportBundle\Command\GenerationReportPrinter;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Writer\FileStatus;
use Juf\DoctrineImportBundle\Writer\WrittenFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(GenerationReportPrinter::class)]
#[UsesClass(GenerationReport::class)]
#[UsesClass(WrittenFile::class)]
#[UsesClass(Diagnostic::class)]
final class GenerationReportPrinterTest extends TestCase
{
    #[Test]
    public function it_shows_paths_relative_to_the_project_directory(): void
    {
        $output = new BufferedOutput();
        $report = new GenerationReport([
            new WrittenFile('App\Entity\Post', '/app/src/Entity/Post.php', FileStatus::Created),
            new WrittenFile('Shared\Entity\Tag', '/shared/Entity/Tag.php', FileStatus::Created),
        ], []);

        (new GenerationReportPrinter('/app/'))->print(new SymfonyStyle(new ArrayInput([]), $output), $report, false);

        $display = $output->fetch();
        self::assertMatchesRegularExpression('#\s src/Entity/Post\.php\s+created#', $display);
        self::assertMatchesRegularExpression('#\s /shared/Entity/Tag\.php\s+created#', $display);
    }

    #[Test]
    public function it_titles_the_files_and_diagnostics_sections(): void
    {
        $output = new BufferedOutput();
        $report = new GenerationReport(
            [new WrittenFile('App\Entity\Post', '/app/src/Entity/Post.php', FileStatus::Created)],
            [new Diagnostic(Severity::Info, 'table.composite_primary_key', 'Composite.', 'order_line')],
        );

        (new GenerationReportPrinter('/app'))->print(new SymfonyStyle(new ArrayInput([]), $output), $report, false);

        $display = $output->fetch();
        self::assertMatchesRegularExpression('#Files\n-----\n#', $display);
        self::assertMatchesRegularExpression('#Diagnostics\n-----------\n#', $display);
    }

    #[Test]
    public function it_shows_no_diagnostics_section_when_there_is_nothing_to_report(): void
    {
        $output = new BufferedOutput();
        $report = new GenerationReport([new WrittenFile('App\Entity\Post', '/app/src/Entity/Post.php', FileStatus::Created)], []);

        (new GenerationReportPrinter('/app'))->print(new SymfonyStyle(new ArrayInput([]), $output), $report, false);

        $display = $output->fetch();
        self::assertStringNotContainsString('Diagnostics', $display);
        self::assertStringNotContainsString('Severity', $display);
        self::assertStringContainsString('Generation complete: 0 error(s), 0 warning(s).', $display);
    }

    #[Test]
    public function it_lists_errors_first_and_reports_an_incomplete_generation(): void
    {
        $output = new BufferedOutput();
        $report = new GenerationReport([new WrittenFile('App\\Entity\\Post', '/app/src/Entity/Post.php', FileStatus::Skipped)], [
            new Diagnostic(Severity::Info, 'table.composite_primary_key', 'Composite.', 'order_line'),
            new Diagnostic(Severity::Warning, 'column.default_expression', 'Expression.', 'post', 'created_at'),
            new Diagnostic(Severity::Error, 'table.no_primary_key', 'No primary key.', 'audit_log'),
        ]);

        (new GenerationReportPrinter('/app'))->print(new SymfonyStyle(new ArrayInput([]), $output), $report, true);

        $display = $output->fetch();
        self::assertMatchesRegularExpression('#File\s+Status#', $display);
        self::assertMatchesRegularExpression('#Severity\s+Code\s+Table\s+Column\s+Message#', $display);
        self::assertMatchesRegularExpression('#error\s+table\.no_primary_key\s+audit_log\s+No primary key\.#', $display);
        self::assertMatchesRegularExpression('#warning\s+column\.default_expression\s+post\s+created_at\s+Expression\.#', $display);
        self::assertLessThan(strpos($display, 'column.default_expression'), strpos($display, 'table.no_primary_key'));
        self::assertLessThan(strpos($display, 'table.composite_primary_key'), strpos($display, 'column.default_expression'));
        self::assertMatchesRegularExpression('#src/Entity/Post\.php\s+skipped \(exists, use --force\)#', $display);
        self::assertStringContainsString('Dry run: no file was written.', $display);
        self::assertStringContainsString('Generation incomplete: 1 error(s), 1 warning(s). Skipped tables and columns are listed above.', preg_replace('/\s+/', ' ', $display) ?? '');
    }
}
