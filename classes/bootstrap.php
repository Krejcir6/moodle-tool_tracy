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
 * Required directly from config.php, so no dependency on $DB/autoloading —
 * none of that exists yet at that point. Callers must never reference
 * \Tracy\Debugger before calling enable(): this file is what defines it,
 * so nothing guarantees it's loaded yet at the point a caller's own code
 * runs. That's why enable() takes a plain mode string, not a Tracy constant.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

// Must run before the Tracy require below: bdump.php's replacement bdump()
// is guarded the same way Tracy's own is, so ours has to load first.
require_once __DIR__ . '/../bdump.php';

// Defines tool_tracy_log_query(), target of the optional dev/moodle_database.patch.
require_once __DIR__ . '/../query_log.php';

$library = __DIR__ . '/../thirdparty/tracy/src/tracy.php';
if (is_file($library)) {
    require_once $library;
}
unset($library);

// Explicit requires: Moodle's autoloader isn't registered yet this early.
require_once __DIR__ . '/scrubber.php';
require_once __DIR__ . '/query_log.php';

final class bootstrap
{
    /**
     * @param string|array|bool|null $mode 'development', 'production', or
     *                                IP whitelist. A raw Tracy\Debugger bool works too.
     * @param string|null $logDirectory Absolute path Tracy logs to, or null to disable.
     * @param bool $keepHandlers Whether Tracy should remain the active exception/error handler.
     */
    public static function enable(
        string|array|bool|null $mode = null,
        ?string $logDirectory = null,
        bool $keepHandlers = true
    ): void
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
        $handlers = $keepHandlers ? null : self::captureHandlers();
        $runtime = $keepHandlers ? null : [
            'error_reporting' => error_reporting(),
            'display_errors' => ini_get('display_errors'),
            'html_errors' => ini_get('html_errors'),
            'log_errors' => ini_get('log_errors'),
            'zend.exception_ignore_args' => ini_get('zend.exception_ignore_args'),
        ];

        \Tracy\Debugger::enable($mode, $logDirectory);

        if ($handlers !== null) {
            self::restoreHandlers($handlers);
            error_reporting($runtime['error_reporting']);
            foreach (['display_errors', 'html_errors', 'log_errors', 'zend.exception_ignore_args'] as $setting) {
                if ($runtime[$setting] !== false) {
                    ini_set($setting, (string) $runtime[$setting]);
                }
            }
        }
        \Tracy\Debugger::$strictMode = true;
        \Tracy\Debugger::$showBar = PHP_SAPI !== 'cli';

        // $CFG only has config.php's own ~30 properties this early, not the
        // ~600 initialise_cfg() loads later — refreshCfgScrubbing() gets
        // called again once that's done. This call only matters for a
        // crash in the narrow window before then.
        self::refreshCfgScrubbing();
    }

    /**
     * Captures the currently active PHP handlers without changing them.
     *
     * @return array{exception: callable|null, error: callable|null}
     */
    public static function captureHandlers(): array
    {
        $exceptionHandler = set_exception_handler(static fn () => null);
        set_exception_handler($exceptionHandler);

        $errorHandler = set_error_handler(static fn () => false);
        set_error_handler($errorHandler);

        return [
            'exception' => $exceptionHandler,
            'error' => $errorHandler,
        ];
    }

    /** @param array{exception: callable|null, error: callable|null} $handlers */
    public static function restoreHandlers(array $handlers): void
    {
        set_exception_handler($handlers['exception']);
        set_error_handler($handlers['error']);
    }

    /**
     * (Re-)applies scrubbing using $CFG as it is *right now*. Safe to call
     * repeatedly: each call fully replaces the scrubber, and $keysToHide
     * only ever grows, so a later call with a fuller $CFG can't lose
     * coverage. No-op if Tracy was never enabled.
     */
    public static function refreshCfgScrubbing(): void
    {
        if (!class_exists(\Tracy\Debugger::class, false)) {
            return;
        }

        global $CFG;
        $cfgKeys = isset($CFG) ? array_keys((array) $CFG) : [];

        \Tracy\Debugger::getBlueScreen()->scrubber = scrubber::forLevel(scrubber::LEVEL_STRICT, $cfgKeys);
        \Tracy\Debugger::$keysToHide = array_values(array_unique(array_merge(
            \Tracy\Debugger::$keysToHide,
            scrubber::keysToHide(scrubber::LEVEL_STRICT, $cfgKeys)
        )));
    }

    /**
     * PHP has one active exception/error handler at a time, no stacking.
     * lib/setup.php unconditionally installs Moodle's own
     * default_exception_handler/default_error_handler, stealing them back
     * from Tracy if enable() ran earlier (the low-level path). Peeks
     * without disturbing whichever handler currently holds it.
     *
     * @return string[] Any of 'exception', 'error' — which Moodle reclaimed.
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

    /**
     * Takes the exception/error handlers back from Moodle. Not done by
     * calling \Tracy\Debugger::enable() again — it no-ops once already
     * enabled — so this reimplements its two registration lines directly
     * (exceptionHandler()/errorHandler() are public static, made for this).
     */
    public static function reclaimHandlers(): void
    {
        if (!class_exists(\Tracy\Debugger::class, false)) {
            return;
        }

        set_exception_handler(static function (\Throwable $e): void {
            self::abortOpenTransactions();
            \Tracy\Debugger::exceptionHandler($e);
            exit(255);
        });
        set_error_handler([\Tracy\Debugger::class, 'errorHandler']);
    }

    /**
     * Mirrors default_exception_handler()'s first line: an exception
     * handler must never leave a Moodle DB transaction open. Split out
     * from reclaimHandlers()'s closure so it's testable without triggering
     * a real exit(255). Safe/cheap to always call — no-ops if nothing's open.
     */
    public static function abortOpenTransactions(): void
    {
        if (function_exists('abort_all_db_transactions')) {
            abort_all_db_transactions();
        }
    }

    /**
     * Dumper options applying the same masking as everywhere else in the
     * plugin. Panels rendering arbitrary Moodle globals ($USER, $SESSION,
     * ...) use this instead of hand-picking "safe" fields, so properties
     * added later are covered automatically too.
     */
    public static function scrubbedDumpOptions(): array
    {
        if (!class_exists(\Tracy\Debugger::class, false)) {
            return [];
        }

        return [
            \Tracy\Dumper::KEYS_TO_HIDE => \Tracy\Debugger::$keysToHide,
            \Tracy\Dumper::SCRUBBER => \Tracy\Debugger::getBlueScreen()->scrubber,
        ];
    }
}
