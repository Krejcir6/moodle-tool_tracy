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

namespace tool_tracy;

/**
 * Tests for \tool_tracy\query_log.
 *
 * Regression coverage for a real leak found live: params are positional
 * (key names lost) by the time moodle_database::query_log() runs, so
 * commit() must never surface them for display — only capture()'s
 * still-named params, correlated by SQL text, are ever shown. See
 * classes/query_log.php and classes/database_panel.php.
 *
 * Every test runs @runInSeparateProcess and uses a unique, made-up SQL
 * string as a marker: this environment's own config.php enables Tracy
 * (and the query-logging core patch) unconditionally on every request,
 * so real bootstrap queries flow through the exact same static state
 * these tests exercise — asserting on entries()'s overall shape/count
 * would be testing bootstrap noise, not this class. Filtering to just the
 * marker SQL sidesteps that regardless of what else got logged.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_log_test extends \basic_testcase {
    private function find(string $sql): ?array {
        foreach (query_log::entries() as $entry) {
            if ($entry['sql'] === $sql) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @runInSeparateProcess
     */
    public function test_commit_correlates_named_params_captured_earlier(): void {
        $marker = 'TRACY_TEST_MARKER ' . uniqid();
        query_log::capture($marker, ['id' => 2, 'password' => 'secret-value']);
        query_log::commit($marker, [2, 'secret-value'], 0.01);

        $this->assertSame(['id' => 2, 'password' => 'secret-value'], $this->find($marker)['params']);
    }

    /**
     * The actual bug this class exists to fix: commit() must not fall back
     * to the positional $params it's given directly — only ever to what
     * capture() correlated.
     *
     * @runInSeparateProcess
     */
    public function test_commit_never_uses_its_own_positional_params_argument(): void {
        $marker = 'TRACY_TEST_MARKER ' . uniqid();
        // No capture() call at all for this SQL.
        query_log::commit($marker, [2, 'secret-value'], 0.01);

        $this->assertNull($this->find($marker)['params'], 'must not surface the raw positional params it was handed');
    }

    /**
     * @runInSeparateProcess
     */
    public function test_commit_pairs_repeated_identical_sql_in_order(): void {
        $marker = 'TRACY_TEST_MARKER ' . uniqid();
        query_log::capture($marker, ['v' => 'first']);
        query_log::capture($marker, ['v' => 'second']);

        query_log::commit($marker, [0], 0.01);
        query_log::commit($marker, [0], 0.01);

        $matches = array_values(array_filter(query_log::entries(), static fn ($e) => $e['sql'] === $marker));
        $this->assertSame(['v' => 'first'], $matches[0]['params']);
        $this->assertSame(['v' => 'second'], $matches[1]['params']);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_capture_without_matching_commit_does_not_leak_into_an_unrelated_query(): void {
        $captured = 'TRACY_TEST_MARKER_CAPTURED ' . uniqid();
        $committed = 'TRACY_TEST_MARKER_COMMITTED ' . uniqid();

        query_log::capture($captured, ['v' => 'unrelated-query-never-executed']);
        // A different query commits — must not pick up the pending entry above.
        query_log::commit($committed, [], 0.01);

        $this->assertNull($this->find($committed)['params']);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_totaltime_sums_committed_entries(): void {
        $before = query_log::totalTime();

        query_log::commit('TRACY_TEST_MARKER ' . uniqid(), [], 0.01);
        query_log::commit('TRACY_TEST_MARKER ' . uniqid(), [], 0.02);

        $this->assertEqualsWithDelta(0.03, query_log::totalTime() - $before, 0.0001);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_entries_cap_is_enforced(): void {
        for ($i = 0; $i < 600; $i++) {
            query_log::commit("TRACY_TEST_MARKER_CAP {$i}", [], 0.0001);
        }

        $this->assertLessThanOrEqual(500, count(query_log::entries()));
    }
}
