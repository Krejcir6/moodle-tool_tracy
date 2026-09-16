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
 * Moodle calls this once $USER and the session are available, so this is
 * the earliest point at which we can make the debug bar's visibility depend
 * on capabilities rather than just the Tracy production/development mode.
 */
function tool_tracy_after_config(): void {
    if (!class_exists(\Tracy\Debugger::class) || !\Tracy\Debugger::$productionMode) {
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
