<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\Types;

use LogicException;

use function PHPStan\Testing\assertType;

use RuntimeException;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;

use Valbeat\Result\Result;
use Valbeat\Result\Results;

/**
 * Covariance test: an Ok<int> (= Result<int, never>) can be
 * returned as a Result<int, RuntimeException>.
 *
 * @return Result<int, RuntimeException>
 */
function parsePositiveInt(string $input): Result
{
    $value = (int) $input;
    if ($value > 0) {
        return new Ok($value);
    }

    return new Err(new RuntimeException('not a positive int'));
}

/**
 * @return Result<string, LogicException>
 */
function findNameById(int $id): Result
{
    if ($id === 42) {
        return new Ok('Alice');
    }

    return new Err(new LogicException('not found'));
}

/**
 * @param Result<int, RuntimeException> $result
 */
function testAndThenComposesErrorTypes(Result $result): void
{
    // andThen accepts a callback that returns a different error type; the error type is combined into E|F
    $chained = $result->andThen(findNameById(...));
    assertType('Valbeat\Result\Result<string, LogicException|RuntimeException>', $chained);
}

/**
 * @param Result<int, RuntimeException> $result
 * @param Result<string, LogicException> $other
 */
function testAndComposesErrorTypes(Result $result, Result $other): void
{
    assertType('Valbeat\Result\Result<string, LogicException|RuntimeException>', $result->and($other));
}

/**
 * @param Result<int, RuntimeException> $result
 */
function testOrElseComposesSuccessTypes(Result $result): void
{
    // orElse accepts a callback that returns a different success type; the success type is combined into T|U
    $recovered = $result->orElse(static fn (RuntimeException $e): Result => findNameById(0));
    assertType('Valbeat\Result\Result<int|string, LogicException>', $recovered);
}

/**
 * @param Result<int, RuntimeException> $result
 * @param Result<string, LogicException> $other
 */
function testOrComposesSuccessTypes(Result $result, Result $other): void
{
    assertType('Valbeat\Result\Result<int|string, LogicException>', $result->or($other));
}

/**
 * @param Result<int, RuntimeException> $result
 */
function testIsOkNarrowing(Result $result): void
{
    if ($result->isOk()) {
        assertType('Valbeat\Result\Ok<int>', $result);
        assertType('int', $result->unwrap());
    } else {
        assertType('Valbeat\Result\Err<RuntimeException>', $result);
        assertType('RuntimeException', $result->unwrapErr());
    }
}

/**
 * Narrowing via isErr: narrowed to Err on the true branch and Ok on the false branch.
 *
 * @param Result<int, RuntimeException> $result
 */
function testIsErrNarrowing(Result $result): void
{
    if ($result->isErr()) {
        assertType('Valbeat\Result\Err<RuntimeException>', $result);
        assertType('RuntimeException', $result->unwrapErr());
    } else {
        assertType('Valbeat\Result\Ok<int>', $result);
        assertType('int', $result->unwrap());
    }
}

/**
 * Known limitation: on the true branch of instanceof Ok the generics are lost, and unwrap becomes mixed.
 * To narrow while preserving the type arguments, use isOk() / isErr() (see testIsOkNarrowing).
 *
 * @param Result<int, RuntimeException> $result
 */
function testInstanceofOkNarrowing(Result $result): void
{
    if ($result instanceof Ok) {
        assertType('Valbeat\Result\Ok', $result);
        assertType('mixed', $result->unwrap());
    }
}

/**
 * Sealed test: on the else branch of instanceof Ok, narrowed to Err.
 *
 * @param Result<int, RuntimeException> $result
 */
function testInstanceofNarrowing(Result $result): bool
{
    if ($result instanceof Ok) {
        return true;
    }
    // Effect of sealed: on the else branch of instanceof Ok, narrowed to Err
    assertType('Valbeat\Result\Err', $result);

    return false;
}

/**
 * Effect of sealed: Ok and Err are deemed to cover all cases, so match does not raise a non-exhaustive error.
 *
 * @param Result<int, RuntimeException> $result
 */
function testExhaustiveMatch(Result $result): string
{
    return match (true) {
        $result instanceof Ok => 'ok',
        $result instanceof Err => 'err',
    };
}

/**
 * Known limitation: in a match expression's instanceof arm the generics are lost and become mixed.
 * To preserve the type arguments, use an isOk() arm (see testMatchArmNarrowingWithIsOk).
 *
 * @param Result<int, RuntimeException> $result
 */
function testMatchArmNarrowing(Result $result): void
{
    $value = match (true) {
        $result instanceof Ok => $result->unwrap(),
        $result instanceof Err => $result->unwrapErr(),
    };
    assertType('mixed', $value);
}

/**
 * In-arm narrowing in a match expression: assert-if-true also works with isOk().
 *
 * @param Result<int, RuntimeException> $result
 */
function testMatchArmNarrowingWithIsOk(Result $result): void
{
    $value = match (true) {
        $result->isOk() => $result->unwrap(),
        default => $result->unwrapErr(),
    };
    assertType('int|RuntimeException', $value);
}

/**
 * The return value of the match() method is combined into U|V from both callbacks' return types.
 *
 * @param Result<int, RuntimeException> $result
 */
function testMatchMethodComposesReturnTypes(Result $result, float $fallback): void
{
    $value = $result->match(
        stringify(...),
        static fn (RuntimeException $e): float => $fallback,
    );
    assertType('float|string', $value);
}

/**
 * inspect / inspectErr do not change the type.
 *
 * @param Result<int, RuntimeException> $result
 */
function testInspectPreservesType(Result $result): void
{
    assertType('Valbeat\Result\Result<int, RuntimeException>', $result->inspect(static function (int $v): void {
    }));
    assertType('Valbeat\Result\Result<int, RuntimeException>', $result->inspectErr(static function (RuntimeException $e): void {
    }));
}

/**
 * The callback parameter types of isOkAnd / isErrAnd / inspect are inferred.
 *
 * @param Result<int, RuntimeException> $result
 */
function testCallbackParamInference(Result $result): void
{
    $result->isOkAnd(static function ($value): bool {
        assertType('int', $value);

        return true;
    });
    $result->isErrAnd(static function ($error): bool {
        assertType('RuntimeException', $error);

        return true;
    });
    $result->inspect(static function ($value): void {
        assertType('int', $value);
    });
    $result->inspectErr(static function ($error): void {
        assertType('RuntimeException', $error);
    });
}

/**
 * Type inference from the constructor: new Ok / new Err determine the type arguments.
 */
function testConstructorInference(int $value, RuntimeException $error): void
{
    assertType('Valbeat\Result\Ok<int>', new Ok($value));
    assertType('Valbeat\Result\Err<RuntimeException>', new Err($error));
}

/**
 * unwrap / unwrapErr on a generic Result receiver resolve their conditional return types.
 *
 * @param Result<int, RuntimeException> $result
 */
function testUnwrapOnGenericReceiver(Result $result): void
{
    assertType('int', $result->unwrap());
    assertType('RuntimeException', $result->unwrapErr());
}

/**
 * expect / expectErr resolve the same conditional return types as unwrap / unwrapErr.
 *
 * @param Result<int, RuntimeException> $result
 */
function testExpectOnGenericReceiver(Result $result): void
{
    assertType('int', $result->expect('should have a value'));
    assertType('RuntimeException', $result->expectErr('should have an error'));
}

/**
 * unwrapOr / unwrapOrElse on a concrete receiver: do not mix in the type of the branch that cannot occur at runtime.
 *
 * @param Ok<int> $ok
 * @param Err<RuntimeException> $err
 */
function testConcreteUnwrapVariants(Ok $ok, Err $err, string $default, float $fallback): void
{
    assertType('int', $ok->unwrap());
    assertType('int', $ok->unwrapOr($default));
    assertType('int', $ok->unwrapOrElse(static fn (never $e): float => $fallback));
    assertType('RuntimeException', $err->unwrapErr());
    assertType('string', $err->unwrapOr($default));
    assertType('float', $err->unwrapOrElse(static fn (RuntimeException $e): float => $fallback));
}

/**
 * Flattening a nested Result: andThen can infer the inner success type and the combination of both error types.
 *
 * @param Result<Result<int, RuntimeException>, LogicException> $nested
 */
function testNestedResultFlattening(Result $nested): void
{
    $flattened = $nested->andThen(static fn (Result $inner): Result => $inner);
    assertType('Valbeat\Result\Result<int, LogicException|RuntimeException>', $flattened);
}

/**
 * On a concrete Ok receiver, the no-op-side methods do not mix in types that cannot occur at runtime.
 *
 * @param Ok<int> $ok
 */
function testConcreteOkPrecision(Ok $ok): void
{
    // or/orElse return $this, so it stays Ok<int>
    assertType('Valbeat\Result\Ok<int>', $ok->orElse(static fn (never $e): Result => findNameById(0)));
    assertType('Valbeat\Result\Ok<int>', $ok->or(findNameById(0)));
    // mapErr is a no-op
    assertType('Valbeat\Result\Ok<int>', $ok->mapErr(static fn (never $e): LogicException => $e));
    // map returns Ok<U>
    assertType('Valbeat\Result\Ok<string>', $ok->map(stringify(...)));
    // mapOr/mapOrElse always yield the result of $fn (the default value's type is not mixed in)
    assertType('string', $ok->mapOr(0.5, stringify(...)));
    assertType('string', $ok->mapOrElse(static fn (): float => 0.5, stringify(...)));
}

/**
 * On a concrete Err receiver, the no-op-side methods do not mix in types that cannot occur at runtime.
 *
 * @param Err<RuntimeException> $err
 */
function testConcreteErrPrecision(Err $err, float $fallback): void
{
    // and/andThen return $this, so it stays Err<RuntimeException>
    assertType('Valbeat\Result\Err<RuntimeException>', $err->andThen(static fn (never $v): Result => findNameById(0)));
    assertType('Valbeat\Result\Err<RuntimeException>', $err->and(findNameById(0)));
    // map is a no-op
    assertType('Valbeat\Result\Err<RuntimeException>', $err->map(static fn (never $v): int => $v));
    // mapErr returns Err<F>
    assertType('Valbeat\Result\Err<LogicException>', $err->mapErr(static fn (RuntimeException $e): LogicException => new LogicException($e->getMessage())));
    // mapOr/mapOrElse always yield the default side (the return type of $fn is not mixed in)
    assertType('float', $err->mapOr($fallback, static fn (never $v): string => $v));
    assertType('float', $err->mapOrElse(static fn (): float => $fallback, static fn (never $v): string => $v));
}

function stringify(int $value): string
{
    return 'value: ' . $value;
}

/**
 * @param Result<int, RuntimeException> $result
 */
function testUnwrapVariants(Result $result, string $default, float $fallback): void
{
    assertType('int|string', $result->unwrapOr($default));
    assertType('float|int', $result->unwrapOrElse(static fn (RuntimeException $e): float => $fallback));
    assertType('string', $result->mapOr($default, stringify(...)));
}

/**
 * @param Result<int, RuntimeException> $result
 */
function testMap(Result $result): void
{
    assertType(
        'Valbeat\Result\Result<string, RuntimeException>',
        $result->map(stringify(...)),
    );
    assertType(
        'Valbeat\Result\Result<int, LogicException>',
        $result->mapErr(static fn (RuntimeException $e): LogicException => new LogicException($e->getMessage())),
    );
}

/**
 * Fixture for error-side exhaustiveness tests: native enum.
 */
enum HttpError
{
    case NotFound;
    case Forbidden;
}

/**
 * Fixture for error-side exhaustiveness tests: a @phpstan-sealed error union.
 *
 * @phpstan-sealed ValidationFailure|NetworkFailure
 */
interface AppError
{
}

final class ValidationFailure implements AppError
{
}

final class NetworkFailure implements AppError
{
}

/**
 * When the error type is a native enum: via isErr(), unwrapErr() preserves the enum type, and
 * a match covering all cases is recognized as exhaustive without a default.
 * (Dropping any case makes phpstan analyse fail, so this itself pins down the exhaustiveness.)
 *
 * @param Result<int, HttpError> $result
 */
function testEnumErrorExhaustiveness(Result $result): string
{
    if ($result->isErr()) {
        $error = $result->unwrapErr();
        assertType('Valbeat\Result\Tests\Types\HttpError', $error);

        return match ($error) {
            HttpError::NotFound => 'not found',
            HttpError::Forbidden => 'forbidden',
        };
    }

    return 'ok';
}

/**
 * When the error type is a @phpstan-sealed union: for a value extracted via isErr(),
 * match(true)+instanceof is recognized as exhaustive (the sealed annotation is required; the error classes are non-generic, so no type-argument loss occurs).
 *
 * @param Result<int, AppError> $result
 */
function testSealedErrorExhaustiveness(Result $result): string
{
    if ($result->isErr()) {
        $error = $result->unwrapErr();
        assertType('Valbeat\Result\Tests\Types\AppError', $error);

        return match (true) {
            $error instanceof ValidationFailure => 'validation',
            $error instanceof NetworkFailure => 'network',
        };
    }

    return 'ok';
}

/**
 * Pinning down a known pitfall (enum error): with instanceof Err, E is lost, and even for an
 * enum unwrapErr() becomes mixed. In branches that handle the value, use isErr() (see the two cases above).
 *
 * @param Result<int, HttpError> $result
 */
function testInstanceofErrLosesEnumErrorType(Result $result): void
{
    if ($result instanceof Err) {
        assertType('mixed', $result->unwrapErr());
    }
}

/**
 * Pinning down a known pitfall (sealed error): even with a sealed union, instanceof Err
 * loses E and unwrapErr() becomes mixed.
 *
 * @param Result<int, AppError> $result
 */
function testInstanceofErrLosesSealedErrorType(Result $result): void
{
    if ($result instanceof Err) {
        assertType('mixed', $result->unwrapErr());
    }
}

/**
 * Results::try infers the return type on the Ok side and the potentially thrown exception as Throwable on the Err side.
 */
function testTryInference(): void
{
    assertType('Valbeat\Result\Result<int, Throwable>', Results::try(static fn (): int => 42));
}

/**
 * Results::combine infers Result<list<T>, E> from iterable<Result<T, E>>.
 *
 * @param list<Result<int, RuntimeException>> $results
 */
function testCombineInference(array $results): void
{
    assertType('Valbeat\Result\Result<list<int>, RuntimeException>', Results::combine($results));
}

/**
 * Results::flatten infers the inner success type of a nested Result and the combination of both error types.
 *
 * @param Result<Result<int, RuntimeException>, LogicException> $nested
 */
function testFlattenInference(Result $nested): void
{
    assertType('Valbeat\Result\Result<int, LogicException|RuntimeException>', Results::flatten($nested));
}
