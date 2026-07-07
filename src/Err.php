<?php

declare(strict_types=1);

namespace Valbeat\Result;

use Override;

/**
 * Err はエラー値を表します.
 *
 * @template-covariant E
 *
 * @implements Result<never, E>
 */
final readonly class Err implements Result
{
    /**
     * @param E $value
     */
    public function __construct(
        private mixed $value,
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
        throw UnwrapException::unwrapOnErr($this->value);
    }

    /**
     * @return E
     */
    #[Override]
    public function unwrapErr(): mixed
    {
        return $this->value;
    }

    #[Override]
    public function expect(string $message): never
    {
        throw UnwrapException::withMessage($message, $this->value);
    }

    /**
     * @return E
     */
    #[Override]
    public function expectErr(string $message): mixed
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

    /**
     * @return $this
     */
    #[Override]
    public function map(callable $fn): Result
    {
        return $this;
    }

    /**
     * @template F
     *
     * @param callable(E): F $fn
     *
     * @return Err<F>
     */
    #[Override]
    public function mapErr(callable $fn): Result
    {
        return new self($fn($this->value));
    }

    /**
     * @return $this
     */
    #[Override]
    public function inspect(callable $fn): Result
    {
        return $this;
    }

    /**
     * @return $this
     */
    #[Override]
    public function inspectErr(callable $fn): Result
    {
        $fn($this->value);

        return $this;
    }

    /**
     * @template U
     * @template V
     *
     * @param U $default
     * @param callable(never): V $fn
     *
     * @return U
     */
    #[Override]
    public function mapOr(mixed $default, callable $fn): mixed
    {
        return $default;
    }

    /**
     * @template U
     * @template V
     *
     * @param callable(): U $defaultFn
     * @param callable(never): V $fn
     *
     * @return U
     */
    #[Override]
    public function mapOrElse(callable $defaultFn, callable $fn): mixed
    {
        return $defaultFn();
    }

    /**
     * @return $this
     */
    #[Override]
    public function and(Result $res): Result
    {
        return $this;
    }

    /**
     * @return $this
     */
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
    public function match(callable $ok, callable $err): mixed
    {
        return $err($this->value);
    }
}
