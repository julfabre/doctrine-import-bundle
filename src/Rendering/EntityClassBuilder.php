<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * Builds the properties and accessors of an entity class. The mapping is added by a {@see MappingStrategyInterface}.
 */
final readonly class EntityClassBuilder
{
    private const COLLECTION = 'Doctrine\Common\Collections\Collection';

    private const ARRAY_COLLECTION = 'Doctrine\Common\Collections\ArrayCollection';

    private Inflector $inflector;

    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    public function build(EntityDefinition $entity, PhpNamespace $namespace): ClassType
    {
        $class = $namespace->addClass($entity->className);

        if ([] !== $entity->oneToMany) {
            $namespace->addUse(self::ARRAY_COLLECTION)->addUse(self::COLLECTION);
            $class->addMethod('__construct')->setBody(implode("\n", array_map(
                static fn (OneToManyDefinition $collection): string => \sprintf('$this->%s = new ArrayCollection();', $collection->propertyName),
                $entity->oneToMany,
            )));
        }

        foreach ($entity->fields as $field) {
            $this->addField($class, $field);
        }

        foreach ($entity->manyToOne as $association) {
            $this->addManyToOne($class, $association, $namespace->getName().'\\'.$association->targetEntity);
        }

        foreach ($entity->oneToMany as $collection) {
            $this->addOneToMany($class, $collection, $namespace->getName().'\\'.$collection->targetEntity);
        }

        return $class;
    }

    private function addField(ClassType $class, FieldDefinition $field): void
    {
        $nullable = $field->nullable || $this->isGenerated($field);
        $property = $class->addProperty($field->propertyName)->setPrivate()->setType($field->phpType);
        if ('mixed' !== $field->phpType) {
            $property->setNullable($nullable);
        }

        $literal = PhpLiteral::fromDefault($field->options->default, $field->phpType);
        if ($literal instanceof PhpLiteral) {
            $property->setValue($literal->value);
        } elseif ($nullable) {
            $property->setValue(null);
        }

        $getter = 'bool' === $field->phpType ? $this->booleanGetterName($field->propertyName) : 'get'.ucfirst($field->propertyName);
        $this->addGetter($class, $getter, $field->propertyName, $field->phpType, $nullable);

        if (!$this->isGenerated($field)) {
            $this->addSetter($class, $field->propertyName, $field->phpType, $nullable);
        }
    }

    /**
     * @param string $targetClass fully qualified, so that the printer resolves it against the namespace
     */
    private function addManyToOne(ClassType $class, ManyToOneDefinition $association, string $targetClass): void
    {
        $class->addProperty($association->propertyName)->setPrivate()->setType($targetClass)->setNullable()->setValue(null);
        $this->addGetter($class, 'get'.ucfirst($association->propertyName), $association->propertyName, $targetClass, true);
        $this->addSetter($class, $association->propertyName, $targetClass, true);
    }

    /**
     * @param string $targetClass fully qualified, so that the printer resolves it against the namespace
     */
    private function addOneToMany(ClassType $class, OneToManyDefinition $collection, string $targetClass): void
    {
        $name = $collection->propertyName;
        $element = $this->inflector->singularize($name);
        $owningSetter = 'set'.ucfirst($collection->mappedBy);

        $class->addProperty($name)->setPrivate()->setType(self::COLLECTION)
            ->addComment(\sprintf('@var Collection<int, %s>', $collection->targetEntity));

        $class->addMethod('get'.ucfirst($name))->setReturnType(self::COLLECTION)
            ->addComment(\sprintf('@return Collection<int, %s>', $collection->targetEntity))
            ->setBody(\sprintf('return $this->%s;', $name));

        $add = $class->addMethod('add'.ucfirst($element))->setReturnType('static');
        $add->addParameter($element)->setType($targetClass);
        $add->setBody(<<<PHP
            if (!\$this->{$name}->contains(\${$element})) {
                \$this->{$name}->add(\${$element});
                \${$element}->{$owningSetter}(\$this);
            }

            return \$this;
            PHP);

        $remove = $class->addMethod('remove'.ucfirst($element))->setReturnType('static');
        $remove->addParameter($element)->setType($targetClass);
        $remove->setBody(\sprintf(<<<'PHP'
            // Unset the owning side, unless it already points to another entity.
            if ($this->%1$s->removeElement($%2$s) && $this === $%2$s->get%3$s()) {
                $%2$s->%4$s(null);
            }

            return $this;
            PHP, $name, $element, ucfirst($collection->mappedBy), $owningSetter));
    }

    private function addGetter(ClassType $class, string $name, string $property, string $type, bool $nullable): void
    {
        $class->addMethod($name)->setReturnType($type)->setReturnNullable($nullable && 'mixed' !== $type)
            ->setBody(\sprintf('return $this->%s;', $property));
    }

    private function addSetter(ClassType $class, string $property, string $type, bool $nullable): void
    {
        $setter = $class->addMethod('set'.ucfirst($property))->setReturnType('static')
            ->setBody(\sprintf("\$this->%1\$s = \$%1\$s;\n\nreturn \$this;", $property));
        $setter->addParameter($property)->setType($type)->setNullable($nullable && 'mixed' !== $type);
    }

    /**
     * The database sets the value: the property starts null and has no setter.
     */
    private function isGenerated(FieldDefinition $field): bool
    {
        return IdGeneration::Identity === $field->idGeneration || IdGeneration::Sequence === $field->idGeneration;
    }

    private function booleanGetterName(string $property): string
    {
        // "isFeatured" already reads as a question: keep it instead of "isIsFeatured".
        return 1 === preg_match('/^(is|has|can)[A-Z]/', $property) ? $property : 'is'.ucfirst($property);
    }
}
