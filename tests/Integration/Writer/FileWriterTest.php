<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Writer;

use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Writer\FileStatus;
use Juf\DoctrineImportBundle\Writer\FileWriter;
use Juf\DoctrineImportBundle\Writer\OutputLocation;
use Juf\DoctrineImportBundle\Writer\UnmappedClassException;
use Juf\DoctrineImportBundle\Writer\WriteFailedException;
use Juf\DoctrineImportBundle\Writer\WriteOptions;
use Juf\DoctrineImportBundle\Writer\WrittenFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileWriter::class)]
#[CoversClass(WrittenFile::class)]
#[CoversClass(OutputLocation::class)]
#[CoversClass(WriteOptions::class)]
#[CoversClass(UnmappedClassException::class)]
#[CoversClass(WriteFailedException::class)]
#[UsesClass(GeneratedFile::class)]
final class FileWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/juf_doctrine_import/writer_'.bin2hex(random_bytes(6));
    }

    #[Test]
    public function it_writes_each_file_in_the_directory_of_its_namespace(): void
    {
        $written = $this->write([$this->post(), $this->postRepository()], new WriteOptions(dryRun: false, force: false));

        self::assertSame("<?php // Post\n", file_get_contents($this->directory.'/src/Entity/Post.php'));
        self::assertSame("<?php // PostRepository\n", file_get_contents($this->directory.'/src/Repository/PostRepository.php'));
        self::assertEquals([
            new WrittenFile('App\Entity\Post', $this->directory.'/src/Entity/Post.php', FileStatus::Created),
            new WrittenFile('App\Repository\PostRepository', $this->directory.'/src/Repository/PostRepository.php', FileStatus::Created),
        ], $written);
    }

    #[Test]
    public function it_creates_missing_directories(): void
    {
        $this->write([new GeneratedFile('App\Entity\Blog\Post', "<?php\n")], new WriteOptions(dryRun: false, force: false));

        self::assertFileExists($this->directory.'/src/Entity/Blog/Post.php');
    }

    #[Test]
    public function it_skips_existing_files_without_force(): void
    {
        $this->givenExistingFile('src/Entity/Post.php', "<?php // edited by hand\n");

        $written = $this->write([$this->post()], new WriteOptions(dryRun: false, force: false));

        self::assertSame("<?php // edited by hand\n", file_get_contents($this->directory.'/src/Entity/Post.php'));
        self::assertSame(FileStatus::Skipped, $written[0]->status ?? null);
    }

    #[Test]
    public function it_overwrites_existing_files_with_force(): void
    {
        $this->givenExistingFile('src/Entity/Post.php', "<?php // edited by hand\n");

        $written = $this->write([$this->post()], new WriteOptions(dryRun: false, force: true));

        self::assertSame("<?php // Post\n", file_get_contents($this->directory.'/src/Entity/Post.php'));
        self::assertSame(FileStatus::Overwritten, $written[0]->status ?? null);
    }

    #[Test]
    public function it_writes_nothing_in_dry_run_and_reports_what_would_happen(): void
    {
        $this->givenExistingFile('src/Entity/Post.php', "<?php // edited by hand\n");

        $skipped = $this->write([$this->post(), $this->postRepository()], new WriteOptions(dryRun: true, force: false));
        $forced = $this->write([$this->post()], new WriteOptions(dryRun: true, force: true));

        self::assertSame("<?php // edited by hand\n", file_get_contents($this->directory.'/src/Entity/Post.php'));
        self::assertDirectoryDoesNotExist($this->directory.'/src/Repository');
        self::assertSame(
            [FileStatus::Skipped, FileStatus::Created, FileStatus::Overwritten],
            array_map(static fn (WrittenFile $file): FileStatus => $file->status, [...$skipped, ...$forced]),
        );
    }

    #[Test]
    public function it_fails_when_the_directory_cannot_be_created(): void
    {
        mkdir($this->directory, 0o777, true);
        chmod($this->directory, 0o555);

        try {
            $this->expectException(WriteFailedException::class);
            $this->expectExceptionMessage(\sprintf('Cannot write "%s/src/Entity/Post.php": check that its directory is writable.', $this->directory));

            $this->write([self::post()], new WriteOptions(dryRun: false, force: false));
        } finally {
            chmod($this->directory, 0o777);
        }
    }

    #[Test]
    public function it_fails_when_an_existing_file_cannot_be_overwritten(): void
    {
        $this->givenExistingFile('src/Entity/Post.php', "<?php // read-only\n");
        chmod($this->directory.'/src/Entity/Post.php', 0o444);

        $this->expectException(WriteFailedException::class);

        $this->write([self::post()], new WriteOptions(dryRun: false, force: true));
    }

    #[Test]
    public function it_rejects_a_class_outside_the_configured_namespaces(): void
    {
        $this->expectException(UnmappedClassException::class);
        $this->expectExceptionMessage('Class "Other\Thing" is not in any configured namespace ("App\Entity", "App\Repository").');

        $this->write([new GeneratedFile('Other\Thing', "<?php\n")], new WriteOptions(dryRun: false, force: false));
    }

    /**
     * @param list<GeneratedFile> $files
     *
     * @return list<WrittenFile>
     */
    private function write(array $files, WriteOptions $options): array
    {
        return (new FileWriter())->write($files, [
            new OutputLocation('App\Entity', $this->directory.'/src/Entity'),
            new OutputLocation('App\Repository', $this->directory.'/src/Repository'),
        ], $options);
    }

    private function givenExistingFile(string $relativePath, string $contents): void
    {
        mkdir(\dirname($this->directory.'/'.$relativePath), 0o777, true);
        file_put_contents($this->directory.'/'.$relativePath, $contents);
    }

    private function post(): GeneratedFile
    {
        return new GeneratedFile('App\Entity\Post', "<?php // Post\n");
    }

    private function postRepository(): GeneratedFile
    {
        return new GeneratedFile('App\Repository\PostRepository', "<?php // PostRepository\n");
    }
}
