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

## Usage

```bash
php bin/console generate:entities:from-database
```

## Contributing

- [Coding standards](contributing/standards.md)
- [Architecture](contributing/architecture.md)
- [Testing](contributing/testing.md)
