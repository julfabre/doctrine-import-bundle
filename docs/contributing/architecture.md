# Architecture

The bundle reads a database schema and turns it into PHP files. Each step is a layer
with a single responsibility; Deptrac (`composer deptrac`) fails when a layer depends
on something it should not.

## Pipeline

```text
Database ──► Schema ──► Analysis ──► Definition ──► Rendering ──► Writer ──► files
   (DBAL)    read-only    naming,     entity and      PHP / XML     dry run,
             model        types,      repository      source code   skip, force,
                          relations   definitions                   report
```

`Application` orchestrates the pipeline. `Command` is the console entry point and
the only layer that talks to the user.

## Layers and allowed dependencies

| Layer | Namespace | May depend on |
|---|---|---|
| Diagnostic | `Diagnostic\` | nothing |
| Exception | `Exception\` | nothing |
| Definition | `Definition\` | Diagnostic |
| Schema | `Schema\` (model only) | Diagnostic |
| SchemaDbal | `Schema\Dbal\` | Schema, Diagnostic, Exception, Doctrine DBAL |
| Analysis | `Analysis\` | Schema, Definition, Diagnostic |
| Rendering | `Rendering\` | Definition, nette/php-generator |
| Writer | `Writer\` | Rendering |
| Application | `Application\` | Schema, SchemaDbal, Analysis, Definition, Rendering, Writer, Diagnostic, Exception |
| Command | `Command\` | Application, Definition, Diagnostic, Exception, Symfony, Doctrine ORM/Persistence |
| Bundle | `JufDoctrineImportBundle` | Command, Symfony |

Consequences:

- Only `Schema\Dbal` knows DBAL. It copies the database structure into the read-only
  model of `Schema`, so no DBAL class leaks into the rest of the bundle. Deptrac enforces it.
- Every exception thrown by the bundle implements `Exception\ExceptionInterface`.
- `Analysis`, `Definition` and `Rendering` know neither Symfony nor the database.
  They are tested with plain PHPUnit, without a kernel or a connection.
- The bundle never writes to the database. Only `Writer` touches the filesystem.

## Extension points

New behaviour is added by adding a class, not by editing existing ones:

| Need | Extension point |
|---|---|
| A new mapping format (attributes, XML…) | `Rendering\MappingStrategyInterface` implementation |
| A new naming rule | `Analysis\Naming\NamingStrategyInterface` implementation |
| A new SQL type | `Analysis\TypeMapper` rule |
| A new entity style (planned) | Introduced with the second style |

Every implementation of an extension point passes the same contract test suite.

## Diagnostics

When the schema cannot be mapped safely, the bundle reports a structured diagnostic
(`severity`, `code`, `table`, `column`, `message`) instead of generating guessed code.
