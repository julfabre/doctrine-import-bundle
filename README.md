# DoctrineImportBundle

Generate Doctrine ORM entities and repositories from an existing database, for Symfony.

Doctrine ORM 3 removed `orm:convert-mapping` and `orm:generate-entities`. This bundle brings reverse
engineering back as a single command, with code you can keep: attributes, typed properties, explicit
column mapping, and a report of everything it could not map safely instead of guessed code.

```bash
composer require --dev juf/doctrine-import-bundle
php bin/console generate:entities:from-database
```

## Features

- One entity and one `ServiceEntityRepository` per table, mapped with Doctrine attributes.
- Singular class names, camelCase properties, explicit `name:` and `type:` on every column.
- Identifiers (identity, assigned, composite), literal and `CURRENT_TIMESTAMP` defaults, indexes and
  unique constraints, comments.
- `ManyToOne` associations for single-column foreign keys, with their inverse `OneToMany` collection.
- Table filters, table prefixes (`wp_posts` → `Post`), dry run, existing files kept unless `--force`.
- Diagnostics for every table or column that cannot be mapped safely; exit code `1` when the generation is
  incomplete.
- Checked on PostgreSQL, MySQL, MariaDB and SQLite: the schema Doctrine computes from the generated entities
  matches the original database.

## Example

```sql
CREATE TABLE author (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL);
CREATE TABLE post (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE
);
```

```php
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => new CurrentTimestamp()])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Author $author = null;

    // getters and fluent setters...
}
```

`Author` gets a `posts` collection with `addPost()` and `removePost()`.

## Requirements

PHP 8.2+, Symfony 6.4 / 7.x / 8.x, Doctrine ORM 3.3+, Doctrine DBAL 4.6+, DoctrineBundle 2.11+ / 3.x.

## Documentation

[Installation, usage, configuration, diagnostics and known limitations](docs/index.md).

## License

[MIT](LICENSE)
