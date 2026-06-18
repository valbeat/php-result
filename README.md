# PHP Result

![Packagist Version](https://img.shields.io/packagist/v/valbeat/result)
[![codecov](https://codecov.io/gh/valbeat/php-result/branch/main/graph/badge.svg)](https://codecov.io/gh/valbeat/php-result)

A Result type implementation for PHP inspired by Rust's `Result<T, E>` type.

This library provides a robust way to handle operations that might fail, without relying on exceptions. It encourages explicit error handling and makes it impossible to accidentally ignore errors.

## Installation

```bash
composer require valbeat/result
```

## Requirements

- PHP 8.4 or higher (tested on PHP 8.4 and 8.5)
- Composer

## Basic Usage

```php
use Valbeat\Result\Ok;
use Valbeat\Result\Err;
use Valbeat\Result\Result;

// Creating Results
$success = new Ok(42);
$failure = new Err("Something went wrong");

// Pattern matching with match expression
$message = $success->match(
    ok: fn($value) => "Success: $value",
    err: fn($error) => "Error: $error"
);
echo $message; // "Success: 42"

// Checking if a Result is Ok or Err
if ($success->isOk()) {
    echo "Operation succeeded!";
}

if ($failure->isErr()) {
    echo "Operation failed!";
}

// Unwrapping values (throws exception on error)
$value = $success->unwrap(); // 42
// $failure->unwrap(); // throws LogicException

// Safe unwrapping with default values
$value = $failure->unwrapOr(0); // 0
$value = $failure->unwrapOrElse(fn($err) => strlen($err)); // 19
```

## Advanced Usage

### Transforming Results

```php
// Map over success values
$result = (new Ok(5))
    ->map(fn($x) => $x * 2)
    ->map(fn($x) => $x + 1);
echo $result->unwrap(); // 11

// Map over error values
$result = (new Err("error"))
    ->mapErr(fn($e) => strtoupper($e));
echo $result->unwrapErr(); // "ERROR"
```

### Chaining Operations

```php
// Chain operations that might fail
$result = (new Ok(10))
    ->andThen(fn($x) => $x > 5 ? new Ok($x * 2) : new Err("Too small"))
    ->andThen(fn($x) => new Ok($x + 5));
echo $result->unwrap(); // 25

// Short-circuit on first error
$result = (new Ok(2))
    ->andThen(fn($x) => $x > 5 ? new Ok($x * 2) : new Err("Too small"));
echo $result->unwrapErr(); // "Too small"
```

Each step in a chain may fail with a **different error type**. The error types
are composed into a union, so PHPStan tracks every error the chain can produce:

```php
final class ValidationError {}
final class NotFoundError {}

/** @return Result<int, ValidationError> */
function validateUserId(string $raw): Result
{
    return ctype_digit($raw) ? new Ok((int) $raw) : new Err(new ValidationError());
}

/** @return Result<string, NotFoundError> */
function findUserNameById(int $id): Result
{
    return $id === 42 ? new Ok('Alice') : new Err(new NotFoundError());
}

// PHPStan infers Result<string, ValidationError|NotFoundError>
$userName = validateUserId('42')->andThen(findUserNameById(...));
echo $userName->unwrap(); // "Alice"
```

### Combining Results

```php
// Use first Ok value
$result = (new Err("first error"))
    ->or(new Err("second error"))
    ->or(new Ok(42));
echo $result->unwrap(); // 42

// Use first Ok or call function
$result = (new Err("error"))
    ->orElse(fn($e) => new Ok(strlen($e)));
echo $result->unwrap(); // 5
```

### Side Effects

```php
// Inspect values without consuming the Result
$result = (new Ok(42))
    ->inspect(fn($x) => print("Value is: $x\n"))
    ->map(fn($x) => $x * 2);

// Inspect errors
$result = (new Err("oops"))
    ->inspectErr(fn($e) => error_log("Error occurred: $e"));
```

## Type Safety

This library is designed to be used with [PHPStan](https://phpstan.org/) at level max
and leans on several of its generics features:

- **Sealed interface** — `Result` is annotated with `@phpstan-sealed Ok|Err`, so
  PHPStan knows `Ok` and `Err` are the only implementations. A `match (true)` over
  `instanceof` checks is recognized as exhaustive, and the `else` branch of an
  `instanceof Ok` check narrows to `Err`. Note that `instanceof` narrowing loses
  the type arguments (a known PHPStan limitation: `Result<int, E>` narrows to
  plain `Ok`, so `unwrap()` becomes `mixed`), so use `instanceof` in a
  `match (true)` purely for exhaustiveness. When you also need the values, prefer
  the `match()` method — it is exhaustive by construction (both arms are required)
  and keeps `T`/`E` — or narrow with `isOk()`/`isErr()`. (`isOk()`/`isErr()` arms
  inside a `match (true)` keep the type arguments but are *not* recognized as
  exhaustive, so they require a `default` arm.)
- **Covariant type parameters** — `T` and `E` are declared `@template-covariant`,
  so `Ok<T>` (which is `Result<T, never>`) and `Err<E>` (which is `Result<never, E>`)
  are assignable to any `Result<T, E>`. A function declared to return
  `Result<User, DbError>` can simply `return new Ok($user);`.
- **Error-type composition** — `andThen()`/`and()` widen the error channel to
  `E|F` and `orElse()`/`or()` widen the success channel to `T|U`, so chains that
  mix failure types stay precisely typed. (This deliberately diverges from Rust,
  whose `and`/`or` family keeps the other channel's type fixed.)
- **Type narrowing** — `isOk()`/`isErr()` narrow `$result` to `Ok<T>`/`Err<E>`
  via `@phpstan-assert-if-true`; `unwrap()`/`unwrapErr()` use conditional return
  types (`never` on the impossible side), and `unwrapOr()`/`unwrapOrElse()`
  resolve to `T` on `Ok` and to the default's type on `Err`.
- **Precise concrete receivers** — when the receiver is statically `Ok<T>` or
  `Err<E>`, no-op methods keep their exact type (`$ok->orElse(...)` stays
  `Ok<T>`, `$err->andThen(...)` stays `Err<E>`) instead of widening to a union.

Note: because the templates are covariant, PHPStan preserves constant value types
(`new Ok(10)` is `Ok<10>`, not `Ok<int>`). Type a variable or parameter as `int`
if you want the widened type.

## API Reference

### Result Methods

All Result types (both Ok and Err) implement these methods:

#### Type Checking
- `isOk(): bool` - Returns true if the Result is Ok
- `isOkAnd(callable $fn): bool` - Returns true if the Result is Ok and the predicate returns true
- `isErr(): bool` - Returns true if the Result is Err
- `isErrAnd(callable $fn): bool` - Returns true if the Result is Err and the predicate returns true

#### Value Extraction
- `unwrap(): mixed` - Returns the success value or throws LogicException
- `unwrapErr(): mixed` - Returns the error value or throws LogicException
- `unwrapOr(mixed $default): mixed` - Returns the success value or a default
- `unwrapOrElse(callable $fn): mixed` - Returns the success value or computes it from the error

#### Transformation
- `map(callable $fn): Result` - Maps a Result<T, E> to Result<U, E> by applying a function to the success value
- `mapErr(callable $fn): Result` - Maps a Result<T, E> to Result<T, F> by applying a function to the error value
- `mapOr(mixed $default, callable $fn): mixed` - Maps the success value or returns a default
- `mapOrElse(callable $defaultFn, callable $fn): mixed` - Maps the success value or computes a default from the error

#### Combination
- `and(Result $res): Result` - Returns the second Result if the first is Ok, otherwise returns the first Err
- `andThen(callable $fn): Result` - Chains another operation that returns a Result
- `or(Result $res): Result` - Returns the first Ok or the second Result if the first is Err
- `orElse(callable $fn): Result` - Returns the first Ok or calls a function with the error to produce a Result

#### Side Effects
- `inspect(callable $fn): Result` - Calls a function with the success value if Ok
- `inspectErr(callable $fn): Result` - Calls a function with the error value if Err

#### Pattern Matching
- `match(callable $okFn, callable $errFn): mixed` - Pattern match on the Result

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.