<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * Result を生成・合成する静的ヘルパーです.
 */
final class Results
{
    /**
     * 静的ヘルパーのためインスタンス化を禁止します.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * 例外を投げうる処理を実行し、結果を Result に包みます.
     *
     * 成功時は戻り値を Ok に、\Throwable が送出された場合は Err に包んで返します.
     * 例外ベースの既存コードを Result の世界に持ち込む入口として使います.
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
     * 複数の Result を 1 つに合成します.
     *
     * すべて成功なら値のリストを Ok で返し、失敗が含まれる場合は最初の Err を返します.
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
     * ネストした Result を 1 段平坦化します.
     *
     * インスタンスメソッドにしないのは、PHPStan の条件型ではテンプレート T を
     * Result<U, F> に分解できない（infer がない）ため。静的ヘルパーなら
     * パラメータ側のテンプレートで内側の型を正確に推論できます.
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
