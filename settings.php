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
 * Admin settings.
 *
 * These settings are informational / used by admin-facing behaviour that
 * runs after bootstrap (see lib.php). The Debugger::enable() call itself
 * happens before Moodle's config is available, so it is controlled via
 * environment variables instead — see classes/bootstrap.php and README.md.
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
        'tool_tracy/restricttoadmins',
        get_string('restricttoadmins', 'tool_tracy'),
        get_string('restricttoadmins_desc', 'tool_tracy'),
        1
    ));
}
