<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;

class ErrTest extends TestCase
{
    #[Test]
    public function isOk_returns_false(): void
    {
        $err = new Err('error');
        $this->assertFalse($err->isOk());
    }

    #[Test]
    public function isErr_returns_true(): void
    {
        $err = new Err('error');
        $this->assertTrue($err->isErr());
    }

    #[Test]
    public function isOkAnd_always_returns_false(): void
    {
        $err = new Err('error');
        $result = $err->isOkAnd(fn () => true);
        $this->assertFalse($result);
    }

    #[Test]
    public function isErrAnd_whenCallbackReturnsTrue_returns_true(): void
    {
        $err = new Err('critical');
        $result = $err->isErrAnd(fn ($error) => $error === 'critical');
        $this->assertTrue($result);
    }

    #[Test]
    public function isErrAnd_whenCallbackReturnsFalse_returns_false(): void
    {
        $err = new Err('warning');
        $result = $err->isErrAnd(fn ($error) => $error === 'critical');
        $this->assertFalse($result);
    }

    #[Test]
    public function unwrap_throws_exception(): void
    {
        $err = new Err('error');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('called Result::unwrap() on an Err value');
        $err->unwrap();
    }

    #[Test]
    public function unwrapErr_returns_error_value(): void
    {
        $err = new Err('error message');
        $this->assertSame('error message', $err->unwrapErr());
    }

    #[Test]
    public function unwrapErr_withIntValue_returns_int(): void
    {
        $err = new Err(404);
        $this->assertSame(404, $err->unwrapErr());
    }

    #[Test]
    public function unwrapErr_withArrayValue_returns_array(): void
    {
        $error = ['code' => 500, 'message' => 'Internal Server Error'];
        $err = new Err($error);
        $this->assertSame($error, $err->unwrapErr());
    }

    #[Test]
    public function unwrapErr_withObjectValue_returns_object(): void
    {
        $error = new \Exception('Test exception');
        $err = new Err($error);
        $this->assertSame($error, $err->unwrapErr());
    }

    #[Test]
    public function unwrapOr_returns_default(): void
    {
        $err = new Err('error');
        $this->assertSame(42, $err->unwrapOr(42));
    }

    #[Test]
    public function unwrapOr_withDifferentTypes_returns_default(): void
    {
        $err = new Err('error');
        $this->assertSame('default', $err->unwrapOr('default'));
        $this->assertSame(['default'], $err->unwrapOr(['default']));
    }

    #[Test]
    public function unwrapOrElse_calls_function(): void
    {
        $err = new Err('error');
        $result = $err->unwrapOrElse(fn ($error) => "Handled: $error");
        $this->assertSame('Handled: error', $result);
    }

    #[Test]
    public function unwrapOrElse_receives_error_value(): void
    {
        $err = new Err(404);
        $result = $err->unwrapOrElse(fn ($code) => $code === 404 ? 'Not Found' : 'Unknown');
        $this->assertSame('Not Found', $result);
    }

    #[Test]
    public function map_does_not_apply_function(): void
    {
        $err = new Err('error');
        $mapped = $err->map(fn ($x) => $x * 2);
        $this->assertSame($err, $mapped);
        $this->assertSame('error', $mapped->unwrapErr());
    }

    #[Test]
    public function mapErr_applies_function_to_error(): void
    {
        $err = new Err('error');
        $mapped = $err->mapErr(fn ($e) => strtoupper($e));
        $this->assertInstanceOf(Err::class, $mapped);
        $this->assertSame('ERROR', $mapped->unwrapErr());
    }

    #[Test]
    public function mapErr_withTypeChange_transforms_type(): void
    {
        $err = new Err(404);
        $mapped = $err->mapErr(fn ($code) => "Error code: $code");
        $this->assertInstanceOf(Err::class, $mapped);
        $this->assertSame('Error code: 404', $mapped->unwrapErr());
    }

    #[Test]
    public function inspect_does_not_call_function(): void
    {
        $err = new Err('error');
        $called = false;
        $result = $err->inspect(function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        $this->assertSame($err, $result);
    }

    #[Test]
    public function inspectErr_calls_function_with_error(): void
    {
        $err = new Err('error');
        $capturedError = null;
        $result = $err->inspectErr(function ($error) use (&$capturedError) {
            $capturedError = $error;
        });
        $this->assertSame('error', $capturedError);
        $this->assertSame($err, $result);
    }

    #[Test]
    public function mapOr_returns_default(): void
    {
        $err = new Err('error');
        $result = $err->mapOr(100, fn ($x) => $x * 2);
        $this->assertSame(100, $result);
    }

    #[Test]
    public function mapOrElse_calls_default_function(): void
    {
        $err = new Err('error');
        $result = $err->mapOrElse(fn () => 100, fn ($x) => $x * 2);
        $this->assertSame(100, $result);
    }

    #[Test]
    public function and_returns_self(): void
    {
        $err1 = new Err('error1');
        $ok = new Ok(42);
        $result = $err1->and($ok);
        $this->assertSame($err1, $result);
        $this->assertSame('error1', $result->unwrapErr());
    }

    #[Test]
    public function and_withAnotherErr_returns_self(): void
    {
        $err1 = new Err('error1');
        $err2 = new Err('error2');
        $result = $err1->and($err2);
        $this->assertSame($err1, $result);
        $this->assertSame('error1', $result->unwrapErr());
    }

    #[Test]
    public function andThen_returns_self(): void
    {
        $err = new Err('error');
        $result = $err->andThen(fn ($x) => new Ok($x * 2));
        $this->assertSame($err, $result);
        $this->assertSame('error', $result->unwrapErr());
    }

    #[Test]
    public function or_withAnotherErr_returns_second_err(): void
    {
        $err1 = new Err('error1');
        $err2 = new Err('error2');
        $result = $err1->or($err2);
        $this->assertSame($err2, $result);
        $this->assertSame('error2', $result->unwrapErr());
    }

    #[Test]
    public function orElse_canReturnAnotherErr_returns_new_err(): void
    {
        $err = new Err(404);
        $result = $err->orElse(fn ($code) => new Err("HTTP Error: $code"));
        $this->assertInstanceOf(Err::class, $result);
        $this->assertSame('HTTP Error: 404', $result->unwrapErr());
    }

    #[Test]
    public function match_calls_err_function(): void
    {
        $err = new Err('error');
        $result = $err->match(
            fn ($value) => "Success: $value",
            fn ($error) => "Error: $error",
        );
        $this->assertSame('Error: error', $result);
    }

    #[Test]
    public function match_withDifferentReturnTypes_returns_err_branch(): void
    {
        $err = new Err(404);
        $result = $err->match(
            fn ($value) => ['status' => 'ok', 'data' => $value],
            fn ($error) => ['status' => 'error', 'code' => $error],
        );
        $this->assertSame(['status' => 'error', 'code' => 404], $result);
    }

    #[Test]
    public function err_withNullValue_handles_null(): void
    {
        $err = new Err(null);
        $this->assertNull($err->unwrapErr());
        $this->assertFalse($err->isOk());
        $this->assertTrue($err->isErr());
    }

    #[Test]
    public function err_withFalseValue_handles_false(): void
    {
        $err = new Err(false);
        $this->assertFalse($err->unwrapErr());
        $this->assertTrue($err->isErr());
    }

    #[Test]
    public function err_withZeroValue_handles_zero(): void
    {
        $err = new Err(0);
        $this->assertSame(0, $err->unwrapErr());
        $this->assertTrue($err->isErr());
    }

    #[Test]
    public function err_withEmptyString_handles_empty_string(): void
    {
        $err = new Err('');
        $this->assertSame('', $err->unwrapErr());
        $this->assertTrue($err->isErr());
    }

    #[Test]
    public function chainingOperations_applies_transformations(): void
    {
        $err = new Err('initial error');
        $result = $err
            ->mapErr(fn ($e) => strtoupper($e))
            ->orElse(fn ($e) => new Err("[$e]"))
            ->mapErr(fn ($e) => "Final: $e");

        $this->assertInstanceOf(Err::class, $result);
        $this->assertSame('Final: [INITIAL ERROR]', $result->unwrapErr());
    }

    #[Test]
    public function exceptionAsErrorValue_handles_exception(): void
    {
        $exception = new \RuntimeException('Something went wrong');
        $err = new Err($exception);

        $this->assertTrue($err->isErr());
        $this->assertSame($exception, $err->unwrapErr());

        $handled = $err->mapErr(fn ($e) => $e->getMessage());
        $this->assertSame('Something went wrong', $handled->unwrapErr());
    }
}
