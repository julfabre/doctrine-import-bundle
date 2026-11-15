# Coding standards

These rules apply to every file of the bundle. Most of them are enforced by tools:
run `composer qa:static` before pushing. This document only lists what a tool
cannot check, plus the sources the configuration is based on.

## Sources

| Source | Version read |
|---|---|
| [Symfony: Best Practices for Reusable Bundles](https://symfony.com/doc/current/bundles/best_practices.html) | 2026-11-15 |
| [Symfony: Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html) | 2026-11-15 |
| [Doctrine ORM: Best Practices](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/best-practices.html) | 2026-11-15 |
| [Doctrine ORM: Limitations and Known Issues](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/limitations-and-known-issues.html) | 2026-11-15 |
| [PER Coding Style 2.0](https://www.php-fig.org/per/coding-style/) | 2.0 |

## Enforced by tools

| Tool | What it enforces |
|---|---|
| PHP-CS-Fixer (`@PER-CS2.0`, `@Symfony`, `@PHP82Migration`) | Formatting, Yoda conditions, strict comparisons, `declare(strict_types=1)`, `final` classes, imports |
| PHPStan (max level, strict and deprecation rules) | Types, dead branches, unchecked exceptions, deprecated API usage |
| Rector (PHP 8.2 set) | Modern syntax, never above PHP 8.2 |
| PHPMD | Size, complexity, naming, unused code |
| Deptrac | Dependencies between layers (see [architecture](architecture.md)) |
| composer-require-checker, composer-unused | Declared dependencies match used symbols |

## Language and code

- English everywhere: code, comments, PHPDoc, exception messages, console output, documentation.
- Classes are `final` by default. Value objects are `final readonly`.
  Closed sets of values are `enum`s.
- Constructor injection only. No service locator, no static state, no global function
  with side effects.
- No public property, except promoted `readonly` properties on value objects.
- Exceptions live in the `Exception` namespace and implement the bundle
  `ExceptionInterface`. Messages use `sprintf()`, start with a capital letter, end with
  a period, quote values with double quotes, and use `get_debug_type()` for types.
- No `else` or `elseif` after a branch that returns or throws.

## PHPDoc and comments

- PHPDoc only adds what native types cannot say: generics (`list<Column>`), array
  shapes, `@throws`, one sentence on public interfaces. Never repeat the signature.
- Comments are rare and explain *why*: a non-obvious decision, a workaround for a
  database platform or a DBAL version, a link to an issue. No commented-out code,
  no `TODO` without an issue link.
- No license header in PHP files: the `LICENSE` file covers the whole package.

## Symfony bundle rules

- The bundle class extends `AbstractBundle` and lives in `src/`.
- Services are declared explicitly in `config/services.php`: **no autowiring, no
  autoconfiguration**, as recommended for reusable bundles.
- Service IDs are prefixed with the bundle alias (`juf_doctrine_import.`), and
  internal services with a dot (`.juf_doctrine_import.`). Services are private
  unless an application must fetch them; extension points get an interface alias.
- Configuration keys and parameters are `snake_case`, prefixed with the alias.
- Sub-directories of `src/` are at most two levels deep. Commands live in `src/Command/`.
- The bundle never writes inside its own directory.

## Generated code

The generated entities belong to the application, not to the bundle. They follow the
Doctrine best practices, which sometimes differ from the rules above:

- Entities are mutable (setters, collections): immutability applies to the bundle
  code, not to the generated code.
- Generated code must run on PHP 8.2.
- Generated classes are not `final`: Doctrine lazy-loading proxies extend entities.
- Every column is named explicitly and typed with a `Types::` constant, so that the mapping reads
  without knowing Doctrine defaults and the schema round-trips exactly.
- No public property. Collections are initialised in the constructor.
- A foreign key is mapped as an association, never as both a field and an association.
- Date and time columns use the immutable types.
- Identifiers are never quoted. Names that would need quoting (reserved words,
  characters outside `[a-zA-Z0-9_]`) produce a diagnostic instead of guessed code.
- Unsupported schemas produce a diagnostic, never silently wrong code:
  join columns referencing a non-primary key, date/time identifiers,
  several identical tables mapped to one entity.
- Composite primary keys are supported but reported, as Doctrine advises against them.

## Git

- One branch per step of the roadmap, one pull request, green CI before merge.
- Atomic commits with conventional messages (`feat:`, `fix:`, `test:`, `refactor:`,
  `docs:`, `chore:`, `ci:`).
