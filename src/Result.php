<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * Result型は、成功（Ok）または失敗（Err）を表現します。
 *
 * ## 基本的な使い方
 *
 * ```php
 * use Valbeat\Result\Ok;
 * use Valbeat\Result\Err;
 *
 * function divide(float $x, float $y): Result {
 *     if ($y === 0.0) {
 *         return new Err('Division by zero');
 *     }
 *     return new Ok($x / $y);
 * }
 *
 * $result = divide(10, 2);
 * if ($result->isOk()) {
 *     echo "Result: " . $result->unwrap(); // Result: 5
 * }
 * ```
 *
 * @template T 成功時の値の型
 * @template E 失敗時のエラーの型
 */
interface Result
{
    /**
     * 結果が成功（Ok）の場合に true を返します.
     *
     * ## Example
     *
     * ```php
     * $ok = new Ok(42);
     * assert($ok->isOk() === true);
     *
     * $err = new Err('error');
     * assert($err->isOk() === false);
     * ```
     *
     * @phpstan-assert-if-true Ok<T> $this
     *
     * @return bool
     */
    public function isOk(): bool;

    /**
     * 結果が成功（Ok）でありコールバックが true を返す場合に true を返します.
     *
     * @param callable(T): bool $fn
     *
     * @return bool
     */
    public function isOkAnd(callable $fn): bool;

    /**
     * 結果が失敗（Err）の場合に true を返します.
     *
     * ## Example
     *
     * ```php
     * $ok = new Ok(42);
     * assert($ok->isErr() === false);
     *
     * $err = new Err('error');
     * assert($err->isErr() === true);
     * ```
     *
     * @phpstan-assert-if-true Err<E> $this
     *
     * @return bool
     */
    public function isErr(): bool;

    /**
     * 結果が失敗（Err）でありコールバックが true を返す場合に true を返します.
     *
     * @param callable(E): bool $fn
     *
     * @return bool
     */
    public function isErrAnd(callable $fn): bool;

    /**
     * 成功値を返します。失敗の場合は例外を投げます.
     *
     * @return T
     */
    public function unwrap(): mixed;

    /**
     * エラー値を返します。成功の場合は例外を投げます.
     *
     * @return E
     */
    public function unwrapErr(): mixed;

    /**
     * 成功値またはデフォルト値を返します.
     *
     * @template U
     * @param U $default
     * @return T|U
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * 成功値またはクロージャーの結果を返します.
     *
     * @template U
     * @param callable(E): U $fn
     *
     * @return T|U
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * 成功値に関数を適用します.
     *
     * ## Example
     *
     * ```php
     * $result = new Ok(10);
     * $doubled = $result->map(fn($x) => $x * 2);
     * assert($doubled->unwrap() === 20);
     *
     * $error = new Err('failed');
     * $mapped = $error->map(fn($x) => $x * 2);
     * assert($mapped->isErr() === true);
     * ```
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Result<U, E>
     */
    public function map(callable $fn): self;

    /**
     * エラー値に関数を適用します.
     *
     * @template F
     *
     * @param callable(E): F $fn
     *
     * @return Result<T, F>
     */
    public function mapErr(callable $fn): self;

    /**
     * 成功値に副作用を適用します.
     *
     * @param callable(T): void $fn
     *
     * @return Result<T, E>
     */
    public function inspect(callable $fn): self;

    /**
     * エラー値に副作用を適用します.
     *
     * @param callable(E): void $fn
     *
     * @return Result<T, E>
     */
    public function inspectErr(callable $fn): self;

    /**
     * 成功値に関数を適用するか、デフォルト値を返します.
     *
     * @template U
     *
     * @param U $default
     * @param callable(T): U $fn
     *
     * @return T|U
     */
    public function mapOr(mixed $default, callable $fn): mixed;

    /**
     * 成功値に関数を適用するか、クロージャーの結果を返します.
     *
     * @template U
     *
     * @param callable(): U $default_fn
     * @param callable(T): U $fn
     *
     * @return U
     */
    public function mapOrElse(callable $default_fn, callable $fn): mixed;

    /**
     * 成功の場合は第2の結果を返し、失敗の場合は最初のエラーを返します.
     *
     * @template U
     *
     * @param Result<U, E> $res
     *
     * @return Result<U, E>
     */
    public function and(self $res): self;

    /**
     * 成功の場合は関数を適用し、失敗の場合は現在のエラーを返します.
     *
     * ## Example
     *
     * ```php
     * function checkPositive(int $x): Result {
     *     return $x > 0
     *         ? new Ok($x)
     *         : new Err('Must be positive');
     * }
     *
     * $result = new Ok(10)
     *     ->andThen(fn($x) => checkPositive($x - 5))
     *     ->andThen(fn($x) => new Ok($x * 2));
     * assert($result->unwrap() === 10);
     * ```
     *
     * @template U
     *
     * @param callable(T): Result<U, E> $fn
     *
     * @return Result<U, E>
     */
    public function andThen(callable $fn): self;

    /**
     * 失敗の場合は第2の結果を返し、成功の場合は最初の値を返します.
     *
     * @template F
     *
     * @param Result<T, F> $res
     *
     * @return Result<T, F>
     */
    public function or(self $res): self;

    /**
     * 失敗の場合は関数を適用し、成功の場合は現在の値を返します.
     *
     * @template F
     *
     * @param callable(E): Result<T, F> $fn
     *
     * @return Result<T, F>
     */
    public function orElse(callable $fn): self;

    /**
     * 成功の場合はok_fnを、失敗の場合はerr_fnを適用します.
     * RustのResult型のmatch式に相当する機能です.
     *
     * ## Example
     *
     * ```php
     * function processResult(Result $result): string {
     *     return $result->match(
     *         fn($value) => "Success: $value",
     *         fn($error) => "Error: $error"
     *     );
     * }
     *
     * assert(processResult(new Ok(42)) === 'Success: 42');
     * assert(processResult(new Err('failed')) === 'Error: failed');
     * ```
     *
     * @template U
     * @template V
     *
     * @param callable(T): U $ok_fn 成功値に適用する関数
     * @param callable(E): V $err_fn エラー値に適用する関数
     *
     * @return U|V 適用された関数の結果
     */
    public function match(callable $ok_fn, callable $err_fn): mixed;
}
