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
 * Tracy Bar panel showing session metadata and $SESSION contents.
 *
 * $SESSION is a grab-bag any plugin can put anything in (SSO/LTI tokens
 * included), so like user_panel it's rendered via
 * bootstrap::scrubbedDumpOptions(), not a hand-picked field list.
 *
 * The raw session ID is never shown, hardcoded regardless of the scrubber
 * — leaking it is equivalent to leaking an auth cookie.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class session_panel implements \Tracy\IBarPanel {
    public function getTab(): string {
        return '<span title="' . htmlspecialchars(get_string('session_title', 'tool_tracy'), ENT_QUOTES) . '">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<circle cx="12" cy="12" r="9" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M12 7v5l4 2" fill="none" stroke="#6ba9e6" stroke-width="1.6" stroke-linecap="round"/>'
            . '</svg>'
            . '<span class="tracy-label">' . htmlspecialchars(get_string('session_label', 'tool_tracy'), ENT_QUOTES) . '</span></span>';
    }

    public function getPanel(): string {
        global $SESSION;

        $meta = [
            get_string('session_started', 'tool_tracy') => get_string(session_id() !== '' ? 'yes' : 'no'),
            get_string('session_handler', 'tool_tracy') => ini_get('session.save_handler') ?: null,
        ];

        $html = '<h1>' . htmlspecialchars(get_string('session_title', 'tool_tracy'), ENT_QUOTES) . '</h1><div class="tracy-inner">'
            .'<table>';
        foreach ($meta as $label => $value) {
            if ($value === null) {
                continue;
            }
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . htmlspecialchars($value, ENT_QUOTES) . '</td></tr>';
        }
        $html .= '</table>';

        $data = isset($SESSION) ? (array) $SESSION : [];
        unset($data['sesskey']); // belt-and-braces alongside the scrubber

        $html .= \Tracy\Dumper::toHtml($data, bootstrap::scrubbedDumpOptions());
        $html .= '</div>';

        return $html;
    }
}
