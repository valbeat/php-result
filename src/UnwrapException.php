<?php

declare(strict_types=1);

namespace Valbeat\Result;

/**
 * The exception thrown when unwrap() / unwrapErr() is called on the opposite variant.
 *
 * It extends \LogicException, so existing catch (\LogicException) blocks keep working.
 * The message contains a summary of the held value (analogous to Rust's panic message).
 * Note: scalar values appear in the message verbatim (after truncation), so be careful
 * about where you log if you put sensitive strings in the error value.
 */
final class UnwrapException extends \LogicException
{
    /**
     * The maximum length of the value summary embedded in the message (excess is truncated).
     */
    private const int MAX_SUMMARY_LENGTH = 120;

    /**
     * Creates the exception for when unwrap() is called on an Err.
     */
    public static function unwrapOnErr(mixed $error): self
    {
        return new self(\sprintf('called Result::unwrap() on an Err value: %s', self::describe($error)));
    }

    /**
     * Creates the exception for when unwrapErr() is called on an Ok.
     */
    public static function unwrapErrOnOk(mixed $value): self
    {
        return new self(\sprintf('called Result::unwrapErr() on an Ok value: %s', self::describe($value)));
    }

    /**
     * Creates the exception for expect() / expectErr() from the caller's message and a value summary.
     */
    public static function withMessage(string $message, mixed $value): self
    {
        return new self(\sprintf('%s: %s', $message, self::describe($value)));
    }

    /**
     * Builds a value summary for the exception message.
     *
     * The summary is normalized to a single line and truncated beyond MAX_SUMMARY_LENGTH.
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
            // Use mb_strcut to cut at a character boundary while respecting the byte limit
            // (substr would cut in the middle of a multibyte character, producing invalid
            // UTF-8 and making json_encode fail).
            return mb_strcut($summary, 0, self::MAX_SUMMARY_LENGTH, 'UTF-8') . '... (truncated)';
        }

        return $summary;
    }

    /**
     * Builds a summary for a Stringable. To avoid replacing this exception when
     * __toString() throws, it falls back to the class name only on failure.
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
     * Returns the class name. Anonymous classes are normalized to the
     * "Foo@anonymous" form, stripping the file path and line number.
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
