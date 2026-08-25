<?php
declare(strict_types=1);

namespace ElanRegistry;

/**
 * String input normalization utility shared across validation logic.
 *
 * Trims whitespace and enforces a maximum length without modifying or
 * escaping content — HTML entities and special characters are preserved.
 * Callers MUST apply htmlspecialchars() at the render layer (encode-at-output).
 *
 * @see https://github.com/unibrain1/elanregistry/issues/941 for encode-at-output rationale
 */
class InputSanitizer
{
    private function __construct() {}

    /**
     * @param string $input     Raw input string
     * @param int    $maxLength Maximum allowed length (default 255)
     * @return string Trimmed, length-capped string — raw, not HTML-encoded
     */
    public static function normalize(string $input, int $maxLength = 255): string
    {
        $normalized = trim($input);
        return mb_strlen($normalized) > $maxLength ? mb_substr($normalized, 0, $maxLength) : $normalized;
    }

    /**
     * Strip CR, LF, and tab from a value before it flows into an SMTP header
     * built via string concatenation (e.g. reply-to, subject, log lines).
     *
     * Does not HTML-encode — callers still apply htmlspecialchars() at the
     * render layer per this class's encode-at-output contract.
     *
     * preg_replace() can return null on a PCRE engine failure (e.g. invalid
     * UTF-8 in $value, or a backtrack/recursion-limit hit) rather than on any
     * input this method's own pattern would produce. That failure is distinct
     * from "nothing to strip" and must not be silently coerced to '' — a
     * blank header-bound value with no signal of *why* it went blank is
     * exactly the silent-failure shape this method exists to prevent. Callers
     * building SMTP headers should fail loudly (catch and log with request
     * context) rather than send a mail with an unexplained empty field.
     *
     * @param string|null $value Raw header-bound value
     * @return string $value with \r, \n, and \t removed
     * @throws \RuntimeException If the underlying PCRE engine fails (see preg_last_error())
     */
    public static function stripHeaderInjectionChars(?string $value): string
    {
        $result = preg_replace('/[\r\n\t]/', '', (string) $value);

        if ($result === null) {
            throw new \RuntimeException(
                'InputSanitizer::stripHeaderInjectionChars: preg_replace failed, PCRE error code ' . preg_last_error()
            );
        }

        return $result;
    }
}
