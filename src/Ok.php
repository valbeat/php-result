<?php

declare(strict_types=1);

namespace Package\Domain\Result;

use Override;

/**
 * Ok は成功値を表します.
 *
 * @template T
 *
 * @extends Result<T, never>
 */
final readonly class Ok extends Result
{
    /**
     * @param T $value
     */
    public function __construct(
        private mixed $value
    ) {
    }

    #[Override]
    public function isOk(): bool
    {
        return true;
    }

    #[Override]
    public function isOkAnd(callable $fn): bool
    {
        return $fn($this->value);
    }

    #[Override]
    public function isErr(): bool
    {
        return false;
    }

    #[Override]
    public function isErrAnd(callable $fn): bool
    {
        return false;
    }

    /**
     * @return T
     */
    #[Override]
    public function unwrap(): mixed
    {
        return $this->value;
    }

    #[Override]
    public function unwrapErr(): never
    {
        throw new \RuntimeException('called Result::unwrapErr() on an Ok value');
    }

    /**
     * @param T $default_value
     *
     * @return T
     */
    #[Override]
    public function unwrapOr(mixed $default_value): mixed
    {
        return $this->value;
    }

    /**
     * @param callable(): T $op
     *
     * @return T
     */
    #[Override]
    public function unwrapOrElse(callable $op): mixed
    {
        return $this->value;
    }

    #[Override]
    public function map(callable $fn): Result
    {
        return new self($fn($this->value));
    }

    #[Override]
    public function mapErr(callable $fn): Result
    {
        return $this;
    }

    #[Override]
    public function inspect(callable $fn): Result
    {
        $fn($this->value);

        return $this;
    }

    #[Override]
    public function inspectErr(callable $fn): Result
    {
        return $this;
    }

    /**
     * @template U
     *
     * @param U              $default_value
     * @param callable(T): U $fn
     *
     * @return U
     */
    #[Override]
    public function mapOr(mixed $default_value, callable $fn): mixed
    {
        return $fn($this->value);
    }

    /**
     * @template U
     *
     * @param callable(): U  $default_fn
     * @param callable(T): U $fn
     *
     * @return U
     */
    #[Override]
    public function mapOrElse(callable $default_fn, callable $fn): mixed
    {
        return $fn($this->value);
    }

    #[Override]
    public function and(Result $res): Result
    {
        return $res;
    }

    #[Override]
    public function andThen(callable $fn): Result
    {
        return $fn($this->value);
    }

    #[Override]
    public function or(Result $res): Result
    {
        return $this;
    }

    #[Override]
    public function orElse(callable $fn): Result
    {
        return $this;
    }

    #[Override]
    public function match(callable $ok_fn, callable $err_fn): mixed
    {
        return $ok_fn($this->value);
    }
}
