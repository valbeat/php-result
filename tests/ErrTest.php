<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;

class ErrTest extends TestCase
{
    public function testIsOkReturnsFalse(): void
    {
        $err = new Err('error');
        $this->assertFalse($err->isOk());
    }

    public function testIsErrReturnsTrue(): void
    {
        $err = new Err('error');
        $this->assertTrue($err->isErr());
    }

    public function testIsOkAndAlwaysReturnsFalse(): void
    {
        $err = new Err('error');
        $result = $err->isOkAnd(fn () => true);
        $this->assertFalse($result);
    }

    public function testIsErrAndReturnsTrueWhenCallbackReturnsTrue(): void
    {
        $err = new Err('critical');
        $result = $err->isErrAnd(fn ($error) => $error === 'critical');
        $this->assertTrue($result);
    }

    public function testIsErrAndReturnsFalseWhenCallbackReturnsFalse(): void
    {
        $err = new Err('warning');
        $result = $err->isErrAnd(fn ($error) => $error === 'critical');
        $this->assertFalse($result);
    }

    public function testUnwrapThrowsException(): void
    {
        $err = new Err('error');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('called Result::unwrap() on an Err value');
        $err->unwrap();
    }

    public function testUnwrapErrReturnsErrorValue(): void
    {
        $err = new Err('error message');
        $this->assertSame('error message', $err->unwrapErr());
    }

    public function testUnwrapErrWithIntValue(): void
    {
        $err = new Err(404);
        $this->assertSame(404, $err->unwrapErr());
    }

    public function testUnwrapErrWithArrayValue(): void
    {
        $error = ['code' => 500, 'message' => 'Internal Server Error'];
        $err = new Err($error);
        $this->assertSame($error, $err->unwrapErr());
    }

    public function testUnwrapErrWithObjectValue(): void
    {
        $error = new \Exception('Test exception');
        $err = new Err($error);
        $this->assertSame($error, $err->unwrapErr());
    }

    public function testUnwrapOrReturnsDefault(): void
    {
        $err = new Err('error');
        $this->assertSame(42, $err->unwrapOr(42));
    }

    public function testUnwrapOrWithDifferentTypes(): void
    {
        $err = new Err('error');
        $this->assertSame('default', $err->unwrapOr('default'));
        $this->assertSame(['default'], $err->unwrapOr(['default']));
    }

    public function testUnwrapOrElseCallsFunction(): void
    {
        $err = new Err('error');
        $result = $err->unwrapOrElse(fn ($error) => "Handled: $error");
        $this->assertSame('Handled: error', $result);
    }

    public function testUnwrapOrElseReceivesErrorValue(): void
    {
        $err = new Err(404);
        $result = $err->unwrapOrElse(fn ($code) => $code === 404 ? 'Not Found' : 'Unknown');
        $this->assertSame('Not Found', $result);
    }

    public function testMapDoesNotApplyFunction(): void
    {
        $err = new Err('error');
        $mapped = $err->map(fn ($x) => $x * 2);
        $this->assertSame($err, $mapped);
        $this->assertSame('error', $mapped->unwrapErr());
    }

    public function testMapErrAppliesFunctionToError(): void
    {
        $err = new Err('error');
        $mapped = $err->mapErr(fn ($e) => strtoupper($e));
        $this->assertInstanceOf(Err::class, $mapped);
        $this->assertSame('ERROR', $mapped->unwrapErr());
    }

    public function testMapErrWithTypeChange(): void
    {
        $err = new Err(404);
        $mapped = $err->mapErr(fn ($code) => "Error code: $code");
        $this->assertInstanceOf(Err::class, $mapped);
        $this->assertSame('Error code: 404', $mapped->unwrapErr());
    }

    public function testInspectDoesNotCallFunction(): void
    {
        $err = new Err('error');
        $called = false;
        $result = $err->inspect(function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        $this->assertSame($err, $result);
    }

    public function testInspectErrCallsFunctionWithError(): void
    {
        $err = new Err('error');
        $capturedError = null;
        $result = $err->inspectErr(function ($error) use (&$capturedError) {
            $capturedError = $error;
        });
        $this->assertSame('error', $capturedError);
        $this->assertSame($err, $result);
    }

    public function testMapOrReturnsDefault(): void
    {
        $err = new Err('error');
        $result = $err->mapOr(100, fn ($x) => $x * 2);
        $this->assertSame(100, $result);
    }

    public function testMapOrElseCallsDefaultFunction(): void
    {
        $err = new Err('error');
        $result = $err->mapOrElse(fn () => 100, fn ($x) => $x * 2);
        $this->assertSame(100, $result);
    }

    public function testAndReturnsSelf(): void
    {
        $err1 = new Err('error1');
        $ok = new Ok(42);
        $result = $err1->and($ok);
        $this->assertSame($err1, $result);
        $this->assertSame('error1', $result->unwrapErr());
    }

    public function testAndWithAnotherErrReturnsSelf(): void
    {
        $err1 = new Err('error1');
        $err2 = new Err('error2');
        $result = $err1->and($err2);
        $this->assertSame($err1, $result);
        $this->assertSame('error1', $result->unwrapErr());
    }

    public function testAndThenReturnsSelf(): void
    {
        $err = new Err('error');
        $result = $err->andThen(fn ($x) => new Ok($x * 2));
        $this->assertSame($err, $result);
        $this->assertSame('error', $result->unwrapErr());
    }

    public function testOrReturnsSecondResult(): void
    {
        $err1 = new Err('error1');
        $ok = new Ok(42);
        $result = $err1->or($ok);
        $this->assertSame($ok, $result);
        $this->assertSame(42, $result->unwrap());
    }

    public function testOrWithAnotherErrReturnsSecondErr(): void
    {
        $err1 = new Err('error1');
        $err2 = new Err('error2');
        $result = $err1->or($err2);
        $this->assertSame($err2, $result);
        $this->assertSame('error2', $result->unwrapErr());
    }

    public function testOrElseCallsFunction(): void
    {
        $err = new Err('error');
        $result = $err->orElse(fn ($e) => new Ok("Recovered from: $e"));
        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame('Recovered from: error', $result->unwrap());
    }

    public function testOrElseCanReturnAnotherErr(): void
    {
        $err = new Err(404);
        $result = $err->orElse(fn ($code) => new Err("HTTP Error: $code"));
        $this->assertInstanceOf(Err::class, $result);
        $this->assertSame('HTTP Error: 404', $result->unwrapErr());
    }

    public function testMatchCallsErrFunction(): void
    {
        $err = new Err('error');
        $result = $err->match(
            fn ($value) => "Success: $value",
            fn ($error) => "Error: $error",
        );
        $this->assertSame('Error: error', $result);
    }

    public function testMatchWithDifferentReturnTypes(): void
    {
        $err = new Err(404);
        $result = $err->match(
            fn ($value) => ['status' => 'ok', 'data' => $value],
            fn ($error) => ['status' => 'error', 'code' => $error],
        );
        $this->assertSame(['status' => 'error', 'code' => 404], $result);
    }

    public function testErrWithNullValue(): void
    {
        $err = new Err(null);
        $this->assertNull($err->unwrapErr());
        $this->assertFalse($err->isOk());
        $this->assertTrue($err->isErr());
    }

    public function testErrWithFalseValue(): void
    {
        $err = new Err(false);
        $this->assertFalse($err->unwrapErr());
        $this->assertTrue($err->isErr());
    }

    public function testErrWithZeroValue(): void
    {
        $err = new Err(0);
        $this->assertSame(0, $err->unwrapErr());
        $this->assertTrue($err->isErr());
    }

    public function testErrWithEmptyStringValue(): void
    {
        $err = new Err('');
        $this->assertSame('', $err->unwrapErr());
        $this->assertTrue($err->isErr());
    }

    public function testChainingOperations(): void
    {
        $err = new Err('initial error');
        $result = $err
            ->mapErr(fn ($e) => strtoupper($e))
            ->orElse(fn ($e) => new Err("[$e]"))
            ->mapErr(fn ($e) => "Final: $e");

        $this->assertInstanceOf(Err::class, $result);
        $this->assertSame('Final: [INITIAL ERROR]', $result->unwrapErr());
    }

    public function testExceptionAsErrorValue(): void
    {
        $exception = new \RuntimeException('Something went wrong');
        $err = new Err($exception);

        $this->assertTrue($err->isErr());
        $this->assertSame($exception, $err->unwrapErr());

        $handled = $err->mapErr(fn ($e) => $e->getMessage());
        $this->assertSame('Something went wrong', $handled->unwrapErr());
    }
}
