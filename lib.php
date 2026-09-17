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

defined('MOODLE_INTERNAL') || die();

function tool_tracy_security_checks(): array {
    return [new \tool_tracy\check\activation()];
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
        $activationips = trim((string) get_config('tool_tracy', 'activationips'));
        if ($activationips !== '' && !address_in_subnet(getremoteaddr(), $activationips)) {
            return;
        }

        \tool_tracy\bootstrap::enable('development', null, $tracyhandlers);

        if (!class_exists(\Tracy\Debugger::class, false)) {
            return; // thirdparty/tracy not vendored.
        }
    }

    // $CFG is fully populated by now, unlike when the low-level path called
    // enable() from config.php — see bootstrap::refreshCfgScrubbing().
    \tool_tracy\bootstrap::refreshCfgScrubbing();

    // Low-level activation owns handlers during early bootstrap. Moodle then
    // installs its own; reclaim them only when explicitly configured to do so.
    if ($tracyhandlers && \tool_tracy\bootstrap::hijackedHandlers() !== []) {
        \tool_tracy\bootstrap::reclaimHandlers();
    }

    if (\Tracy\Debugger::$showBar) {
        $bar = \Tracy\Debugger::getBar();
        $bar->addPanel(new \tool_tracy\routing_panel());
        $bar->addPanel(new \tool_tracy\user_panel());
        $bar->addPanel(new \tool_tracy\session_panel());
        $bar->addPanel(new \tool_tracy\database_panel());
        $bar->addPanel(new \tool_tracy\events_panel());
    }

    if (!\Tracy\Debugger::$productionMode) {
        return;
    }

}

function tool_tracy_normalize_root(string $root): string {
    $parts = parse_url(rtrim($root, '/'));
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    return strtolower(($parts['scheme'] ?? '') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? ''));
}

function tool_tracy_deployment_matches(string $wwwroot): bool {
    global $DB;

    $deployment = $DB->get_record('tool_tracy_deploy', [], 'wwwroot', IGNORE_MULTIPLE);
    return $deployment !== false
        && $deployment->wwwroot === tool_tracy_normalize_root($wwwroot);
}

function tool_tracy_disable_if_deployment_mismatch(string $wwwroot): bool {
    if (tool_tracy_deployment_matches($wwwroot)) {
        return false;
    }

    set_config('activationmode', 'off', 'tool_tracy');
    return true;
}
