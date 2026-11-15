<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Rendering;

use Juf\DoctrineImportBundle\Rendering\CodeGenerator;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Compares the generated code with reviewed reference files. Set UPDATE_SNAPSHOTS=1 to rewrite them,
 * then review the diff before committing.
 */
#[CoversNothing]
final class SnapshotTest extends TestCase
{
    private const SNAPSHOT_DIR = __DIR__.'/../../Fixtures/expected/blog';

    #[Test]
    public function it_generates_the_reviewed_code_for_the_blog_schema(): void
    {
        $files = (new CodeGenerator())->generate(BlogSchema::model(), new RenderingOptions());
        $update = '1' === getenv('UPDATE_SNAPSHOTS');
        $missing = [];

        foreach ($files as $file) {
            $path = $this->snapshotPath($file);
            if ($update || !is_file($path)) {
                if (!is_dir(\dirname($path))) {
                    mkdir(\dirname($path), 0o777, true);
                }

                file_put_contents($path, $file->contents);
                $missing[] = $path;

                continue;
            }

            self::assertSame(file_get_contents($path), $file->contents, \sprintf('The code generated for "%s" changed.', $file->className));
        }

        if ([] !== $missing) {
            self::markTestIncomplete(\sprintf('Snapshots written, review them: %s', implode(', ', $missing)));
        }
    }

    private function snapshotPath(GeneratedFile $file): string
    {
        return self::SNAPSHOT_DIR.'/'.str_replace(['App\\', '\\'], ['', '/'], $file->className).'.php';
    }
}
