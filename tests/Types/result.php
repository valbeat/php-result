<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\Types;

use LogicException;

use function PHPStan\Testing\assertType;

use RuntimeException;
use Valbeat\Result\Err;
use Valbeat\Result\Ok;

use Valbeat\Result\Result;

/**
 * 共変性のテスト: Ok<int> (= Result<int, never>) を
 * Result<int, RuntimeException> として返せる.
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
    // andThen は異なるエラー型を返すコールバックを受け取れ、エラー型は E|F に合成される
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
    // orElse は異なる成功型を返すコールバックを受け取れ、成功型は T|U に合成される
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
 * sealed のテスト: instanceof Ok の else 分岐で Err に絞り込まれる.
 *
 * @param Result<int, RuntimeException> $result
 */
function testInstanceofNarrowing(Result $result): bool
{
    if ($result instanceof Ok) {
        return true;
    }
    // sealed の効果: instanceof Ok の else 分岐で Err に絞り込まれる
    assertType('Valbeat\Result\Err', $result);

    return false;
}

/**
 * sealed の効果: Ok と Err で全ケース網羅と判断され、match が非網羅エラーにならない.
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
 * ネストした Result の平坦化: andThen が内側の成功型と両エラー型の合成を推論できる.
 *
 * @param Result<Result<int, RuntimeException>, LogicException> $nested
 */
function testNestedResultFlattening(Result $nested): void
{
    $flattened = $nested->andThen(static fn (Result $inner): Result => $inner);
    assertType('Valbeat\Result\Result<int, LogicException|RuntimeException>', $flattened);
}

/**
 * 具象 Ok レシーバでは no-op 側のメソッドが実行時に起こり得ない型を混ぜない.
 *
 * @param Ok<int> $ok
 */
function testConcreteOkPrecision(Ok $ok): void
{
    // or/orElse は $this を返すため Ok<int> のまま
    assertType('Valbeat\Result\Ok<int>', $ok->orElse(static fn (never $e): Result => findNameById(0)));
    assertType('Valbeat\Result\Ok<int>', $ok->or(findNameById(0)));
    // mapErr は no-op
    assertType('Valbeat\Result\Ok<int>', $ok->mapErr(static fn (never $e): LogicException => $e));
    // map は Ok<U> を返す
    assertType('Valbeat\Result\Ok<string>', $ok->map(stringify(...)));
    // mapOr/mapOrElse は常に $fn の結果（デフォルト値の型は混ざらない）
    assertType('string', $ok->mapOr(0.5, stringify(...)));
    assertType('string', $ok->mapOrElse(static fn (): float => 0.5, stringify(...)));
}

/**
 * 具象 Err レシーバでは no-op 側のメソッドが実行時に起こり得ない型を混ぜない.
 *
 * @param Err<RuntimeException> $err
 */
function testConcreteErrPrecision(Err $err, float $fallback): void
{
    // and/andThen は $this を返すため Err<RuntimeException> のまま
    assertType('Valbeat\Result\Err<RuntimeException>', $err->andThen(static fn (never $v): Result => findNameById(0)));
    assertType('Valbeat\Result\Err<RuntimeException>', $err->and(findNameById(0)));
    // map は no-op
    assertType('Valbeat\Result\Err<RuntimeException>', $err->map(static fn (never $v): int => $v));
    // mapErr は Err<F> を返す
    assertType('Valbeat\Result\Err<LogicException>', $err->mapErr(static fn (RuntimeException $e): LogicException => new LogicException($e->getMessage())));
    // mapOr/mapOrElse は常にデフォルト側（$fn の戻り値型は混ざらない）
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
