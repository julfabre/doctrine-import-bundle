# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[0.1.0]: https://github.com/julfabre/doctrine-import-bundle/releases/tag/v0.1.0
