# SQL import analysis

`IlluminateDatabaseConnection.stub` changes `Connection::unprepared()` from
`literal-string` to `string` for this repository's PHPStan analysis. SQL imports
intentionally execute file contents through Laravel's existing connection API.
PHPStan continues checking the argument type and return type; runtime behavior
does not change. Consumers do not automatically load this configuration.

Larastan already declares `Connection` in its database stub. The development-only
provider in `tests/PHPStan` replaces that one file, preserving its other database
declarations and all other stub files. The compiler extension selects that
provider without relying on PHPStan's generated service IDs.

When upgrading Larastan, compare its `stubs/common/Database/Database.stub` with
this replacement so new declarations are retained.
