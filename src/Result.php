<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * Result型は、成功（Ok）または失敗（Err）を表現します。
 *
 * 注意: instanceof による絞り込みでは型引数が失われます（PHPStan の既知の制限。
 * Result<int, E> が型引数なしの Ok になり unwrap() は mixed になる）。
 * 値を取り出す分岐では isOk() / isErr() で絞り込んでください。
 *
 * @template-covariant T 成功時の値の型
 * @template-covariant E 失敗時のエラーの型
 *
 * @phpstan-sealed Ok|Err
 */
interface Result
{
    /**
     * 結果が成功（Ok）の場合に true を返します.
     *
     * @phpstan-assert-if-true Ok<T> $this
     * @phpstan-assert-if-false Err<E> $this
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
     * @phpstan-assert-if-true Err<E> $this
     * @phpstan-assert-if-false Ok<T> $this
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
     * @return ($this is Ok<mixed> ? T : never)
     *
     * @throws UnwrapException $this が Err の場合
     */
    public function unwrap(): mixed;

    /**
     * エラー値を返します。成功の場合は例外を投げます.
     *
     * @return ($this is Err<mixed> ? E : never)
     *
     * @throws UnwrapException $this が Ok の場合
     */
    public function unwrapErr(): mixed;

    /**
     * 成功値を返します。失敗の場合は指定したメッセージで例外を投げます.
     *
     * @param string $message 失敗時の例外メッセージ
     *
     * @return ($this is Ok<mixed> ? T : never)
     */
    public function expect(string $message): mixed;

    /**
     * エラー値を返します。成功の場合は指定したメッセージで例外を投げます.
     *
     * @param string $message 成功時の例外メッセージ
     *
     * @return ($this is Err<mixed> ? E : never)
     */
    public function expectErr(string $message): mixed;

    /**
     * 成功値またはデフォルト値を返します.
     *
     * @template U
     * @param U $default
     * @return ($this is Ok<mixed> ? T : U)
     */
    public function unwrapOr(mixed $default): mixed;

    /**
     * 成功値またはクロージャーの結果を返します.
     *
     * @template U
     * @param callable(E): U $fn
     *
     * @return ($this is Ok<mixed> ? T : U)
     */
    public function unwrapOrElse(callable $fn): mixed;

    /**
     * 成功値に関数を適用します.
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
     * @return U
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
     * @template F
     *
     * @param Result<U, F> $res
     *
     * @return Result<U, E|F>
     */
    public function and(self $res): self;

    /**
     * 成功の場合は関数を適用し、失敗の場合は現在のエラーを返します.
     *
     * 関数は元と異なるエラー型を返せます。エラー型は E|F に合成されます.
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
     * 失敗の場合は第2の結果を返し、成功の場合は最初の値を返します.
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
     * 失敗の場合は関数を適用し、成功の場合は現在の値を返します.
     *
     * 関数は元と異なる成功型を返せます。成功型は T|U に合成されます.
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
     * 成功の場合はok_fnを、失敗の場合はerr_fnを適用します.
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
