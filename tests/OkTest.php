<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;

class OkTest extends TestCase
{
    #[Test]
    public function isOk_returns_true(): void
    {
        $ok = new Ok(42);
        $this->assertTrue($ok->isOk());
    }

    #[Test]
    public function isErr_returns_false(): void
    {
        $ok = new Ok(42);
        $this->assertFalse($ok->isErr());
    }

    #[Test]
    public function isOkAnd_whenCallbackReturnsTrue_returns_true(): void
    {
        $ok = new Ok(10);
        $result = $ok->isOkAnd(fn ($value) => $value > 5);
        $this->assertTrue($result);
    }

    #[Test]
    public function isOkAnd_whenCallbackReturnsFalse_returns_false(): void
    {
        $ok = new Ok(3);
        $result = $ok->isOkAnd(fn ($value) => $value > 5);
        $this->assertFalse($result);
    }

    #[Test]
    public function isErrAnd_always_returns_false(): void
    {
        $ok = new Ok(42);
        $result = $ok->isErrAnd(fn ($value) => true);
        $this->assertFalse($result);
    }

    #[Test]
    public function unwrap_returns_value(): void
    {
        $ok = new Ok(42);
        $this->assertSame(42, $ok->unwrap());
    }

    #[Test]
    public function unwrap_withStringValue_returns_string(): void
    {
        $ok = new Ok('hello');
        $this->assertSame('hello', $ok->unwrap());
    }

    #[Test]
    public function unwrap_withArrayValue_returns_array(): void
    {
        $value = ['foo' => 'bar'];
        $ok = new Ok($value);
        $this->assertSame($value, $ok->unwrap());
    }

    #[Test]
    public function unwrap_withObjectValue_returns_object(): void
    {
        $value = new \stdClass();
        $value->foo = 'bar';
        $ok = new Ok($value);
        $this->assertSame($value, $ok->unwrap());
    }

    #[Test]
    public function unwrapErr_throws_exception(): void
    {
        $ok = new Ok(42);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('called Result::unwrapErr() on an Ok value');
        $ok->unwrapErr();
    }

    #[Test]
    public function unwrapOr_returns_value(): void
    {
        $ok = new Ok(42);
        $this->assertSame(42, $ok->unwrapOr(100));
    }

    #[Test]
    public function unwrapOrElse_returns_value(): void
    {
        $ok = new Ok(42);
        $result = $ok->unwrapOrElse(fn () => 100);
        $this->assertSame(42, $result);
    }

    #[Test]
    public function map_applies_function_to_value(): void
    {
        $ok = new Ok(10);
        $mapped = $ok->map(fn ($x) => $x * 2);
        $this->assertInstanceOf(Ok::class, $mapped);
        $this->assertSame(20, $mapped->unwrap());
    }

    #[Test]
    public function map_withTypeChange_transforms_type(): void
    {
        $ok = new Ok(42);
        $mapped = $ok->map(fn ($x) => "Value is: $x");
        $this->assertInstanceOf(Ok::class, $mapped);
        $this->assertSame('Value is: 42', $mapped->unwrap());
    }

    #[Test]
    public function mapErr_does_nothing(): void
    {
        $ok = new Ok(42);
        $mapped = $ok->mapErr(fn ($x) => $x * 2);
        $this->assertSame($ok, $mapped);
        $this->assertSame(42, $mapped->unwrap());
    }

    #[Test]
    public function inspect_calls_function_with_value(): void
    {
        $ok = new Ok(42);
        $capturedValue = null;
        $result = $ok->inspect(function ($value) use (&$capturedValue) {
            $capturedValue = $value;
        });
        $this->assertSame(42, $capturedValue);
        $this->assertSame($ok, $result);
    }

    #[Test]
    public function inspectErr_does_not_call_function(): void
    {
        $ok = new Ok(42);
        $called = false;
        $result = $ok->inspectErr(function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        $this->assertSame($ok, $result);
    }

    #[Test]
    public function mapOr_applies_function(): void
    {
        $ok = new Ok(10);
        $result = $ok->mapOr(100, fn ($x) => $x * 2);
        $this->assertSame(20, $result);
    }

    #[Test]
    public function mapOrElse_applies_function(): void
    {
        $ok = new Ok(10);
        $result = $ok->mapOrElse(fn () => 100, fn ($x) => $x * 2);
        $this->assertSame(20, $result);
    }

    #[Test]
    public function and_returns_second_result(): void
    {
        $ok1 = new Ok(42);
        $ok2 = new Ok('hello');
        $result = $ok1->and($ok2);
        $this->assertSame($ok2, $result);
        $this->assertSame('hello', $result->unwrap());
    }

    #[Test]
    public function andThen_applies_function(): void
    {
        $ok = new Ok(10);
        $result = $ok->andThen(fn ($x) => new Ok($x * 2));
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame(20, $result->unwrap());
    }

    #[Test]
    public function or_returns_self(): void
    {
        $ok1 = new Ok(42);
        $ok2 = new Ok(100);
        $result = $ok1->or($ok2);
        $this->assertSame($ok1, $result);
        $this->assertSame(42, $result->unwrap());
    }

    #[Test]
    public function or_withErr_returns_self(): void
    {
        $ok = new Ok(42);
        $err = new Err('error');
        $result = $ok->or($err);
        $this->assertSame($ok, $result);
        $this->assertSame(42, $result->unwrap());
    }

    #[Test]
    public function orElse_returns_self(): void
    {
        $ok = new Ok(42);
        $result = $ok->orElse(fn () => new Ok(100));
        $this->assertSame($ok, $result);
        $this->assertSame(42, $result->unwrap());
    }

    #[Test]
    public function match_calls_ok_function(): void
    {
        $ok = new Ok(42);
        $result = $ok->match(
            fn ($value) => "Success: $value",
            fn ($error) => "Error: $error",
        );
        $this->assertSame('Success: 42', $result);
    }

    #[Test]
    public function match_withDifferentReturnTypes_returns_ok_branch(): void
    {
        $ok = new Ok('hello');
        $result = $ok->match(
            fn ($value) => \strlen($value),
            fn ($error) => -1,
        );
        $this->assertSame(5, $result);
    }

    #[Test]
    public function ok_withNullValue_handles_null(): void
    {
        $ok = new Ok(null);
        $this->assertNull($ok->unwrap());
        $this->assertTrue($ok->isOk());
        $this->assertFalse($ok->isErr());
    }

    #[Test]
    public function ok_withFalseValue_handles_false(): void
    {
        $ok = new Ok(false);
        $this->assertFalse($ok->unwrap());
        $this->assertTrue($ok->isOk());
    }

    #[Test]
    public function ok_withZeroValue_handles_zero(): void
    {
        $ok = new Ok(0);
        $this->assertSame(0, $ok->unwrap());
        $this->assertTrue($ok->isOk());
    }

    #[Test]
    public function ok_withEmptyString_handles_empty_string(): void
    {
        $ok = new Ok('');
        $this->assertSame('', $ok->unwrap());
        $this->assertTrue($ok->isOk());
    }

    #[Test]
    public function chainingOperations_applies_transformations(): void
    {
        $ok = new Ok(10);
        $result = $ok
            ->map(fn ($x) => $x * 2)
            ->andThen(fn ($x) => new Ok($x + 5))
            ->map(fn ($x) => $x - 3);

        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame(22, $result->unwrap()); // (10 * 2) + 5 - 3 = 22
    }
}