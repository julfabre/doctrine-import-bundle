# Testing

## Workflow

Every feature is written test first:

1. **Specify**: list the expected behaviours as test names.
2. **Red**: write the tests and run them. They must fail for the right reason
   (a wrong assertion, not an autoload or syntax error).
3. **Green**: write the minimal code that makes them pass.
4. **Refactor**: clean up while the tests stay green.
5. **Validate**: `composer qa` (static analysis, tests with coverage, mutation testing).
   A surviving mutant means a missing or weak test: go back to step 2.

A test is never changed to make it pass without explaining why in the pull request.

## Conventions

- One behaviour per test, Arrange / Act / Assert.
- Test methods use the `#[Test]` attribute and a snake_case name that reads as a
  sentence: `it_generates_one_id_attribute_per_primary_key_column()`.
- Assertions are called statically: `self::assertSame()`.
- Every test class declares what it covers (`#[CoversClass]`).
- In-memory fakes come first. Mocks are only used to check an interaction.

## Test suites

| Suite | Directory | Scope |
|---|---|---|
| Unit | `tests/Unit` | Pure code: naming, type mapping, analysis, rendering. No database, no kernel |
| Integration | `tests/Integration` | Schema reading on real databases. SQLite always runs; PostgreSQL, MySQL and MariaDB run when `DATABASE_URL_POSTGRESQL`, `DATABASE_URL_MYSQL` or `DATABASE_URL_MARIADB` is set, and are skipped otherwise |
| Functional | `tests/Functional` | Test kernel and console command |

Two kinds of tests deserve a specific mention:

- **Snapshot tests** compare the generated code with reviewed golden files.
- **Round-trip tests** are the reference: SQL schema → generated entities →
  schema computed from their mapping → **no difference** with the original schema.

## Commands

```bash
composer test            # PHPUnit
composer test:coverage   # PHPUnit with coverage (needs PCOV or Xdebug)
composer test:mutation   # Infection, run after test:coverage
composer qa              # everything
```

## Thresholds

| Metric | Minimum |
|---|---|
| Line coverage | 95 % |
| Mutation score indicator (MSI) | 80 % |
| Covered code MSI | 90 % |
