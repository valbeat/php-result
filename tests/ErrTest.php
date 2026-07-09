<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;
use Valbeat\Result\UnwrapException;

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
        $err = new Err(self::asString('critical'));
        $result = $err->isErrAnd(fn ($error) => $error === 'critical');
        $this->assertTrue($result);
    }

    #[Test]
    public function isErrAnd_whenCallbackReturnsFalse_returns_false(): void
    {
        $err = new Err(self::asString('warning'));
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
    public function expectErr_returns_error_value(): void
    {
        $err = new Err('error');
        $this->assertSame('error', $err->expectErr('should have an error'));
    }

    #[Test]
    public function unwrap_throwsUnwrapException_withErrorValueInMessage(): void
    {
        $err = new Err('error');
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage("called Result::unwrap() on an Err value: 'error'");
        $err->unwrap();
    }

    #[Test]
    public function unwrap_withThrowableError_includesClassAndMessage(): void
    {
        $err = new Err(new \RuntimeException('boom'));
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('called Result::unwrap() on an Err value: RuntimeException: boom');
        $err->unwrap();
    }

    #[Test]
    public function unwrap_withArrayError_describesType(): void
    {
        $err = new Err(['code' => 500]);
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('called Result::unwrap() on an Err value: array');
        $err->unwrap();
    }

    #[Test]
    public function unwrap_withThrowingStringableError_stillThrowsUnwrapException(): void
    {
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                throw new \RuntimeException('rendering failed');
            }
        };
        $err = new Err($stringable);
        $this->expectException(UnwrapException::class);
        $err->unwrap();
    }

    #[Test]
    public function unwrap_withEnumError_includesCaseName(): void
    {
        $err = new Err(SampleEnumError::NotFound);
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('SampleEnumError::NotFound');
        $err->unwrap();
    }

    #[Test]
    public function unwrap_withLongStringError_truncatesMessage(): void
    {
        $err = new Err(str_repeat('a', 10000));

        try {
            $err->unwrap();
        } catch (UnwrapException $e) {
            $this->assertLessThan(300, \strlen($e->getMessage()));
            $this->assertStringContainsString('(truncated)', $e->getMessage());
        }
    }

    #[Test]
    public function unwrap_withMultilineStringError_keepsMessageSingleLine(): void
    {
        $err = new Err("line1\nline2");

        try {
            $err->unwrap();
        } catch (UnwrapException $e) {
            $this->assertStringNotContainsString("\n", $e->getMessage());
        }
    }

    #[Test]
    public function unwrap_withLongMultibyteError_keepsValidUtf8Message(): void
    {
        // Each character is 3 bytes, so the 120-byte truncation boundary falls in the middle of a character
        $err = new Err(str_repeat('あ', 200));

        try {
            $err->unwrap();
        } catch (UnwrapException $e) {
            $message = $e->getMessage();
            $this->assertTrue(
                mb_check_encoding($message, 'UTF-8'),
                'truncated message must remain valid UTF-8',
            );
            $this->assertNotFalse(
                json_encode(['message' => $message]),
                'message must be json_encode-able (no malformed UTF-8)',
            );
            $this->assertStringContainsString('(truncated)', $message);
        }
    }

    #[Test]
    public function expect_throwsUnwrapException_withErrorValueInMessage(): void
    {
        $err = new Err(new \RuntimeException('boom'));
        $this->expectException(UnwrapException::class);
        $this->expectExceptionMessage('config file should be readable: RuntimeException: boom');
        $err->expect('config file should be readable');
    }

    #[Test]
    public function unwrapErr_returns_error_value(): void
    {
        $err = new Err('error message');
        $this->assertSame('error message', $err->unwrapErr());
    }

    #[Test]
    public function unwrapOr_returns_default(): void
    {
        $err = new Err('error');
        $this->assertSame(42, $err->unwrapOr(42));
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
        $err = new Err(self::asInt(404));
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
    public function orElse_withDifferentSuccessType_recovers(): void
    {
        $err = new Err(self::asString('original'));
        $result = $err->orElse(fn ($e) => new Ok(\strlen($e)));

        $this->assertInstanceOf(Ok::class, $result);
        $this->assertSame(8, $result->unwrap());
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
    public function match_supportsNamedArguments(): void
    {
        $err = new Err('error');
        $result = $err->match(
            ok: fn ($value) => "Success: $value",
            err: fn ($error) => "Error: $error",
        );
        $this->assertSame('Error: error', $result);
    }

    #[Test]
    public function mapOrElse_supportsNamedArguments(): void
    {
        $err = new Err('error');
        $result = $err->mapOrElse(defaultFn: fn () => 100, fn: fn ($x) => $x * 2);
        $this->assertSame(100, $result);
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

    /**
     * Widens a literal type to int (covariant templates preserve constant types).
     */
    private static function asInt(int $value): int
    {
        return $value;
    }

    /**
     * Widens a literal type to string (covariant templates preserve constant types).
     */
    private static function asString(string $value): string
    {
        return $value;
    }
}

/**
 * Fixture for verifying that the UnwrapException message contains the enum case name.
 */
enum SampleEnumError
{
    case NotFound;
}
