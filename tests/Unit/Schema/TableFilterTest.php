<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Schema;

use Juf\DoctrineImportBundle\Schema\TableFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableFilter::class)]
final class TableFilterTest extends TestCase
{
    #[Test]
    public function it_accepts_every_table_without_pattern(): void
    {
        $filter = new TableFilter();

        self::assertTrue($filter->accepts('user'));
        self::assertTrue($filter->accepts('blog_post'));
    }

    #[Test]
    public function it_accepts_only_tables_matching_an_include_pattern(): void
    {
        $filter = new TableFilter(include: ['blog_*']);

        self::assertTrue($filter->accepts('blog_post'));
        self::assertFalse($filter->accepts('user'));
    }

    #[Test]
    public function it_accepts_tables_matching_any_of_several_include_patterns(): void
    {
        $filter = new TableFilter(include: ['blog_*', 'user']);

        self::assertTrue($filter->accepts('blog_post'));
        self::assertTrue($filter->accepts('user'));
        self::assertFalse($filter->accepts('invoice'));
    }

    #[Test]
    public function it_rejects_tables_matching_an_exclude_pattern_even_when_included(): void
    {
        $filter = new TableFilter(include: ['blog_*'], exclude: ['*_archive']);

        self::assertTrue($filter->accepts('blog_post'));
        self::assertFalse($filter->accepts('blog_post_archive'));
    }

    #[Test]
    public function it_matches_patterns_case_sensitively(): void
    {
        $filter = new TableFilter(include: ['Blog_*']);

        self::assertFalse($filter->accepts('blog_post'));
        self::assertTrue($filter->accepts('Blog_post'));
    }
}
