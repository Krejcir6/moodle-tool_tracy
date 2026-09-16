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
 * Enables Tracy before Moodle's own bootstrap runs.
 *
 * This file is required directly from config.php, so it must not depend on
 * Moodle internals (DB, $CFG completeness, autoloader) that are not yet
 * available at that point. It also takes its configuration as explicit
 * arguments rather than reading environment variables itself — how a given
 * site wants to decide the mode (env var, hostname, hardcoded) is a
 * deployment concern that belongs in that site's config.php, not in the
 * plugin.
 *
 * All \Tracy\Debugger:: references live in this file only, and only inside
 * enable()'s body — never in a caller, and never at this file's top level.
 * The Tracy library itself is required as soon as this file is included (so
 * that the SAME file always fully defines \Tracy\Debugger before anything
 * in it can run), but callers (config.php, lib.php) must never reference
 * \Tracy\Debugger themselves before calling enable(): PHP would have to
 * load *this* file first for the class to exist, and nothing guarantees
 * that happened yet at the point a caller's own code runs. That's why
 * enable() accepts a plain mode name string instead of a Tracy constant.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

$library = __DIR__ . '/../thirdparty/tracy/src/tracy.php';
if (is_file($library)) {
    require_once $library;
}
unset($library);

final class bootstrap
{
    /**
     * @param string|bool|null $mode 'development', 'production', or null/'detect'
     *                                to auto-detect from the client IP. A raw
     *                                bool (as Tracy\Debugger::Development/
     *                                Production would give you) also works.
     * @param string|null $logDirectory Absolute path Tracy logs to, or null to disable.
     */
    public static function enable(string|bool|null $mode = null, ?string $logDirectory = null): void
    {
        if (!class_exists(\Tracy\Debugger::class)) {
            return;
        }

        if (is_string($mode)) {
            $mode = match ($mode) {
                'development' => \Tracy\Debugger::Development,
                'production' => \Tracy\Debugger::Production,
                default => \Tracy\Debugger::Detect,
            };
        }
        \Tracy\Debugger::enable($mode, $logDirectory);
        \Tracy\Debugger::$strictMode = true;
        \Tracy\Debugger::$showBar = PHP_SAPI !== 'cli';
    }

    /**
     * PHP only ever has one active exception/error handler at a time —
     * set_exception_handler()/set_error_handler() simply replace whatever
     * was registered before, there is no stacking. lib/setup.php
     * unconditionally calls set_exception_handler('default_exception_handler')
     * and set_error_handler('default_error_handler', ...) as part of
     * Moodle's own bootstrap, which — if enable() ran earlier, e.g. from
     * config.php — silently steals both handlers back from Tracy for the
     * rest of the request. This detects that without disturbing whichever
     * handler currently holds it.
     *
     * @return string[] Which handler(s) Moodle's bootstrap reclaimed: any of
     *                   'exception', 'error'. Empty if Tracy still owns both
     *                   (e.g. because it was enabled from tool_tracy_after_config(),
     *                   which runs after Moodle registers its own).
     */
    public static function hijackedHandlers(): array
    {
        $hijacked = [];

        $exceptionHandler = set_exception_handler(static fn () => null);
        set_exception_handler($exceptionHandler);
        if ($exceptionHandler === 'default_exception_handler') {
            $hijacked[] = 'exception';
        }

        $errorHandler = set_error_handler(static fn () => false);
        set_error_handler($errorHandler);
        if ($errorHandler === 'default_error_handler') {
            $hijacked[] = 'error';
        }

        return $hijacked;
    }
}
