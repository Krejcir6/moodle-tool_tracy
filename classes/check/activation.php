<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Site administration "Check API" report of the current activation mode.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy\check;

use core\check\check;
use core\check\result;

/**
 * Reports the current Tracy activation mode and deployment status.
 */
final class activation extends check {
    /**
     * Display name for this check.
     */
    public function get_name(): string {
        return get_string('check_activation_name', 'tool_tracy');
    }

    /**
     * Current activation mode and deployment-mismatch status.
     */
    public function get_result(): result {
        global $CFG;

        $mode = get_config('tool_tracy', 'activationmode') ?: 'admins';
        if (!\tool_tracy_deployment_matches($CFG->wwwroot)) {
            return new result(
                result::ERROR,
                get_string('check_activation_mismatch', 'tool_tracy'),
                get_string('check_activation_mismatch_details', 'tool_tracy')
            );
        }

        if ($mode === 'all') {
            return new result(
                result::WARNING,
                get_string('check_activation_all', 'tool_tracy'),
                get_string('check_activation_all_details', 'tool_tracy')
            );
        }

        if ($mode === 'admins') {
            return new result(result::INFO, get_string('check_activation_admins', 'tool_tracy'));
        }

        return new result(result::OK, get_string('check_activation_off', 'tool_tracy'));
    }
}
