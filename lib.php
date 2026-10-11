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

/**
 * Declares the activation-mode Check API report.
 *
 * @return array<int,\tool_tracy\check\activation>
 */
function tool_tracy_security_checks(): array {
    return [new \tool_tracy\check\activation()];
}

/**
 * Records the current deployment (wwwroot) whenever activation mode changes,
 * so a later request on a different wwwroot can detect the mismatch.
 *
 * @param string $fullname The updated setting's full name (unused).
 */
function tool_tracy_activationmode_updated(string $fullname): void {
    global $CFG, $DB;

    if (get_config('tool_tracy', 'activationmode') === 'off') {
        return;
    }

    $record = $DB->get_record('tool_tracy_deploy', [], '*', IGNORE_MULTIPLE);
    $data = (object) [
        'wwwroot' => tool_tracy_normalize_root($CFG->wwwroot),
        'timecreated' => time(),
    ];
    if ($record === false) {
        $DB->insert_record('tool_tracy_deploy', $data);
        return;
    }

    $data->id = $record->id;
    $DB->update_record('tool_tracy_deploy', $data);
}

/**
 * Standard activation path. Tracy is enabled per request according to the
 * activation mode, once $USER/session exist.
 */
function tool_tracy_after_config(): void {
    $alreadyenabled = class_exists(\Tracy\Debugger::class, false);
    $tracyhandlers = get_config('tool_tracy', 'handlerownership') === 'tracy';

    if (!$alreadyenabled) {
        $activationmode = get_config('tool_tracy', 'activationmode') ?: 'admins';
        if ($activationmode === 'off') {
            return;
        }
        global $CFG;
        if (tool_tracy_disable_if_deployment_mismatch($CFG->wwwroot)) {
            return;
        }
        if ($activationmode === 'admins') {
            global $USER;
            if (empty($USER->id) || !has_capability('moodle/site:config', \context_system::instance())) {
                return;
            }
        }
        $activationips = get_config('tool_tracy', 'activationips');
        $activationips = trim(is_string($activationips) ? $activationips : '');
        $tracymode = tool_tracy_client_ip_is_allowed($activationips) ? 'development' : 'production';

        \tool_tracy\bootstrap::enable($tracymode, null, $tracyhandlers);

        if (!class_exists(\Tracy\Debugger::class, false)) {
            return; // Thirdparty/tracy not vendored.
        }
    }

    // Config.php's $CFG is fully populated by now, unlike when the
    // low-level path called enable() from config.php — see
    // bootstrap::refresh_cfg_scrubbing().
    \tool_tracy\bootstrap::refresh_cfg_scrubbing();

    // Low-level activation owns handlers during early bootstrap. Moodle then
    // installs its own; reclaim them only when explicitly configured to do so.
    if ($tracyhandlers && \tool_tracy\bootstrap::hijacked_handlers() !== []) {
        \tool_tracy\bootstrap::reclaim_handlers();
    }

    if (\Tracy\Debugger::$showBar) { // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $bar = \Tracy\Debugger::getBar();
        $bar->addPanel(new \tool_tracy\routing_panel());
        $bar->addPanel(new \tool_tracy\user_panel());
        $bar->addPanel(new \tool_tracy\session_panel());
        $bar->addPanel(new \tool_tracy\database_panel());
        $bar->addPanel(new \tool_tracy\events_panel());
    }
}

/**
 * Whether the current client IP is covered by the activation IP allowlist.
 *
 * @param string $activationips The allowlist, as configured.
 * @return bool true if the allowlist is empty or the client IP is in it.
 */
function tool_tracy_client_ip_is_allowed(string $activationips): bool {
    return $activationips === '' || remoteip_in_list($activationips);
}

/**
 * Normalizes a wwwroot to scheme://host[:port][/path], lower-cased, so
 * deployment comparisons ignore a trailing slash or host case.
 *
 * @param string $root The wwwroot to normalize.
 * @return string $root, normalized, or '' if it has no host.
 */
function tool_tracy_normalize_root(string $root): string {
    $parts = parse_url(rtrim($root, '/'));
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    return strtolower(($parts['scheme'] ?? '') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? ''));
}

/**
 * Whether the given wwwroot matches the deployment Tracy was activated on.
 *
 * @param string $wwwroot The wwwroot to check.
 * @return bool true if it matches the recorded deployment.
 */
function tool_tracy_deployment_matches(string $wwwroot): bool {
    global $DB;

    $deployment = $DB->get_record('tool_tracy_deploy', [], 'wwwroot', IGNORE_MULTIPLE);
    return $deployment !== false
        && $deployment->wwwroot === tool_tracy_normalize_root($wwwroot);
}

/**
 * Turns activation off if the current wwwroot no longer matches the
 * recorded deployment, so a copied database doesn't silently carry an
 * active debugger to a different site URL.
 *
 * @param string $wwwroot The current wwwroot.
 * @return bool true if activation was turned off.
 */
function tool_tracy_disable_if_deployment_mismatch(string $wwwroot): bool {
    if (tool_tracy_deployment_matches($wwwroot)) {
        return false;
    }

    set_config('activationmode', 'off', 'tool_tracy');
    return true;
}
