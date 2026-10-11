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
 * Tracy Bar panel showing database activity.
 *
 * Two tiers: aggregate counters ($DB->perf_get_reads() etc., public API,
 * always shown) and a per-query table needing the optional
 * dev/moodle_database.patch — see query_log.php and README.md. SQL text
 * only, no params (see query_log.php for why).
 *
 * Rows are colour-coded for slow queries and ones repeated &ge;5&times;
 * in one request (a classic N+1 smell), both counted by exact SQL text.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

/**
 * Tracy Bar panel showing database activity. See the file docblock above
 * for the two tiers of detail it renders.
 */
final class database_panel implements \Tracy\IBarPanel {
    /** Row background switches to "slow" (red) at/above this, in ms. */
    private const SLOW_MS = 20.0;

    /** Row background switches to "moderate" (amber) at/above this, in ms. */
    private const MODERATE_MS = 5.0;

    /** Same exact SQL text executed at least this many times => flagged as frequent. */
    private const FREQUENT_THRESHOLD = 5;

    /**
     * Tab label shown in the debug bar: query counts/time, red if flagged.
     * Named getTab() because it implements \Tracy\IBarPanel.
     */
    public function getTab(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        global $DB;

        $label = get_string('db_tab', 'tool_tracy', $DB->perf_get_reads() + $DB->perf_get_writes());
        $label .= sprintf(' (%.1f ms)', $DB->perf_get_queries_time() * 1000);

        $warn = $this->has_warnings();
        $color = $warn ? '#c00' : '#6ba9e6';

        return '<span title="' . htmlspecialchars(get_string('db_title', 'tool_tracy'), ENT_QUOTES) . '">'
            . '<svg viewBox="0 0 24 24" width="16" height="16">'
            . '<ellipse cx="12" cy="5" rx="8" ry="3" fill="none" stroke="' . $color . '" stroke-width="1.6"/>'
            . '<path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5" fill="none" stroke="' . $color . '" stroke-width="1.6"/>'
            . '<path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" fill="none" stroke="' . $color . '" stroke-width="1.6"/>'
            . '</svg>'
            . '<span class="tracy-label">' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
            . '</span>';
    }

    /**
     * Panel body: aggregate counters plus the per-query table (duplicate
     * summary and full list) when query logging is active.
     * Named getPanel() because it implements \Tracy\IBarPanel.
     */
    public function getPanel(): string { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        global $DB;

        $rows = [
            get_string('db_reads', 'tool_tracy') => $DB->perf_get_reads(),
            get_string('db_reads_replica', 'tool_tracy') => $this->reads_replica($DB),
            get_string('db_writes', 'tool_tracy') => $DB->perf_get_writes(),
            get_string('db_totaltime', 'tool_tracy') => sprintf('%.2f ms', $DB->perf_get_queries_time() * 1000),
        ];

        $html = '<h1>' . htmlspecialchars(get_string('db_title', 'tool_tracy'), ENT_QUOTES) . '</h1>'
            . '<div class="tracy-inner"><table>';
        foreach ($rows as $label => $value) {
            if ($value === null) {
                continue;
            }
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . htmlspecialchars((string) $value, ENT_QUOTES) . '</td></tr>';
        }
        $html .= '</table>';

        $entries = query_log::entries();

        if ($entries === []) {
            $html .= '<p>' . htmlspecialchars(get_string('db_nolog', 'tool_tracy'), ENT_QUOTES) . '</p>';
        } else {
            $counts = array_count_values(array_column($entries, 'sql'));

            $html .= '<p><span style="' . $this->style(self::MODERATE_MS, 0) . '">&nbsp;&nbsp;</span> &ge; '
                . self::MODERATE_MS . ' ms &nbsp; '
                . '<span style="' . $this->style(self::SLOW_MS, 0) . '">&nbsp;&nbsp;</span> &ge; '
                . self::SLOW_MS . ' ms &nbsp; '
                . '<span style="' . $this->style(0, self::FREQUENT_THRESHOLD) . '">&nbsp;&nbsp;</span> '
                . htmlspecialchars(get_string('db_legend_frequent', 'tool_tracy', self::FREQUENT_THRESHOLD), ENT_QUOTES)
                . '</p>';

            $duplicates = $this->duplicate_summary($entries);
            if ($duplicates !== []) {
                $html .= '<h2>' . htmlspecialchars(get_string('db_duplicates', 'tool_tracy'), ENT_QUOTES) . '</h2>'
                    . '<table><tr><th>&times;</th><th>'
                    . htmlspecialchars(get_string('db_totaltime', 'tool_tracy'), ENT_QUOTES)
                    . '</th><th>SQL</th></tr>';
                foreach ($duplicates as $sql => $group) {
                    $html .= '<tr style="' . $this->style(0, $group['count']) . '">'
                        . '<td>' . htmlspecialchars((string) $group['count'], ENT_QUOTES) . '</td>'
                        . '<td>' . htmlspecialchars(sprintf('%.2f ms', $group['time'] * 1000), ENT_QUOTES) . '</td>'
                        . '<td><code>' . htmlspecialchars((string) $sql, ENT_QUOTES) . '</code></td>'
                        . '</tr>';
                }
                $html .= '</table>';
            }

            $html .= '<h2>' . htmlspecialchars(get_string('db_allqueries', 'tool_tracy'), ENT_QUOTES) . '</h2>';
            $html .= '<table><tr><th>' . htmlspecialchars(get_string('db_time', 'tool_tracy'), ENT_QUOTES)
                . '</th><th>&times;</th><th>SQL</th></tr>';
            foreach ($entries as $entry) {
                $timems = $entry['time'] * 1000;
                $count = $counts[$entry['sql']] ?? 1;

                $html .= '<tr style="' . $this->style($timems, $count) . '">'
                    . '<td>' . htmlspecialchars(sprintf('%.2f ms', $timems), ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars((string) $count, ENT_QUOTES) . '</td>'
                    . '<td><code>' . htmlspecialchars((string) $entry['sql'], ENT_QUOTES) . '</code></td>'
                    . '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Moodle 5.1 renamed perf_get_reads_slave() to perf_get_reads_replica()
     * and deprecated the old name.
     *
     * @param \moodle_database $db The current database connection.
     * @return ?int The replica read count, or null if neither method exists.
     */
    private function reads_replica(\moodle_database $db): ?int {
        return match (true) {
            method_exists($db, 'perf_get_reads_replica') => $db->perf_get_reads_replica(),
            method_exists($db, 'perf_get_reads_slave') => $db->perf_get_reads_slave(),
            default => null,
        };
    }

    /**
     * Groups by exact SQL text, keeping only those run
     * self::FREQUENT_THRESHOLD+ times, with combined time — the number
     * that matters for N+1, since one occurrence alone can look harmless.
     *
     * @param array $entries The request's logged query entries, as returned by query_log::entries().
     * phpcs:ignore moodle.Commenting.ValidTags.Invalid
     * @phpstan-param array<int,array{sql:?string,time:float,error:mixed}> $entries
     * @return array<string,array{count:int,time:float}> Duplicates, keyed by SQL text.
     */
    private function duplicate_summary(array $entries): array {
        $groups = [];
        foreach ($entries as $entry) {
            $sql = $entry['sql'] ?? '';
            $groups[$sql]['count'] = ($groups[$sql]['count'] ?? 0) + 1;
            $groups[$sql]['time'] = ($groups[$sql]['time'] ?? 0.0) + $entry['time'];
        }

        $duplicates = array_filter($groups, static fn (array $g): bool => $g['count'] >= self::FREQUENT_THRESHOLD);
        uasort($duplicates, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        return $duplicates;
    }

    /**
     * Whether any logged query is slow or repeated often enough to flag.
     */
    private function has_warnings(): bool {
        $entries = query_log::entries();
        if ($entries === []) {
            return false;
        }

        foreach (array_count_values(array_column($entries, 'sql')) as $count) {
            if ($count >= self::FREQUENT_THRESHOLD) {
                return true;
            }
        }

        foreach ($entries as $entry) {
            if ($entry['time'] * 1000 >= self::MODERATE_MS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Row background colour for a given query time/repeat count.
     *
     * @param float $timems The query's time, in milliseconds.
     * @param int $count The query's repeat count.
     * @return string A CSS "background:#hex" declaration, or '' if neither
     *                 threshold is met.
     */
    private function style(float $timems, int $count): string {
        if ($timems >= self::SLOW_MS) {
            return 'background:#ffcccc';
        }
        if ($count >= self::FREQUENT_THRESHOLD) {
            return 'background:#e0d4f7';
        }
        if ($timems >= self::MODERATE_MS) {
            return 'background:#fff3cd';
        }

        return '';
    }
}
