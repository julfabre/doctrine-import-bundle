<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Rendering;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaValidator;
use Juf\DoctrineImportBundle\Rendering\CodeGenerator;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DoctrineMappingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/juf_doctrine_import/'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/Entity', 0o777, true);
        mkdir($this->directory.'/Repository', 0o777, true);
        mkdir($this->directory.'/Proxy', 0o777, true);
    }

    #[Test]
    public function it_generates_code_that_compiles_on_the_running_php_version(): void
    {
        foreach ($this->writeGeneratedFiles('Lint') as $path) {
            exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg($path)), $output, $exitCode);

            self::assertSame(0, $exitCode, implode("\n", $output));
        }
    }

    #[Test]
    public function it_generates_entities_that_doctrine_loads_without_mapping_error(): void
    {
        foreach ($this->writeGeneratedFiles('Mapping') as $path) {
            require_once $path;
        }

        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([$this->directory.'/Entity']));
        $configuration->setProxyDir($this->directory.'/Proxy');
        $configuration->setProxyNamespace('JufGeneratedProxies');
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);

        self::assertCount(4, $entityManager->getMetadataFactory()->getAllMetadata());
        self::assertSame([], (new SchemaValidator($entityManager))->validateMapping());
    }

    /**
     * @return list<string> paths of the written files
     */
    private function writeGeneratedFiles(string $suffix): array
    {
        // A namespace per run: generated classes cannot be declared twice in the same process.
        $namespace = 'JufGenerated\\'.$suffix.bin2hex(random_bytes(4));
        $files = (new CodeGenerator())->generate(BlogSchema::model(), new RenderingOptions($namespace.'\\Entity', $namespace.'\\Repository'));

        return array_map(function (GeneratedFile $file) use ($namespace): string {
            $path = $this->directory.'/'.str_replace([$namespace.'\\', '\\'], ['', '/'], $file->className).'.php';
            file_put_contents($path, $file->contents);

            return $path;
        }, $files);
    }
}
