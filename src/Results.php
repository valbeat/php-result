<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * Static helpers for creating and composing Results.
 */
final class Results
{
    /**
     * Prevents instantiation since this is a static helper.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Executes a callable that may throw and wraps the result in a Result.
     *
     * On success the return value is wrapped in Ok; if a \Throwable is thrown it is wrapped in Err.
     * Use it as an entry point for bringing existing exception-based code into the Result world.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return Result<T, \Throwable>
     */
    public static function try(callable $fn): Result
    {
        try {
            return new Ok($fn());
        } catch (\Throwable $e) {
            return new Err($e);
        }
    }

    /**
     * Combines multiple Results into one.
     *
     * If all are successes, returns the list of values as an Ok; if any failure is present, returns the first Err.
     *
     * @template T
     * @template E
     *
     * @param iterable<Result<T, E>> $results
     *
     * @return Result<list<T>, E>
     */
    public static function combine(iterable $results): Result
    {
        $values = [];
        foreach ($results as $result) {
            if ($result->isErr()) {
                return $result;
            }
            $values[] = $result->unwrap();
        }

        return new Ok($values);
    }

    /**
     * Flattens a nested Result by one level.
     *
     * This is not an instance method because PHPStan's conditional types cannot
     * decompose the template T into Result<U, F> (there is no infer). As a static
     * helper, the inner type can be inferred precisely from the parameter-side template.
     *
     * @template T
     * @template E1
     * @template E2
     *
     * @param Result<Result<T, E2>, E1> $result
     *
     * @return Result<T, E1|E2>
     */
    public static function flatten(Result $result): Result
    {
        return $result->andThen(static fn (Result $inner): Result => $inner);
    }
}
