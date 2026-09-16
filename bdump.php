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
 * Replaces Tracy's global bdump() with one that respects $keysToHide/the
 * scrubber — Tracy\Debugger::barDump() applies neither on its own, unlike
 * dump()/the bluescreen (see README.md's "Sensitive data"), so unpatched
 * `bdump($CFG)` renders $CFG->dbpass in full.
 *
 * Tracy's functions.php defines bdump() guarded by
 * `if (!function_exists('bdump'))`; requiring this before
 * thirdparty/tracy/src/tracy.php (see classes/bootstrap.php) makes ours win.
 *
 * No namespace: bdump() must be callable bare, like Tracy's own.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!function_exists('bdump')) {
    /**
     * @tracySkipLocation
     */
    function bdump(mixed $var, ?string $title = null, array $options = []): mixed
    {
        \Tracy\Debugger::barDump($var, $title, $options + \tool_tracy\bootstrap::scrubbedDumpOptions());

        return $var;
    }
}

if (!function_exists('bdump_unsafe')) {
    /**
     * Deliberate escape hatch: identical to Tracy's own unprotected
     * barDump(), nothing masked. Its own greppable name (rather than e.g.
     * a `bdump($x, unsafe: true)` flag) and the forced "UNSAFE" title
     * prefix are both there so this is never reached by accident.
     *
     * @tracySkipLocation
     */
    function bdump_unsafe(mixed $var, ?string $title = null, array $options = []): mixed
    {
        \Tracy\Debugger::barDump($var, '⚠ UNSAFE' . ($title !== null ? ": {$title}" : ''), $options);

        return $var;
    }
}
