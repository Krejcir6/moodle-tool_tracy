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
 * Moodle's `global $CFG;` convention puts $CFG (DB/SMTP passwords, API
 * secrets, ...) in the stack trace of almost any exception. A name pattern
 * alone is a guess, so bootstrap::enable() also passes every actual $CFG
 * property name in as $extraExactKeys — at strict level, all of $CFG is
 * hidden unconditionally, not just properties that look sensitive by name.
 *
 * Feeds two separate Tracy mechanisms, since neither alone covers
 * everything: BlueScreen::$scrubber (a callable, checked against every
 * key the bluescreen renders) and Debugger::$keysToHide (an exact-match
 * array — the only thing bdump() respects, since it accepts no scrubber).
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
     * @param string[] $extraExactKeys Hidden unconditionally regardless of
     *                                  $level/pattern — bootstrap::enable()
     *                                  passes every real $CFG key here.
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
            // Real $CFG keys the pattern below wouldn't otherwise catch.
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
