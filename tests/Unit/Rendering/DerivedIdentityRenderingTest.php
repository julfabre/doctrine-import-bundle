<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Rendering\AttributeMappingStrategy;
use Juf\DoctrineImportBundle\Rendering\EntityClassBuilder;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\PhpLiteral;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Doctrine builds the primary key in the declaration order of the identifier properties.
 */
#[CoversClass(AttributeMappingStrategy::class)]
#[CoversClass(EntityClassBuilder::class)]
#[UsesClass(PhpLiteral::class)]
#[UsesClass(GeneratedFile::class)]
#[UsesClass(RenderingOptions::class)]
#[UsesClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
#[UsesClass(ManyToOneDefinition::class)]
final class DerivedIdentityRenderingTest extends TestCase
{
    #[Test]
    public function it_renders_an_identifier_association_with_the_id_attribute(): void
    {
        $code = $this->render($this->filmActor());

        self::assertStringContainsString(
            "    #[ORM\\Id]\n"
            ."    #[ORM\\ManyToOne(targetEntity: Actor::class, inversedBy: 'filmActors')]\n"
            ."    #[ORM\\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: false)]\n"
            .'    private ?Actor $actor = null;',
            $code,
        );
    }

    #[Test]
    public function it_declares_identifier_associations_in_primary_key_order_before_other_properties(): void
    {
        $code = $this->render($this->filmActor());

        self::assertSame(['actor', 'film', 'lastUpdate'], $this->propertyOrder($code, ['film', 'actor', 'lastUpdate']));
    }

    #[Test]
    public function it_declares_mixed_identifiers_in_primary_key_order(): void
    {
        $code = $this->render(DefinitionBuilder::entity(
            'OrderItem',
            [DefinitionBuilder::field('lineNo', 'line_no', 'integer', 'int', idGeneration: IdGeneration::None)],
            manyToOne: [new ManyToOneDefinition('film', 'Film', 'film_id', 'id', false, null, null)],
            primaryKey: ['film_id', 'line_no'],
        ));

        self::assertSame(['film', 'lineNo'], $this->propertyOrder($code, ['lineNo', 'film']));
        self::assertStringContainsString("    #[ORM\\Id]\n    #[ORM\\ManyToOne(targetEntity: Film::class)]\n", $code);
    }

    #[Test]
    public function it_declares_composite_scalar_identifiers_in_primary_key_order(): void
    {
        $code = $this->render(DefinitionBuilder::entity('OrderLine', [
            DefinitionBuilder::field('orderId', 'order_id', 'integer', 'int', idGeneration: IdGeneration::None),
            DefinitionBuilder::field('productId', 'product_id', 'integer', 'int', idGeneration: IdGeneration::None),
            DefinitionBuilder::field('quantity', 'quantity', 'integer', 'int'),
        ], primaryKey: ['product_id', 'order_id']));

        self::assertSame(['productId', 'orderId', 'quantity'], $this->propertyOrder($code, ['orderId', 'productId', 'quantity']));
    }

    private function filmActor(): EntityDefinition
    {
        return DefinitionBuilder::entity(
            'FilmActor',
            [DefinitionBuilder::field('lastUpdate', 'last_update', 'datetime_immutable', '\DateTimeImmutable')],
            manyToOne: [
                new ManyToOneDefinition('film', 'Film', 'film_id', 'id', false, null, 'filmActors'),
                new ManyToOneDefinition('actor', 'Actor', 'actor_id', 'id', false, null, 'filmActors'),
            ],
            primaryKey: ['actor_id', 'film_id'],
        );
    }

    /**
     * @param list<string> $properties
     *
     * @return list<string> the properties, sorted by their declaration in the code
     */
    private function propertyOrder(string $code, array $properties): array
    {
        $positions = [];
        foreach ($properties as $property) {
            preg_match('/private [^$;]*\\$'.$property.'\\b/', $code, $match, \PREG_OFFSET_CAPTURE);
            $positions[$property] = $match[0][1] ?? -1;
        }

        asort($positions);

        return array_keys($positions);
    }

    private function render(EntityDefinition $entity): string
    {
        return (new AttributeMappingStrategy())->render($entity, new RenderingOptions())[0]->contents ?? '';
    }
}
