<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis\Naming;

use Juf\DoctrineImportBundle\Analysis\Naming\ClassName;
use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\Naming\InvalidIdentifierException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultNamingStrategy::class)]
#[CoversClass(ClassName::class)]
#[CoversClass(InvalidIdentifierException::class)]
final class DefaultNamingStrategyTest extends TestCase
{
    #[Test]
    public function it_singularizes_and_pascal_cases_table_names_into_class_names(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('BlogPost', $naming->classNameForTable('blog_posts')->value);
        self::assertSame('Category', $naming->classNameForTable('categories')->value);
        self::assertSame('User', $naming->classNameForTable('user')->value);
        self::assertSame([], $naming->classNameForTable('user')->adjustments);
    }

    #[Test]
    public function it_suffixes_class_names_that_are_reserved_php_words(): void
    {
        $className = (new DefaultNamingStrategy())->classNameForTable('list');

        self::assertSame('ListEntity', $className->value);
        self::assertSame(['naming.reserved_word'], $className->adjustments);
    }

    #[Test]
    public function it_prefixes_class_names_starting_with_a_digit(): void
    {
        $className = (new DefaultNamingStrategy())->classNameForTable('2fa_codes');

        self::assertSame('Table2faCode', $className->value);
        self::assertSame(['naming.leading_digit'], $className->adjustments);
    }

    #[Test]
    public function it_rejects_names_with_characters_outside_ascii_letters_digits_and_underscore(): void
    {
        $naming = new DefaultNamingStrategy();

        foreach (['order-lines', "user\n", 'café'] as $name) {
            try {
                $naming->classNameForTable($name);
                self::fail(\sprintf('"%s" should be rejected.', $name));
            } catch (InvalidIdentifierException $exception) {
                self::assertStringContainsString(\sprintf('"%s"', $name), $exception->getMessage());
            }
        }
    }

    #[Test]
    public function it_camel_cases_column_names_into_property_names(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('firstName', $naming->propertyNameForColumn('first_name'));
        self::assertSame('id', $naming->propertyNameForColumn('ID'));
        self::assertSame('createdAt', $naming->propertyNameForColumn('CreatedAt'));
    }

    #[Test]
    public function it_names_an_association_after_its_column_without_the_id_suffix_or_prefix(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('author', $naming->associationNameForColumn('author_id'));
        self::assertSame('author', $naming->associationNameForColumn('id_author'));
        self::assertSame('author', $naming->associationNameForColumn('authorId'));
        self::assertSame('createdBy', $naming->associationNameForColumn('created_by'));
    }

    #[Test]
    public function it_pluralizes_the_source_entity_for_an_inverse_collection(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('posts', $naming->collectionNameForEntity('Post'));
        self::assertSame('categories', $naming->collectionNameForEntity('Category'));
        self::assertSame('blogPosts', $naming->collectionNameForEntity('BlogPost'));
    }

    #[Test]
    public function it_strips_the_longest_matching_table_prefix_before_naming_the_class(): void
    {
        $naming = new DefaultNamingStrategy(tablePrefixes: ['wp_', 'wp_shop_']);

        self::assertSame('Post', $naming->classNameForTable('wp_posts')->value);
        self::assertSame('Order', $naming->classNameForTable('wp_shop_orders')->value);
        self::assertSame('Customer', $naming->classNameForTable('customers')->value);
    }

    #[Test]
    public function it_keeps_the_prefix_when_stripping_it_would_leave_an_empty_or_invalid_name(): void
    {
        $naming = new DefaultNamingStrategy(tablePrefixes: ['tbl_']);

        self::assertSame('Tbl', $naming->classNameForTable('tbl_')->value);
        self::assertSame('Tbl2024Archive', $naming->classNameForTable('tbl_2024_archives')->value);
    }

    #[Test]
    public function it_names_a_class_without_singularizing_to_resolve_collisions(): void
    {
        $naming = new DefaultNamingStrategy(tablePrefixes: ['wp_']);

        self::assertSame('Users', $naming->unsingularizedClassNameForTable('wp_users')->value);
        self::assertSame('ListEntity', $naming->unsingularizedClassNameForTable('list')->value);
    }
}
