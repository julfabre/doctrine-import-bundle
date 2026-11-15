<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit;

use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass(JufDoctrineImportBundle::class)]
final class JufDoctrineImportBundleTest extends TestCase
{
    #[Test]
    public function it_exposes_the_juf_doctrine_import_configuration_alias(): void
    {
        $bundle = new JufDoctrineImportBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertSame('juf_doctrine_import', $extension->getAlias());
    }
}
