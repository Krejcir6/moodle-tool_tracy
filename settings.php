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
 * Admin settings — the high-level activation path, see README.md.
 * The activation mode applies to the standard post-bootstrap path. The
 * experimental config.php path is intentionally outside this setting.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_tracy', get_string('pluginname', 'tool_tracy'));
    $ADMIN->add('tools', $settings);

    $settings->add(new admin_setting_configselect(
        'tool_tracy/activationmode',
        get_string('activationmode', 'tool_tracy'),
        get_string('activationmode_desc', 'tool_tracy'),
        'admins',
        [
            'off' => get_string('activationmode_off', 'tool_tracy'),
            'admins' => get_string('activationmode_admins', 'tool_tracy'),
            'all' => get_string('activationmode_all', 'tool_tracy'),
        ]
    ));


    $settings->add(new admin_setting_configselect(
        'tool_tracy/handlerownership',
        get_string('handlerownership', 'tool_tracy'),
        get_string('handlerownership_desc', 'tool_tracy'),
        'moodle',
        [
            'moodle' => get_string('handlerownership_moodle', 'tool_tracy'),
            'tracy' => get_string('handlerownership_tracy', 'tool_tracy'),
        ]
    ));

}
