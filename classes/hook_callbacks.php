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

namespace tool_tracy;

/**
 * Hook callbacks.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Standard activation path, run once Moodle's config, session and $USER are ready.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG;

        // The legacy callback path skipped these states and swallowed exceptions; hooks do neither.
        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        require_once(__DIR__ . '/../lib.php');

        try {
            tool_tracy_after_config();
        } catch (\Throwable $e) {
            debugging('Exception in tool_tracy activation: ' . $e->getMessage(), DEBUG_DEVELOPER, $e->getTrace());
        }
    }
}
