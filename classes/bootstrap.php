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

// Must run before the Tracy library require below: bdump.php defines a
// replacement global bdump() guarded by the same function_exists() check
// Tracy's own functions.php uses, so ours has to be declared first for
// Tracy to skip its own (unprotected) one — see bdump.php.
require_once __DIR__ . '/../bdump.php';

// Defines tool_tracy_log_query(), the target of the optional dev/
// moodle_database.patch core patch — see query_log.php and
// classes/database_panel.php.
require_once __DIR__ . '/../query_log.php';

$library = __DIR__ . '/../thirdparty/tracy/src/tracy.php';
if (is_file($library)) {
    require_once $library;
}
unset($library);

// Required explicitly, same reasoning as the Tracy library above: when
// enable() is called from config.php, Moodle's own component autoloader
// isn't registered yet, so \tool_tracy\scrubber (and \tool_tracy\query_log,
// which the very first DB query of the request could conceivably reach
// before lib/setup.php has registered that autoloader) can't be relied on
// to autoload there the way they would once lib/setup.php has run.
require_once __DIR__ . '/scrubber.php';
require_once __DIR__ . '/query_log.php';

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

        // Best effort for right now: from config.php (the low-level path),
        // $CFG only has the ~30 properties config.php itself sets — the
        // few hundred more that lib/setup.php's initialise_cfg() loads from
        // the mdl_config table (siteidentifier and any DB-stored secrets
        // among them) don't exist yet. refreshCfgScrubbing(), called again
        // from tool_tracy_after_config() once bootstrap has actually
        // finished, is what brings this up to full coverage — this call
        // only matters for masking a genuine crash in the tiny window
        // between here and there.
        self::refreshCfgScrubbing();
    }

    /**
     * (Re-)applies scrubbing using $CFG exactly as it is *right now*. Safe
     * to call repeatedly and before $CFG is fully populated (see enable()):
     * the BlueScreen scrubber closure is fully replaced each call (so it
     * always reflects the latest $CFG snapshot), and Debugger::$keysToHide
     * only ever grows (properties $CFG already had stay valid $CFG keys —
     * initialise_cfg() adds more, it never removes any), so calling this
     * again later with a fuller $CFG can only add coverage, never lose it.
     * No-op if Tracy was never enabled at all.
     */
    public static function refreshCfgScrubbing(): void
    {
        if (!class_exists(\Tracy\Debugger::class, false)) {
            return;
        }

        global $CFG;
        $cfgKeys = isset($CFG) ? array_keys((array) $CFG) : [];

        // Safe by default, not opt-in: mask anything sensitive-looking
        // before it can ever be rendered — see classes/scrubber.php for why
        // this needs two different mechanisms to cover both the bluescreen
        // and ad-hoc bdump() calls.
        \Tracy\Debugger::getBlueScreen()->scrubber = scrubber::forLevel(scrubber::LEVEL_STRICT, $cfgKeys);
        \Tracy\Debugger::$keysToHide = array_values(array_unique(array_merge(
            \Tracy\Debugger::$keysToHide,
            scrubber::keysToHide(scrubber::LEVEL_STRICT, $cfgKeys)
        )));
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

    /**
     * Dumper::toHtml()/toText() options that apply the same masking as
     * everywhere else in the plugin (the BlueScreen scrubber, kept current
     * by refreshCfgScrubbing(), plus Debugger::$keysToHide). Bar panels
     * that render arbitrary Moodle globals ($USER, $SESSION, query
     * parameters, ...) use this instead of hand-picking "safe" fields —
     * new properties something adds later are covered automatically,
     * rather than only the ones anyone thought to list.
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
