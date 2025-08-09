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

- PHP 8.4 or higher
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