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
 * Every test uses a unique marker SQL string, not entries()'s overall
 * shape/count: this site's own config.php feeds real bootstrap queries
 * through the same static state.
 *
 * @covers \tool_tracy\query_log
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class query_log_test extends \basic_testcase {
    protected function setUp(): void {
        parent::setUp();
        query_log::clear();
    }

    /**
     * Finds a recorded entry by its exact SQL text.
     *
     * @param string $sql The exact SQL text to look for.
     * @return ?array The matching entry, or null if none was found.
     */
    private function find(string $sql): ?array {
        foreach (query_log::entries() as $entry) {
            if ($entry['sql'] === $sql) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * add() must record the SQL text and timing of an entry.
     *
     * @runInSeparateProcess
     */
    public function test_add_records_sql_and_time(): void {
        $marker = 'TRACY_TEST_MARKER ' . uniqid();

        query_log::add($marker, 0.012);

        $entry = $this->find($marker);
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.012, $entry['time'], 0.0001);
        $this->assertFalse($entry['error']);
    }

    /**
     * add() must record an entry's error status.
     *
     * @runInSeparateProcess
     */
    public function test_add_records_error_status(): void {
        $marker = 'TRACY_TEST_MARKER ' . uniqid();

        query_log::add($marker, 0.01, 'some db error');

        $this->assertSame('some db error', $this->find($marker)['error']);
    }

    /**
     * total_time() must sum the time of all recorded entries.
     *
     * @runInSeparateProcess
     */
    public function test_totaltime_sums_committed_entries(): void {
        $before = query_log::total_time();

        query_log::add('TRACY_TEST_MARKER ' . uniqid(), 0.01);
        query_log::add('TRACY_TEST_MARKER ' . uniqid(), 0.02);

        $this->assertEqualsWithDelta(0.03, query_log::total_time() - $before, 0.0001);
    }

    /**
     * query_log must cap stored entries to bound memory use.
     *
     * @runInSeparateProcess
     */
    public function test_entries_cap_is_enforced(): void {
        for ($i = 0; $i < 600; $i++) {
            query_log::add("TRACY_TEST_MARKER_CAP {$i}", 0.0001);
        }

        $this->assertLessThanOrEqual(500, count(query_log::entries()));
    }
}
