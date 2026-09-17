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
 * "restricttoadmins" applies regardless of which path enabled Tracy.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_tracy', get_string('pluginname', 'tool_tracy'));
    $ADMIN->add('tools', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'tool_tracy/enabled',
        get_string('enabled', 'tool_tracy'),
        get_string('enabled_desc', 'tool_tracy'),
        '0'
    ));

    $settings->add(new admin_setting_configselect(
        'tool_tracy/mode',
        get_string('mode', 'tool_tracy'),
        get_string('mode_desc', 'tool_tracy'),
        'detect',
        [
            'detect' => get_string('mode_detect', 'tool_tracy'),
            'development' => get_string('mode_development', 'tool_tracy'),
            'production' => get_string('mode_production', 'tool_tracy'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'tool_tracy/logdirectory',
        get_string('logdirectory', 'tool_tracy'),
        get_string('logdirectory_desc', 'tool_tracy'),
        '',
        PARAM_PATH
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

    $settings->add(new admin_setting_configcheckbox(
        'tool_tracy/restricttoadmins',
        get_string('restricttoadmins', 'tool_tracy'),
        get_string('restricttoadmins_desc', 'tool_tracy'),
        '1'
    ));
}
