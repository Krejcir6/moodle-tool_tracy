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
 * Tracy Bar panel showing the current request/page.
 *
 * Reads $_SERVER/$PAGE lazily in getTab()/getPanel(), not the constructor
 * — $PAGE isn't set up yet when this panel gets registered.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

/**
 * Tracy Bar panel showing the current request/page. See the file
 * docblock above for why $_SERVER/$PAGE are read lazily.
 */
final class routing_panel implements \Tracy\IBarPanel {
    /**
     * Tab label shown in the debug bar: method and shortened path.
     * Named getTab() because it implements \Tracy\IBarPanel.
     */
    public function getTab(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $method = $_SERVER['REQUEST_METHOD'] ?? get_string('routing_cli', 'tool_tracy');
        $path = $this->shorten($this->path());

        return '<span title="' . htmlspecialchars(get_string('routing_request', 'tool_tracy'), ENT_QUOTES) . '">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<circle cx="12" cy="12" r="9" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M12 3v3M12 18v3M3 12h3M18 12h3" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M12 12l4-2-2 4z" fill="#6ba9e6"/>'
            . '</svg>'
            . '<span class="tracy-label">' . htmlspecialchars($method, ENT_QUOTES) . ' '
            . htmlspecialchars($path, ENT_QUOTES) . '</span>'
            . '</span>';
    }

    /**
     * Panel body: request method/URL, page type/layout/context/course,
     * and scrubbed GET parameters.
     * Named getPanel() because it implements \Tracy\IBarPanel.
     */
    public function getPanel(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        global $PAGE;

        $rows = [
            get_string('routing_method', 'tool_tracy') => $_SERVER['REQUEST_METHOD'] ?? null,
            get_string('routing_url', 'tool_tracy') => $this->path(),
            get_string('routing_pagetype', 'tool_tracy') => $PAGE->pagetype ?? null,
            get_string('routing_layout', 'tool_tracy') => $PAGE->pagelayout ?? null,
            get_string('routing_heading', 'tool_tracy') => $PAGE->heading ?? null,
            get_string('routing_context', 'tool_tracy') => isset($PAGE->context)
                ? $PAGE->context->get_context_name(true, false, false) . ' (id ' . $PAGE->context->id . ')'
                : null,
            get_string('routing_course', 'tool_tracy') => isset($PAGE->course) && $PAGE->course->id
                ? "{$PAGE->course->shortname} (id {$PAGE->course->id})"
                : null,
        ];

        $html = '<h1>' . htmlspecialchars(get_string('routing_title', 'tool_tracy'), ENT_QUOTES) . '</h1>'
            . '<div class="tracy-inner"><table>';
        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . htmlspecialchars((string) $value, ENT_QUOTES) . '</td></tr>';
        }
        $html .= '</table>';

        if ($_GET !== []) {
            $html .= '<h2>' . htmlspecialchars(get_string('routing_getparams', 'tool_tracy'), ENT_QUOTES) . '</h2>';
            $html .= \Tracy\Dumper::toHtml(
                scrubber::scrub_array($_GET),
                bootstrap::scrubbed_dump_options()
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Request path only. GET parameters are rendered separately from $_GET.
     */
    private function path(): string {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;

        if ($uri === null) {
            $pid = getmypid();

            return get_string('routing_cli_pid', 'tool_tracy', $pid !== false ? $pid : 0);
        }

        $path = explode('?', $uri, 2)[0];

        return $host !== null ? "//{$host}{$path}" : $path;
    }

    /**
     * Truncates a long path for the tab label.
     *
     * @param string $path The path to shorten.
     * @return string $path, truncated to 60 characters if longer.
     */
    private function shorten(string $path): string {
        return strlen($path) > 60 ? substr($path, 0, 57) . '…' : $path;
    }
}
