<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Analysis\ClassNameResolver;
use Juf\DoctrineImportBundle\Analysis\EntityAnalyzer;
use Juf\DoctrineImportBundle\Analysis\EnumAnalyzer;
use Juf\DoctrineImportBundle\Analysis\FieldAnalyzer;
use Juf\DoctrineImportBundle\Analysis\MappedType;
use Juf\DoctrineImportBundle\Analysis\Naming\ClassName;
use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\Naming\InvalidIdentifierException;
use Juf\DoctrineImportBundle\Analysis\TypeMapper;
use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\EnumCaseDefinition;
use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityAnalyzer::class)]
#[CoversClass(EntityDefinition::class)]
#[CoversClass(FieldDefinition::class)]
#[CoversClass(ColumnOptions::class)]
#[CoversClass(IndexDefinition::class)]
#[CoversClass(GenerationModel::class)]
#[CoversClass(Diagnostic::class)]
#[CoversClass(DiagnosticCollector::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(ClassName::class)]
#[UsesClass(InvalidIdentifierException::class)]
#[UsesClass(TypeMapper::class)]
#[CoversClass(FieldAnalyzer::class)]
#[CoversClass(ClassNameResolver::class)]
#[UsesClass(MappedType::class)]
#[UsesClass(SchemaMetadata::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(ForeignKeyMetadata::class)]
#[UsesClass(IndexMetadata::class)]
#[CoversClass(EnumDefinition::class)]
#[CoversClass(EnumCaseDefinition::class)]
#[CoversClass(EnumAnalyzer::class)]
final class EntityAnalyzerTest extends TestCase
{
    use DefinitionAssertions;

    #[Test]
    public function it_creates_one_entity_per_table_with_its_class_table_and_repository_names(): void
    {
        $model = $this->analyze(SchemaBuilder::table('blog_posts', [SchemaBuilder::id()], comment: 'Articles'));

        $entity = self::entity($model, 'BlogPost');
        self::assertSame('blog_posts', $entity->tableName);
        self::assertSame('BlogPostRepository', $entity->repositoryClassName);
        self::assertSame('Articles', $entity->comment);
    }

    #[Test]
    public function it_creates_one_field_per_column_with_property_column_types_and_nullability(): void
    {
        $model = $this->analyze(SchemaBuilder::table('user', [
            SchemaBuilder::id(),
            SchemaBuilder::column('first_name', 'string', length: 100),
            SchemaBuilder::column('born_on', 'date', nullable: true),
        ]));

        $user = self::entity($model, 'User');
        self::assertSame(['id', 'firstName', 'bornOn'], self::propertyNames($user));
        $bornOn = self::field($user, 'bornOn');
        self::assertSame(['born_on', 'date_immutable', '\DateTimeImmutable', true], [$bornOn->columnName, $bornOn->doctrineType, $bornOn->phpType, $bornOn->nullable]);
    }

    #[Test]
    public function it_keeps_length_precision_scale_unsigned_fixed_and_comment(): void
    {
        $model = $this->analyze(SchemaBuilder::table('product', [
            SchemaBuilder::id(),
            SchemaBuilder::column('code', 'string', comment: 'SKU', length: 12, fixed: true),
            SchemaBuilder::column('price', 'decimal', precision: 10, scale: 2),
            SchemaBuilder::column('stock', 'integer', unsigned: true),
        ]));

        $product = self::entity($model, 'Product');
        self::assertSame([12, null, 0, false, true, 'SKU'], $this->options($product, 'code'));
        self::assertSame([null, 10, 2, false, false, null], $this->options($product, 'price'));
        self::assertSame([null, null, 0, true, false, null], $this->options($product, 'stock'));
    }

    #[Test]
    public function it_generates_an_identity_id_for_an_auto_increment_primary_key(): void
    {
        $model = $this->analyze(SchemaBuilder::table('user', [SchemaBuilder::id(), SchemaBuilder::column('age')]));

        $user = self::entity($model, 'User');
        self::assertSame(IdGeneration::Identity, self::field($user, 'id')->idGeneration);
        self::assertTrue(self::field($user, 'id')->isId());
        self::assertFalse(self::field($user, 'age')->isId());
    }

    #[Test]
    public function it_keeps_an_assigned_id_for_a_primary_key_without_auto_increment(): void
    {
        $model = $this->analyze(SchemaBuilder::table('country', [SchemaBuilder::column('code', 'string', length: 2)], primaryKey: ['code']));

        self::assertSame(IdGeneration::None, self::field(self::entity($model, 'Country'), 'code')->idGeneration);
    }

    #[Test]
    public function it_creates_one_id_field_per_composite_primary_key_column_with_an_info(): void
    {
        $model = $this->analyze(SchemaBuilder::table('order_line', [
            SchemaBuilder::column('order_id'),
            SchemaBuilder::column('product_id'),
            SchemaBuilder::column('quantity'),
        ], primaryKey: ['order_id', 'product_id']));

        $orderLine = self::entity($model, 'OrderLine');
        self::assertSame(IdGeneration::None, self::field($orderLine, 'orderId')->idGeneration);
        self::assertSame(IdGeneration::None, self::field($orderLine, 'productId')->idGeneration);
        self::assertFalse(self::field($orderLine, 'quantity')->isId());
        self::assertSame([['info', 'table.composite_primary_key', 'order_line', null]], self::diagnostics($model));
    }

    #[Test]
    public function it_skips_a_table_without_primary_key_with_an_error(): void
    {
        $model = $this->analyze(SchemaBuilder::table('audit_log', [SchemaBuilder::column('message', 'text')], primaryKey: []), SchemaBuilder::table('user', [SchemaBuilder::id()]));

        self::assertSame(['User'], self::classNames($model));
        self::assertSame([['error', 'table.no_primary_key', 'audit_log', null]], self::diagnostics($model));
    }

    #[Test]
    public function it_keeps_literal_and_standard_date_defaults_and_reports_other_expressions(): void
    {
        $model = $this->analyze(SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('status', 'string', default: 'draft'),
            SchemaBuilder::column('created_at', 'datetime', default: 'CURRENT_TIMESTAMP'),
            SchemaBuilder::column('updated_at', 'datetime', default: 'now()'),
            SchemaBuilder::column('reviewed_at', 'datetime', default: 'current_timestamp()'),
        ]));

        $post = self::entity($model, 'Post');
        self::assertSame('draft', self::field($post, 'status')->options->default);
        self::assertSame('CURRENT_TIMESTAMP', self::field($post, 'createdAt')->options->default);
        self::assertNull(self::field($post, 'updatedAt')->options->default);
        self::assertSame('CURRENT_TIMESTAMP', self::field($post, 'reviewedAt')->options->default);
        self::assertSame([['warning', 'column.default_expression', 'post', 'updated_at']], self::diagnostics($model));
    }

    #[Test]
    public function it_skips_a_column_of_unsupported_type_with_an_error(): void
    {
        $model = $this->analyze(SchemaBuilder::table('place', [SchemaBuilder::id(), SchemaBuilder::column('location', 'geometry')]));

        self::assertSame(['id'], self::propertyNames(self::entity($model, 'Place')));
        self::assertSame([['error', 'column.unsupported_type', 'place', 'location']], self::diagnostics($model));
    }

    #[Test]
    public function it_skips_a_table_with_an_invalid_name_with_an_error(): void
    {
        $model = $this->analyze(SchemaBuilder::table('list', [SchemaBuilder::id()]), SchemaBuilder::table('order-lines', [SchemaBuilder::id()]));

        self::assertSame(['ListEntity'], self::classNames($model));
        self::assertSame([
            ['warning', 'naming.reserved_word', 'list', null],
            ['error', 'naming.invalid_identifier', 'order-lines', null],
        ], self::diagnostics($model));
        self::assertSame(
            'The name "order-lines" contains characters other than ASCII letters, digits and underscores. The table is skipped.',
            ($model->diagnostics[1] ?? null)?->message,
        );
    }

    #[Test]
    public function it_keeps_unsingularized_names_when_two_tables_map_to_the_same_class(): void
    {
        $model = $this->analyze(SchemaBuilder::table('user', [SchemaBuilder::id()]), SchemaBuilder::table('users', [SchemaBuilder::id()]));

        self::assertSame('user', self::entity($model, 'User')->tableName);
        self::assertSame('users', self::entity($model, 'Users')->tableName);
        self::assertSame([['warning', 'naming.class_collision', 'users', null]], self::diagnostics($model));
    }

    #[Test]
    public function it_keeps_indexes_and_unique_constraints(): void
    {
        $model = $this->analyze(SchemaBuilder::table('user', [SchemaBuilder::id(), SchemaBuilder::column('email', 'string')], indexes: [
            new IndexMetadata('uniq_user_email', ['email'], unique: true),
            new IndexMetadata('idx_user_email_id', ['email', 'id'], unique: false),
            new IndexMetadata('idx_user_bio', ['email'], unique: false, flags: ['fulltext']),
        ]));

        self::assertEquals([
            new IndexDefinition('uniq_user_email', ['email'], unique: true),
            new IndexDefinition('idx_user_email_id', ['email', 'id'], unique: false),
            new IndexDefinition('idx_user_bio', ['email'], unique: false, flags: ['fulltext']),
        ], self::entity($model, 'User')->indexes);
    }

    #[Test]
    public function it_maps_the_entity_to_the_full_table_name_when_a_prefix_is_stripped(): void
    {
        $model = (new EntityAnalyzer(new DefaultNamingStrategy(tablePrefixes: ['wp_'])))
            ->analyze(SchemaBuilder::schema([SchemaBuilder::table('wp_posts', [SchemaBuilder::id()])]));

        self::assertSame('wp_posts', self::entity($model, 'Post')->tableName);
    }

    #[Test]
    public function it_skips_a_table_whose_class_name_collides_even_unsingularized_with_an_error(): void
    {
        $model = (new EntityAnalyzer(new DefaultNamingStrategy(tablePrefixes: ['wp_'])))->analyze(SchemaBuilder::schema([
            SchemaBuilder::table('user', [SchemaBuilder::id()]),
            SchemaBuilder::table('users', [SchemaBuilder::id()]),
            SchemaBuilder::table('wp_users', [SchemaBuilder::id()]),
        ]));

        self::assertSame(['User', 'Users'], self::classNames($model));
        self::assertSame([
            ['warning', 'naming.class_collision', 'users', null],
            ['error', 'naming.class_collision', 'wp_users', null],
        ], self::diagnostics($model));
    }

    #[Test]
    public function it_reports_an_info_when_a_column_type_is_mapped_approximately(): void
    {
        $model = self::analyze(SchemaBuilder::table('ticket', [SchemaBuilder::id(), SchemaBuilder::column('attachment', 'blob')]));

        self::assertSame('mixed', self::field(self::entity($model, 'Ticket'), 'attachment')->phpType);
        self::assertSame([['info', 'column.binary_as_mixed', 'ticket', 'attachment']], self::diagnostics($model));
    }

    #[Test]
    public function it_generates_a_database_generated_id_for_a_primary_key_defaulting_to_nextval(): void
    {
        $model = $this->analyze(SchemaBuilder::table('product', [SchemaBuilder::column('id', default: "nextval('product_id_seq'::regclass)")]));

        $id = self::field(self::entity($model, 'Product'), 'id');
        self::assertSame(IdGeneration::Sequence, $id->idGeneration);
        self::assertSame('product_id_seq', $id->sequenceName);
        self::assertSame("nextval('product_id_seq'::regclass)", $id->options->default);
    }

    #[Test]
    public function it_does_not_report_the_nextval_default_of_a_serial_primary_key(): void
    {
        $model = $this->analyze(SchemaBuilder::table('product', [SchemaBuilder::column('id', default: "nextval('product_id_seq'::regclass)")]));

        self::assertSame([], self::diagnostics($model));
    }

    #[Test]
    public function it_still_reports_a_nextval_default_on_a_column_outside_the_primary_key(): void
    {
        $model = $this->analyze(SchemaBuilder::table('ticket', [
            SchemaBuilder::id(),
            SchemaBuilder::column('number', default: "nextval('ticket_number_seq'::regclass)"),
        ]));

        $number = self::field(self::entity($model, 'Ticket'), 'number');
        self::assertNull($number->idGeneration);
        self::assertNull($number->sequenceName);
        self::assertNull($number->options->default);
        self::assertSame([['warning', 'column.default_expression', 'ticket', 'number']], self::diagnostics($model));
    }

    #[Test]
    public function it_keeps_assigned_ids_for_a_nextval_default_in_a_composite_primary_key(): void
    {
        $model = $this->analyze(SchemaBuilder::table('order_line', [
            SchemaBuilder::column('order_id', default: "nextval('order_line_order_id_seq'::regclass)"),
            SchemaBuilder::column('product_id'),
        ], primaryKey: ['order_id', 'product_id']));

        $orderId = self::field(self::entity($model, 'OrderLine'), 'orderId');
        self::assertSame(IdGeneration::None, $orderId->idGeneration);
        self::assertNull($orderId->sequenceName);
    }

    #[Test]
    public function it_keeps_an_assigned_id_when_the_sequence_name_is_qualified_or_quoted(): void
    {
        $model = $this->analyze(
            SchemaBuilder::table('product', [SchemaBuilder::column('id', default: "nextval('public.product_id_seq'::regclass)")]),
            SchemaBuilder::table('category', [SchemaBuilder::column('id', default: "nextval('\"Category_id_seq\"'::regclass)")]),
        );

        self::assertSame(IdGeneration::None, self::field(self::entity($model, 'Product'), 'id')->idGeneration);
        self::assertSame(IdGeneration::None, self::field(self::entity($model, 'Category'), 'id')->idGeneration);
    }

    #[Test]
    public function it_marks_the_field_of_a_generated_column_as_generated(): void
    {
        $model = $this->analyze(SchemaBuilder::table('product', [
            SchemaBuilder::id(),
            SchemaBuilder::column('price'),
            SchemaBuilder::column('price_doubled', generated: true),
        ]));

        $product = self::entity($model, 'Product');
        self::assertTrue(self::field($product, 'priceDoubled')->generated);
        self::assertFalse(self::field($product, 'price')->generated);
    }

    #[Test]
    public function it_reports_that_a_generated_column_is_mapped_read_only(): void
    {
        $model = $this->analyze(SchemaBuilder::table('product', [SchemaBuilder::id(), SchemaBuilder::column('price_doubled', generated: true)]));

        self::assertSame([['info', 'column.generated', 'product', 'price_doubled']], self::diagnostics($model));
        self::assertSame(
            'Column "product.price_doubled" is generated by the database: it is mapped read-only, without its expression.',
            $model->diagnostics[0]->message ?? null,
        );
    }

    #[Test]
    public function it_maps_an_enum_column_to_a_backed_enum(): void
    {
        $model = $this->analyze(SchemaBuilder::table('film', [SchemaBuilder::id(), SchemaBuilder::column('rating', 'enum', nullable: true, values: ['G', 'PG-13'])]));

        $rating = self::field(self::entity($model, 'Film'), 'rating');
        self::assertSame('enum', $rating->doctrineType);
        self::assertEquals(new EnumDefinition('FilmRating', [new EnumCaseDefinition('G', 'G'), new EnumCaseDefinition('Pg13', 'PG-13')]), $rating->enum);
        self::assertSame([], self::diagnostics($model));
    }

    #[Test]
    public function it_keeps_the_default_value_of_an_enum_column(): void
    {
        $model = $this->analyze(SchemaBuilder::table('film', [SchemaBuilder::id(), SchemaBuilder::column('rating', 'enum', default: 'G', values: ['G', 'PG-13'])]));

        self::assertSame('G', self::field(self::entity($model, 'Film'), 'rating')->options->default);
    }

    #[Test]
    public function it_generates_one_enum_per_column_even_when_values_are_identical(): void
    {
        $model = $this->analyze(
            SchemaBuilder::table('invoice', [SchemaBuilder::id(), SchemaBuilder::column('status', 'enum', values: ['open', 'closed'])]),
            SchemaBuilder::table('ticket', [SchemaBuilder::id(), SchemaBuilder::column('status', 'enum', values: ['open', 'closed'])]),
        );

        self::assertSame('InvoiceStatus', self::field(self::entity($model, 'Invoice'), 'status')->enum?->className);
        self::assertSame('TicketStatus', self::field(self::entity($model, 'Ticket'), 'status')->enum?->className);
    }

    #[Test]
    public function it_falls_back_to_a_string_with_a_warning_when_two_values_give_the_same_case_name(): void
    {
        $model = $this->analyze(SchemaBuilder::table('ticket', [SchemaBuilder::id(), SchemaBuilder::column('kind', 'enum', values: ['a-b', 'a_b'])]));

        $kind = self::field(self::entity($model, 'Ticket'), 'kind');
        self::assertSame(['string', 'string', null], [$kind->doctrineType, $kind->phpType, $kind->enum]);
        self::assertSame([['warning', 'column.enum_as_string', 'ticket', 'kind']], self::diagnostics($model));
        self::assertSame(
            'Column "ticket.kind" is mapped as a string, without its allowed values: the values "a-b" and "a_b" give the same enum case "AB".',
            $model->diagnostics[0]->message ?? null,
        );
    }

    #[Test]
    public function it_falls_back_to_a_string_with_a_warning_for_an_empty_or_reserved_value(): void
    {
        $model = $this->analyze(SchemaBuilder::table('ticket', [
            SchemaBuilder::id(),
            SchemaBuilder::column('kind', 'enum', values: ['', 'bug']),
            SchemaBuilder::column('scope', 'enum', values: ['class', 'method']),
        ]));

        $ticket = self::entity($model, 'Ticket');
        self::assertNull(self::field($ticket, 'kind')->enum);
        self::assertNull(self::field($ticket, 'scope')->enum);
        self::assertSame([['warning', 'column.enum_as_string', 'ticket', 'kind'], ['warning', 'column.enum_as_string', 'ticket', 'scope']], self::diagnostics($model));
    }

    #[Test]
    public function it_falls_back_to_a_string_with_a_warning_when_two_enums_get_the_same_class_name(): void
    {
        $model = $this->analyze(
            SchemaBuilder::table('film', [SchemaBuilder::id(), SchemaBuilder::column('type_name', 'enum', values: ['a', 'b'])]),
            SchemaBuilder::table('film_type', [SchemaBuilder::id(), SchemaBuilder::column('name', 'enum', values: ['c', 'd'])]),
        );

        self::assertSame('FilmTypeName', self::field(self::entity($model, 'Film'), 'typeName')->enum?->className);
        self::assertNull(self::field(self::entity($model, 'FilmType'), 'name')->enum);
        self::assertSame([['warning', 'column.enum_as_string', 'film_type', 'name']], self::diagnostics($model));
    }

    #[Test]
    public function it_remembers_every_enum_class_name_taken_by_previous_entities(): void
    {
        $model = $this->analyze(
            SchemaBuilder::table('film', [
                SchemaBuilder::id(),
                SchemaBuilder::column('rating', 'enum', values: ['G']),
                SchemaBuilder::column('type_name', 'enum', values: ['a']),
            ]),
            SchemaBuilder::table('ticket', [SchemaBuilder::id(), SchemaBuilder::column('status', 'enum', values: ['open'])]),
            SchemaBuilder::table('film_type', [SchemaBuilder::id(), SchemaBuilder::column('name', 'enum', values: ['b'])]),
        );

        self::assertNull(self::field(self::entity($model, 'FilmType'), 'name')->enum);
        self::assertSame([['warning', 'column.enum_as_string', 'film_type', 'name']], self::diagnostics($model));
    }

    #[Test]
    public function it_falls_back_to_a_string_with_a_warning_when_no_value_was_read(): void
    {
        $model = $this->analyze(SchemaBuilder::table('ticket', [SchemaBuilder::id(), SchemaBuilder::column('kind', 'enum')]));

        self::assertNull(self::field(self::entity($model, 'Ticket'), 'kind')->enum);
        self::assertSame(
            'Column "ticket.kind" is mapped as a string, without its allowed values: no allowed value was read.',
            $model->diagnostics[0]->message ?? null,
        );
    }

    /**
     * @return array{?int, ?int, int, bool, bool, ?string} length, precision, scale, unsigned, fixed, comment
     */
    private function options(EntityDefinition $entity, string $propertyName): array
    {
        $options = self::field($entity, $propertyName)->options;

        return [$options->length, $options->precision, $options->scale, $options->unsigned, $options->fixed, $options->comment];
    }

    private function analyze(TableMetadata ...$tables): GenerationModel
    {
        return (new EntityAnalyzer())->analyze(SchemaBuilder::schema(array_values($tables)));
    }
}
