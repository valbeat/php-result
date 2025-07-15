<?php

declare(strict_types=1);

namespace Package\Domain\Result;

use Override;

/**
 * Err はエラー値を表します.
 *
 * @template E
 *
 * @extends Result<never, E>
 */
final readonly class Err extends Result
{
    /**
     * @param E $value
     */
    public function __construct(
        private mixed $value
    ) {
    }

    #[Override]
    public function isOk(): bool
    {
        return false;
    }

    #[Override]
    public function isOkAnd(callable $fn): bool
    {
        return false;
    }

    #[Override]
    public function isErr(): bool
    {
        return true;
    }

    #[Override]
    public function isErrAnd(callable $fn): bool
    {
        return $fn($this->value);
    }

    #[Override]
    public function unwrap(): never
    {
        throw new \LogicException('called Result::unwrap() on an Err value');
    }

    /**
     * @return E
     */
    #[Override]
    public function unwrapErr(): mixed
    {
        return $this->value;
    }

    /**
     * @template U
     * @param U $default
     *
     * @return U
     */
    #[Override]
    public function unwrapOr(mixed $default): mixed
    {
        /** @var U */
        return $default;
    }

    /**
     * @template U
     * @param callable(E): U $fn
     * @return U
     */
    #[Override]
    public function unwrapOrElse(callable $fn): mixed
    {
        return $fn($this->value);
    }

    #[Override]
    public function map(callable $fn): Result
    {
        return $this;
    }

    #[Override]
    public function mapErr(callable $fn): Result
    {
        return new self($fn($this->value));
    }

    #[Override]
    public function inspect(callable $fn): Result
    {
        return $this;
    }

    #[Override]
    public function inspectErr(callable $fn): Result
    {
        $fn($this->value);

        return $this;
    }

    #[Override]
    public function mapOr(mixed $default, callable $fn): mixed
    {
        return $default;
    }

    #[Override]
    public function mapOrElse(callable $default_fn, callable $fn): mixed
    {
        return $default_fn();
    }

    #[Override]
    public function and(Result $res): Result
    {
        return $this;
    }

    #[Override]
    public function andThen(callable $fn): Result
    {
        return $this;
    }

    #[Override]
    public function or(Result $res): Result
    {
        return $res;
    }

    #[Override]
    public function orElse(callable $fn): Result
    {
        return $fn($this->value);
    }

    #[Override]
    public function match(callable $ok_fn, callable $err_fn): mixed
    {
        return $err_fn($this->value);
    }
}
