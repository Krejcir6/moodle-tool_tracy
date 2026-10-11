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

/**
 * Hides sensitive-looking values from Tracy output. See the file docblock
 * above for the two Tracy mechanisms this feeds.
 */
final class scrubber {
    /** Default. Errs on the side of hiding anything that looks sensitive. */
    public const LEVEL_STRICT = 'strict';

    /** Narrower: only well-established sensitive-sounding names. */
    public const LEVEL_MODERATE = 'moderate';

    /**
     * Builds the callable Tracy\BlueScreen::$scrubber expects.
     *
     * @param string $level One of self::LEVEL_STRICT or self::LEVEL_MODERATE.
     * @param string[] $extraexactkeys Hidden unconditionally regardless of
     *                                  $level/pattern — bootstrap::enable()
     *                                  passes every real $CFG key here.
     * @return \Closure callable(string $key, mixed $value, ?string $class): bool
     *                   for Tracy\BlueScreen::$scrubber.
     */
    public static function for_level(string $level = self::LEVEL_STRICT, array $extraexactkeys = []): \Closure {
        $pattern = self::pattern($level);
        $exact = array_flip(array_map('strtolower', $extraexactkeys));

        return static function (string $key, mixed $value, ?string $class = null) use ($pattern, $exact): bool {
            return isset($exact[strtolower($key)]) || (bool) preg_match($pattern, $key);
        };
    }

    /**
     * Literal (exact-match) key names for Debugger::$keysToHide.
     *
     * @param string $level One of self::LEVEL_STRICT or self::LEVEL_MODERATE.
     * @param string[] $extraexactkeys See for_level().
     * @return string[] Literal (exact-match) key names for Debugger::$keysToHide.
     */
    public static function keys_to_hide(string $level = self::LEVEL_STRICT, array $extraexactkeys = []): array {
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

        return array_values(array_unique(array_merge($common, $extraexactkeys)));
    }

    /**
     * Recursively masks sensitive keys in request-style arrays.
     *
     * @param array $values The array to scrub, recursively.
     * @param string $level One of self::LEVEL_STRICT or self::LEVEL_MODERATE.
     * @return array $values, with sensitive-looking values masked.
     */
    public static function scrub_array(array $values, string $level = self::LEVEL_STRICT): array {
        $scrub = self::for_level($level);

        foreach ($values as $key => $value) {
            if (is_string($key) && $scrub($key, $value)) {
                $values[$key] = '***';
            } else if (is_array($value)) {
                $values[$key] = self::scrub_array($value, $level);
            }
        }

        return $values;
    }

    /**
     * Scrubs standard Moodle event data. The generic "other" payload is
     * handled fail-closed because its field names and contents are defined by
     * arbitrary components rather than the Events API.
     *
     * @param array $data The event's "other" payload and friends.
     * @return array $data, with sensitive-looking values masked.
     */
    public static function scrub_event_data(array $data): array {
        if (array_key_exists('other', $data)) {
            $data['other'] = self::scrub_event_other($data['other']);
        }

        return self::scrub_array($data);
    }

    /**
     * Recursively scrubs an event's "other" payload, fail-closed.
     *
     * @param mixed $value The value to scrub, recursively if it is an array
     *                      or a JSON-encoded array.
     * @return mixed $value, with sensitive-looking values masked.
     */
    private static function scrub_event_other(mixed $value): mixed {
        if (is_array($value)) {
            $scrub = self::for_level();
            foreach ($value as $key => $item) {
                $value[$key] = is_string($key) && $scrub($key, $item)
                    ? '***'
                    : self::scrub_event_other($item);
            }

            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::scrub_event_other($decoded);
            }

            return '***';
        }

        return $value;
    }

    /**
     * Regex pattern matching sensitive-looking key names for the given level.
     *
     * @param string $level One of self::LEVEL_STRICT or self::LEVEL_MODERATE.
     * @return string A regex pattern, for use with preg_match().
     */
    private static function pattern(string $level): string {
        if ($level === self::LEVEL_MODERATE) {
            return '~password|passwd|pass|pwd|secret|token|credential~i';
        }

        return '~pass|secret|token|key|auth|credential|cookie|salt|hash|pin\b|private~i';
    }
}
