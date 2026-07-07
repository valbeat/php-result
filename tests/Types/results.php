<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\Types;

use LogicException;

use function PHPStan\Testing\assertType;

use RuntimeException;
use Valbeat\Result\Result;

use Valbeat\Result\Results;

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
