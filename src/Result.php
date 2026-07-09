<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * The Result type represents either success (Ok) or failure (Err).
 *
 * Note: narrowing via instanceof loses the type arguments (a known PHPStan
 * limitation; Result<int, E> becomes an Ok without type arguments and unwrap()
 * returns mixed). In branches that extract the value, narrow with isOk() / isErr().
 *
 * @template-covariant T the type of the success value
 * @template-covariant E the type of the error value
 *
 * @phpstan-sealed Ok|Err
 */
interface Result
{
    /**
     * Returns true if the result is a success (Ok).
     *
     * @phpstan-assert-if-true Ok<T> $this
     * @phpstan-assert-if-false Err<E> $this
     *
     * @return bool
     */
    public function isOk(): bool;

    /**
     * Returns true if the result is a success (Ok) and the callback returns true.
     *
     * @param callable(T): bool $fn
     *
     * @return bool
     */
    public function isOkAnd(callable $fn): bool;

    /**
     * Returns true if the result is a failure (Err).
     *
     * @phpstan-assert-if-true Err<E> $this
     * @phpstan-assert-if-false Ok<T> $this
     *
     * @return bool
     */
    public function isErr(): bool;

    /**
     * Returns true if the result is a failure (Err) and the callback returns true.
     *
     * @param callable(E): bool $fn
     *
     * @return bool
     */
    public function isErrAnd(callable $fn): bool;

    /**
     * Returns the success value. Throws an exception on failure.
     *
     * @return ($this is Ok<mixed> ? T : never)
     *
     * @throws UnwrapException if $this is Err
     */
    public function unwrap(): mixed;

    /**
     * Returns the error value. Throws an exception on success.
     *
     * @return ($this is Err<mixed> ? E : never)
     *
     * @throws UnwrapException if $this is Ok
     */
    public function unwrapErr(): mixed;

    /**
     * Returns the success value. On failure, throws an exception with the given message.
     *
     * @param string $message the exception message on failure (a summary of the error value is appended)
     *
     * @return ($this is Ok<mixed> ? T : never)
     *
     * @throws UnwrapException if $this is Err
     */
    public function expect(string $message): mixed;

    /**
     * Returns the error value. On success, throws an exception with the given message.
     *
     * @param string $message the exception message on success (a summary of the success value is appended)
     *
     * @return ($this is Err<mixed> ? E : never)
     *
     * @throws UnwrapException if $this is Ok
     */
    public function expectErr(string $message): mixed;

    /**
     * Returns the success value or a default value.
     *
     * @template U
     * @param U $default
     * @return ($this is Ok<mixed> ? T : U)
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * Returns the success value or the result of the closure.
     *
     * @template U
     * @param callable(E): U $fn
     *
     * @return ($this is Ok<mixed> ? T : U)
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * Applies a function to the success value.
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Result<U, E>
     */
    public function map(callable $fn): self;

    /**
     * Applies a function to the error value.
     *
     * @template F
     *
     * @param callable(E): F $fn
     *
     * @return Result<T, F>
     */
    public function mapErr(callable $fn): self;

    /**
     * Applies a side effect to the success value.
     *
     * @param callable(T): void $fn
     *
     * @return Result<T, E>
     */
    public function inspect(callable $fn): self;

    /**
     * Applies a side effect to the error value.
     *
     * @param callable(E): void $fn
     *
     * @return Result<T, E>
     */
    public function inspectErr(callable $fn): self;

    /**
     * Applies a function to the success value, or returns a default value.
     *
     * @template U
     *
     * @param U $default
     * @param callable(T): U $fn
     *
     * @return U
     */
    public function mapOr(mixed $default, callable $fn): mixed;

    /**
     * Applies a function to the success value, or returns the result of the closure.
     *
     * @template U
     *
     * @param callable(): U $defaultFn
     * @param callable(T): U $fn
     *
     * @return U
     */
    public function mapOrElse(callable $defaultFn, callable $fn): mixed;

    /**
     * Returns the second result on success, or the first error on failure.
     *
     * @template U
     * @template F
     *
     * @param Result<U, F> $res
     *
     * @return Result<U, E|F>
     */
    public function and(self $res): self;

    /**
     * Applies a function on success, or returns the current error on failure.
     *
     * The function may return a different error type; the error type is combined into E|F.
     *
     * @template U
     * @template F
     *
     * @param callable(T): Result<U, F> $fn
     *
     * @return Result<U, E|F>
     */
    public function andThen(callable $fn): self;

    /**
     * Returns the second result on failure, or the first value on success.
     *
     * @template U
     * @template F
     *
     * @param Result<U, F> $res
     *
     * @return Result<T|U, F>
     */
    public function or(self $res): self;

    /**
     * Applies a function on failure, or returns the current value on success.
     *
     * The function may return a different success type; the success type is combined into T|U.
     *
     * @template U
     * @template F
     *
     * @param callable(E): Result<U, F> $fn
     *
     * @return Result<T|U, F>
     */
    public function orElse(callable $fn): self;

    /**
     * Applies ok on success, or err on failure.
     *
     * @template U
     * @template V
     *
     * @param callable(T): U $ok the function applied to the success value
     * @param callable(E): V $err the function applied to the error value
     *
     * @return U|V the result of the applied function
     */
    public function match(callable $ok, callable $err): mixed;
}
