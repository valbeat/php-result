<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;
use Valbeat\Result\Result;
use Valbeat\Result\Results;

class ResultsTest extends TestCase
{
    #[Test]
    public function try_whenCallableSucceeds_returns_ok(): void
    {
        $result = Results::try(fn () => 42);
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame(42, $result->unwrap());
    }

    #[Test]
    public function try_whenCallableThrows_returns_err_with_exception(): void
    {
        $exception = new \RuntimeException('boom');
        $result = Results::try(function () use ($exception): int {
            throw $exception;
        });
        $this->assertInstanceOf(Err::class, $result);
        $this->assertSame($exception, $result->unwrapErr());
    }

    #[Test]
    public function try_catches_errors_not_only_exceptions(): void
    {
        $result = Results::try(fn () => intdiv(1, 0));
        $this->assertInstanceOf(Err::class, $result);
        $this->assertInstanceOf(\DivisionByZeroError::class, $result->unwrapErr());
    }

    #[Test]
    public function combine_allOk_returns_ok_with_values_in_order(): void
    {
        $result = Results::combine([new Ok(1), new Ok(2), new Ok(3)]);
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame([1, 2, 3], $result->unwrap());
    }

    #[Test]
    public function combine_withErr_returns_first_err(): void
    {
        $firstErr = new Err('first error');
        $result = Results::combine([new Ok(1), $firstErr, new Ok(3), new Err('second error')]);
        $this->assertSame($firstErr, $result);
    }

    #[Test]
    public function combine_withEmptyIterable_returns_ok_with_empty_array(): void
    {
        /** @var list<Result<int, string>> $results */
        $results = [];
        $result = Results::combine($results);
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame([], $result->unwrap());
    }

    #[Test]
    public function combine_acceptsGenerator(): void
    {
        $results = (static function (): \Generator {
            yield new Ok('a');
            yield new Ok('b');
        })();
        $result = Results::combine($results);
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame(['a', 'b'], $result->unwrap());
    }

    #[Test]
    public function flatten_okOfOk_returns_inner_ok(): void
    {
        $inner = new Ok(42);
        $result = Results::flatten(new Ok($inner));
        $this->assertSame($inner, $result);
    }

    #[Test]
    public function flatten_okOfErr_returns_inner_err(): void
    {
        $inner = new Err('inner error');
        $result = Results::flatten(new Ok($inner));
        $this->assertSame($inner, $result);
    }

    #[Test]
    public function flatten_err_returns_outer_err(): void
    {
        $outer = self::asNestedResult(new Err('outer error'));
        $result = Results::flatten($outer);
        $this->assertSame($outer, $result);
    }

    /**
     * リテラル型を Result<Result<int, string>, string> に widening するためのヘルパ.
     *
     * @param Result<Result<int, string>, string> $result
     *
     * @return Result<Result<int, string>, string>
     */
    private static function asNestedResult(Result $result): Result
    {
        return $result;
    }
}
