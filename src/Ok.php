<?php

declare(strict_types=1);

namespace Valbeat\Result;

use Override;

/**
 * Ok は成功値を表します.
 *
 * @template-covariant T
 *
 * @implements Result<T, never>
 */
final readonly class Ok implements Result
{
    /**
     * @param T $value
     */
    public function __construct(
        private mixed $value,
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
        throw UnwrapException::unwrapErrOnOk($this->value);
    }

    /**
     * @return T
     */
    #[Override]
    public function expect(string $message): mixed
    {
        return $this->value;
    }

    #[Override]
    public function expectErr(string $message): never
    {
        throw UnwrapException::withMessage($message, $this->value);
    }

    /**
     * @template U
     * @param U $default
     * @return T
     */
    #[Override]
    public function unwrapOr(mixed $default): mixed
    {
        return $this->value;
    }

    /**
     * @template U
     * @param callable(never): U $fn
     *
     * @return T
     */
    #[Override]
    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->value;
    }

    /**
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return Ok<U>
     */
    #[Override]
    public function map(callable $fn): Result
    {
        return new self($fn($this->value));
    }

    /**
     * @return $this
     */
    #[Override]
    public function mapErr(callable $fn): Result
    {
        return $this;
    }

    /**
     * @return $this
     */
    #[Override]
    public function inspect(callable $fn): Result
    {
        $fn($this->value);

        return $this;
    }

    /**
     * @return $this
     */
    #[Override]
    public function inspectErr(callable $fn): Result
    {
        return $this;
    }

    /**
     * @template U
     * @template V
     *
     * @param V $default
     * @param callable(T): U $fn
     *
     * @return U
     */
    #[Override]
    public function mapOr(mixed $default, callable $fn): mixed
    {
        return $fn($this->value);
    }

    /**
     * @template U
     * @template V
     *
     * @param callable(): V $defaultFn
     * @param callable(T): U $fn
     *
     * @return U
     */
    #[Override]
    public function mapOrElse(callable $defaultFn, callable $fn): mixed
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

    /**
     * @return $this
     */
    #[Override]
    public function or(Result $res): Result
    {
        return $this;
    }

    /**
     * @return $this
     */
    #[Override]
    public function orElse(callable $fn): Result
    {
        return $this;
    }

    #[Override]
    public function match(callable $ok, callable $err): mixed
    {
        return $ok($this->value);
    }
}
