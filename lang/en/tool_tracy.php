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
$string['activationmode'] = 'Tracy activation';
$string['activationmode_desc'] = 'Controls both Tracy activation and debug bar visibility. The default is administrators only. Keep Tracy disabled in production.';
$string['activationmode_off'] = 'Disabled';
$string['activationmode_admins'] = 'Administrators only (recommended)';
$string['activationmode_all'] = 'All users (development only)';
$string['activationips'] = 'Allowed IP addresses';
$string['activationips_desc'] = 'Optional IP addresses or subnets from which standard Tracy activation is allowed. Leave empty to allow any client IP. Moodle proxy settings determine the client IP.';
$string['deploymentmismatch'] = 'Deployment identity mismatch';
$string['deploymentmismatch_desc'] = 'The stored Moodle URL differs from the current $CFG->wwwroot. Tracy has been disabled. Save the Tracy activation setting as administrators only or all users to register the current URL and re-enable Tracy.';
$string['handlerownership'] = 'Error handler ownership';
$string['handlerownership_desc'] = 'Choose whether Moodle keeps its standard exception/error handlers after bootstrap, or Tracy replaces them. Moodle handlers preserve Moodle-specific AJAX responses, redirects, logging, rendering, and exit semantics. Tracy handlers provide full post-bootstrap Tracy bluescreens.';
$string['handlerownership_moodle'] = 'Moodle handlers (recommended)';
$string['handlerownership_tracy'] = 'Tracy handlers';
$string['db_title'] = 'Database';
$string['db_tab'] = '{$a} queries';
$string['db_reads'] = 'Reads';
$string['db_reads_replica'] = 'Reads (replica)';
$string['db_writes'] = 'Writes';
$string['db_totaltime'] = 'Total time';
$string['db_time'] = 'Time';
$string['db_duplicates'] = 'Duplicate queries (possible N+1)';
$string['db_allqueries'] = 'All queries';
$string['db_legend_frequent'] = 'run ≥ {$a}× (possible N+1)';
$string['db_nolog'] = 'Per-query log not available. Moodle core does not expose a public per-query hook, so this needs an optional, development-only patch to lib/dml/moodle_database.php. See dev/moodle_database.patch and the "Database panel" section of README.md.';
$string['events_title'] = 'Events';
$string['events_tab'] = '{$a} events';
$string['events_none'] = 'No events triggered during this request.';
$string['events_noobservers'] = 'No observers registered for this event.';
$string['events_component'] = 'Component';
$string['events_callback'] = 'Callback';
$string['events_priority'] = 'Priority';
$string['user_title'] = 'Current user';
$string['user_notloggedin'] = 'not logged in';
$string['user_loggedin'] = 'Logged in';
$string['user_guest'] = 'Guest';
$string['user_siteadmin'] = 'Site admin';
$string['session_title'] = 'Session';
$string['session_label'] = 'session';
$string['session_started'] = 'Session started';
$string['session_handler'] = 'PHP session handler';
$string['routing_title'] = 'Routing';
$string['routing_request'] = 'Current request';
$string['routing_method'] = 'Method';
$string['routing_url'] = 'URL';
$string['routing_pagetype'] = 'Page type';
$string['routing_layout'] = 'Layout';
$string['routing_heading'] = 'Heading';
$string['routing_context'] = 'Context';
$string['routing_course'] = 'Course';
$string['routing_getparams'] = 'GET parameters';
$string['routing_cli'] = 'CLI';
$string['routing_cli_pid'] = 'CLI (PID {$a})';
$string['check_activation_name'] = 'Tracy activation';
$string['check_activation_off'] = 'Tracy is disabled.';
$string['check_activation_admins'] = 'Tracy is available to administrators only.';
$string['check_activation_all'] = 'Tracy is available to all users.';
$string['check_activation_all_details'] = 'This is recommended only for controlled development diagnostics. Disable Tracy in production or restrict it to administrators.';
$string['check_activation_mismatch'] = 'Tracy deployment identity does not match the current Moodle URL.';
$string['check_activation_mismatch_details'] = 'Tracy standard activation will be disabled. Review the deployment identity after a deliberate URL change.';
$string['privacy:metadata:log'] = 'The experimental low-level Tracy API can write diagnostic logs outside Moodle-managed database storage. Administrators control their location, access, retention, and deletion.';
$string['privacy:metadata:log:context'] = 'Application and request context captured when an error occurs.';
$string['privacy:metadata:log:exception'] = 'Exception type and message.';
$string['privacy:metadata:log:requesturl'] = 'The URL of the request that caused the diagnostic entry.';
$string['privacy:metadata:log:stacktrace'] = 'The exception stack trace, including function arguments that were available to Tracy.';
$string['privacy:metadata:log:userid'] = 'User identifiers that may be present in request context, objects, arguments, or URLs.';
