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
| Definition | `Definition\` | nothing (pure value objects) |
| Schema | `Schema\` | Doctrine DBAL, Doctrine ORM/Persistence |
| Analysis | `Analysis\` | Schema, Definition |
| Rendering | `Rendering\` | Definition, nette/php-generator |
| Writer | `Writer\` | Rendering |
| Application | `Application\` | Schema, Analysis, Definition, Rendering, Writer |
| Command | `Command\` | Application, Definition, Symfony, Doctrine ORM/Persistence |
| Bundle | `JufDoctrineImportBundle` | Command, Symfony |

Consequences:

- Only `Schema` knows DBAL. No DBAL class leaks into the rest of the bundle: the
  schema is copied into the bundle own read-only model.
- `Analysis`, `Definition` and `Rendering` know neither Symfony nor the database.
  They are tested with plain PHPUnit, without a kernel or a connection.
- The bundle never writes to the database. Only `Writer` touches the filesystem.

## Extension points

New behaviour is added by adding a class, not by editing existing ones:

| Need | Extension point |
|---|---|
| A new mapping format (attributes, XML…) | `MappingStrategy` implementation |
| A new entity style | `EntityStyle` implementation |
| A new SQL type | `TypeMapper` rule |
| A new naming rule | `NamingStrategy` implementation |

Every implementation of an extension point passes the same contract test suite.

## Diagnostics

When the schema cannot be mapped safely, the bundle reports a structured diagnostic
(`severity`, `code`, `table`, `column`, `message`) instead of generating guessed code.
