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
 * Upgrade steps for tool_tracy.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the upgrade steps needed to reach the plugin's current version.
 */
function xmldb_tool_tracy_upgrade(int $oldversion): bool {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091702) {
        $table = new xmldb_table('tool_tracy_deploy');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('wwwroot', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        $record = $DB->get_record('tool_tracy_deploy', [], '*', IGNORE_MULTIPLE);
        if (!$record) {
            $DB->insert_record('tool_tracy_deploy', (object) [
                'wwwroot' => tool_tracy_upgrade_normalize_root($CFG->wwwroot),
                'timecreated' => time(),
            ]);
        }
        upgrade_plugin_savepoint(true, 2026091702, 'tool', 'tracy');
    }

    return true;
}

/**
 * Normalizes a wwwroot to scheme://host[:port][/path], lower-cased.
 */
function tool_tracy_upgrade_normalize_root(string $root): string {
    $parts = parse_url(rtrim($root, '/'));
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    return strtolower(($parts['scheme'] ?? '') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? ''));
}
