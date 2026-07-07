<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * unwrap() / unwrapErr() を反対側の変種に対して呼び出したときに送出される例外です.
 *
 * \LogicException を継承しているため、既存の catch (\LogicException) はそのまま動作します.
 * メッセージには保持している値の要約が含まれます（Rust の panic メッセージに相当）.
 */
final class UnwrapException extends \LogicException
{
    /**
     * Err に対して unwrap() が呼ばれた場合の例外を生成します.
     */
    public static function unwrapOnErr(mixed $error): self
    {
        return new self(\sprintf('called Result::unwrap() on an Err value: %s', self::describe($error)));
    }

    /**
     * Ok に対して unwrapErr() が呼ばれた場合の例外を生成します.
     */
    public static function unwrapErrOnOk(mixed $value): self
    {
        return new self(\sprintf('called Result::unwrapErr() on an Ok value: %s', self::describe($value)));
    }

    /**
     * 例外メッセージ用に値の要約を生成します.
     */
    private static function describe(mixed $value): string
    {
        return match (true) {
            $value instanceof \Throwable => \sprintf('%s: %s', $value::class, $value->getMessage()),
            $value instanceof \Stringable => \sprintf('%s: %s', $value::class, (string) $value),
            \is_object($value) => $value::class,
            \is_scalar($value), null === $value => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
