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
 * Post-install hook: records the deployment this install happened on.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Records the current wwwroot as the deployment Tracy was installed on.
 */
function xmldb_tool_tracy_install(): void {
    global $CFG, $DB;

    $DB->insert_record('tool_tracy_deploy', (object) [
        'wwwroot' => tool_tracy_install_normalize_root($CFG->wwwroot),
        'timecreated' => time(),
    ]);
}

/**
 * Normalizes a wwwroot to scheme://host[:port][/path], lower-cased.
 */
function tool_tracy_install_normalize_root(string $root): string {
    $parts = parse_url(rtrim($root, '/'));
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    return strtolower(($parts['scheme'] ?? '') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? ''));
}
