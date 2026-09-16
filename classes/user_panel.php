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
 * Tracy Bar panel showing the current $USER.
 *
 * Deliberately dumps $USER wholesale through the same scrubbed Dumper
 * options as everything else in the plugin (bootstrap::scrubbedDumpOptions())
 * rather than hand-picking "safe" fields: $USER is a plain stdClass any
 * auth plugin/SSO integration/custom profile field can add properties to,
 * so a hand-picked field list would need updating every time one of those
 * adds something — the scrubber covers whatever's there today and whatever
 * gets added later, the same way it does for $CFG.
 *
 * It's always the *viewing* user's own data — not a privacy leak of
 * someone else's account — but the debug bar itself is visible to every
 * logged-in user (not just admins) whenever Tracy is in development mode,
 * so this still goes through the same masking as everything else.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class user_panel implements \Tracy\IBarPanel
{
    public function getTab(): string
    {
        global $USER;

        $label = (!empty($USER->id) && (int) $USER->id > 0)
            ? ($USER->username ?? ('id ' . $USER->id))
            : 'not logged in';

        return '<span title="Current user">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<circle cx="12" cy="8" r="4" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '</svg>'
            . '<span class="tracy-label">' . htmlspecialchars((string) $label, ENT_QUOTES) . '</span>'
            . '</span>';
    }

    public function getPanel(): string
    {
        global $USER;

        $flags = [
            'Logged in' => isloggedin() ? 'yes' : 'no',
            'Guest' => isguestuser() ? 'yes' : 'no',
            'Site admin' => is_siteadmin() ? 'yes' : 'no',
        ];

        $html = '<h1>Current user</h1><div class="tracy-inner"><table>';
        foreach ($flags as $label => $value) {
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . htmlspecialchars($value, ENT_QUOTES) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= \Tracy\Dumper::toHtml((array) $USER, bootstrap::scrubbedDumpOptions());
        $html .= '</div>';

        return $html;
    }
}
