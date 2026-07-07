<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * unwrap() / unwrapErr() を反対側の変種に対して呼び出したときに送出される例外です.
 *
 * \LogicException を継承しているため、既存の catch (\LogicException) はそのまま動作します.
 * メッセージには保持している値の要約が含まれます（Rust の panic メッセージに相当）.
 * 注意: スカラー値はメッセージにそのまま（切り詰めの上）現れるため、機微な文字列を
 * エラー値に載せる場合はログ出力先に注意してください.
 */
final class UnwrapException extends \LogicException
{
    /**
     * メッセージに埋め込む値要約の最大長（超過分は切り詰め）.
     */
    private const int MAX_SUMMARY_LENGTH = 120;

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
     * expect() / expectErr() 用に、呼び出し側のメッセージと値の要約から例外を生成します.
     */
    public static function withMessage(string $message, mixed $value): self
    {
        return new self(\sprintf('%s: %s', $message, self::describe($value)));
    }

    /**
     * 例外メッセージ用に値の要約を生成します.
     *
     * 要約は単一行に正規化し、MAX_SUMMARY_LENGTH を超える部分は切り詰めます.
     */
    private static function describe(mixed $value): string
    {
        $summary = match (true) {
            $value instanceof \Throwable => \sprintf('%s: %s', self::className($value), $value->getMessage()),
            $value instanceof \UnitEnum => \sprintf('%s::%s', $value::class, $value->name),
            $value instanceof \Stringable => self::describeStringable($value),
            \is_object($value) => self::className($value),
            \is_scalar($value), null === $value => var_export($value, true),
            default => get_debug_type($value),
        };

        $summary = str_replace(["\r\n", "\r", "\n"], '\n', $summary);
        if (\strlen($summary) > self::MAX_SUMMARY_LENGTH) {
            return substr($summary, 0, self::MAX_SUMMARY_LENGTH) . '... (truncated)';
        }

        return $summary;
    }

    /**
     * Stringable の要約を生成します。__toString() が例外を投げてもこの例外を
     * 置き換えないよう、失敗時はクラス名のみへフォールバックします.
     */
    private static function describeStringable(\Stringable $value): string
    {
        try {
            return \sprintf('%s: %s', self::className($value), (string) $value);
        } catch (\Throwable) {
            return self::className($value);
        }
    }

    /**
     * クラス名を返します。匿名クラスはファイルパス・行番号を除いた
     * 「Foo@anonymous」形式に正規化します.
     */
    private static function className(object $value): string
    {
        $class = $value::class;
        $pos = strpos($class, '@anonymous');
        if ($pos === false) {
            return $class;
        }

        return substr($class, 0, $pos + \strlen('@anonymous'));
    }
}
