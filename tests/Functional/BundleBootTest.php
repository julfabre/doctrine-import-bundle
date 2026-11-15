<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Functional;

use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(JufDoctrineImportBundle::class)]
final class BundleBootTest extends KernelTestCase
{
    #[Test]
    public function it_boots_a_kernel_with_the_bundle_and_doctrine(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('JufDoctrineImportBundle', $kernel->getBundles());
        self::assertTrue(self::getContainer()->has('doctrine'));
    }
}
