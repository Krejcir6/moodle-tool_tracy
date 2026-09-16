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
 * Replaces Tracy's global bdump() with one that actually respects
 * $keysToHide/the scrubber.
 *
 * Tracy\Debugger::barDump() (what bdump() calls) does not apply
 * Debugger::$keysToHide or BlueScreen::$scrubber at all — unlike
 * Debugger::dump() and the bluescreen, see README.md's "Sensitive data"
 * section. Without this, `bdump($CFG)` renders $CFG->dbpass and everything
 * else in full, in plain text, in the debug bar.
 *
 * Tracy's own functions.php defines bdump() guarded by
 * `if (!function_exists('bdump'))`, so as long as this file is required
 * BEFORE thirdparty/tracy/src/tracy.php (see classes/bootstrap.php), ours
 * wins and Tracy's own definition is silently skipped.
 *
 * Must have no namespace: bdump() is meant to be called bare, exactly like
 * Tracy's own, from anywhere in the codebase.
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
     * Deliberate escape hatch: identical to Tracy's own, unprotected
     * barDump() — no $keysToHide, no scrubber, nothing masked. Everything
     * dumped this way appears in full, secrets included.
     *
     * This must never be reached by accident, which is why it has its own
     * unmistakable, greppable name instead of e.g. a flag on bdump()
     * (`bdump($x, unsafe: true)` is exactly the kind of thing that gets
     * left in and forgotten), and why its panel title is always prefixed
     * with a visible "UNSAFE" marker regardless of what title is passed.
     *
     * @tracySkipLocation
     */
    function bdump_unsafe(mixed $var, ?string $title = null, array $options = []): mixed
    {
        \Tracy\Debugger::barDump($var, '⚠ UNSAFE' . ($title !== null ? ": {$title}" : ''), $options);

        return $var;
    }
}
