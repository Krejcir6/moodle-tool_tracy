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
 * Strings for component 'tool_tracy'.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Tracy debugger';
$string['enabled'] = 'Enable Tracy (admin settings)';
$string['enabled_desc'] = 'Turns on Tracy from these settings once Moodle has fully bootstrapped. Has no effect if config.php already enabled Tracy directly via \\tool_tracy\\bootstrap::enable() — that call always takes priority and is required if you need Tracy to catch errors during Moodle\'s own bootstrap.';
$string['mode'] = 'Mode';
$string['mode_desc'] = 'Only used when Tracy is enabled from these settings (see above).';
$string['mode_detect'] = 'Auto-detect (Tracy decides from the client IP)';
$string['mode_development'] = 'Development (show the debug bar and full error detail)';
$string['mode_production'] = 'Production (suppress debug output, log/email errors instead)';
$string['logdirectory'] = 'Log directory';
$string['logdirectory_desc'] = 'Absolute path Tracy writes its logs to. Leave empty to disable file logging. Only used when Tracy is enabled from these settings (see above).';
$string['handlerownership'] = 'Error handler ownership';
$string['handlerownership_desc'] = 'Choose whether Moodle keeps its standard exception/error handlers after bootstrap, or Tracy replaces them. Moodle handlers preserve Moodle-specific AJAX responses, redirects, logging, rendering, and exit semantics. Tracy handlers provide full post-bootstrap Tracy bluescreens.';
$string['handlerownership_moodle'] = 'Moodle handlers (recommended)';
$string['handlerownership_tracy'] = 'Tracy handlers';
$string['restricttoadmins'] = 'Restrict debug bar to admins in production mode';
$string['restricttoadmins_desc'] = 'When Tracy runs in production mode, only show the debug bar to users with the moodle/site:config capability. Applies regardless of which of the two activation paths enabled Tracy.';
$string['privacy:metadata:log'] = 'When file logging is configured, Tracy writes diagnostic logs outside Moodle-managed database storage. Administrators control their location, access, retention, and deletion.';
$string['privacy:metadata:log:context'] = 'Application and request context captured when an error occurs.';
$string['privacy:metadata:log:exception'] = 'Exception type and message.';
$string['privacy:metadata:log:requesturl'] = 'The URL of the request that caused the diagnostic entry.';
$string['privacy:metadata:log:stacktrace'] = 'The exception stack trace, including function arguments that were available to Tracy.';
$string['privacy:metadata:log:userid'] = 'User identifiers that may be present in request context, objects, arguments, or URLs.';
