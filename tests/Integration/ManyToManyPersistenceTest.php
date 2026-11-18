<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\Common\Collections\Collection;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\DatabaseTestCase;
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\ManyToManySchemaFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A pure join table is written and read through the generated ManyToMany association.
 */
#[CoversNothing]
final class ManyToManyPersistenceTest extends DatabaseTestCase
{
    use GeneratesEntities;

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_persists_a_post_with_two_tags_and_reads_it_back_from_a_tag(string $platform): void
    {
        $connection = $this->connect($platform);
        ManyToManySchemaFixture::create($connection);
        [$namespace, $directory] = $this->generateEntities($connection, $platform);
        $entityManager = DoctrineTestConfiguration::entityManager($connection, $directory);
        $postClass = $namespace.'\Post';
        $tagClass = $namespace.'\Tag';
        if (!class_exists($postClass) || !class_exists($tagClass)) {
            self::fail('The post and tag entities were not generated.');
        }

        $postMetadata = $entityManager->getClassMetadata($postClass);
        $tagMetadata = $entityManager->getClassMetadata($tagClass);
        self::assertTrue($postMetadata->hasAssociation('tags'), 'Post has no ManyToMany association to its tags.');
        // The constructor initializes the collections: ClassMetadata::newInstance() would skip it.
        $post = new $postClass();
        $postMetadata->setFieldValue($post, 'title', 'Hello');
        $tags = $postMetadata->getFieldValue($post, 'tags');
        self::assertInstanceOf(Collection::class, $tags);
        $php = new $tagClass();
        $tagMetadata->setFieldValue($php, 'label', 'php');
        $doctrine = new $tagClass();
        $tagMetadata->setFieldValue($doctrine, 'label', 'doctrine');
        $tags->add($php);
        $tags->add($doctrine);

        $entityManager->persist($php);
        $entityManager->persist($doctrine);
        $entityManager->persist($post);
        $entityManager->flush();

        $tagId = $tagMetadata->getFieldValue($doctrine, 'id');
        $entityManager->clear();

        // The generated repositories need the Symfony container: the tag is read back by its identifier.
        $reloadedTag = $entityManager->find($tagClass, $tagId);
        $posts = null === $reloadedTag ? null : $tagMetadata->getFieldValue($reloadedTag, 'posts');

        self::assertEquals(2, $connection->fetchOne('SELECT COUNT(*) FROM post_tag'));
        self::assertInstanceOf(Collection::class, $posts);
        self::assertCount(1, $posts);
    }
}
