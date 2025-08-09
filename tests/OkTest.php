<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;

class OkTest extends TestCase
{
    public function testIsOkReturnsTrue(): void
    {
        $ok = new Ok(42);
        $this->assertTrue($ok->isOk());
    }

    public function testIsErrReturnsFalse(): void
    {
        $ok = new Ok(42);
        $this->assertFalse($ok->isErr());
    }

    public function testIsOkAndReturnsTrueWhenCallbackReturnsTrue(): void
    {
        $ok = new Ok(10);
        $result = $ok->isOkAnd(fn ($value) => $value > 5);
        $this->assertTrue($result);
    }

    public function testIsOkAndReturnsFalseWhenCallbackReturnsFalse(): void
    {
        $ok = new Ok(3);
        $result = $ok->isOkAnd(fn ($value) => $value > 5);
        $this->assertFalse($result);
    }

    public function testIsErrAndAlwaysReturnsFalse(): void
    {
        $ok = new Ok(42);
        $result = $ok->isErrAnd(fn ($value) => true);
        $this->assertFalse($result);
    }

    public function testUnwrapReturnsValue(): void
    {
        $ok = new Ok(42);
        $this->assertSame(42, $ok->unwrap());
    }

    public function testUnwrapWithStringValue(): void
    {
        $ok = new Ok('hello');
        $this->assertSame('hello', $ok->unwrap());
    }

    public function testUnwrapWithArrayValue(): void
    {
        $value = ['foo' => 'bar'];
        $ok = new Ok($value);
        $this->assertSame($value, $ok->unwrap());
    }

    public function testUnwrapWithObjectValue(): void
    {
        $value = new \stdClass();
        $value->foo = 'bar';
        $ok = new Ok($value);
        $this->assertSame($value, $ok->unwrap());
    }

    public function testUnwrapErrThrowsException(): void
    {
        $ok = new Ok(42);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('called Result::unwrapErr() on an Ok value');
        $ok->unwrapErr();
    }

    public function testUnwrapOrReturnsValue(): void
    {
        $ok = new Ok(42);
        $this->assertSame(42, $ok->unwrapOr(100));
    }

    public function testUnwrapOrElseReturnsValue(): void
    {
        $ok = new Ok(42);
        $result = $ok->unwrapOrElse(fn () => 100);
        $this->assertSame(42, $result);
    }

    public function testMapAppliesFunctionToValue(): void
    {
        $ok = new Ok(10);
        $mapped = $ok->map(fn ($x) => $x * 2);
        $this->assertInstanceOf(Ok::class, $mapped);
        $this->assertSame(20, $mapped->unwrap());
    }

    public function testMapWithTypeChange(): void
    {
        $ok = new Ok(42);
        $mapped = $ok->map(fn ($x) => "Value is: $x");
        $this->assertInstanceOf(Ok::class, $mapped);
        $this->assertSame('Value is: 42', $mapped->unwrap());
    }

    public function testMapErrDoesNothing(): void
    {
        $ok = new Ok(42);
        $mapped = $ok->mapErr(fn ($x) => $x * 2);
        $this->assertSame($ok, $mapped);
        $this->assertSame(42, $mapped->unwrap());
    }

    public function testInspectCallsFunctionWithValue(): void
    {
        $ok = new Ok(42);
        $capturedValue = null;
        $result = $ok->inspect(function ($value) use (&$capturedValue) {
            $capturedValue = $value;
        });
        $this->assertSame(42, $capturedValue);
        $this->assertSame($ok, $result);
    }

    public function testInspectErrDoesNotCallFunction(): void
    {
        $ok = new Ok(42);
        $called = false;
        $result = $ok->inspectErr(function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        $this->assertSame($ok, $result);
    }

    public function testMapOrAppliesFunction(): void
    {
        $ok = new Ok(10);
        $result = $ok->mapOr(100, fn ($x) => $x * 2);
        $this->assertSame(20, $result);
    }

    public function testMapOrElseAppliesFunction(): void
    {
        $ok = new Ok(10);
        $result = $ok->mapOrElse(fn () => 100, fn ($x) => $x * 2);
        $this->assertSame(20, $result);
    }

    public function testAndReturnsSecondResult(): void
    {
        $ok1 = new Ok(42);
        $ok2 = new Ok('hello');
        $result = $ok1->and($ok2);
        $this->assertSame($ok2, $result);
        $this->assertSame('hello', $result->unwrap());
    }

    public function testAndThenAppliesFunction(): void
    {
        $ok = new Ok(10);
        $result = $ok->andThen(fn ($x) => new Ok($x * 2));
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame(20, $result->unwrap());
    }

    public function testOrReturnsSelf(): void
    {
        $ok1 = new Ok(42);
        $ok2 = new Ok(100);
        $result = $ok1->or($ok2);
        $this->assertSame($ok1, $result);
        $this->assertSame(42, $result->unwrap());
    }

    public function testOrWithErrReturnsSelf(): void
    {
        $ok = new Ok(42);
        $err = new Err('error');
        $result = $ok->or($err);
        $this->assertSame($ok, $result);
        $this->assertSame(42, $result->unwrap());
    }

    public function testOrElseReturnsSelf(): void
    {
        $ok = new Ok(42);
        $result = $ok->orElse(fn () => new Ok(100));
        $this->assertSame($ok, $result);
        $this->assertSame(42, $result->unwrap());
    }

    public function testMatchCallsOkFunction(): void
    {
        $ok = new Ok(42);
        $result = $ok->match(
            fn ($value) => "Success: $value",
            fn ($error) => "Error: $error",
        );
        $this->assertSame('Success: 42', $result);
    }

    public function testMatchWithDifferentReturnTypes(): void
    {
        $ok = new Ok('hello');
        $result = $ok->match(
            fn ($value) => \strlen($value),
            fn ($error) => -1,
        );
        $this->assertSame(5, $result);
    }

    public function testOkWithNullValue(): void
    {
        $ok = new Ok(null);
        $this->assertNull($ok->unwrap());
        $this->assertTrue($ok->isOk());
        $this->assertFalse($ok->isErr());
    }

    public function testOkWithFalseValue(): void
    {
        $ok = new Ok(false);
        $this->assertFalse($ok->unwrap());
        $this->assertTrue($ok->isOk());
    }

    public function testOkWithZeroValue(): void
    {
        $ok = new Ok(0);
        $this->assertSame(0, $ok->unwrap());
        $this->assertTrue($ok->isOk());
    }

    public function testOkWithEmptyStringValue(): void
    {
        $ok = new Ok('');
        $this->assertSame('', $ok->unwrap());
        $this->assertTrue($ok->isOk());
    }

    public function testChainingOperations(): void
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
