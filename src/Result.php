<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * Result型は、成功（Ok）または失敗（Err）を表現します。
 *
 * @template T 成功時の値の型
 * @template E 失敗時のエラーの型
 */
abstract readonly class Result
{
    /**
     * 結果が成功（Ok）の場合に true を返します.
     *
     * @phpstan-assert-if-true Ok<T> $this
     *
     * @return bool
     */
    abstract public function isOk(): bool;

    /**
     * 結果が成功（Ok）でありコールバックが true を返す場合に true を返します.
     *
     * @param callable(T): bool $fn
     *
     * @return bool
     */
    abstract public function isOkAnd(callable $fn): bool;

    /**
     * 結果が失敗（Err）の場合に true を返します.
     *
     * @phpstan-assert-if-true Err<E> $this
     *
     * @return bool
     */
    abstract public function isErr(): bool;

    /**
     * 結果が失敗（Err）でありコールバックが true を返す場合に true を返します.
     *
     * @param callable(E): bool $fn
     *
     * @return bool
     */
    abstract public function isErrAnd(callable $fn): bool;

    /**
     * 成功値を返します。失敗の場合は例外を投げます.
     *
     * @return T
     */
    abstract public function unwrap(): mixed;

    /**
     * エラー値を返します。成功の場合は例外を投げます.
     *
     * @return E
     */
    abstract public function unwrapErr(): mixed;

    /**
     * 成功値またはデフォルト値を返します.
     *
     * @template U
     * @param U $default
     * @return T|U
     */
    abstract public function unwrapOr(mixed $default): mixed;

    /**
     * 成功値またはクロージャーの結果を返します.
     *
     * @template U
     * @param callable(E): U $fn
     *
     * @return T|U
     */
    abstract public function unwrapOrElse(callable $fn): mixed;

    /**
     * 成功値に関数を適用します.
     *
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Result<U, E>
     */
    abstract public function map(callable $fn): self;

    /**
     * エラー値に関数を適用します.
     *
     * @template F
     *
     * @param callable(E): F $fn
     *
     * @return Result<T, F>
     */
    abstract public function mapErr(callable $fn): self;

    /**
     * 成功値に副作用を適用します.
     *
     * @param callable(T): void $fn
     *
     * @return Result<T, E>
     */
    abstract public function inspect(callable $fn): self;

    /**
     * エラー値に副作用を適用します.
     *
     * @param callable(E): void $fn
     *
     * @return Result<T, E>
     */
    abstract public function inspectErr(callable $fn): self;

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
    abstract public function mapOr(mixed $default, callable $fn): mixed;

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
    abstract public function mapOrElse(callable $default_fn, callable $fn): mixed;

    /**
     * 成功の場合は第2の結果を返し、失敗の場合は最初のエラーを返します.
     *
     * @template U
     *
     * @param Result<U, E> $res
     *
     * @return Result<U, E>
     */
    abstract public function and(self $res): self;

    /**
     * 成功の場合は関数を適用し、失敗の場合は現在のエラーを返します.
     *
     * @template U
     *
     * @param callable(T): Result<U, E> $fn
     *
     * @return Result<U, E>
     */
    abstract public function andThen(callable $fn): self;

    /**
     * 失敗の場合は第2の結果を返し、成功の場合は最初の値を返します.
     *
     * @template F
     *
     * @param Result<T, F> $res
     *
     * @return Result<T, F>
     */
    abstract public function or(self $res): self;

    /**
     * 失敗の場合は関数を適用し、成功の場合は現在の値を返します.
     *
     * @template F
     *
     * @param callable(E): Result<T, F> $fn
     *
     * @return Result<T, F>
     */
    abstract public function orElse(callable $fn): self;

    /**
     * 成功の場合はok_fnを、失敗の場合はerr_fnを適用します.
     * RustのResult型のmatch式に相当する機能です.
     *
     * @template U
     * @template V
     *
     * @param callable(T): U $ok_fn 成功値に適用する関数
     * @param callable(E): V $err_fn エラー値に適用する関数
     *
     * @return U|V 適用された関数の結果
     */
    abstract public function match(callable $ok_fn, callable $err_fn): mixed;
}
