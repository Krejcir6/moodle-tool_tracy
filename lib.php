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
 * Library of interface functions and constants.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Second, later activation path: if config.php didn't already enable Tracy
 * (the low-level path, see classes/bootstrap.php), this admin-configured
 * fallback can still turn it on here, once $USER/session are available.
 * It won't catch errors from earlier in bootstrap, but needs no file edits.
 *
 * This is also the earliest point at which the debug bar's visibility can
 * depend on capabilities rather than just the Tracy production/development
 * mode, which is used regardless of which path enabled Tracy.
 */
function tool_tracy_after_config(): void {
    $alreadyenabled = class_exists(\Tracy\Debugger::class, false);

    if (!$alreadyenabled) {
        if (empty(get_config('tool_tracy', 'enabled'))) {
            return;
        }

        // enable() does its own string-to-constant translation internally —
        // see classes/bootstrap.php for why callers must not reference
        // \Tracy\Debugger themselves here.
        \tool_tracy\bootstrap::enable(
            get_config('tool_tracy', 'mode') ?: null,
            get_config('tool_tracy', 'logdirectory') ?: null
        );

        if (!class_exists(\Tracy\Debugger::class, false)) {
            // thirdparty/tracy is not vendored; nothing more to do.
            return;
        }
    }

    // $CFG is fully populated by now (initialise_cfg() has already loaded
    // everything from mdl_config), unlike when the low-level path called
    // enable() from config.php — refresh scrubbing against the real, full
    // $CFG so both activation paths converge to the same protection for
    // everything that happens from here on, which is virtually the entire
    // request. See bootstrap::refreshCfgScrubbing().
    \tool_tracy\bootstrap::refreshCfgScrubbing();

    if (\Tracy\Debugger::$showBar) {
        $hijacked = \tool_tracy\bootstrap::hijackedHandlers();
        if ($hijacked !== []) {
            \Tracy\Debugger::getBar()->addPanel(new \tool_tracy\handler_check_panel($hijacked));
        }
    }

    if (!\Tracy\Debugger::$productionMode) {
        return;
    }

    if (empty(get_config('tool_tracy', 'restricttoadmins'))) {
        return;
    }

    global $USER;
    if (empty($USER->id) || !has_capability('moodle/site:config', \context_system::instance())) {
        \Tracy\Debugger::$showBar = false;
    }
}
