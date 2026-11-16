# DoctrineImportBundle

Generate Doctrine ORM entities and repositories from an existing database.

> The bundle is under active development. The first usable release will be `0.1.0`.

## Requirements

| Dependency | Supported versions |
|---|---|
| PHP | 8.2 or higher |
| Symfony | 6.4, 7.x, 8.x |
| Doctrine ORM | 3.x |
| Doctrine DBAL | 4.6+ |
| DoctrineBundle | 2.11+, 3.x |
| Databases | PostgreSQL, MySQL, MariaDB, SQLite |

## Installation

The bundle is a development tool:

```bash
composer require --dev juf/doctrine-import-bundle
```

Without Symfony Flex, enable it for the `dev` environment in `config/bundles.php`:

```php
return [
    // ...
    Juf\DoctrineImportBundle\JufDoctrineImportBundle::class => ['dev' => true],
];
```

## Usage

```bash
php bin/console generate:entities:from-database
```

The command reads the tables of the database of the entity manager, then writes one entity and one
repository per table. It prints the files with their status, then the diagnostics.

| Option | Description |
|---|---|
| `--em=NAME` | Entity manager whose connection is read (default: the default entity manager) |
| `--table=PATTERN` | Glob pattern of the tables to generate, repeatable (`--table='blog_*'`) |
| `--exclude=PATTERN` | Glob pattern of the tables to ignore, repeatable. An exclusion wins over an inclusion |
| `--table-prefix=PREFIX` | Prefix removed from table names before naming classes, repeatable (`wp_posts` → `Post`) |
| `--namespace=NS` / `--dir=PATH` | Namespace and directory of the entities |
| `--repository-namespace=NS` / `--repository-dir=PATH` | Namespace and directory of the repositories |
| `--dry-run` | List the files without writing them |
| `--force` | Overwrite existing files |

- Existing files are **never overwritten without `--force`**. With `--force`, the whole file is
  replaced: manual changes are lost.
- The command exits with code `1` when a table or a column could not be generated (see the
  diagnostics), `0` otherwise.

## Configuration

Every option has a default. Command line options override the configuration; repeatable options
replace the configured lists.

```yaml
# config/packages/dev/juf_doctrine_import.yaml
juf_doctrine_import:
    entity_manager: ~             # default entity manager
    table_prefixes: []            # e.g. ['wp_']
    tables:
        include: []               # glob patterns, empty means every table
        exclude: []
    entity:
        namespace: 'App\Entity'
        dir: '%kernel.project_dir%/src/Entity'
    repository:
        namespace: 'App\Repository'
        dir: '%kernel.project_dir%/src/Repository'
```

## Generated code

- One entity per table, mapped with Doctrine attributes; one `ServiceEntityRepository` per entity.
- Class names are singular PascalCase (`blog_posts` → `BlogPost`), properties are camelCase.
- Every column is named and typed explicitly. Dates use immutable types, decimals are strings.
- Single-column foreign keys become `ManyToOne` associations with their inverse `OneToMany`
  collection (`children` for a table referencing itself).
- A PostgreSQL `SERIAL` primary key (default `nextval('product_id_seq'::regclass)`) keeps using its sequence:
  `GeneratedValue(strategy: 'SEQUENCE')` with `allocationSize: 1`, and the default stays mapped so that
  other clients of the database keep inserting through it.
- The code runs on PHP 8.2.

## Diagnostics

When part of the schema cannot be mapped safely, the bundle reports it instead of generating
guessed code.

| Code | Severity | Meaning |
|---|---|---|
| `table.unreadable` | error | A column type is unknown to Doctrine DBAL (geometry, PostgreSQL enum…): the table is skipped. Map the type with `doctrine.dbal.mapping_types`, or exclude the table |
| `table.no_primary_key` | error | Doctrine needs an identifier: the table is skipped |
| `naming.invalid_identifier` | error | Name with characters other than `[A-Za-z0-9_]`: the table is skipped |
| `column.unsupported_type` | error | Type unknown to the bundle: the column is skipped |
| `naming.class_collision` | warning / error | Two tables give the same class name |
| `naming.reserved_word`, `naming.leading_digit` | warning | Class name adjusted to be valid PHP |
| `naming.property_collision` | warning | Association renamed to avoid an existing property |
| `column.default_expression` | warning | SQL expression default (`now()`, function call) not mapped. `CURRENT_TIMESTAMP`, `CURRENT_DATE` and `CURRENT_TIME` are mapped |
| `relation.on_update_not_mapped` | warning | `ON UPDATE` action of a foreign key: Doctrine cannot map it |
| `relation.composite_foreign_key` | warning | Composite foreign key kept as scalar fields |
| `relation.non_primary_key_target` | warning | Foreign key to a non-primary-key column, unsupported by Doctrine |
| `relation.foreign_key_in_primary_key` | warning | Derived identity, not mapped as an association yet |
| `relation.missing_target` | warning | Foreign key to a table that is not generated |
| `table.outside_search_path` | warning | PostgreSQL table outside the default schema, not read |
| `table.composite_primary_key` | info | Supported, but Doctrine advises against it |
| `relation.one_to_one_as_many_to_one` | info | Unique foreign key mapped as `ManyToOne` |
| `column.enum_as_string`, `column.binary_as_mixed` | info | Type mapped approximately |
| `naming.table_prefix_detected` | info | Every table shares a known prefix: configure it |

## Known limitations

- **Associations**: only single-column foreign keys to a primary key become associations, as `ManyToOne` /
  `OneToMany`. A unique foreign key (one-to-one) is mapped as `ManyToOne` and reported. Many-to-many join
  tables, composite foreign keys and derived identities (a foreign key that is also the primary key) are
  kept as scalar fields and reported.
- **Inheritance** is never inferred: each table becomes an independent entity.
- **Generated columns** (`GENERATED ALWAYS AS …`) are not detected yet. On MySQL, MariaDB and PostgreSQL they
  become ordinary fields, which Doctrine tries to write: add `insertable: false, updatable: false` by hand.
  On SQLite they are not read at all.
- **Unknown database types**, such as spatial types: Doctrine DBAL reads a table as a whole, so the whole
  table is skipped and reported (`table.unreadable`). Map the type with `doctrine.dbal.mapping_types`, or
  exclude the table.
- **`ON UPDATE` actions** of foreign keys cannot be expressed with Doctrine: they are lost if the schema is
  later created from the entities.
- **Enums**: MySQL `ENUM` columns become `string` (the allowed values are not kept); `SET` columns become
  `simple_array`. PHP enums are planned.
- **Charsets and collations** of columns are not mapped.
- **MySQL `YEAR`** is read by Doctrine DBAL as a `date` column: map or exclude it before generating.
- **Views** are not generated.
- **Mapping formats**: attributes only. XML is planned.

## Contributing

- [Coding standards](contributing/standards.md)
- [Architecture](contributing/architecture.md)
- [Testing](contributing/testing.md)
