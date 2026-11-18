<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\OneToOneSchemaFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A unique foreign key is generated as a one-to-one association that Doctrine writes and reads back.
 */
#[CoversNothing]
final class OneToOnePersistenceTest extends DatabaseTestCase
{
    use GeneratesEntities;

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_persists_and_reloads_a_one_to_one_association(string $platform): void
    {
        $connection = $this->connect($platform);
        OneToOneSchemaFixture::create($connection);
        [$namespace, $directory] = $this->generateEntities($connection, $platform);
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $directory);
        $authorClass = $namespace.'\Author';
        $profileClass = $namespace.'\AuthorProfile';
        if (!class_exists($authorClass) || !class_exists($profileClass)) {
            self::fail('The author and its profile were not generated.');
        }

        $authorMetadata = $entityManager->getClassMetadata($authorClass);
        $profileMetadata = $entityManager->getClassMetadata($profileClass);
        $author = $authorMetadata->newInstance();
        $authorMetadata->setFieldValue($author, 'name', 'Ada');
        $profile = $profileMetadata->newInstance();
        $profileMetadata->setFieldValue($profile, 'author', $author);
        $entityManager->persist($author);
        $entityManager->persist($profile);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find($profileClass, $profileMetadata->getFieldValue($profile, 'id'));
        $reloadedAuthor = null === $reloaded ? null : $profileMetadata->getFieldValue($reloaded, 'author');

        self::assertTrue($profileMetadata->getAssociationMapping('author')->isOneToOne());
        self::assertInstanceOf($authorClass, $reloadedAuthor);
        // The author is a lazy proxy: reading a property through reflection does not load it.
        $entityManager->initializeObject($reloadedAuthor);
        self::assertSame('Ada', $authorMetadata->getFieldValue($reloadedAuthor, 'name'));
    }
}
