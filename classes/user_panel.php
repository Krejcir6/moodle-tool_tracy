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
 * Dumps $USER wholesale through bootstrap::scrubbed_dump_options() rather
 * than hand-picking "safe" fields — same reasoning as $CFG: any auth/SSO
 * plugin can add properties, and a hand-picked list would go stale. Always
 * the *viewing* user's own data, but the bar is visible to every logged-in
 * user (not just admins) in development mode, so it's still scrubbed.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

/**
 * Tracy Bar panel showing the current $USER. See the file docblock above
 * for why it dumps $USER wholesale rather than hand-picking fields.
 */
final class user_panel implements \Tracy\IBarPanel {
    /**
     * Tab label shown in the debug bar, with the current user's identity.
     * Named getTab() because it implements \Tracy\IBarPanel.
     */
    public function getTab(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        global $USER;

        $label = (!empty($USER->id) && (int) $USER->id > 0)
            ? ($USER->username ?? ('id ' . $USER->id))
            : get_string('user_notloggedin', 'tool_tracy');

        return '<span title="' . htmlspecialchars(get_string('user_title', 'tool_tracy'), ENT_QUOTES) . '">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<circle cx="12" cy="8" r="4" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '</svg>'
            . '<span class="tracy-label">' . htmlspecialchars((string) $label, ENT_QUOTES) . '</span>'
            . '</span>';
    }

    /**
     * Panel body: login/guest/admin flags plus a scrubbed $USER dump.
     * Named getPanel() because it implements \Tracy\IBarPanel.
     */
    public function getPanel(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        global $USER;

        $flags = [
            get_string('user_loggedin', 'tool_tracy') => get_string(isloggedin() ? 'yes' : 'no'),
            get_string('user_guest', 'tool_tracy') => get_string(isguestuser() ? 'yes' : 'no'),
            get_string('user_siteadmin', 'tool_tracy') => get_string(is_siteadmin() ? 'yes' : 'no'),
        ];

        $html = '<h1>' . htmlspecialchars(get_string('user_title', 'tool_tracy'), ENT_QUOTES) . '</h1>'
            . '<div class="tracy-inner"><table>';
        foreach ($flags as $label => $value) {
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . htmlspecialchars($value, ENT_QUOTES) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= \Tracy\Dumper::toHtml((array) $USER, bootstrap::scrubbed_dump_options());
        $html .= '</div>';

        return $html;
    }
}
