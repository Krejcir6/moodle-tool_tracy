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
 * observersFor() reimplements the same trivial get_parent_class() loop.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class events_panel implements \Tracy\IBarPanel
{
    public function getTab(): string
    {
        $count = count(event_log::entries());

        return '<span title="Events">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6-4.5-4.2 6.1-.7z" '
            . 'fill="none" stroke="#6ba9e6" stroke-width="1.4"/>'
            . '</svg>'
            . '<span class="tracy-label">' . $count . ' events</span>'
            . '</span>';
    }

    public function getPanel(): string
    {
        $entries = event_log::entries();

        if ($entries === []) {
            return '<h1>Events</h1><div class="tracy-inner">'
                . '<p>No events triggered during this request.</p></div>';
        }

        $groups = [];
        foreach ($entries as $entry) {
            $class = $entry['class'];
            $groups[$class]['count'] = ($groups[$class]['count'] ?? 0) + 1;
            $groups[$class]['data'] ??= $entry['data'];
        }

        $html = '<h1>Events</h1><div class="tracy-inner">';

        foreach ($groups as $class => $group) {
            $html .= '<h2>' . htmlspecialchars($class, ENT_QUOTES) . ' &times; ' . $group['count'] . '</h2>';
            $html .= \Tracy\Dumper::toHtml(
                scrubber::scrubEventData($group['data']),
                bootstrap::scrubbedDumpOptions()
            );
            $html .= $this->renderObservers($this->observersFor($class));
        }

        $html .= '</div>';

        return $html;
    }

    /** @return array<int, \stdClass> */
    private function observersFor(string $eventclass): array
    {
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

    /** @param array<int, \stdClass> $observers */
    private function renderObservers(array $observers): string
    {
        if ($observers === []) {
            return '<p><em>No observers registered for this event.</em></p>';
        }

        $html = '<table><tr><th>Component</th><th>Callback</th><th>Priority</th></tr>';
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
