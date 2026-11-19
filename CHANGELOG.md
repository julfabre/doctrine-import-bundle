# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-11-19

### Added

- Generated columns (`GENERATED ALWAYS AS …`) are detected on MySQL, MariaDB and PostgreSQL and mapped
  read-only (`insertable: false`, `updatable: false`, `generated: 'ALWAYS'`), without setter. On SQLite, where
  Doctrine DBAL cannot read them, they are reported.
- MySQL and MariaDB `ENUM` columns become string-backed PHP enums (`film.rating` → `FilmRating`), mapped with
  `type: Types::ENUM` and `enumType`. Their location is configured with `enum.namespace` / `enum.dir`, or the
  `--enum-namespace` / `--enum-dir` options.
- A foreign key covered by a single-column unique index becomes an owning `OneToOne` association, without
  inverse side, instead of a `ManyToOne`.
- A pure join table becomes a `ManyToMany` association instead of an entity with two scalar fields: the table
  of the first primary key column owns it, the other side is mapped by it, and a table joining itself only gets
  the owning side.
- A foreign key that is part of a composite primary key becomes an identifier association (derived identity),
  such as `film_actor` in Sakila, instead of a scalar field.

### Changed

- Doctrine ORM 3.3 or higher is required: it derives the values of an `ENUM` column from the cases of its PHP enum.
- `NamingStrategyInterface` has two new methods, `enumClassName()` and `enumCaseName()`: custom naming strategies
  must implement them.
- The `relation.one_to_one_as_many_to_one` diagnostic is replaced by `relation.one_to_one_without_inverse_side`.
- `column.enum_as_string` is now a warning, reported only when an `ENUM` column cannot become a PHP enum.

### Fixed

- The properties of a composite identifier are declared in primary key order, not in column order: Doctrine
  builds the primary key in declaration order, so `doctrine:schema:update` recreated a primary key whose columns
  were in another order.
- A join column referencing a PostgreSQL `SERIAL` primary key resets the `nextval()` default that Doctrine copies
  from the referenced column (`options: ['default' => null]`): since 0.1.1, `doctrine:schema:update` added that
  default to every foreign key column.
- The indexes that Doctrine DBAL adds in memory for an unindexed foreign key are no longer read as indexes of the
  table: entities got invented `#[ORM\Index(name: 'IDX_…')]` attributes, and with a derived identity, such as
  Sakila `film_actor`, `doctrine:schema:update` renamed an index.
- A SQLite `INTEGER PRIMARY KEY` without the `AUTOINCREMENT` keyword is mapped as an identity: `persist()` failed
  with `EntityMissingAssignedId`, although SQLite numbers the rowid itself.
- PostgreSQL partitions are skipped with an info (`table.partition`) instead of an unreadable table error, and the
  `doctrine.dbal.mapping_types` advice of `table.unreadable` is only given for an unknown column type.

## [0.1.1] - 2026-11-16

### Fixed

- PostgreSQL `SERIAL` primary keys were generated as assigned identifiers, so `persist()` failed with
  `EntityMissingAssignedId`: Doctrine DBAL does not report them as auto-increment. They now use their existing
  sequence (`GeneratedValue(strategy: 'SEQUENCE')`, `allocationSize: 1`) and keep their `nextval()` default.

## [0.1.0] - 2026-11-16

First release.

### Added

- `generate:entities:from-database` command: reads the tables of an entity manager connection and writes
  one entity (Doctrine attributes) and one `ServiceEntityRepository` per table.
- Options and `juf_doctrine_import` configuration: entity manager, table filters, table prefixes,
  namespaces and directories, dry run, `--force`.
- Single-column foreign keys mapped as `ManyToOne` with their inverse `OneToMany` collection
  (`children` for self-references).
- Literal defaults, and `CURRENT_TIMESTAMP`, `CURRENT_DATE`, `CURRENT_TIME` mapped as DBAL default expressions.
- Fulltext and spatial index flags.
- Diagnostics for every table or column that cannot be mapped safely; a table with a column type unknown to
  DBAL is skipped instead of stopping the generation.
- Support for PHP 8.2 to 8.5, Symfony 6.4 to 8, Doctrine ORM 3, DBAL 4.6+, on PostgreSQL, MySQL, MariaDB and
  SQLite.

[0.2.0]: https://github.com/julfabre/doctrine-import-bundle/releases/tag/v0.2.0
[0.1.1]: https://github.com/julfabre/doctrine-import-bundle/releases/tag/v0.1.1
[0.1.0]: https://github.com/julfabre/doctrine-import-bundle/releases/tag/v0.1.0
