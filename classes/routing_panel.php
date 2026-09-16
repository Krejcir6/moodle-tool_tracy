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

final class routing_panel implements \Tracy\IBarPanel
{
    public function getTab(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $path = $this->shorten($this->path());

        return '<span title="Current request">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<circle cx="12" cy="12" r="9" fill="none" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M12 3v3M12 18v3M3 12h3M18 12h3" stroke="#6ba9e6" stroke-width="1.6"/>'
            . '<path d="M12 12l4-2-2 4z" fill="#6ba9e6"/>'
            . '</svg>'
            . '<span class="tracy-label">' . htmlspecialchars($method, ENT_QUOTES) . ' ' . htmlspecialchars($path, ENT_QUOTES) . '</span>'
            . '</span>';
    }

    public function getPanel(): string
    {
        global $PAGE;

        $rows = [
            'Method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'URL' => $this->path(),
            'Page type' => $PAGE->pagetype ?? null,
            'Layout' => $PAGE->pagelayout ?? null,
            'Heading' => $PAGE->heading ?? null,
            'Context' => isset($PAGE->context)
                ? $PAGE->context->get_context_name(true, false, false) . ' (id ' . $PAGE->context->id . ')'
                : null,
            'Course' => isset($PAGE->course) && $PAGE->course->id
                ? "{$PAGE->course->shortname} (id {$PAGE->course->id})"
                : null,
        ];

        $html = '<h1>Routing</h1><div class="tracy-inner"><table>';
        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . htmlspecialchars((string) $value, ENT_QUOTES) . '</td></tr>';
        }
        $html .= '</table></div>';

        return $html;
    }

    /** $_SERVER['REQUEST_URI'] with sensitive-looking query params (sesskey, ...) masked. */
    private function path(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;

        if ($uri === null) {
            return 'CLI (PID ' . getmypid() . ')';
        }

        [$path, $query] = array_pad(explode('?', $uri, 2), 2, null);

        if ($query !== null && $query !== '') {
            parse_str($query, $params);
            if ($params !== []) {
                $scrub = scrubber::forLevel();
                foreach ($params as $key => $value) {
                    if (is_string($key) && $scrub($key, $value)) {
                        $params[$key] = '***';
                    }
                }
                $path .= '?' . http_build_query($params);
            }
        }

        return $host !== null ? "//{$host}{$path}" : $path;
    }

    private function shorten(string $path): string
    {
        return strlen($path) > 60 ? substr($path, 0, 57) . '…' : $path;
    }
}
