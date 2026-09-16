<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hides sensitive-looking values from Tracy output.
 *
 * Moodle's `global $CFG;` convention means $CFG (DB password, SMTP
 * password, various API secrets, ...) shows up as a local variable in the
 * stack trace of almost any exception, in Tracy\BlueScreen's rendering —
 * without a scrubber, that value is shown in full.
 *
 * The pattern match alone is a guess based on how a key *looks* — good
 * defense in depth, but not a guarantee. bootstrap::enable() additionally
 * passes every actual $CFG property name in as $extraExactKeys, so at the
 * default (strict) level *all* of $CFG is hidden unconditionally, not just
 * whichever properties happen to have a sensitive-looking name.
 *
 * Two, structurally different Tracy mechanisms exist for this, and this
 * class feeds both, since neither on its own covers everything:
 * - Tracy\BlueScreen::$scrubber — a callable(string $key, mixed $val,
 *   ?string $class): bool, checked against every key in every dump the
 *   bluescreen renders (stack frame locals, $_SERVER/$_ENV, ...). Pattern
 *   based, so it catches e.g. `dbpass`, `smtppass`, `proxypassword`
 *   without having to name each one — see forLevel().
 * - Tracy\Debugger::$keysToHide — a plain array Debugger::dump()/bdump()
 *   uses, checked with an *exact*, case-insensitive match only (see
 *   Tracy\Dumper\Describer::isSensitive()). Debugger::dump() does not
 *   accept a scrubber callable at all, so ad-hoc `bdump($x)` calls a
 *   developer writes are only ever protected by literal name matches —
 *   see keysToHide().
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class scrubber
{
    /** Default. Errs on the side of hiding anything that looks sensitive. */
    public const LEVEL_STRICT = 'strict';

    /** Narrower: only well-established sensitive-sounding names. */
    public const LEVEL_MODERATE = 'moderate';

    /**
     * @param string[] $extraExactKeys Extra key names to hide unconditionally,
     *                                  regardless of $level or pattern match.
     *                                  bootstrap::enable() passes every actual
     *                                  $CFG property name here, so hiding
     *                                  $CFG doesn't depend on any of its
     *                                  property names happening to *look*
     *                                  sensitive — every single one is hidden.
     * @return \Closure callable(string $key, mixed $value, ?string $class): bool
     *                   for Tracy\BlueScreen::$scrubber.
     */
    public static function forLevel(string $level = self::LEVEL_STRICT, array $extraExactKeys = []): \Closure
    {
        $pattern = self::pattern($level);
        $exact = array_flip(array_map('strtolower', $extraExactKeys));

        return static function (string $key, mixed $value, ?string $class = null) use ($pattern, $exact): bool {
            return isset($exact[strtolower($key)]) || (bool) preg_match($pattern, $key);
        };
    }

    /**
     * @param string[] $extraExactKeys See forLevel().
     * @return string[] Literal (exact-match) key names for Debugger::$keysToHide.
     */
    public static function keysToHide(string $level = self::LEVEL_STRICT, array $extraExactKeys = []): array
    {
        $common = [
            'password', 'passwd', 'pass', 'pwd', 'secret', 'token', 'apikey', 'api_key',
            'clientsecret', 'client_secret', 'authorization', 'cookie', 'creditcard', 'credit card',
            'cc', 'pin',
        ];

        if ($level !== self::LEVEL_MODERATE) {
            // Moodle $CFG property names that don't literally contain "pass"/
            // "secret"/etc as a substring of *themselves* but are exact
            // matches an exact-match list can still usefully catch when
            // dumped directly.
            $common = array_merge($common, [
                'dbpass', 'smtppass', 'proxypassword', 'passwordsaltmain', 'siteidentifier',
                'recaptchaprivatekey', 'googlemapkey3', 'sessioncookie',
            ]);
        }

        return array_values(array_unique(array_merge($common, $extraExactKeys)));
    }

    private static function pattern(string $level): string
    {
        if ($level === self::LEVEL_MODERATE) {
            return '~password|passwd|pass|pwd|secret|token|credential~i';
        }

        return '~pass|secret|token|key|auth|credential|cookie|salt|hash|pin\b|private~i';
    }
}
