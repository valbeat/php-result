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
 * isErr によるナローイング: true 側で Err、false 側で Ok に絞り込まれる.
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
 * 既知の制限: instanceof Ok の true 側分岐ではジェネリクスが失われ、unwrap は mixed になる.
 * 型引数を保ったまま絞り込みたい場合は isOk() / isErr() を使う（testIsOkNarrowing 参照）.
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
 * 既知の制限: match 式の instanceof アームではジェネリクスが失われ mixed になる.
 * 型引数を保ちたい場合は isOk() アームを使う（testMatchArmNarrowingWithIsOk 参照）.
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
 * match 式のアーム内ナローイング: isOk() でも assert-if-true が効く.
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
 * match() メソッドの戻り値は両コールバックの戻り値型 U|V に合成される.
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
 * inspect / inspectErr は型を変えない.
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
 * isOkAnd / isErrAnd / inspect のコールバック引数の型が推論される.
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
 * コンストラクタからの型推論: new Ok / new Err で型引数が決まる.
 */
function testConstructorInference(int $value, RuntimeException $error): void
{
    assertType('Valbeat\Result\Ok<int>', new Ok($value));
    assertType('Valbeat\Result\Err<RuntimeException>', new Err($error));
}

/**
 * ジェネリック Result レシーバでの unwrap / unwrapErr は条件付き戻り値型が解決される.
 *
 * @param Result<int, RuntimeException> $result
 */
function testUnwrapOnGenericReceiver(Result $result): void
{
    assertType('int', $result->unwrap());
    assertType('RuntimeException', $result->unwrapErr());
}

/**
 * expect / expectErr も unwrap / unwrapErr と同じ条件付き戻り値型が解決される.
 *
 * @param Result<int, RuntimeException> $result
 */
function testExpectOnGenericReceiver(Result $result): void
{
    assertType('int', $result->expect('should have a value'));
    assertType('RuntimeException', $result->expectErr('should have an error'));
}

/**
 * 具象レシーバでの unwrapOr / unwrapOrElse: 実行時に起こり得ない側の型を混ぜない.
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

/**
 * エラー側の網羅性テスト用フィクスチャ: native enum.
 */
enum HttpError
{
    case NotFound;
    case Forbidden;
}

/**
 * エラー側の網羅性テスト用フィクスチャ: @phpstan-sealed なエラー union.
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
 * エラー型が native enum の場合: isErr() 経由なら unwrapErr() が enum 型を保ち、
 * 全ケースを網羅する match は default なしで網羅と認識される.
 * （いずれかのケースを落とすと phpstan analyse がエラーになるため、これ自体が網羅性のピン留めになる）
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
 * エラー型が @phpstan-sealed union の場合: isErr() 経由で取り出した値に対する
 * match(true)+instanceof が網羅と認識される（sealed 指定が前提。エラークラスは非ジェネリックなので型引数喪失は起きない）.
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
 * 既知の落とし穴のピン留め（enum エラー）: instanceof Err では E が失われ、
 * enum であっても unwrapErr() は mixed になる。値を扱う分岐は isErr() を使う（上の2ケース参照）.
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
 * 既知の落とし穴のピン留め（sealed エラー）: sealed union でも instanceof Err では
 * E が失われ unwrapErr() は mixed になる.
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
 * Results::try は戻り値の型を Ok 側に、送出されうる例外を Throwable として Err 側に推論する.
 */
function testTryInference(): void
{
    assertType('Valbeat\Result\Result<int, Throwable>', Results::try(static fn (): int => 42));
}

/**
 * Results::combine は iterable<Result<T, E>> から Result<list<T>, E> を推論する.
 *
 * @param list<Result<int, RuntimeException>> $results
 */
function testCombineInference(array $results): void
{
    assertType('Valbeat\Result\Result<list<int>, RuntimeException>', Results::combine($results));
}

/**
 * Results::flatten はネストした Result の内側の成功型と両エラー型の合成を推論する.
 *
 * @param Result<Result<int, RuntimeException>, LogicException> $nested
 */
function testFlattenInference(Result $nested): void
{
    assertType('Valbeat\Result\Result<int, LogicException|RuntimeException>', Results::flatten($nested));
}
