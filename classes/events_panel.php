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
 * Tracy Bar panel showing triggered events, grouped by class, and — for
 * each class — the observers registered to react to it.
 *
 * Observer list: \core\event\manager::get_all_observers() (public, unlike
 * the database panel — no core patch needed). Matching an event to its
 * observers also needs its parent-class chain (a '*' registration becomes
 * \core\event\base internally) — that walk lives in
 * manager::get_observing_classes(), which is `protected`, so
 * observers_for() reimplements the same trivial get_parent_class() loop.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

/**
 * Tracy Bar panel showing triggered events and their observers. See the
 * file docblock above for how observers are resolved per event class.
 */
final class events_panel implements \Tracy\IBarPanel {
    /**
     * Tab label shown in the debug bar, with the triggered-events count.
     * Named getTab() because it implements \Tracy\IBarPanel.
     */
    public function getTab(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $count = count(event_log::entries());

        return '<span title="' . htmlspecialchars(get_string('events_title', 'tool_tracy'), ENT_QUOTES) . '">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6-4.5-4.2 6.1-.7z" '
            . 'fill="none" stroke="#6ba9e6" stroke-width="1.4"/>'
            . '</svg>'
            . '<span class="tracy-label">'
            . htmlspecialchars(get_string('events_tab', 'tool_tracy', $count), ENT_QUOTES) . '</span>'
            . '</span>';
    }

    /**
     * Panel body: triggered events grouped by class, with their observers.
     * Named getPanel() because it implements \Tracy\IBarPanel.
     */
    public function getPanel(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $entries = event_log::entries();

        if ($entries === []) {
            return '<h1>' . htmlspecialchars(get_string('events_title', 'tool_tracy'), ENT_QUOTES) . '</h1>'
                . '<div class="tracy-inner">'
                . '<p>' . htmlspecialchars(get_string('events_none', 'tool_tracy'), ENT_QUOTES) . '</p></div>';
        }

        $groups = [];
        foreach ($entries as $entry) {
            $class = $entry['class'];
            $groups[$class]['count'] = ($groups[$class]['count'] ?? 0) + 1;
            $groups[$class]['data'] ??= $entry['data'];
        }

        $html = '<h1>' . htmlspecialchars(get_string('events_title', 'tool_tracy'), ENT_QUOTES) . '</h1><div class="tracy-inner">';

        foreach ($groups as $class => $group) {
            $html .= '<h2>' . htmlspecialchars($class, ENT_QUOTES) . ' &times; ' . $group['count'] . '</h2>';
            $html .= \Tracy\Dumper::toHtml(
                scrubber::scrub_event_data($group['data']),
                bootstrap::scrubbed_dump_options()
            );
            $html .= $this->render_observers($this->observers_for($class));
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Resolves the observers Moodle would invoke for an event class,
     * walking its parent-class hierarchy (a '*' registration becomes
     * \core\event\base internally).
     *
     * @param string $eventclass Fully-qualified event class name.
     * @return array<int, \stdClass> The observers Moodle would invoke.
     */
    private function observers_for(string $eventclass): array {
        $hierarchy = ['\\' . ltrim($eventclass, '\\')];
        $parent = $eventclass;
        while ($parent = get_parent_class($parent)) {
            $hierarchy[] = '\\' . ltrim($parent, '\\');
        }
        $hierarchy = array_reverse($hierarchy);

        $all = \core\event\manager::get_all_observers();

        $observers = [];
        foreach ($hierarchy as $class) {
            foreach ($all[$class] ?? [] as $observer) {
                $observers[] = $observer;
            }
        }

        return $observers;
    }

    /**
     * Renders an event's observers as an HTML table.
     *
     * @param array $observers The observers to render, as \stdClass objects.
     * @return string The rendered HTML table.
     */
    private function render_observers(array $observers): string {
        if ($observers === []) {
            return '<p><em>' . htmlspecialchars(get_string('events_noobservers', 'tool_tracy'), ENT_QUOTES) . '</em></p>';
        }

        $html = '<table><tr>'
            . '<th>' . htmlspecialchars(get_string('events_component', 'tool_tracy'), ENT_QUOTES) . '</th>'
            . '<th>' . htmlspecialchars(get_string('events_callback', 'tool_tracy'), ENT_QUOTES) . '</th>'
            . '<th>' . htmlspecialchars(get_string('events_priority', 'tool_tracy'), ENT_QUOTES) . '</th>'
            . '</tr>';
        foreach ($observers as $observer) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string) $observer->plugin, ENT_QUOTES) . '</td>'
                . '<td><code>' . htmlspecialchars((string) $observer->callable, ENT_QUOTES) . '</code></td>'
                . '<td>' . htmlspecialchars((string) $observer->priority, ENT_QUOTES) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
