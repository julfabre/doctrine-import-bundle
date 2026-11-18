# DoctrineImportBundle

Generate Doctrine ORM entities and repositories from an existing database.

> The bundle is under active development. The first usable release will be `0.1.0`.

## Requirements

| Dependency | Supported versions |
|---|---|
| PHP | 8.2 or higher |
| Symfony | 6.4, 7.x, 8.x |
| Doctrine ORM | 3.3+ |
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
| `--enum-namespace=NS` / `--enum-dir=PATH` | Namespace and directory of the enums generated for `ENUM` columns |
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
    enum:
        namespace: 'App\Enum'
        dir: '%kernel.project_dir%/src/Enum'
```

## Generated code

- One entity per table, mapped with Doctrine attributes; one `ServiceEntityRepository` per entity.
- Class names are singular PascalCase (`blog_posts` → `BlogPost`), properties are camelCase.
- Every column is named and typed explicitly. Dates use immutable types, decimals are strings.
- Single-column foreign keys become `ManyToOne` associations with their inverse `OneToMany`
  collection (`children` for a table referencing itself).
- A foreign key covered by a single-column unique index becomes an owning `OneToOne` association, without
  inverse side: Doctrine always loads the inverse side of a `OneToOne`, so it is left for you to add when needed.
- A pure join table (two columns forming the primary key, each one a foreign key to another table, nothing else)
  becomes a `ManyToMany` association instead of an entity: `post_tag(post_id, tag_id)` gives `Post::$tags`, the
  owning side with its `JoinTable`, and `Tag::$posts`, mapped by it. The table of the first primary key column
  owns the association. `Tag::addPost()` / `removePost()` also update the owning side. A table joining a table
  to itself only gets the owning side (`User::$friends`), to be named by hand on the other side.
- A MySQL or MariaDB `ENUM` column becomes a string-backed PHP enum named after the entity and the property
  (`film.rating` → `FilmRating`, cases `G`, `Pg13`…), mapped with `type: Types::ENUM, enumType: FilmRating::class`.
  One enum per column, even when two columns share the same values.
- Generated columns (`GENERATED ALWAYS AS …`) are read-only: `insertable: false, updatable: false`,
  `generated: 'ALWAYS'` so that Doctrine reads the computed value back after each write, and no setter.
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
| `column.generated_not_read` | warning | Generated column hidden from Doctrine DBAL (SQLite): not mapped |
| `relation.on_update_not_mapped` | warning | `ON UPDATE` action of a foreign key: Doctrine cannot map it |
| `relation.composite_foreign_key` | warning | Composite foreign key kept as scalar fields |
| `relation.non_primary_key_target` | warning | Foreign key to a non-primary-key column, unsupported by Doctrine |
| `relation.foreign_key_in_primary_key` | warning | Derived identity, not mapped as an association yet |
| `relation.missing_target` | warning | Foreign key to a table that is not generated |
| `table.outside_search_path` | warning | PostgreSQL table outside the default schema, not read |
| `table.composite_primary_key` | info | Supported, but Doctrine advises against it |
| `relation.one_to_one_without_inverse_side` | info | Unique foreign key mapped as an owning `OneToOne`, without inverse side |
| `relation.many_to_many` | info | Join table mapped as a `ManyToMany` association |
| `relation.many_to_many_without_inverse_side` | info | Join table of a table with itself: only the owning side is generated |
| `relation.join_table_index_renamed` | warning | Index of a join table: a `ManyToMany` mapping cannot name it, `doctrine:schema:update` would rename it |
| `relation.join_table_not_mapped` | warning | Join table to a table that is not generated: it is skipped |
| `column.generated` | info | Generated column mapped read-only; its expression is not part of the mapping |
| `column.enum_as_string` | warning | `ENUM` column kept as a string: a value gives no valid case name, two values give the same case, or the enum name is already taken |
| `column.binary_as_mixed` | info | Type mapped approximately |
| `naming.table_prefix_detected` | info | Every table shares a known prefix: configure it |

## Known limitations

- **Associations**: only single-column foreign keys to a primary key become associations, as `ManyToOne` /
  `OneToMany`, as an owning `OneToOne` when the foreign key is unique, and pure join tables as `ManyToMany`.
  Join tables with extra columns, composite foreign keys and derived identities (a foreign key that is also
  the primary key) are kept as scalar fields and reported.
- **Join table indexes**: a `ManyToMany` mapping cannot name the indexes of its join table. An index other than
  the primary key (MySQL creates one for each foreign key) would be renamed by `doctrine:schema:update`.
- **Inheritance** is never inferred: each table becomes an independent entity.
- **Generated columns on SQLite** are not read by Doctrine DBAL: they are reported
  (`column.generated_not_read`) and missing from the entity.
- **Unknown database types**, such as spatial types: Doctrine DBAL reads a table as a whole, so the whole
  table is skipped and reported (`table.unreadable`). Map the type with `doctrine.dbal.mapping_types`, or
  exclude the table.
- **`ON UPDATE` actions** of foreign keys cannot be expressed with Doctrine: they are lost if the schema is
  later created from the entities.
- **Enums**: PostgreSQL enum types (`CREATE TYPE … AS ENUM`) are unreadable by Doctrine DBAL (`table.unreadable`);
  MySQL `SET` columns become `simple_array`.
- **Charsets and collations** of columns are not mapped.
- **MySQL `YEAR`** is read by Doctrine DBAL as a `date` column: map or exclude it before generating.
- **Views** are not generated.
- **Mapping formats**: attributes only. XML is planned.

## Contributing

- [Coding standards](contributing/standards.md)
- [Architecture](contributing/architecture.md)
- [Testing](contributing/testing.md)
